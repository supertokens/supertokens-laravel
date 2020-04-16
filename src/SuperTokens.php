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
namespace SuperTokens;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SuperTokens\Exceptions\SuperTokensException;
use SuperTokens\Exceptions\SuperTokensTokenTheftException;
use SuperTokens\Helpers\Constants;
use SuperTokens\Helpers\CookieAndHeader;
use SuperTokens\Exceptions\SuperTokensGeneralException;
use SuperTokens\Exceptions\SuperTokensUnauthorisedException;
use SuperTokens\Exceptions\SuperTokensTryRefreshTokenException;
use SuperTokens\Helpers\HandshakeInfo;
use SuperTokens\Helpers\Querier;

class SuperTokens
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
     * @param array $jwtPayload
     * @param array $sessionData
     * @return Session
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    public static function createNewSession(Response $response, $userId, $jwtPayload = null, $sessionData = null)
    {
        $newSession = SessionHandlingFunctions::createNewSession($userId, $jwtPayload, $sessionData);
        CookieAndHeader::attachSessionToResponse($response, $newSession);
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
     * @throws SuperTokensUnauthorisedException
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
                $accessTokenSameSite = Constants::SAME_SITE_COOKIE_DEFAULT_VALUE;
                if (Querier::getInstance()->getApiVersion() !== "1.0") {
                    $accessTokenSameSite = $accessToken['sameSite'];
                }
                CookieAndHeader::attachAccessTokenToCookie($response, $accessToken['token'], $accessToken['expiry'], $accessToken['domain'], $accessToken['cookieSecure'], $accessToken['cookiePath'], $accessTokenSameSite);
            }
            return new Session($newSession['session']['handle'], $newSession['session']['userId'], $newSession['session']['userDataInJWT'], $response);
        } catch (SuperTokensUnauthorisedException $e) {
            $handshakeInfo = HandshakeInfo::getInstance();
            CookieAndHeader::clearSessionFromCookie($response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath, $handshakeInfo->sameSite);
            throw $e;
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Session
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     * @throws SuperTokensUnauthorisedException
     * @throws SuperTokensTokenTheftException
 */
    public static function refreshSession(Request $request, Response $response)
    {
        CookieAndHeader::saveFrontendInfoFromRequest($request);
        $refreshToken = CookieAndHeader::getRefreshTokenFromCookie($request);
        if (!isset($refreshToken)) {
            $handshakeInfo = HandshakeInfo::getInstance();
            CookieAndHeader::clearSessionFromCookie($response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath, $handshakeInfo->sameSite);
            throw SuperTokensException::generateUnauthorisedException("Missing auth tokens in cookies. Have you set the correct refresh API path in your frontend and SuperTokens config?");
        }

        try {
            $newSession = SessionHandlingFunctions::refreshSession($refreshToken);
            CookieAndHeader::attachSessionToResponse($response, $newSession);
            return new Session($newSession['session']['handle'], $newSession['session']['userId'], $newSession['session']['userDataInJWT'], $response);
        } catch (SuperTokensUnauthorisedException $e) {
            $handshakeInfo = HandshakeInfo::getInstance();
            CookieAndHeader::clearSessionFromCookie($response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath, $handshakeInfo->sameSite);
            throw $e;
        } catch (SuperTokensTokenTheftException $e) {
            $handshakeInfo = HandshakeInfo::getInstance();
            CookieAndHeader::clearSessionFromCookie($response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath, $handshakeInfo->sameSite);
            throw $e;
        }
    }

    /**
     * @param $userId
     * @return int
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
     * @return bool
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
     * @return mixed
     * @return array
     *@throws SuperTokensUnauthorisedException
     * @throws SuperTokensGeneralException
     */
    public static function getSessionData($sessionHandle)
    {
        return SessionHandlingFunctions::getSessionData($sessionHandle);
    }

    /**
     * @param $sessionHandle
     * @param array $newSessionData
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     * @throws SuperTokensUnauthorisedException
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
