<?php

Route::namespace('Lambda\Krud\Controllers')
    ->prefix('lambda/krud')
    ->middleware(['api'])
    ->group(function ($router) {
        $router->any('excel/{schema}', 'KrudController@excel');
        $router->any('print/{schema}', 'KrudController@print');
        $router->match(['post', 'POST'], 'update-row/{schema}', 'KrudController@updateRow');
        $router->match(['get', 'post', 'GET', 'POST'], '{schemaId}/{action}/{id?}', 'KrudController@crud');
        $router->match(['delete', 'DELETE'], 'delete/{schema}/{id}', 'KrudController@delete');
    });

Route::namespace('Lambda\Krud\Controllers')
    ->prefix('lambda/krud')
    ->middleware(['api'])
    ->group(function ($router) {
        $router->post('upload', 'KrudController@fileUpload');
        $router->post('upload-tinymce', 'KrudController@fileUploadTinyMce');
        $router->post('unique', 'KrudController@checkUnique');
        $router->post('check_current_password', 'KrudController@checkCurrentPassword');
    });

Route::namespace('Lambda\Krud\Controllers')
    ->group(function ($router) {
        $router->post('/api/lm/form/{schemaId}/{action}', 'KrudController@crud');
    });
