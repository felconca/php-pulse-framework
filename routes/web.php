<?php

use App\Routes\Route;
use App\Middleware\AuthMiddleware;
use App\Middleware\AuthSession;

Route::get('users', 'AppController@index');
Route::post('users', 'AppController@store');
Route::put('users', 'AppController@update');
Route::delete("users", "AppController@delete");

Route::get('users/{id}', 'AppController@show', [new AuthMiddleware()]);
Route::get('users/id/{id}/date/{date}', 'AppController@showByIdDate', [new AuthMiddleware()]);

Route::post('index', 'AppController@index', [new AuthMiddleware()]);
Route::get('index', 'AppController@index', [new AuthSession("user")]);
Route::post('index', 'AppController@index', [new AuthSession("user")]);


// Group with prefix
Route::group(['prefix' => 'api/inventory', 'middleware' => [new AuthMiddleware()]], function () {
    Route::get('items', 'InventoryController@index');           // GET /api/inventory/items
    Route::get('items/{id}', 'InventoryController@show');       // GET /api/inventory/items/123
    Route::post('items', 'InventoryController@store');          // POST /api/inventory/items
});
