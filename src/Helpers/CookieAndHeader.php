<?php
/* Copyright (c) 2020, VRAI Labs and/or its affiliates. All rights reserved.
 *
 * This software is licensed under the Apache License, Version 2.0 (the
 * "License") as published by the Apache Software Foundation.
 *
 * You may not use this file except in compliance with the License. You may
 * obtain a copy of the License at http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */
namespace SuperTokens\Helpers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use SuperTokens\Exceptions\SuperTokensException;
use SuperTokens\Exceptions\SuperTokensGeneralException;

define("ACCESS_TOKEN_COOKIE_KEY", "sAccessToken");
define("REFRESH_TOKEN_COOKIE_KEY", "sRefreshToken");
define("ID_REFRESH_TOKEN_COOKIE_KEY", "sIdRefreshToken");
define("ANTI_CSRF_HEADER_KEY", "anti-csrf");
define("ID_REFRESH_TOKEN_HEADER_KEY", "id-refresh-token");
define("FRONTEND_SDK_NAME_HEADER_KEY", "supertokens-sdk-name");
define("FRONTEND_SDK_VERSION_HEADER_KEY", "supertokens-sdk-version");


class CookieAndHeader
{
    public static function saveFrontendInfoFromRequest(Request $request)
    {
        try {
            $name = CookieAndHeader::getHeader($request, FRONTEND_SDK_NAME_HEADER_KEY);
            $version = CookieAndHeader::getHeader($request, FRONTEND_SDK_VERSION_HEADER_KEY);
            if (isset($name) && isset($version)) {
                DeviceInfo::getInstance()->addToFrontendSDKs([
                    'name' => $name,
                    'version' => $version
                ]);
            }
        } catch (Exception $e) {
        }
    }
    public static function setOptionsAPIHeader(Response $response)
    {
        CookieAndHeader::setHeader($response, "Access-Control-Allow-Headers", ANTI_CSRF_HEADER_KEY);
        CookieAndHeader::setHeader($response, "Access-Control-Allow-Headers", FRONTEND_SDK_NAME_HEADER_KEY);
        CookieAndHeader::setHeader($response, "Access-Control-Allow-Headers", FRONTEND_SDK_VERSION_HEADER_KEY);
        CookieAndHeader::setHeader($response, "Access-Control-Allow-Credentials", "true");
    }

    public static function getCORSAllowedHeaders()
    {
        return [ANTI_CSRF_HEADER_KEY, FRONTEND_SDK_NAME_HEADER_KEY, FRONTEND_SDK_VERSION_HEADER_KEY];
    }

    public static function setHeader(Response $response, $key, $value)
    {
        try {
            $old_values = $response->headers->get($key);
            if (is_string($old_values) and $old_values !== "") {
                $value = $old_values.", ".$value;
            }
            $response->header($key, $value);
        } catch (Exception $e) {
            // error will be thrown if a header is not already set
            $response->header($key, $value);
        }
    }

    private static function getHeader(Request $request, $key)
    {
        return $request->header($key, null);
    }

    public static function attachAntiCsrfHeader(Response $response, $value)
    {
        CookieAndHeader::setHeader($response, ANTI_CSRF_HEADER_KEY, $value);
        CookieAndHeader::setHeader($response, "Access-Control-Expose-Headers", ANTI_CSRF_HEADER_KEY);
    }

    public static function getAntiCsrfHeader(Request $request)
    {
        return CookieAndHeader::getHeader($request, ANTI_CSRF_HEADER_KEY);
    }

    /**
     * @param Response $response
     * @param $key
     * @param $value
     * @param $minutes
     * @param $path
     * @param $domain
     * @param $secure
     * @param $httpOnly
     * @param $sameSite
     * @param null|string $pathType
     */
    public static function setCookie(Response $response, $key, $value, $minutes, $path, $domain, $secure, $httpOnly, $sameSite, $pathType = null)
    {
        $domain = Config::get('supertokens.cookieDomain', $domain);
        $secure = Config::get('supertokens.cookieSecure', $secure);
        $sameSite = Config::get('supertokens.cookieSameSite', $sameSite);
        if ($pathType === 'accessTokenPath') {
            $path = Config::get('supertokens.accessTokenPath', $path);
        } elseif ($pathType === 'refreshTokenPath') {
            $path = Config::get('supertokens.refreshTokenPath', $path);
        }
        $response->withCookie(cookie($key, rawurlencode($value), $minutes, $path, $domain, $secure, $httpOnly, false, $sameSite));
    }

