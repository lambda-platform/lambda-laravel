<?php

//Pages
Route::namespace('Lambda\Translation\Controllers')
    ->prefix('lambda/locale')
    ->middleware(['jwt'])
    ->group(function ($router) {
        $router->get('/languages', 'TranslationController@getLocales');
        $router->get('/trigger', 'TranslationController@localeTrigger');
        $router->get('/translation', 'TranslationController@getTranslation');

        //Crud
        $router->post('/add', 'TranslationController@addTranslation');
        $router->get('/delete/{id}', 'TranslationController@deleteTranslation');
        $router->post('/update', 'TranslationController@updateTranslation');

        //For generating translation files
        $router->get('/generate', 'TranslationController@generateLocale');
    });