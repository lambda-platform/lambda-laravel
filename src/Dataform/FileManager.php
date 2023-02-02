<?php

namespace Lambda\Dataform;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Facades\Image;
use Compress;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;

trait FileManager
{
    public function makeUploadable($file, $file_type): array
    {
        $config = Config::get('lambda');
        $base_dir = $file_type;
        $uploadDir = DIRECTORY_SEPARATOR . 'uploaded' . DIRECTORY_SEPARATOR . $base_dir . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('M') . DIRECTORY_SEPARATOR;
        $destinationPath = public_path() . $uploadDir;

        if (!is_dir($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        $fileName = str_replace('#', "-", $file->getClientOriginalName());
        $fileName = str_replace('/', "-", $fileName);
        $fileName = str_replace(' ', "%20", $fileName);

        $uploadFile = $destinationPath . $fileName;

//         $i = 0;
//         while (File::exists($uploadFile)) {
//             $fileName = ++$i . '-' . $fileName;
//             $uploadFile = $destinationPath . $fileName;
//         }
       if (File::exists($uploadFile)) {
            $currentDate = Carbon::now()->format('YmdHs');
            $fileName = $fileName . '-' . $currentDate;
            $fileName = $currentDate . '-' . $fileName;
            $uploadFile = $destinationPath . $fileName;
          } 

        if ($file_type == 'images') {
            $thumbPath = $destinationPath . DIRECTORY_SEPARATOR . 'thumb' . DIRECTORY_SEPARATOR;
            if (!is_dir($thumbPath)) {
                mkdir($thumbPath, 0755, true);
            }

            $uploadSuccess = Image::make($file->getRealPath());
            $width = $uploadSuccess->width();
            //if ($width > 800) {
            //    $uploadSuccess = $uploadSuccess->resize($config['img_width'], null, function ($constraint) {
            //        $constraint->aspectRatio();
             //   });
            //}
            //$uploadSuccess->save($destinationPath . $fileName, $config['img_quality']);
            $uploadSuccess->save($destinationPath . $fileName);


            $thumb_image = $uploadSuccess->resize($config['img_thumb_width'], null, function ($constraint) {
                $constraint->aspectRatio();
            });
            $thumb_image->save($thumbPath . $fileName);
        } else {
            $file->move($destinationPath, $fileName);
        }

        return [
            'httpPath' =>$uploadDir . $fileName,
            'basePath' => $destinationPath,
            'fileName' => $fileName,
        ];
    }

    public static function upload()
    {
        $t = new self();
        $file = request()->file('file');
        $file_type = "images";
        $rules = [
            'file' => 'mimes:JPG,PNG,GIF,JPEG,png,gif,jpeg,jpg,webp|max:20000000',
        ];

        $ext = $file->getClientOriginalExtension();
        if ($ext == 'dwg' || $ext == 'pdf' || $ext == 'swf' || $ext == 'doc' || $ext == 'docx' || $ext == 'xls' || $ext == 'xlsx' || $ext == 'ppt' || $ext == 'pptx' || $ext == 'svg') {
            $rules = [
                'file' => 'mimes:DWG,PDF,DOC,DOCX,XLS,XLSX,PPT,PPTX,dwg,pdf,doc,docx,xls,xlsx,ppt,pptx|max:400000',
            ];
            $file_type = "documents";
        }
        if ($ext == 'mp4' || $ext == 'm4v' || $ext == 'avi') {
            $rules = [
                'file' => 'mimes:mp4,m4v,avi,MP4,M4V,AVI|max:40000000',
            ];
            $file_type = "videos";
        }

        if ($ext == 'mp3' || $ext == 'MP3') {
            $rules = [
                'file' => 'mimes:application/octet-stream,audio/mpeg,mpga,mp3,wav|max:40000000',
            ];
            $file_type = "audios";
        }

        if ($ext == 'svg') {
            $file_type = "media";
            $rules = [
                'file' => '|max:4000000',
            ];
        }

        $validator = Validator::make(request()->all(), $rules);

        if ($validator->passes()) {
            $upload = $t->makeUploadable($file, $file_type);
            return $upload['httpPath'];
        } else {
            return response()->json(['status' => false]);
        }
    }

    public static function remove()
    {
        $img = Image::make($_FILES['image']['tmp_name']);
        // resize image
        $img->fit(300, 200);
        // save image
        $img->save('foo/bar.jpg');
    }
}
