<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Config;

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

Route::options("*", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    $res->header("Access-Control-Allow-Headers", "content-type");
    $res->header("Access-Control-Allow-Headers", "*");
    \SuperTokens\SuperTokens::setRelevantHeadersForOptionAPI($res);
    return $res;
});

Route::post("/login", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $data = $request->json()->all();
    $userId = $data["userId"];
    \SuperTokens\SuperTokens::createNewSession($res, $userId);
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    $res->header("Access-Control-Allow-Credentials", true);
    return $res->setContent($userId);
});


Route::post("/beforeeach", function (Request $request) {
    error_log("-------------------------");
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

Route::get("/", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    try {
        \App\Utils::getInstance()->incrementSessionCount();
        \SuperTokens\SuperTokens::getSession($request, $res, true);
        $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
        $res->header("Access-Control-Allow-Credentials", true);
        return $res->setContent("success");
    } catch (Exception $err) {
        $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
        $res->header("Access-Control-Allow-Credentials", true);
        return $res->setStatusCode(440);
    }
});

Route::any("/testing", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    if ($request->hasHeader("testing")) {
        $res->header("testing", $request->header("testing"));
    }
    return $res->setContent("success");
});

Route::post("/logout", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    try {
        $session = \SuperTokens\SuperTokens::getSession($request, $res, true);
        $session->revokeSession($res);
        $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
        $res->header("Access-Control-Allow-Credentials", true);
        return $res->setContent("success");
    } catch (Exception $err) {
        return $res->setStatusCode(440);
    }
});

Route::post("/revokeAll", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    try {
        $session = \SuperTokens\SuperTokens::getSession($request, $res, true);
        $userId = $session->getUserId();
        \SuperTokens\SuperTokens::revokeAllSessionsForUser($userId);
        return $res->setContent("success");
    } catch (Exception $err) {
        return $res->setStatusCode(440);
    }
});

Route::post("/refresh", function (Request $request) {
    error_log("REFRESH!");
    $res = new \Illuminate\Http\Response();
    try {
        \SuperTokens\SuperTokens::refreshSession($request, $res);
        \App\Utils::getInstance()->incrementRefreshCount();
        $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
        $res->header("Access-Control-Allow-Credentials", true);
        return $res->setContent("refresh success");
    } catch (Exception $err) {
        $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
        $res->header("Access-Control-Allow-Credentials", true);
        return $res->setStatusCode(440);
    }
});

Route::get("/refreshCalledTime", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    return $res->setContent(\App\Utils::getInstance()->getRefreshCount());
});

Route::get("/getSessionCalledTime", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    return $res->setContent(\App\Utils::getInstance()->getSessionCount());
});

Route::get("/getPackageVersion", function (Request $request) {
    $res = new \Illuminate\Http\Response();
    $res->header("Access-Control-Allow-Origin", "http://127.0.0.1:8080");
    return $res->setContent("4.1.3");
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
    return strcmp($sdkName, "website") === 0 && strcmp($sdkVersion, "4.1.3") === 0;
});

Route::get("/checkAllowCredentials", function (Request $request) {
    return $request->hasHeader("allow-credentials");
});


Route::get("/testError", function (Request $request) {
    return (new \Illuminate\Http\Response())->setStatusCode(500)->setContent("test error message");
});
