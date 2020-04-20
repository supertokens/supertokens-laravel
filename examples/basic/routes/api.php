<?php

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use SuperTokens\Exceptions\SuperTokensGeneralException;
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

Route::get('/create', function (Request $request) {
    $response = new Response();
    $userId = "test user";
    try {
        SuperTokens::createNewSession($response, $userId, [], []);
        return $response->setContent("success");
    } catch (SuperTokensGeneralException $e) {
        return $response->setStatusCode(500)->setContent($e->getMessage());
    }
});

Route::get('/verify', function (Request $request) {
    $response = new Response();
    try {
        $session = SuperTokens::getSession($request, $response, false);
    } catch (\SuperTokens\Exceptions\SuperTokensTryRefreshTokenException $e) {
        return $response->setStatusCode(440)->setContent("try refresh token");
    } catch (\SuperTokens\Exceptions\SuperTokensUnauthorisedException $e) {
        return $response->setStatusCode(440)->setContent("unauthorised");
    } catch (SuperTokensGeneralException $e) {
        return $response->setStatusCode(500)->setContent($e->getMessage());
    }
    return $response->setContent($session->getUserId());
});

Route::get('/refresh', function (Request $request) {
    $response = new Response();
    try {
        SuperTokens::refreshSession($request, $response);
    } catch (\SuperTokens\Exceptions\SuperTokensTokenTheftException $e) {
        return $response->setStatusCode(440)->setContent("token theft detected");
    } catch (\SuperTokens\Exceptions\SuperTokensUnauthorisedException $e) {
        return $response->setStatusCode(440)->setContent("unauthorised");
    } catch (SuperTokensGeneralException $e) {
        return $response->setStatusCode(500)->setContent($e->getMessage());
    }
    return $response->setContent("success");
});

Route::get("/logout", function (Request $request) {
    $response = new Response();
    try {
        $session = SuperTokens::getSession($request, $response, false);
        $session->revokeSession();
    } catch (\SuperTokens\Exceptions\SuperTokensTryRefreshTokenException $e) {
        // pass
    } catch (\SuperTokens\Exceptions\SuperTokensUnauthorisedException $e) {
        // pass
    } catch (SuperTokensGeneralException $e) {
        return $response->setStatusCode(500)->setContent($e->getMessage());
    }
    return $response->setContent("success");
});
