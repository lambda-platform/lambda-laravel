<?php

namespace Lambda\Puzzle;

use DB;

trait DBSchema
{
    public static function tables()
    {
        $ignore_tables = [];

        $tables_ = [];
        $views_ = [];

        if (env('DB_CONNECTION') == 'sqlsrv') {
            $tables = DB::select(DB::raw('SELECT TABLE_NAME, TABLE_TYPE FROM INFORMATION_SCHEMA.TABLES ORDER BY TABLE_NAME'));
            foreach ($tables as $t) {
                $key = 'TABLE_NAME';
                $tableName = $t->$key;
                if (array_search($tableName, $ignore_tables)) {
                } else {
                    if ($t->TABLE_TYPE == 'VIEW') {
                        $views_[] = $tableName;
                    } else {
                        $tables_[] = $tableName;
                    }
                }
            }
        } else if (env('DB_CONNECTION') == 'pgsql') {
            $ignore_tables = ['information_schema'];
            $ignore_schemas = ["'information_schema'", "'pg_catalog'"];
            $databaseName = env('DB_DATABASE', 'lambda_db');

            $qrStr = "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE table_schema <> all(ARRAY[" . join(",", $ignore_schemas) . "]) ORDER BY TABLE_NAME";
            $tables = DB::select(DB::raw($qrStr));

            foreach ($tables as $t) {
                $schemaKey = 'table_schema';
                $key = 'table_name';
                $tableName = $t->$schemaKey . "." . $t->$key;
                if (!array_search($tableName, $ignore_tables)) {
                    if ($t->table_type == 'VIEW') {
                        $views_[] = $tableName;
                    } else {
                        $tables_[] = $tableName;
                    }
                }
            }
        } else if (env('DB_CONNECTION') == 'mongodb') {
            $dbName = DB::connection('mongodb')->getMongoDB()->getDatabaseName();
            $cursors = DB::connection('mongodb')->getMongoClient()->{$dbName}->listCollections();
            foreach ($cursors as $collection) {
                $tables_[] = $collection->getName();
            }
        } else {
            $tables = DB::select('SHOW FULL TABLES');
            $databaseName = env('DB_DATABASE', 'lambda_db');

            foreach ($tables as $t) {
                $key = "Tables_in_$databaseName";
                $tableName = $t->$key;
                if (array_search($tableName, $ignore_tables)) {
                } else {
                    if ($t->Table_type == 'VIEW') {
                        $views_[] = $tableName;
                    } else {
                        $tables_[] = $tableName;
                    }
                }
            }
        }
        return [
            'tables' => $tables_,
            'views' => $views_,
        ];
    }

    /*
     * get table Meta by table name
     * */
    public static function tableMeta($table)
    {
        $data = [];
        try {
            if (env('DB_CONNECTION') == 'sqlsrv') {
                $dataname = env('DB_DATABASE');
                $data = DB::select(DB::raw("SELECT * FROM  $dataname.INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$table'"));
                if ($data) {
                    $newData = [];
                    foreach ($data as $dcolumn) {
                        $type = '';
                        if ($dcolumn->DATA_TYPE == 'nvarchar') {
                            $type = 'varchar(255)';
                        } elseif ($dcolumn->DATA_TYPE == 'ntext') {
                            $type = 'text';
                        }
                        $newData[] = [
                            'model' => $dcolumn->COLUMN_NAME,
                            'title' => $dcolumn->COLUMN_NAME,
                            'dbType' => $type,
                            'table' => $table,
                            'key' => $dcolumn->ORDINAL_POSITION == 1 ? 'PRI' : '',
                            'extra' => $dcolumn->ORDINAL_POSITION == 1 ? 'auto_increment' : '',
                        ];
                    }
                    return $newData;
                } else {
                    return $data;
                }
            }

            if (env('DB_CONNECTION') == 'pgsql') {
                $dataname = env('DB_DATABASE');
                $tableWithSchema = explode('.', $table);
                $tableName = end($tableWithSchema);

                $data = DB::select(DB::raw("SELECT * FROM  $dataname.INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$tableName'"));
                if ($data) {
                    $newData = [];
                    foreach ($data as $dcolumn) {
                        $newData[] = [
                            'model' => $dcolumn->column_name,
                            'title' => $dcolumn->column_name,
                            'dbType' => $dcolumn->udt_name,
                            'table' => $table,
                            'key' => $dcolumn->dtd_identifier == 1 ? 'PRI' : '',
                        ];
                    }
                    return $newData;
                } else {
                    return $data;
                }
                return $data;
            }

            if (env('DB_CONNECTION') == 'mongodb') {
                $col = DB::collection($table)->first();
                if($col){
                    $newData = [];
                    foreach ($col as $key => $val){
                        $newData[] = [
                            'model' => $key,
                            'title' => $key,
                            'dbType' => 'text',
                            'table' => $table,
                            'key' => ''
                        ];
                    }
                    return $newData;
                }

                return $data;
            }

            $data = DB::select("show fields from $table");
        } catch (\Exception $e) {
            dd($e);
        }

        if ($data) {
            $newData = [];
            foreach ($data as $dcolumn) {
                $newData[] = [
                    'model' => $dcolumn->Field,
                    'title' => $dcolumn->Field,
                    'dbType' => $dcolumn->Type,
                    'table' => $table,
                    'key' => $dcolumn->Key,
                    'extra' => $dcolumn->Extra,
                ];
            }

            return $newData;
        }
        if ($data) {
            return $data;
        }
    }

    public static function getDBSchema()
    {
        $tables = Puzzle::tables();
        $dbSchema = [
            'tableList' => $tables['tables'],
            'viewList' => $tables['views'],
            'tableMeta' => [],
        ];

        foreach ($tables['tables'] as $t) {
            $dbSchema['tableMeta'][$t] = Puzzle::tableMeta($t);
        }

        foreach ($tables['views'] as $t) {
            $dbSchema['tableMeta'][$t] = Puzzle::tableMeta($t);
        }

        return $dbSchema;
    }
}