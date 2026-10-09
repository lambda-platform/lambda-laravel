<?php

namespace Lambda\Dataform\Editor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;

class FileManagerController extends Controller
{
    const THUMB_DIR = '.thumbs';

    protected $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];

    protected $resizableExtensions = ['jpg', 'jpeg', 'png', 'webp', 'bmp'];

    protected $allowedExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv',
        'mp4', 'm4v', 'avi', 'webm', 'mp3', 'wav',
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

        $path = $this->normalize($request->input('path'));
        $dir = $this->absolute($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = $this->uniqueName($dir, $this->sanitizeName($file->getClientOriginalName()));
        if ($name === '') {
            return response()->json(['status' => false, 'message' => 'Invalid file name'], 422);
        }

        $config = Config::get('lambda', []);
        $maxWidth = isset($config['img_width']) ? $config['img_width'] : 1600;
        $quality = isset($config['img_quality']) ? $config['img_quality'] : 90;
        $thumbWidth = isset($config['img_thumb_width']) ? $config['img_thumb_width'] : 320;

        if (in_array($ext, $this->resizableExtensions)) {
            $image = Image::make($file->getRealPath());
            if ($image->width() > $maxWidth) {
                $image->resize($maxWidth, null, function ($constraint) {
                    $constraint->aspectRatio();
                });
            }
            $image->save($dir . DIRECTORY_SEPARATOR . $name, $quality);

            $thumbDir = $dir . DIRECTORY_SEPARATOR . self::THUMB_DIR;
            if (!is_dir($thumbDir)) {
                mkdir($thumbDir, 0755, true);
            }
            $image->resize($thumbWidth, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })->save($thumbDir . DIRECTORY_SEPARATOR . $name);
        } else {
            $file->move($dir, $name);
        }

        return response()->json([
            'status' => true,
            'file' => $this->fileEntry($path, $name),
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

        return response()->file($absolute, [
            'Cache-Control' => 'public, max-age=604800',
        ]);
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
