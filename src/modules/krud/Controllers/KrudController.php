<?php

namespace Lambda\Krud\Controllers;

use App\Http\Controllers\Controller;
use Lambda\Dataform\Dataform;
use Lambda\Datagrid\Datagrid;

class KrudController extends Controller
{
    public function crud($schemaID, $action, $dataID = false)
    {
        return Dataform::exec($schemaID, $action, $dataID);
    }

    public function delete($schema, $id)
    {
        if (Datagrid::exec('delete', $schema, $id)) {
            return response()->json(['status' => true]);
        }

        return response()->json(['status' => false]);
    }

    public function updateRow($schema)
    {
        if (Datagrid::exec('update-row', $schema)) {
            return response()->json(['status' => true]);
        }

        return response()->json(['status' => false]);
    }

    public function fileUpload()
    {
        return Dataform::upload();
    }

    public function checkUnique()
    {
        return Dataform::checkUnique();
    }

    public function checkCurrentPassword()
    {
        return Dataform::checkCurrentPassword();
    }

    public function excel($schemaID)
    {
        return Datagrid::exec('excel', $schemaID);
    }

    public function print($schemaID)
    {
        return Datagrid::exec('print', $schemaID);
    }
}
