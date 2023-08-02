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

/* @var $router Laravel\Lumen\Routing\Router */

// WebTigerJython
$router->get('/', 'WtjController@wtj');
$router->get('/{share_id}', 'WtjController@wtj_get_code');
$router->get('/{share_id}/{return_id}', 'WtjController@wtj_get_return_code');
//$router->post('/wtj-login', 'WtjController@wtj_login');
$router->post('/wtj-share/add', 'WtjController@wtj_add_code');
$router->post('/wtj-return/add', 'WtjController@wtj_add_return_code');
