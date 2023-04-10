<?php

Route::namespace('Lambda\Notify\Controllers')
    ->prefix('lambda/notify')
    ->middleware(['api'])
    ->group(function ($router) {
        $router->get('/new/{user}', 'NotifyController@getNewNotifications');
        $router->get('/all', 'NotifyController@getAllNotifications');
        $router->get('/seen/{id}', 'NotifyController@setSeen');
        $router->get('/seen-all', 'NotifyController@setSeenAll');
        $router->get('/token/{user}/{token}', 'NotifyController@setToken');
        $router->get('/fcm', 'NotifyController@fcm');
    });
