<?php

namespace Lambda\Dataform\Editor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;
use Lambda\Dataform\UploadGuard;

class FileManagerController extends Controller
{
    const THUMB_DIR = '.thumbs';

    // Served Content-Type by extension. Never sniffed from the content, so a
    // .txt or .csv that holds HTML can't be served as a web page.
    const CONTENT_TYPES = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
        'webp' => 'image/webp', 'bmp' => 'image/bmp', 'svg' => 'image/svg+xml',
        'pdf' => 'application/pdf', 'txt' => 'text/plain; charset=utf-8', 'csv' => 'text/csv; charset=utf-8',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'mp4' => 'video/mp4', 'm4v' => 'video/x-m4v', 'avi' => 'video/x-msvideo', 'webm' => 'video/webm',
        'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg',
        'm4a' => 'audio/mp4', 'aac' => 'audio/aac', 'flac' => 'audio/flac', 'opus' => 'audio/ogg', 'weba' => 'audio/webm',
        'zip' => 'application/zip', 'rar' => 'application/vnd.rar', 'dwg' => 'image/vnd.dwg',
    ];

    // Size-limit group (lambda.upload.max_size.<group>) for each allowed extension.
    const SIZE_GROUPS = [
        'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'],
        'media' => ['svg'],
        'videos' => ['mp4', 'm4v', 'avi', 'webm'],
        'audios' => ['mp3', 'wav', 'ogg', 'oga', 'm4a', 'aac', 'flac', 'opus', 'weba'],
        'archives' => ['zip', 'rar'],
    ];

    protected $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];

    protected $resizableExtensions = ['jpg', 'jpeg', 'png', 'webp', 'bmp'];

    protected $allowedExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv',
        'mp4', 'm4v', 'avi', 'webm',
        'mp3', 'wav', 'ogg', 'oga', 'm4a', 'aac', 'flac', 'opus', 'weba',
        'zip', 'rar', 'dwg',
    ];

    public function files(Request $request)
    {
        $path = $this->normalize($request->query('path'));
        $dir = $this->absolute($path);

        $folders = [];
        $files = [];

        if (is_dir($dir)) {
            foreach (File::directories($dir) as $folder) {
                $name = basename($folder);
                if ($name[0] === '.') {
                    continue;
                }
                $folders[] = $name;
            }
            foreach (File::files($dir) as $file) {
                $name = $file->getFilename();
                if ($name[0] === '.') {
                    continue;
                }
                $files[] = $this->fileEntry($path, $name);
            }
        }

        return response()->json([
            'path' => $path,
            'folders' => $folders,
            'files' => $files,
        ]);
    }

    public function upload(Request $request)
    {
        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return response()->json(['status' => false, 'message' => 'No file uploaded'], 422);
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, $this->allowedExtensions)) {
            return response()->json(['status' => false, 'message' => 'File type not allowed'], 422);
        }
        if (UploadGuard::tooLarge($file, $this->sizeGroup($ext))) {
            return response()->json(['status' => false, 'message' => 'File is too large'], 422);
        }
        if ($ext === 'svg' && !UploadGuard::isSafeSvg($file->getRealPath())) {
            return response()->json(['status' => false, 'message' => 'SVG contains scripts'], 422);
        }
        if (in_array($ext, UploadGuard::RASTER, true) && !UploadGuard::imageWithinLimits($file->getRealPath())) {
            return response()->json(['status' => false, 'message' => 'Image dimensions are too large'], 422);
        }

        $path = $this->normalize($request->input('path'));
        $dir = $this->absolute($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = $this->uniqueName($dir, $this->sanitizeName($file->getClientOriginalName()));
        if ($name === '') {
            return response()->json(['status' => false, 'message' => 'Invalid file name'], 422);
        }
        $target = $dir . DIRECTORY_SEPARATOR . $name;

        $image = in_array($ext, UploadGuard::RASTER, true) ? UploadGuard::compressed($file->getRealPath(), $ext) : null;
        if ($image) {
            $image->save($target, UploadGuard::quality());
        } else {
            $file->move($dir, $name);
        }
        $this->writeThumb($target, $ext);

        return response()->json([
            'status' => true,
            'file' => $this->fileEntry($path, $name),
        ]);
    }

    // Overwrite an existing image with an edited version (crop/rotate/... done
    // client-side) and regenerate its thumbnail. Unlike upload() this keeps
    // the same name/path so references to the file stay valid.
    public function save(Request $request)
    {
        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return response()->json(['status' => false, 'message' => 'No file uploaded'], 422);
        }

        $path = $this->normalize($request->input('path'));
        $target = $this->absolute($path);
        if ($path === '' || !is_file($target)) {
            return response()->json(['status' => false, 'message' => 'Not found'], 404);
        }

        $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
        if (!in_array($ext, $this->resizableExtensions)) {
            return response()->json(['status' => false, 'message' => 'This file type cannot be edited'], 422);
        }

        if (UploadGuard::tooLarge($file, 'images') || !UploadGuard::imageWithinLimits($file->getRealPath())) {
            return response()->json(['status' => false, 'message' => 'Image is too large'], 422);
        }

        // The edited image always has to be encoded; img_compress decides
        // whether it is also scaled down and saved at img_quality.
        $compress = UploadGuard::compressEnabled();
        $image = Image::make($file->getRealPath());
        $maxWidth = (int)config('lambda.img_width', 1600);
        if ($compress && $maxWidth > 0 && $image->width() > $maxWidth) {
            $image->resize($maxWidth, null, function ($constraint) {
                $constraint->aspectRatio();
            });
        }
        $image->save($target, $compress ? UploadGuard::quality() : 100);
        $this->writeThumb($target, $ext);

        $parent = dirname($path);
        $parentRel = $parent === '.' ? '' : $parent;

        return response()->json([
            'status' => true,
            'file' => $this->fileEntry($parentRel, basename($target)),
        ]);
    }

    public function folder(Request $request)
    {
        $path = $this->normalize($request->input('path'));
        $name = $this->sanitizeName($request->input('name'));
        if ($name === '') {
            return response()->json(['status' => false, 'message' => 'Invalid folder name'], 422);
        }

        $dir = $this->absolute($path === '' ? $name : $path . '/' . $name);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return response()->json(['status' => true, 'name' => $name]);
    }

    public function rename(Request $request)
    {
        $path = $this->normalize($request->input('path'));
        $name = $this->sanitizeName($request->input('name'));
        if ($path === '' || $name === '') {
            return response()->json(['status' => false, 'message' => 'Invalid name'], 422);
        }

        $source = $this->absolute($path);
        if (!file_exists($source)) {
            return response()->json(['status' => false, 'message' => 'Not found'], 404);
        }

        if (is_file($source)) {
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $this->allowedExtensions)) {
                return response()->json(['status' => false, 'message' => 'File type not allowed'], 422);
            }
        }

        $parent = dirname($path);
        $parentRel = $parent === '.' ? '' : $parent;
        $targetRel = $parentRel === '' ? $name : $parentRel . '/' . $name;
        $target = $this->absolute($targetRel);
        if (file_exists($target)) {
            return response()->json(['status' => false, 'message' => 'Already exists'], 422);
        }

        rename($source, $target);

        if (is_file($target)) {
            $thumbDir = dirname($target) . DIRECTORY_SEPARATOR . self::THUMB_DIR;
            $thumb = $thumbDir . DIRECTORY_SEPARATOR . basename($path);
            if (is_file($thumb)) {
                rename($thumb, $thumbDir . DIRECTORY_SEPARATOR . $name);
            }
            return response()->json([
                'status' => true,
                'file' => $this->fileEntry($parentRel, $name),
            ]);
        }

        return response()->json(['status' => true, 'name' => $name]);
    }

    public function delete(Request $request)
    {
        $path = $this->normalize($request->input('path'));
        if ($path === '') {
            return response()->json(['status' => false, 'message' => 'Invalid path'], 422);
        }

        $target = $this->absolute($path);

        if (is_dir($target)) {
            File::deleteDirectory($target);
        } elseif (is_file($target)) {
            File::delete($target);
            $thumb = dirname($target) . DIRECTORY_SEPARATOR . self::THUMB_DIR . DIRECTORY_SEPARATOR . basename($target);
            if (is_file($thumb)) {
                File::delete($thumb);
            }
        } else {
            return response()->json(['status' => false, 'message' => 'Not found'], 404);
        }

        return response()->json(['status' => true]);
    }

    public function file($path = '')
    {
        $path = $this->normalize($path);
        $absolute = $this->absolute($path);
        if ($path === '' || !is_file($absolute)) {
            abort(404);
        }

        $ext = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
        $headers = [
            'Cache-Control' => 'public, max-age=604800',
            'Content-Type' => self::CONTENT_TYPES[$ext] ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ];
        if (!isset(self::CONTENT_TYPES[$ext])) {
            $headers['Content-Disposition'] = 'attachment';
        }
        if ($ext === 'svg') {
            // Opened directly an SVG is a document: no scripts, no outside requests.
            $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox";
        }

        return response()->file($absolute, $headers);
    }

    protected function sizeGroup(string $ext): string
    {
        foreach (self::SIZE_GROUPS as $group => $extensions) {
            if (in_array($ext, $extensions, true)) {
                return $group;
            }
        }
        return 'documents';
    }

    // (Re)write the thumbnail of a raster image stored at $target.
    protected function writeThumb(string $target, string $ext): void
    {
        $thumb = UploadGuard::thumbnail($target, $ext);
        if (!$thumb) {
            return;
        }
        $thumbDir = dirname($target) . DIRECTORY_SEPARATOR . self::THUMB_DIR;
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }
        $thumb->save($thumbDir . DIRECTORY_SEPARATOR . basename($target));
    }

    protected function fileEntry(string $path, string $name): array
    {
        $relative = $path === '' ? $name : $path . '/' . $name;
        $absolute = $this->absolute($relative);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $isImage = in_array($ext, $this->imageExtensions);

        $entry = [
            'name' => $name,
            'path' => $relative,
            'size' => filesize($absolute),
            'modified' => filemtime($absolute),
            'isImage' => $isImage,
            'src' => $this->url($relative),
        ];

        if ($isImage) {
            $thumbRel = ($path === '' ? '' : $path . '/') . self::THUMB_DIR . '/' . $name;
            $entry['thumb'] = is_file($this->absolute($thumbRel)) ? $this->url($thumbRel) : $entry['src'];
        }

        return $entry;
    }

    protected function url(string $relative): string
    {
        return '/lambda/filemanager/file/' . implode('/', array_map('rawurlencode', explode('/', $relative)));
    }

    protected function root(): string
    {
        $root = storage_path('app' . DIRECTORY_SEPARATOR . 'filemanager');
        if (!is_dir($root)) {
            mkdir($root, 0755, true);
        }
        return $root;
    }

    protected function absolute(string $relative): string
    {
        if ($relative === '') {
            return $this->root();
        }
        return $this->root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    protected function normalize($path): string
    {
        $segments = [];
        foreach (explode('/', str_replace('\\', '/', (string) $path)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                abort(400, 'Invalid path');
            }
            $segments[] = $segment;
        }
        return implode('/', $segments);
    }

    protected function sanitizeName($name): string
    {
        $name = str_replace(['\\', '/'], '-', (string) $name);
        $name = preg_replace('/[#?%*:|"<>]/', '-', $name);
        $name = preg_replace('/[\x00-\x1F]/', '', $name);
        return trim($name, ' .');
    }

    protected function uniqueName(string $dir, string $name): string
    {
        $candidate = $name;
        $i = 0;
        while (file_exists($dir . DIRECTORY_SEPARATOR . $candidate)) {
            $candidate = ++$i . '-' . $name;
        }
        return $candidate;
    }
}