    /**
     * @param Request $request
     * @param $key
     * @return string|null
     */
    public static function getCookie(Request $request, $key)
    {
        $val =  $request->cookie($key);
        if (!is_null($val)) {
            return rawurldecode($val);
        }
        return null;
    }

    /**
     * @param Response $response
     * @param string $domain
     * @param string $secure
     * @param string $accessTokenPath
     * @param string $refreshTokenPath
     * @param string $sameSite
     */
    public static function clearSessionFromCookie(Response $response, $domain, $secure, $accessTokenPath, $refreshTokenPath, $sameSite)
    {
        CookieAndHeader::setCookie($response, ACCESS_TOKEN_COOKIE_KEY, "", 0, $accessTokenPath, $domain, $secure, true, $sameSite, 'accessTokenPath');
        CookieAndHeader::setCookie($response, ID_REFRESH_TOKEN_COOKIE_KEY, "", 0, $accessTokenPath, $domain, $secure, true, $sameSite, 'accessTokenPath');
        CookieAndHeader::setCookie($response, REFRESH_TOKEN_COOKIE_KEY, "", 0, $refreshTokenPath, $domain, $secure, true, $sameSite, 'refreshTokenPath');
        CookieAndHeader::setHeader($response, ID_REFRESH_TOKEN_HEADER_KEY, "remove");
        CookieAndHeader::setHeader($response, "Access-Control-Expose-Headers", ID_REFRESH_TOKEN_HEADER_KEY);
    }

    /**
     * @param Response $response
     * @param $token
     * @param $expiresAt
     * @param $domain
     * @param $secure
     * @param $path
     * @param $sameSite
     */
    public static function attachAccessTokenToCookie(Response $response, $token, $expiresAt, $domain, $secure, $path, $sameSite)
    {
        CookieAndHeader::setCookie($response, ACCESS_TOKEN_COOKIE_KEY, $token, CookieAndHeader::getMinutes($expiresAt), $path, $domain, $secure, true, $sameSite, 'accessTokenPath');
    }

    /**
     * @param Response $response
     * @param $token
     * @param $expiresAt
     * @param $domain
     * @param $secure
     * @param $path
     * @param $sameSite
     */
    public static function attachRefreshTokenToCookie(Response $response, $token, $expiresAt, $domain, $secure, $path, $sameSite)
    {
        CookieAndHeader::setCookie($response, REFRESH_TOKEN_COOKIE_KEY, $token, CookieAndHeader::getMinutes($expiresAt), $path, $domain, $secure, true, $sameSite, 'refreshTokenPath');
    }

    /**
     * @param Response $response
     * @param $token
     * @param $expiresAt
     * @param $domain
     * @param $secure
     * @param $path
     * @param $sameSite
     */
    public static function attachIdRefreshTokenToCookieAndHeader(Response $response, $token, $expiresAt, $domain, $secure, $path, $sameSite)
    {
        CookieAndHeader::setHeader($response, ID_REFRESH_TOKEN_HEADER_KEY, $token.";".$expiresAt);
        CookieAndHeader::setHeader($response, "Access-Control-Expose-Headers", ID_REFRESH_TOKEN_HEADER_KEY);
        CookieAndHeader::setCookie($response, ID_REFRESH_TOKEN_COOKIE_KEY, $token, CookieAndHeader::getMinutes($expiresAt), $path, $domain, $secure, true, $sameSite, 'accessTokenPath');
    }

    /**
     * @param Request $request
     * @return string|null
     */
    public static function getAccessTokenFromCookie(Request $request)
    {
        return self::getCookie($request, ACCESS_TOKEN_COOKIE_KEY);
    }

    /**
     * @param Request $request
     * @return string|null
     */
    public static function getRefreshTokenFromCookie(Request $request)
    {
        return self::getCookie($request, REFRESH_TOKEN_COOKIE_KEY);
    }

    /**
     * @param Request $request
     * @return string|null
     */
    public static function getIdRefreshTokenFromCookie(Request $request)
    {
        return self::getCookie($request, ID_REFRESH_TOKEN_COOKIE_KEY);
    }

    /**
     * @param $expiresAt
     * @return int
     */
    private static function getMinutes($expiresAt)
    {
        $currentTimestamp = Utils::getCurrentTimestampMS();
        $minutes = ceil(($expiresAt - $currentTimestamp) / 60000);
        $minutes = max(0, $minutes);
        return (int)$minutes;
    }
}
