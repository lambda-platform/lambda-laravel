<?php

namespace Lambda\Dataform;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;

trait FileManager
{
    public function makeUploadable($file, $file_type): array
    {
        // config/lambda.php → upload.disk тохируулсан бол тухайн disk руу хадгална
        $disk = Config::get('lambda.upload.disk');
        if (!empty($disk)) {
            return $this->storeOnDisk($disk, $file, $file_type);
        }

        $uploadDir = DIRECTORY_SEPARATOR . 'uploaded' . DIRECTORY_SEPARATOR . $file_type . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('M') . DIRECTORY_SEPARATOR;
        $destinationPath = public_path() . $uploadDir;

        if (!is_dir($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        $ext = strtolower($file->getClientOriginalExtension());
        $fileName = UploadGuard::uniqueName(
            UploadGuard::safeName($file->getClientOriginalName(), $ext),
            function ($name) use ($destinationPath) {
                return File::exists($destinationPath . $name);
            }
        );

        if ($file_type == 'images') {
            $image = UploadGuard::compressed($file->getRealPath(), $ext);
            if ($image) {
                $image->save($destinationPath . $fileName, UploadGuard::quality());
            } else {
                $file->move($destinationPath, $fileName);
            }

            $thumb = UploadGuard::thumbnail($destinationPath . $fileName, $ext);
            if ($thumb) {
                $thumbPath = $destinationPath . 'thumb' . DIRECTORY_SEPARATOR;
                if (!is_dir($thumbPath)) {
                    mkdir($thumbPath, 0755, true);
                }
                $thumb->save($thumbPath . $fileName);
            }
        } else {
            $file->move($destinationPath, $fileName);
        }

        return [
            'httpPath' => $uploadDir . $fileName,
            'basePath' => $destinationPath,
            'fileName' => $fileName,
        ];
    }

    /**
     * config/filesystems.php доторх disk руу (s3, public, ...) хадгалаад тухайн disk-ийн URL-ийг буцаана.
     */
    private function storeOnDisk(string $diskName, $file, string $file_type): array
    {
        $storage = Storage::disk($diskName);
        $root = trim((string)Config::get('lambda.upload.dir', 'uploaded'), '/');
        $dir = ltrim($root . '/' . $file_type . '/' . date('Y') . '/' . date('M'), '/');

        $ext = strtolower($file->getClientOriginalExtension());
        $fileName = UploadGuard::uniqueName(
            UploadGuard::safeName($file->getClientOriginalName(), $ext),
            function ($name) use ($storage, $dir) {
                return $storage->exists($dir . '/' . $name);
            }
        );

        $image = $file_type == 'images' ? UploadGuard::compressed($file->getRealPath(), $ext) : null;
        if ($image) {
            $path = $dir . '/' . $fileName;
            $stored = $storage->put($path, (string)$image->encode($ext, UploadGuard::quality()));
        } else {
            $stored = $path = $storage->putFileAs($dir, $file, $fileName);
        }
        if ($stored === false) {
            throw new \RuntimeException("Lambda upload: '{$diskName}' disk руу хадгалж чадсангүй");
        }

        $thumb = $file_type == 'images' ? UploadGuard::thumbnail($file->getRealPath(), $ext) : null;
        if ($thumb) {
            $storage->put($dir . '/thumb/' . $fileName, (string)$thumb->encode($ext));
        }

        return [
            'httpPath' => $storage->url($path),
            'basePath' => $dir,
            'fileName' => $fileName,
        ];
    }

    /**
     * Accepted extensions per folder under uploaded/. Anything else is refused.
     * SVG can carry scripts and these files are served straight from public/,
     * so it is only accepted when lambda.upload.allow_svg is on (and then only
     * SVGs without scripts or event handlers).
     */
    protected static function uploadTypeFor(string $ext): ?string
    {
        $types = [
            'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'documents' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'dwg'],
            'videos' => ['mp4', 'm4v', 'avi'],
            'audios' => ['mp3', 'wav'],
        ];
        if (Config::get('lambda.upload.allow_svg', false)) {
            $types['media'] = ['svg'];
        }
        foreach ($types as $type => $extensions) {
            if (in_array($ext, $extensions, true)) {
                return $type;
            }
        }
        return null;
    }

    // Size limit plus a check that the content really is of that kind.
    protected static function uploadRules(string $type): string
    {
        $content = [
            'images' => 'mimes:jpg,jpeg,png,gif,webp',
            'documents' => 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,dwg',
            'videos' => 'mimetypes:video/*',
            'audios' => 'mimetypes:audio/*',
        ];
        $rules = 'max:' . UploadGuard::maxKb($type);
        return isset($content[$type]) ? $rules . '|' . $content[$type] : $rules;
    }

    public static function upload()
    {
        $t = new self();
        $file = request()->file('file');
        if (!$file || !$file->isValid()) {
            return response()->json(['status' => false], 422);
        }

        $ext = strtolower($file->getClientOriginalExtension());
        $file_type = self::uploadTypeFor($ext);
        if ($file_type === null) {
            return response()->json(['status' => false, 'message' => 'File type not allowed']);
        }

        $validator = Validator::make(['file' => $file], ['file' => self::uploadRules($file_type)]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first('file')]);
        }
        if ($file_type == 'media' && !UploadGuard::isSafeSvg($file->getRealPath())) {
            return response()->json(['status' => false, 'message' => 'SVG contains scripts']);
        }
        if ($file_type == 'images' && !UploadGuard::imageWithinLimits($file->getRealPath())) {
            return response()->json(['status' => false, 'message' => 'Image dimensions are too large']);
        }

        $upload = $t->makeUploadable($file, $file_type);
        return $upload['httpPath'];
    }

}
