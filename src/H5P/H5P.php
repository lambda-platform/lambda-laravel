<?php

namespace Lambda\H5P;

use Brnysn\LaravelH5P\Repositories\H5PEditorStorageRepository;
use Brnysn\LaravelH5P\Repositories\H5PFileStorageRepository;
use Brnysn\LaravelH5P\Services\Contracts\HeadlessH5PServiceContract;
use Brnysn\LaravelH5P\Services\HeadlessH5PService;
use Lambda\H5P\Repositories\H5PEditorAjaxTokenRepository;
use Lambda\H5P\Repositories\H5PFixedRepository;

class H5P
{
    /**
     * Модуль нь brnysn/laravel-h5p багцаас хамаардаг — багцгүй хост дээр
     * route/binding огт бүртгэхгүй байхын тулд үүгээр шалгана.
     */
    public static function available(): bool
    {
        return class_exists(HeadlessH5PService::class);
    }

    /**
     * Upstream h5p-editor санд байхгүй editor UI орчуулгыг (одоогоор mn.js)
     * vendor-ийн language хавтас руу хуулна — HeadlessH5PService-ийн
     * margeFileList нь файлыг vendor дотроос уншдаг тул тэнд заавал байх ёстой.
     * Орчуулга өөрчлөгдсөн бол vendor-ийн хуулбарыг шинэчилж, merged bundle-ийн
     * нэр нь файлын ЖАГСААЛТААС (агуулгаас биш) hash-лэгддэг тул хуучин
     * bundle-уудыг устгаж дахин үүсгүүлнэ. Идемпотент.
     */
    public static function ensureEditorLanguage(): void
    {
        $lang = config('hh5p.language');
        if (!$lang) {
            return;
        }

        $bundled = __DIR__ . '/language/' . $lang . '.js';
        $target = base_path('vendor/h5p/h5p-editor/language/' . $lang . '.js');

        if (!file_exists($bundled) || !is_dir(dirname($target)) || !is_writable(dirname($target))) {
            return;
        }

        if (!file_exists($target)) {
            copy($bundled, $target);
            return;
        }

        if (md5_file($bundled) !== md5_file($target)) {
            copy($bundled, $target);
            // хуучирсан merged bundle-уудыг цэвэрлэнэ (дараагийн хүсэлт дээр дахин үүснэ)
            foreach (glob(base_path('vendor/h5p/h5p-editor/js/*.js')) ?: [] as $merged) {
                @unlink($merged);
            }
        }
    }

    /**
     * public/h5p-core, public/h5p-editor нь vendor/h5p/* руу заасан symlink байх ёстой:
     * HeadlessH5PService::margeFileList нь нэгтгэсэн bundle-аа vendor/h5p/h5p-editor/js
     * дотор бичээд /h5p-editor/js/<hash>.js URL-аар лавладаг тул статик copy болохгүй.
     * Идемпотент: байвал юу ч хийхгүй, storage-link шиг автоматаар үүсгэнэ.
     */
    public static function ensurePublicLibraryLinks(): void
    {
        foreach (['h5p-core', 'h5p-editor'] as $dir) {
            $target = base_path('vendor/h5p/' . $dir);
            $link = public_path($dir);

            if (is_dir($target) && !file_exists($link) && !is_link($link)) {
                // relative link — deploy хийхэд docroot-ийн зам өөрчлөгдсөн ч эвдрэхгүй
                symlink('../vendor/h5p/' . $dir, $link);
            }
        }
    }

    /**
     * HeadlessH5PServiceProvider::bindH5P()-тэй ижил wiring, хоёр repository-г сольсон:
     * - H5PFixedRepository: контент UPDATE-ийн "Array to string conversion" болон
     *   library file URL-ийн засвартай
     * - H5PEditorAjaxTokenRepository: editor token-ийг Passport RSA биш
     *   tymon/jwt-auth (HS256)-аар шалгана
     *
     * boot үед дуудагдана — бүх register-ийн дараа ажилладаг тул багцын
     * binding-ийг найдвартай дарж чадна.
     */
    public static function bind($app): void
    {
        $app->singleton(HeadlessH5PServiceContract::class, function () {
            $repository = new H5PFixedRepository();
            $fileStorage = new H5PFileStorageRepository(
                storage_path(config('hh5p.h5p_storage_path', 'app/h5p'))
            );
            $core = new \H5PCore($repository, $fileStorage, url(config('hh5p.url', 'h5p')), config('hh5p.language', 'en'), true);
            $core->aggregateAssets = true;
            $validator = new \H5PValidator($repository, $core);
            $storage = new \H5PStorage($repository, $core);
            $editorStorage = new H5PEditorStorageRepository();
            $editorAjaxRepository = new H5PEditorAjaxTokenRepository();
            $editor = new \H5peditor($core, $editorStorage, $editorAjaxRepository);
            $contentValidator = new \H5PContentValidator($repository, $core);

            return new HeadlessH5PService(
                $repository,
                $fileStorage,
                $core,
                $validator,
                $storage,
                $editorStorage,
                $editorAjaxRepository,
                $editor,
                $contentValidator
            );
        });
    }
}
