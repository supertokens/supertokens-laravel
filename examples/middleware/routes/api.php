<?php

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use SuperTokens\SuperTokens;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware("supertokens.errorhandler")->get('/create', function (Request $request) {
    $response = new Response();
    $userId = "test user";
    SuperTokens::createNewSession($response, $userId, [], []);
    return $response->setContent("success");
});

Route::middleware("supertokens.verify:false")->get('/verify', function (Request $request) {
    $response = new Response();
    return $response->setContent($request->request->get('supertokenSession')->getUserId());
});

Route::middleware("supertokens.errorhandler")->get('/refresh', function (Request $request) {
    $response = new Response();
    SuperTokens::refreshSession($request, $response);
    return $response->setContent("success");
});

Route::middleware("supertokens.verify:false")->get("/logout", function (Request $request) {
    $response = new Response();
    $request->request->get('supertokenSession')->revokeSession($response);
    ;
    return $response->setContent("success");
});
