<?php

Route::namespace('Lambda\H5P\Controllers')
    ->prefix('lambda/h5p')
    ->middleware(['api', 'jwt'])
    ->group(function ($router) {
        $router->get('editor-settings', 'H5PController@editorSettings');
        $router->get('content-settings/{id}', 'H5PController@contentSettings');
        $router->get('contents', 'H5PController@contents');
    });
