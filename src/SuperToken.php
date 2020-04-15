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
namespace SuperTokens\Session;

use ArrayObject;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SuperTokens\Session\Exceptions\SuperTokensException;
use SuperTokens\Session\Exceptions\SuperTokensTokenTheftException;
use SuperTokens\Session\Helpers\CookieAndHeader;
use SuperTokens\Session\Exceptions\SuperTokensGeneralException;
use SuperTokens\Session\Exceptions\SuperTokensUnauthorizedException;
use SuperTokens\Session\Exceptions\SuperTokensTryRefreshTokenException;
use SuperTokens\Session\Helpers\HandshakeInfo;

class SuperToken
{

    /**
     * SessionHandlingFunctions constructor.
     * @throws Exception
     */
    public function __construct()
    {
        new SessionHandlingFunctions();
    }

    /**
     * @param Response $response
     * @param string $userId
     * @param array | null $jwtPayload
     * @param array | null $sessionData
     * @return Session
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    public static function createNewSession(Response $response, $userId, $jwtPayload = null, $sessionData = null)
    {
        $newSession = SessionHandlingFunctions::createNewSession($userId, $jwtPayload, $sessionData);
        $accessToken = $newSession['accessToken'];
        $refreshToken = $newSession['refreshToken'];
        $idRefreshToken = $newSession['idRefreshToken'];
        CookieAndHeader::attachAccessTokenToCookie($response, $accessToken['token'], $accessToken['expiry'], $accessToken['domain'], $accessToken['cookieSecure'], $accessToken['cookiePath']);
        CookieAndHeader::attachRefreshTokenToCookie($response, $refreshToken['token'], $refreshToken['expiry'], $refreshToken['domain'], $refreshToken['cookieSecure'], $refreshToken['cookiePath']);
        CookieAndHeader::attachIdRefreshTokenToCookieAndHeader($response, $idRefreshToken['token'], $idRefreshToken['expiry'], $idRefreshToken['domain'], $idRefreshToken['cookieSecure'], $idRefreshToken['cookiePath']);
        if (isset($newSession['antiCsrfToken'])) {
            CookieAndHeader::attachAntiCsrfHeader($response, $newSession['antiCsrfToken']);
        }
        return new Session($newSession['session']['handle'], $newSession['session']['userId'], $newSession['session']['userDataInJWT'], $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param boolean $doAntiCsrfCheck
     * @return Session
     * @throws SuperTokensGeneralException
     * @throws SuperTokensTryRefreshTokenException
     * @throws SuperTokensException
     * @throws SuperTokensUnauthorizedException
     */
    public static function getSession(Request $request, Response $response, $doAntiCsrfCheck)
    {
        CookieAndHeader::saveFrontendInfoFromRequest($request);
        $accessToken = CookieAndHeader::getAccessTokenFromCookie($request);
        if (!isset($accessToken)) {
            throw SuperTokensException::generateTryRefreshTokenException("access token missing in cookies");
        }
        try {
            $idRefreshToken = CookieAndHeader::getIdRefreshTokenFromCookie($request);
            $antiCsrfToken = CookieAndHeader::getAntiCsrfHeader($request);
            $newSession = SessionHandlingFunctions::getSession($accessToken, $antiCsrfToken, $doAntiCsrfCheck, $idRefreshToken);
            if (isset($newSession['accessToken'])) {
                $accessToken = $newSession['accessToken'];
                CookieAndHeader::attachAccessTokenToCookie($response, $accessToken['token'], $accessToken['expiry'], $accessToken['domain'], $accessToken['cookieSecure'], $accessToken['cookiePath']);
            }
            return new Session($newSession['session']['handle'], $newSession['session']['userId'], $newSession['session']['userDataInJWT'], $response);
        } catch (SuperTokensUnauthorizedException $e) {
            $handshakeInfo = HandshakeInfo::getInstance();
            CookieAndHeader::clearSessionFromCookie($response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath);
            throw $e;
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Session
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     * @throws SuperTokensUnauthorizedException
     * @throws SuperTokensTokenTheftException
 */
    public static function refreshSession(Request $request, Response $response)
    {
        CookieAndHeader::saveFrontendInfoFromRequest($request);
        $refreshToken = CookieAndHeader::getRefreshTokenFromCookie($request);
        if (!isset($refreshToken)) {
            $handshakeInfo = HandshakeInfo::getInstance();
            CookieAndHeader::clearSessionFromCookie($response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath);
            throw SuperTokensException::generateUnauthorisedException("Missing auth tokens in cookies. Have you set the correct refresh API path in your frontend and SuperTokens config?");
        }

        try {
            $newSession = SessionHandlingFunctions::refreshSession($refreshToken);

            $accessToken = $newSession['accessToken'];
            $refreshToken = $newSession['refreshToken'];
            $idRefreshToken = $newSession['idRefreshToken'];
            CookieAndHeader::attachAccessTokenToCookie($response, $accessToken['token'], $accessToken['expiry'], $accessToken['domain'], $accessToken['cookieSecure'], $accessToken['cookiePath']);
            CookieAndHeader::attachRefreshTokenToCookie($response, $refreshToken['token'], $refreshToken['expiry'], $refreshToken['domain'], $refreshToken['cookieSecure'], $refreshToken['cookiePath']);
            CookieAndHeader::attachIdRefreshTokenToCookieAndHeader($response, $idRefreshToken['token'], $idRefreshToken['expiry'], $idRefreshToken['domain'], $idRefreshToken['cookieSecure'], $idRefreshToken['cookiePath']);
            if (isset($newSession['antiCsrfToken'])) {
                CookieAndHeader::attachAntiCsrfHeader($response, $newSession['antiCsrfToken']);
            }
            return new Session($newSession['session']['handle'], $newSession['session']['userId'], $newSession['session']['userDataInJWT'], $response);
        } catch (SuperTokensUnauthorizedException $e) {
            $handshakeInfo = HandshakeInfo::getInstance();
            CookieAndHeader::clearSessionFromCookie($response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath);
            throw $e;
        } catch (SuperTokensTokenTheftException $e) {
            $handshakeInfo = HandshakeInfo::getInstance();
            CookieAndHeader::clearSessionFromCookie($response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath);
            throw $e;
        }
    }

    /**
     * @param $userId
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     * @return integer
     */
    public static function revokeAllSessionsForUser($userId)
    {
        return SessionHandlingFunctions::revokeAllSessionsForUser($userId);
    }

    /**
     * @param $userId
     * @return array
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    public static function getAllSessionHandlesForUser($userId)
    {
        return SessionHandlingFunctions::getAllSessionHandlesForUser($userId);
    }

    /**
     * @param $sessionHandle
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     * @return bool
     */
    public static function revokeSessionUsingSessionHandle($sessionHandle)
    {
        return SessionHandlingFunctions::revokeSessionUsingSessionHandle($sessionHandle);
    }

//    /**
//     * @param $sessionHandles
//     * @throws SuperTokensException
//     * @throws SuperTokensGeneralException
//     */
//      TODO: CDI 2.0 - return type should be list of sessions revoked
//    public static function revokeMultipleSessionsUsingSessionHandles($sessionHandles)
//    {
//        SessionHandlingFunctions::revokeMultipleSessionsUsingSessionHandles($sessionHandles);
//    }

    /**
     * @param $sessionHandle
     * @throws SuperTokensGeneralException
     * @throws SuperTokensUnauthorizedException
     * @return array | null
     */
    public static function getSessionData($sessionHandle)
    {
        return SessionHandlingFunctions::getSessionData($sessionHandle);
    }

    /**
     * @param $sessionHandle
     * @param array | null $newSessionData
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     * @throws SuperTokensUnauthorizedException
     */
    public static function updateSessionData($sessionHandle, $newSessionData)
    {
        SessionHandlingFunctions::updateSessionData($sessionHandle, $newSessionData);
    }

    /**
     * @param Response $response
     */
    public static function setRelevantHeadersForOptionAPI(Response $response)
    {
        CookieAndHeader::setOptionsAPIHeader($response);
    }
}
