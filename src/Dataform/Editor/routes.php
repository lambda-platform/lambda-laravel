<?php

// config/lambda.php → filemanager_roles: e.g. [1, 2] limits the media library
// (list, upload, rename, delete) to those roles. Empty: any logged-in user.
$fileManagerRoles = (array)config('lambda.filemanager_roles', []);
$fileManagerAuth = $fileManagerRoles ? 'jwt:' . implode(',', $fileManagerRoles) : 'jwt';

Route::namespace('Lambda\Dataform\Editor')
    ->prefix('lambda/filemanager')
    ->middleware(['api', $fileManagerAuth])
    ->group(function ($router) {
        $router->get('files', 'FileManagerController@files');
        $router->post('upload', 'FileManagerController@upload');
        $router->post('save', 'FileManagerController@save');
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
