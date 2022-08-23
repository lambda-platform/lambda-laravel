<?php

namespace Lambda\Translation;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;

class Translation extends Facade
{
    public function __construct()
    {

    }

    public function build()
    {
    }

    public function generateJson()
    {

    }

    public $locales_table = "locales";
    public $static_words_table = "translation";

    static function generateLocale()
    {
        $locales = DB::table(config('lambda.locale_tbl'))->get();
        $localeGroup = DB::table('sub_category')->where('parent_id', 16)->get();
        $i18Path = public_path('i18n') . DIRECTORY_SEPARATOR;

        $localeArr = [];
        if (!is_dir($i18Path)) {
            mkdir($i18Path, 0755, true);
        }

        foreach ($localeGroup as $g){
            $words = DB::table(config('lambda.translation_tbl'))->where('component', $g->id)->get();
            foreach ($words as $w) {
                foreach ($locales as $l) {
                    $localeArr[$l->lang_code][$g->title_en][$w->key] = $w->{$l->lang_code};
                }
            }
        }

        foreach ($localeArr as $key => $value) {
            $file = $i18Path . strtolower($key) . ".json";
            file_put_contents($file, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    static function generateLocalePhp()
    {
//        $words = DB::table($this->static_words_table)->get();
//        $locales = DB::table($this->locales_table)->get();
//        $i18Path = base_path('resources' . DIRECTORY_SEPARATOR . 'lang') . DIRECTORY_SEPARATOR;
//        $localeArr = [];
//
//        if (!is_dir($i18Path)) {
//            mkdir($i18Path, 0755, true);
//        }
//
//        foreach ($locales as $l) {
//            $localeArr[$l->code] = [];
//        }
//
//        foreach ($words as $w) {
//            $translation = json_decode($w->translation);
//            $key = $w->key;
//            foreach ($translation as $t) {
//                if (array_key_exists($t->locale, $localeArr)) {
//                    $arr = [];
//                    $arr[$key] = $t->value;
//                    $localeArr[$t->locale][$key] = $t->value;
//                }
//            }
//        }
//
//        foreach ($localeArr as $key => $value) {
//            $langFilePath = $i18Path . strtolower($key) . DIRECTORY_SEPARATOR;
//            if (!is_dir($langFilePath)) {
//                mkdir($langFilePath, 0755, true);
//            }
//            $file = $langFilePath . 'tr.php';
//            $str = '<?php return ' . var_export($value, true) . ';';
//            file_put_contents($file, $str);
//        }
    }
}
