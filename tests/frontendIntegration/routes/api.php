<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Config;
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


$noOfTimesRefreshCalledDuringTest = 0;


Route::post("/login", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $data = $request->json()->all();
    $userId = $data["userId"];
    SuperTokens::createNewSession($res, $userId);
    return $res->setContent($userId);
});


Route::post("/beforeeach", function (Request $request) {
    \App\Utils::getInstance()->reset();
    return "";
});


Route::post("/testUserConfig", function (Request $request) {
    return "";
});

Route::post("/multipleInterceptors", function (Request $request) {
    $result = $request->hasHeader("interceptorheader2") && $request->hasHeader("interceptorheader1") ?
        "success" : "failure";
    return $result;
});


Route::middleware("supertokens.middleware:true")->get("/", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    try {
        \App\Utils::getInstance()->incrementSessionCount();
        $res->header("Cache-Control", "no-cache, private");
        return $res->setContent($request->request->get('supertokens')->getUserId());
    } catch (Exception $err) {
        return $res->setStatusCode(401);
    }
});



Route::middleware("supertokens.middleware:true")->get("/update-jwt", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Cache-Control", "no-cache, private");
    return $res->setContent($request->request->get('supertokens')->getJWTPayload());
});

Route::middleware("supertokens.middleware:true")->post("/update-jwt", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Cache-Control", "no-cache, private");
    $request->request->get('supertokens')->updateJWTPayload($request->json()->all());
    return $res->setContent($request->request->get('supertokens')->getJWTPayload());
});

Route::any("/testing", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    if ($request->hasHeader("testing")) {
        $res->header("testing", $request->header("testing"));
    }
    return $res->setContent("success");
});


Route::middleware("supertokens.middleware:true")->post("/logout", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $session = $request->request->get('supertokens');
    $session->revokeSession($res);
    return $res->setContent("success");
});


Route::middleware("supertokens.middleware:true")->post("/revokeAll", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $session = $request->request->get('supertokens');
    $userId = $session->getUserId();
    SuperTokens::revokeAllSessionsForUser($userId);
    return $res->setContent("success");
});


Route::middleware("supertokens.middleware:true")->post("/refresh", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    \App\Utils::getInstance()->incrementRefreshCount();
    return $res->setContent("refresh success");
});


Route::get("/refreshCalledTime", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    return $res->setContent(\App\Utils::getInstance()->getRefreshCount());
});


Route::get("/getSessionCalledTime", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    return $res->setContent(\App\Utils::getInstance()->getSessionCount());
});


Route::get("/ping", function (Request $request) {
    return "success";
});



Route::get("/testHeader", function (Request $request) {
    $success = $request->hasHeader("st-custom-header");
    $data = ["success" => $success];
    return (new \Illuminate\Http\Response())->json($data);
});



Route::get("/checkDeviceInfo", function (Request $request) {
    $sdkName = $request->header("supertokens-sdk-name");
    $sdkVersion = $request->header("supertokens-sdk-version");
    return (strcmp($sdkName, "website") === 0 && !is_null($sdkVersion) && is_string($sdkVersion)) ? "true" : "false";
});


Route::get("/checkAllowCredentials", function (Request $request) {
    return $request->hasHeader("allow-credentials");
});



Route::get("/testError", function (Request $request) {
    return (new \Illuminate\Http\Response())->setStatusCode(500)->setContent("test error message");
});
