<?php

use Illuminate\Support\Facades\Config;

$config = Config::get('lambda');
$adminMiddleware = (isset($config['lambda_access']) && $config['lambda_access']) ? 'jwt:' . $config['lambda_access'] : 'jwt';

// Authenticating
Route::namespace('Lambda\Agent\Controllers')
    ->prefix('auth')
    ->group(function ($router) {
        $router->match(['get', 'post'], 'login', 'AuthController@login');
        $router->get('/', 'AuthController@login');
        $router->post('logout', 'AuthController@logout');
        $router->post('refresh', 'AuthController@refresh');
        $router->get('me', 'AuthController@me');
        $router->post('password-reset', 'PasswordController@passwordReset')->middleware('throttle:10,1');
        $router->post('send-forgot-mail', 'PasswordController@sendMail')->middleware('throttle:5,1')->name('sendMail');
        // Catch-all must stay last so it doesn't shadow the routes above
        $router->get('/{any}', 'AuthController@login');
    });

//Agent routes (user management)
Route::namespace('Lambda\Agent\Controllers')
    ->prefix('agent')
    ->middleware([$adminMiddleware])
    ->group(function ($router) {
        $router->get('/users/{type?}', 'AgentController@getUsers');
        $router->get('/user/{id}', 'AgentController@getUser');
        $router->get('/delete/{id}', 'AgentController@deleteUser');
        $router->get('/delete/complete/{id}', 'AgentController@deleteUserComplete');
        $router->get('/restore/{id}', 'AgentController@restoreUser');
        $router->get('/search/{q?}', 'AgentController@searchUsers');
        $router->get('/roles', 'AgentController@getRoles');
    });
