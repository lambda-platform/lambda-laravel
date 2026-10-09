<?php

Route::namespace('Lambda\Krud\Controllers')
    ->prefix('lambda/krud')
    ->middleware(['api', 'jwt'])
    ->group(function ($router) {
        $router->any('excel/{schema}', 'KrudController@excel');
        $router->any('print/{schema}', 'KrudController@print');
        $router->post('import-excel', 'KrudController@excelImport');
        $router->match(['post', 'POST'], 'update-row/{schema}', 'KrudController@updateRow');
        $router->match(['get', 'post', 'GET', 'POST'], '{schemaId}/{action}/{id?}', 'KrudController@crud');
        $router->match(['delete', 'DELETE'], 'delete/{schema}/{id}', 'KrudController@delete');
        $router->post('check_current_password', 'KrudController@checkCurrentPassword')->middleware('throttle:10,1');
    });

Route::namespace('Lambda\Krud\Controllers')
    ->prefix('lambda/krud')
    ->middleware(['api'])
    ->group(function ($router) {
        $router->post('upload', 'KrudController@fileUpload');
        $router->post('upload-tinymce', 'KrudController@fileUploadTinyMce');
        $router->post('unique', 'KrudController@checkUnique');
    });