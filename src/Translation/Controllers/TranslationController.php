<?php

namespace Lambda\Translation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TranslationController extends Controller
{

    function getLocales()
    {
        return DB::table('tr_locales')->where('is_active', 1)->get();
    }

    function localeTrigger()
    {
        $locales = DB::table('tr_locales')->where('is_active', 1)->get();
        foreach ($locales as $l) {
            if (!Schema::hasColumn('tr_translation', $l->code)) {
                Schema::table('tr_translation', function ($table) use ($l) {
                    $table->string($l->code, 255)->nullable();
                });
            }
        }
    }

    function getTranslation()
    {
        $components = DB::table('tr_components')->get();
        foreach ($components as $c) {
            $c->translation = DB::table('tr_translation')->where('component_id', $c->id)->get();
        }

        return response()->json($components);
    }

    function generateLocale()
    {
        $locales = DB::table('tr_locales')->get();
        $localeComponents = DB::table('tr_components')->get();
        $i18Path = public_path('i18n') . DIRECTORY_SEPARATOR;

        $localeArr = [];
        if (!is_dir($i18Path)) {
            mkdir($i18Path, 0755, true);
        }

        foreach ($localeComponents as $c) {
            $words = DB::table('tr_translation')->where('component_id', $c->id)->get();
            foreach ($words as $w) {
                foreach ($locales as $l) {
                    $localeArr[$l->code][$c->code][$w->key] = $w->{$l->code};
                }
            }
        }

        foreach ($localeArr as $key => $value) {
            $file = $i18Path . strtolower($key) . ".json";
            file_put_contents($file, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return response()->json(['status' => true, 'msg' => 'Орчуулгын файл үүслээ']);
    }

    function addTranslation()
    {
        $r = DB::table('tr_translation')->insertGetId([
            'component_id' => request('component_id'),
            'key' => request('key'),
        ]);

        $tr = DB::table('tr_translation')->where('id', $r)->first();
        return response()->json(['status' => true, 'tr' => $tr]);
    }

    function deleteTranslation($id)
    {
        $r = DB::table('tr_translation')->where('id', $id)->delete();
        if ($r) {
            return response()->json(['status' => true, 'msg' => 'Орчуулга устгагдлаа!']);
        }
        return response()->json(['status' => false, 'msg' => 'Орчуулга устгахад алдаа гарлаа!']);
    }

    function updateTranslation()
    {
        $data = request()->all();
        DB::table('tr_translation')->where('id', request('id'))->update($data);
        return response()->json(['status' => true]);
    }
}
