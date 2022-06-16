<?php

namespace Lambda\DataSource;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;

class DataSource extends Facade
{
    public static function viewHandler($when, $action, $data, $id)
    {
        if ($when == 'before' && $action == 'update' || $when == 'before' && $action == 'delete') {
            $item = DB::table('vb_schemas')->where('id', $id)->first();

            if ($item) {
                static::deleteView('ds_'.$item->name);
            }
        }
        if ($when == 'after') {
            if ($action == 'insert' || $action == 'update') {
                $schema = json_decode($data['schema']);

                static::createView('ds_'.$data['name'], $schema->query);
            }
        }
    }

    public static function deleteView($view_name)
    {
        DB::statement("DROP VIEW IF EXISTS $view_name");
    }

    public static function createView($view_name, $query)
    {
        DB::statement("CREATE VIEW $view_name as $query");
    }
}
