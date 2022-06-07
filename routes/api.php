<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\User\AdminsController;
use App\Http\Controllers\User\CountryPartnerController;
use App\Http\Controllers\OrganizationController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group.
|
*/

Route::post('login', [UsersController::class,"login"]);

Route::group(['middleware' => ['auth:sanctum']], function() {
    Route::post('logout', [UsersController::class,"logout"]);

    Route::group(['middleware' => ['role:super admin']], function() {
        Route::apiResource('users/admins', AdminsController::class);
        Route::apiResource('roles', RoleController::class);
    });

    Route::group(['middleware' => ['role:super admin|admin']], function() {
        Route::apiResource('organizations', OrganizationController::class);
        Route::apiResource('users/country_partners', CountryPartnerController::class);
    });
});
