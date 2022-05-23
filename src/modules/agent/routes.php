<?php

Route::namespace('Lambda\Agent\Controllers')
    ->prefix('agent')
    ->group(function ($router) {
        $router->get('/', 'AgentController@index');
        $router->get('users', 'AgentController@getUsers');
        $router->get('deletedusers', 'AgentController@getDeletedUsers');
        $router->get('users/show/{id}', 'AgentController@show')->middleware('jwt');
        $router->post('users/create', 'AgentController@store');
        $router->post('users/upload', 'AgentController@upload')->middleware('jwt');
        $router->patch('users/store/{id}', 'AgentController@update');
        $router->delete('users/destroy/{id}', 'AgentController@destroy');
        $router->delete('users/forceDestroy/{id}', 'AgentController@forceDelete');
        $router->get('users/restore/{id}', 'AgentController@restore');
        $router->get('wizard', 'AgentController@wizard')->middleware(['jwt', 'web']);
        $router->post('wizard/create', 'ProfileController@store')->middleware(['jwt', 'web']);
        $router->get('profile/{id}', 'ProfileController@show')->middleware(['jwt', 'web']);
    });

// Authenticating
Route::namespace('Lambda\Agent\Controllers')
    ->prefix('auth')
    ->group(function ($router) {
        $router->match(['get', 'post'], 'login', 'AuthController@login');
        $router->get('/', 'AuthController@login');
        $router->get('/{any}', 'AuthController@login');
        $router->post('logout', 'AuthController@logout');
        $router->post('refresh', 'AuthController@refresh');
        $router->get('me', 'AuthController@me');
        $router->get('check', 'RoleController@checkRoleAndRedirect');
        $router->post('password-reset', 'PasswordController@passwordReset');
        $router->post('send-forgot-mail', 'PasswordController@sendMail')->name('sendMail');
    });

//Pages
Route::namespace('Lambda\Agent\Controllers')
    ->prefix('pages')
    ->middleware(['jwt'])
    ->group(function ($router) {
        $router->get('/', 'PagesController@index');
        $router->get('/moqup/{id}', 'PagesController@moqup');
    });

//Agent routes
Route::namespace('Lambda\Agent\Controllers')
    ->prefix('agent')
    ->group(function ($router) {
        $router->get('/users/{type?}', 'AgentController@getUsers');
        $router->get('/user/{id}', 'AgentController@getUser');
        $router->get('/delete/{id}', 'AgentController@deleteUser');
        $router->get('/delete/complete/{id}', 'AgentController@deleteUserComplete');
        $router->get('/restore/{id}', 'AgentController@restoreUser');
        $router->get('/search/{q?}', 'AgentController@searchUsers');
        $router->get('/roles', 'AgentController@getRoles');
    });
