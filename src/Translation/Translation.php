<?php

namespace Lambda\Translation;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;

class Translation extends Facade
{
    static function generateLocale()
    {
        $locales = DB::table(config('lambda.tr_locale'))->get();
        $localeComponents = DB::table(config('lambda.tr_components'))->get();
        $i18Path = public_path('i18n') . DIRECTORY_SEPARATOR;

        $localeArr = [];
        if (!is_dir($i18Path)) {
            mkdir($i18Path, 0755, true);
        }

        foreach ($localeComponents as $c) {
            $words = DB::table(config('lambda.tr_word'))->where('component', $c->id)->get();
            foreach ($words as $w) {
                foreach ($locales as $l) {
                    $localeArr[$l->lang_code][$c->code][$w->key] = $w->{$l->lang_code};
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
