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

Route::options("/login", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    $res->header("Access-Control-Allow-Headers", "Content-Type");
    SuperTokens::setRelevantHeadersForOptionsAPI($res);
    return $res;
});

Route::post("/login", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $data = $request->json()->all();
    $userId = $data["userId"];
    SuperTokens::createNewSession($res, $userId);
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    $res->header("Access-Control-Allow-Credentials", "true");
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

Route::options("/multipleInterceptors", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    $res->header("Access-Control-Allow-Headers", "Content-Type");
    SuperTokens::setRelevantHeadersForOptionsAPI($res);
    return $res;
});

Route::middleware("supertokens.middleware:true")->get("/", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    try {
        \App\Utils::getInstance()->incrementSessionCount();
        $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
        $res->header("Access-Control-Allow-Credentials", "true");
        $res->header("Cache-Control", "no-cache, private");
        return $res->setContent($request->request->get('supertokens')->getUserId());
    } catch (Exception $err) {
        $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
        $res->header("Access-Control-Allow-Credentials", "true");
        return $res->setStatusCode(440);
    }
});

Route::options("/", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    $res->header("Access-Control-Allow-Headers", "Content-Type");
    SuperTokens::setRelevantHeadersForOptionsAPI($res);
    return $res;
});


Route::options("/update-jwt", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    $res->header("Access-Control-Allow-Headers", "Content-Type");
    SuperTokens::setRelevantHeadersForOptionsAPI($res);
    return $res;
});

Route::middleware("supertokens.middleware:true")->get("/update-jwt", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    $res->header("Access-Control-Allow-Credentials", "true");
    $res->header("Cache-Control", "no-cache, private");
    return $res->setContent($request->request->get('supertokens')->getJWTPayload());
});

Route::middleware("supertokens.middleware:true")->post("/update-jwt", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    $res->header("Access-Control-Allow-Credentials", "true");
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

Route::options("/testing", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    $res->header("Access-Control-Allow-Headers", "Content-Type");
    SuperTokens::setRelevantHeadersForOptionsAPI($res);
    return $res;
});

Route::middleware("supertokens.middleware:true")->post("/logout", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $session = $request->request->get('supertokens');
    $session->revokeSession($res);
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    $res->header("Access-Control-Allow-Credentials", "true");
    return $res->setContent("success");
});

Route::options("/logout", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    $res->header("Access-Control-Allow-Headers", "Content-Type");
    SuperTokens::setRelevantHeadersForOptionsAPI($res);
    return $res;
});

Route::middleware("supertokens.middleware:true")->post("/revokeAll", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $session = $request->request->get('supertokens');
    $userId = $session->getUserId();
    SuperTokens::revokeAllSessionsForUser($userId);
    return $res->setContent("success");
});

Route::options("/revokeAll", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    $res->header("Access-Control-Allow-Headers", "Content-Type");
    SuperTokens::setRelevantHeadersForOptionsAPI($res);
    return $res;
});

Route::middleware("supertokens.middleware:true")->post("/refresh", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    \App\Utils::getInstance()->incrementRefreshCount();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    $res->header("Access-Control-Allow-Credentials", "true");
    return $res->setContent("refresh success");
});

Route::options("/refresh", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    $res->header("Access-Control-Allow-Headers", "Content-Type");
    SuperTokens::setRelevantHeadersForOptionsAPI($res);
    return $res;
});

Route::get("/refreshCalledTime", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    return $res->setContent(\App\Utils::getInstance()->getRefreshCount());
});

Route::options("/refreshCalledTime", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    $res->header("Access-Control-Allow-Headers", "Content-Type");
    SuperTokens::setRelevantHeadersForOptionsAPI($res);
    return $res;
});

Route::get("/getSessionCalledTime", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    return $res->setContent(\App\Utils::getInstance()->getSessionCount());
});

Route::options("/getSessionCalledTime", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    $res->header("Access-Control-Allow-Headers", "Content-Type");
    SuperTokens::setRelevantHeadersForOptionsAPI($res);
    return $res;
});

Route::get("/getPackageVersion", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    return $res->setContent("4.1.3");
});

Route::options("/getPackageVersion", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    $res->header("Access-Control-Allow-Headers", "Content-Type");
    SuperTokens::setRelevantHeadersForOptionsAPI($res);
    return $res;
});

Route::get("/ping", function (Request $request) {
    return "success";
});

Route::options("/ping", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    $res->header("Access-Control-Allow-Headers", "Content-Type");
    SuperTokens::setRelevantHeadersForOptionsAPI($res);
    return $res;
});

Route::get("/testHeader", function (Request $request) {
    $success = $request->hasHeader("st-custom-header");
    $data = ["success" => $success];
    return (new \Illuminate\Http\Response())->json($data);
});

Route::options("/testHeader", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    $res->header("Access-Control-Allow-Headers", "Content-Type");
    SuperTokens::setRelevantHeadersForOptionsAPI($res);
    return $res;
});

Route::get("/checkDeviceInfo", function (Request $request) {
    $sdkName = $request->header("supertokens-sdk-name");
    $sdkVersion = $request->header("supertokens-sdk-version");
    return (strcmp($sdkName, "website") === 0 && strcmp($sdkVersion, "4.1.4") === 0) ? "true" : "false";
});

Route::options("/checkDeviceInfo", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    $res->header("Access-Control-Allow-Headers", "Content-Type");
    SuperTokens::setRelevantHeadersForOptionsAPI($res);
    return $res;
});

Route::get("/checkAllowCredentials", function (Request $request) {
    return $request->hasHeader("allow-credentials");
});

Route::options("/checkAllowCredentials", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    $res->header("Access-Control-Allow-Headers", "Content-Type");
    SuperTokens::setRelevantHeadersForOptionsAPI($res);
    return $res;
});

Route::get("/testError", function (Request $request) {
    return (new \Illuminate\Http\Response())->setStatusCode(500)->setContent("test error message");
});

Route::options("/testError", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    $res->header("Access-Control-Allow-Headers", "Content-Type");
    SuperTokens::setRelevantHeadersForOptionsAPI($res);
    return $res;
});
