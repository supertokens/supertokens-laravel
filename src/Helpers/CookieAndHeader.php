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
     */
    public static function setCookie(Response $response, $key, $value, $minutes, $path, $domain, $secure, $httpOnly, $sameSite)
    {
        $response->withCookie(cookie($key, $value, $minutes, $path, $domain, $secure, $httpOnly, false, $sameSite));
    }

    /**
     * @param Request $request
     * @param $key
     * @return string|null
     */
    public static function getCookie(Request $request, $key)
    {
        return $request->cookie($key);
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
        CookieAndHeader::setCookie($response, ACCESS_TOKEN_COOKIE_KEY, "", 0, $accessTokenPath, $domain, $secure, true, $sameSite);
        CookieAndHeader::setCookie($response, ID_REFRESH_TOKEN_COOKIE_KEY, "", 0, $accessTokenPath, $domain, $secure, true, $sameSite);
        CookieAndHeader::setCookie($response, REFRESH_TOKEN_COOKIE_KEY, "", 0, $refreshTokenPath, $domain, $secure, true, $sameSite);
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
        CookieAndHeader::setCookie($response, ACCESS_TOKEN_COOKIE_KEY, $token, CookieAndHeader::getMinutes($expiresAt), $path, $domain, $secure, true, $sameSite);
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
        CookieAndHeader::setCookie($response, REFRESH_TOKEN_COOKIE_KEY, $token, CookieAndHeader::getMinutes($expiresAt), $path, $domain, $secure, true, $sameSite);
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
        CookieAndHeader::setCookie($response, ID_REFRESH_TOKEN_COOKIE_KEY, $token, CookieAndHeader::getMinutes($expiresAt), $path, $domain, $secure, true, $sameSite);
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
        $expiresAt = (int)floor($expiresAt / 1000);
        $currentTimestamp = Utils::getCurrentTimestamp();
        $minutes = floor(($expiresAt - $currentTimestamp) / 60);
        $minutes = max(0, $minutes);
        return (int)$minutes;
    }

    /**
     * @param Response $response
     * @param $session
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    public static function attachSessionToResponse(Response $response, $session)
    {
        $accessToken = $session['accessToken'];
        $refreshToken = $session['refreshToken'];
        $idRefreshToken = $session['idRefreshToken'];
        $accessTokenSameSite = Constants::SAME_SITE_COOKIE_DEFAULT_VALUE;
        $refreshTokenSameSite = Constants::SAME_SITE_COOKIE_DEFAULT_VALUE;
        $idRefreshTokenSameSite = Constants::SAME_SITE_COOKIE_DEFAULT_VALUE;
        $idRefreshTokenDomain = $accessToken['domain'];
        $idRefreshCookieSecure = $accessToken['cookieSecure'];
        $idRefreshCookiePath = $accessToken['cookiePath'];
        if (Querier::getInstance()->getApiVersion() !== "1.0") {
            $accessTokenSameSite = $accessToken['sameSite'];
            $refreshTokenSameSite = $refreshToken['sameSite'];
            $idRefreshTokenSameSite = $idRefreshToken['sameSite'];
            $idRefreshTokenDomain = $idRefreshToken['domain'];
            $idRefreshCookieSecure = $idRefreshToken['cookieSecure'];
            $idRefreshCookiePath = $idRefreshToken['cookiePath'];
        }
        CookieAndHeader::attachAccessTokenToCookie($response, $accessToken['token'], $accessToken['expiry'], $accessToken['domain'], $accessToken['cookieSecure'], $accessToken['cookiePath'], $accessTokenSameSite);
        CookieAndHeader::attachRefreshTokenToCookie($response, $refreshToken['token'], $refreshToken['expiry'], $refreshToken['domain'], $refreshToken['cookieSecure'], $refreshToken['cookiePath'], $refreshTokenSameSite);
        CookieAndHeader::attachIdRefreshTokenToCookieAndHeader($response, $idRefreshToken['token'], $idRefreshToken['expiry'], $idRefreshTokenDomain, $idRefreshCookieSecure, $idRefreshCookiePath, $idRefreshTokenSameSite);
        if (isset($session['antiCsrfToken'])) {
            CookieAndHeader::attachAntiCsrfHeader($response, $session['antiCsrfToken']);
        }
    }
}
