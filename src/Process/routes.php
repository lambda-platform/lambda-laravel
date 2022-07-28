<?php

Route::namespace('Lambda\Process\Controllers')
    ->prefix('lambda/process')
    ->middleware(['api', 'jwt'])
    ->group(function ($router) {
        $router->get('list', 'ProcessController@getProcessList');
    });