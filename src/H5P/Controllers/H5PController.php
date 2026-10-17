<?php

namespace Lambda\H5P\Controllers;

use App\Http\Controllers\Controller;
use Brnysn\LaravelH5P\Services\Contracts\HeadlessH5PServiceContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class H5PController extends Controller
{
    private HeadlessH5PServiceContract $hh5pService;

    public function __construct(HeadlessH5PServiceContract $hh5pService)
    {
        $this->hh5pService = $hh5pService;

        // l10n (__('h5p::h5p')) нь app locale-оос хамаардаг; H5PLangMiddleware
        // зөвхөн багцын route-уудад байдаг тул энд өөрсдөө тохируулна
        app()->setLocale(config('hh5p.language'));
    }

    /* ---------- JSON endpoint-ууд (builder-ийн h5p модульд) ---------- */

    public function editorSettings(Request $request)
    {
        return response()->json($this->buildEditorSettings($request->query('id')));
    }

    public function contentSettings(int $id)
    {
        return response()->json($this->buildPlayerSettings($id));
    }

    public function contents()
    {
        // uuid (uniqueidentifier) баганыг сонгохгүй — sqlsrv дээр binary утга
        // json_encode-ийг эвддэг
        $contents = \Brnysn\LaravelH5P\Models\H5PContent::query()
            ->with('library:id,name')
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'library_id', 'updated_at'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'library' => $c->library?->name,
                'updated_at' => optional($c->updated_at)->format('Y-m-d H:i'),
            ])
            ->values();

        return response()->json($contents);
    }

    /* ---------- Settings бэлтгэх ---------- */

    private function buildEditorSettings(?string $contentId): array
    {
        $settings = $this->hh5pService->getEditorSettings($contentId);

        $this->scrubBinaryUuids($settings);

        // Шинэ контентын metadata-ийн default хэл (Vue editor-ийн contentLanguage)
        $settings['editor']['language'] = config('hh5p.language');

        // JWT is stored in a plain (non-encrypted) cookie by the frontend,
        // same as Lambda\Agent\Middleware\JWT reads it.
        $token = isset($_COOKIE['token'])
            ? str_replace('Bearer ', '', $_COOKIE['token'])
            : null;
        $settings['token'] = $token;

        // Re-sign the files upload URL with _token INSIDE the signature:
        // uploadFile() validates it, and appending it client-side would
        // invalidate the signed URL. Longer expiry than the package's 30 min
        // so uploads don't start failing mid-editing-session.
        if ($token) {
            $settings['filesAjaxPath'] = URL::temporarySignedRoute(
                'hh5p.files.upload.nonce',
                now()->addHours(6),
                ['nonce' => $settings['nonce'], '_token' => $token]
            );
        }

        // Patch asset-ууд builder-ийн src/modules/h5p/assets/-аас хостын
        // webpack.lambda.js-ийн mix.copy-оор public/assets/lambda/h5p/ руу хуулагддаг.
        // filemtime version залгаж browser/iframe-ийн кэшийг автоматаар хүчингүй болгоно.
        $patchAsset = function (string $file) {
            $path = public_path('assets/lambda/h5p/' . $file);
            $version = is_file($path) ? '?v=' . filemtime($path) : '';

            return asset('assets/lambda/h5p/' . $file) . $version;
        };

        // Must stay FIRST: sets CKEDITOR_BASEPATH before the merged bundle
        // (which contains ckeditor.js) loads — in the page and in the iframe.
        array_unshift($settings['editor']['assets']['js'], $patchAsset('h5p-ckeditor-base.js'));
        // Hub UX: сонгоход шууд Use, картын info товч + modal detail
        $settings['editor']['assets']['js'][] = $patchAsset('h5p-hub-ux.js');
        // Must stay LAST — it re-patches getAjaxUrl after h5peditor-init.js,
        // in the parent page and inside the editor iframe.
        $settings['editor']['assets']['js'][] = $patchAsset('h5p-ajax-patch.js');
        // Editor UI override: makes image/file action buttons clearly visible.
        $settings['editor']['assets']['css'][] = $patchAsset('h5p-editor-custom.css');

        return $settings;
    }

    private function buildPlayerSettings(int $id): array
    {
        $settings = $this->hh5pService->getContentSettings($id);

        $this->scrubBinaryUuids($settings);

        return $settings;
    }

    /**
     * sqlsrv can return uniqueidentifier columns as raw binary, which breaks
     * json_encode of the whole page settings (@json silently emits nothing).
     */
    private function scrubBinaryUuids(array &$settings): void
    {
        foreach ($settings['contents'] ?? [] as $cid => $content) {
            $uuid = $content['content']['uuid'] ?? null;
            if (is_string($uuid) && !mb_check_encoding($uuid, 'UTF-8')) {
                unset($settings['contents'][$cid]['content']['uuid']);
            }
        }
    }
}
