<?php

use Illuminate\Support\Facades\Config;

$config = Config::get('lambda');
Route::namespace('Lambda\Puzzle\Controllers')
    ->prefix('lambda/puzzle')
    ->middleware(['api', $config['lambda_access']?'jwt:'.$config['lambda_access']:'jwt'])
    //->middleware(['api'])
    ->group(function ($router) {
        $router->get('/', 'PuzzleController@index');
        $router->get('/builder', 'PuzzleController@builder');
        $router->post('/builder/save', 'PuzzleController@savePage');

        //Roles
        $router->get('roles-menus', 'RolesController@getRolesMenus');
//        $router->get('deletedroles', 'RolesController@getDeletedRoles');
        $router->get('get-krud-fields/{id}', 'RolesController@getKrudFields');
        $router->post('roles/create', 'RolesController@store');
        $router->post('roles/store/{id}', 'RolesController@update');
        $router->post('save-role', 'RolesController@saveRole');
        $router->delete('roles/destroy/{id}', 'RolesController@destroy');
        $router->get('roles/restore/{id}', 'RolesController@restore');
        $router->delete('roles/forceDestroy/{id}', 'RolesController@forceDelete');
    });


Route::namespace('Lambda\Puzzle\Controllers')
    ->prefix('lambda/puzzle')
    ->middleware(['api', 'jwt'])
    //->middleware(['api'])
    ->group(function ($router) {

        //Puzzle
        $router->get('/schema/{type}/{id?}/{condition?}', 'PuzzleController@getVB');
        $router->post('/schema/{type}/{id?}', 'PuzzleController@saveVB');

        //Crud
        $router->any('/form/{action}/{schemaID}', 'PuzzleController@formVB');
        $router->any('/grid/{action}/{schemaID}', 'PuzzleController@gridVB');
        $router->any('/upload', 'PuzzleController@fileUpload');

        //Get From Options
        $router->post('/get_options', 'PuzzleController@getOptions');

        //Embed
        $router->get('embed', 'PuzzleController@embed');
        $router->get('/krud/:id', 'PuzzleController@getKrud');
    });

Route::namespace('Lambda\Puzzle\Controllers')
    ->group(function ($router) {
        $router->get('/api/lm/puzzle/schema/{type}/{id?}/{condition?}', 'PuzzleController@getVB');
        $router->post('/api/lm/puzzle/get_options', 'PuzzleController@getOptions');
    });

