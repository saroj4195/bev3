<?php
/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/
$router->get('/', function () use ($router) {
    return $router->app->version();
});
/*------------------agent login----------------------*/
$router->group(['prefix' => 'agent'], function($router) {
    $router->post('/auth',['uses'=>'AgentController@agentLogin']);
    $router->post('/forget-password',['uses'=>'AgentController@forgotPasswordAgent']);
    $router->post('/change-password',['uses'=>'AgentController@changePasswordAgent']);
    $router->get('/verify_user', ['uses' => 'AgentController@verifyUser']);
});
$router->get('/agent/get-all-hotels',['middleware' => 'jwt.auth','uses' => 'crs\AgentController@getAgentHotels']);
/*------------------agent login end-----------------*/