<?php

Route::namespace('Lambda\Dataform\Editor')
    ->prefix('lambda/filemanager')
    ->middleware(['api', 'jwt'])
    ->group(function ($router) {
        $router->get('files', 'FileManagerController@files');
        $router->post('upload', 'FileManagerController@upload');
        $router->post('folder', 'FileManagerController@folder');
        $router->post('rename', 'FileManagerController@rename');
        $router->post('delete', 'FileManagerController@delete');
    });

Route::namespace('Lambda\Dataform\Editor')
    ->prefix('lambda/filemanager')
    ->middleware(['api'])
    ->group(function ($router) {
        $router->get('file/{path}', 'FileManagerController@file')->where('path', '.*');
    });
