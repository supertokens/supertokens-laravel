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
use Throwable;

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
     * @throws SuperTokensGeneralException
     */
    public static function createNewSession(Response $response, string $userId, array $jwtPayload = [], array $sessionData = [])
    {
        $newSession = SessionHandlingFunctions::createNewSession($userId, $jwtPayload, $sessionData);

        $accessToken = $newSession['accessToken'];
        $refreshToken = $newSession['refreshToken'];
        $idRefreshToken = $newSession['idRefreshToken'];
        CookieAndHeader::attachAccessTokenToCookie($response, $accessToken['token'], $accessToken['expiry'], $accessToken['domain'], $accessToken['cookieSecure'], $accessToken['cookiePath'], $accessToken['sameSite']);
        CookieAndHeader::attachRefreshTokenToCookie($response, $refreshToken['token'], $refreshToken['expiry'], $refreshToken['domain'], $refreshToken['cookieSecure'], $refreshToken['cookiePath'], $refreshToken['sameSite']);
        CookieAndHeader::attachIdRefreshTokenToCookieAndHeader($response, $idRefreshToken['token'], $idRefreshToken['expiry'], $idRefreshToken['domain'], $idRefreshToken['cookieSecure'], $idRefreshToken['cookiePath'], $idRefreshToken['sameSite']);
        if (isset($newSession['antiCsrfToken'])) {
            CookieAndHeader::attachAntiCsrfHeader($response, $newSession['antiCsrfToken']);
        }

        return new Session($newSession['accessToken']['token'], $newSession['session']['handle'], $newSession['session']['userId'], $newSession['session']['userDataInJWT'], $response);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param boolean $doAntiCsrfCheck
     * @return Session
     * @throws SuperTokensGeneralException
     * @throws SuperTokensTryRefreshTokenException
     * @throws SuperTokensUnauthorisedException
     */
    public static function getSession(Request $request, $response, $doAntiCsrfCheck)
    {
        // $response is null if this is being called from the middleware
        CookieAndHeader::saveFrontendInfoFromRequest($request);
        $idRefreshToken = CookieAndHeader::getIdRefreshTokenFromCookie($request);
        if (!isset($idRefreshToken)) {
            if (isset($response)) {
                $handshakeInfo = HandshakeInfo::getInstance();
                CookieAndHeader::clearSessionFromCookie($response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath, $handshakeInfo->sameSite);
            }
            throw new SuperTokensUnauthorisedException('idRefreshToken missing');
        }
        $accessToken = CookieAndHeader::getAccessTokenFromCookie($request);
        if (!isset($accessToken)) {
            throw SuperTokensException::generateTryRefreshTokenException("access token missing in cookies");
        }

        try {
            $antiCsrfToken = CookieAndHeader::getAntiCsrfHeader($request);
            $newSession = SessionHandlingFunctions::getSession($accessToken, $antiCsrfToken, $doAntiCsrfCheck);

            if (isset($newSession['accessToken'])) {
                $accessToken = $newSession['accessToken']['token'];
            }

            $session = new Session($accessToken, $newSession['session']['handle'], $newSession['session']['userId'], $newSession['session']['userDataInJWT'], $response);

            if (isset($newSession['accessToken'])) {
                if (isset($response)) {
                    $accessTokenInfo = $newSession['accessToken'];
                    CookieAndHeader::attachAccessTokenToCookie($response, $accessTokenInfo['token'], $accessTokenInfo['expiry'], $accessTokenInfo['domain'], $accessTokenInfo['cookieSecure'], $accessTokenInfo['cookiePath'], $accessTokenInfo['sameSite']);
                } else {
                    $session->newAccessTokenInfo = $newSession['accessToken'];
                }
            }

            return $session;
        } catch (SuperTokensUnauthorisedException $e) {
            if (isset($response)) {
                $handshakeInfo = HandshakeInfo::getInstance();
                CookieAndHeader::clearSessionFromCookie($response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath, $handshakeInfo->sameSite);
            }
            throw $e;
        }
    }


    /**
     * @param Request $request
     * @param Response $response
     * @return Session
     * @throws SuperTokensGeneralException
     * @throws SuperTokensUnauthorisedException
     * @throws SuperTokensTokenTheftException
 */
    public static function refreshSession(Request $request, $response)
    {
        // $response is null if this is being called from the middleware
        CookieAndHeader::saveFrontendInfoFromRequest($request);
        $refreshToken = CookieAndHeader::getRefreshTokenFromCookie($request);
        if (!isset($refreshToken)) {
            if (isset($response)) {
                $handshakeInfo = HandshakeInfo::getInstance();
                CookieAndHeader::clearSessionFromCookie($response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath, $handshakeInfo->sameSite);
            }
            throw SuperTokensException::generateUnauthorisedException("Missing auth tokens in cookies. Have you set the correct refresh API path in your frontend and SuperTokens config?");
        }

        try {
            $antiCsrfToken = CookieAndHeader::getAntiCsrfHeader($request);
            $newSession = SessionHandlingFunctions::refreshSession($refreshToken, $antiCsrfToken);

            $accessToken = $newSession['accessToken'];
            $refreshToken = $newSession['refreshToken'];
            $idRefreshToken = $newSession['idRefreshToken'];

            $session = new Session($newSession['accessToken']['token'], $newSession['session']['handle'], $newSession['session']['userId'], $newSession['session']['userDataInJWT'], $response);

            if (isset($response)) {
                CookieAndHeader::attachAccessTokenToCookie($response, $accessToken['token'], $accessToken['expiry'], $accessToken['domain'], $accessToken['cookieSecure'], $accessToken['cookiePath'], $accessToken['sameSite']);
                CookieAndHeader::attachRefreshTokenToCookie($response, $refreshToken['token'], $refreshToken['expiry'], $refreshToken['domain'], $refreshToken['cookieSecure'], $refreshToken['cookiePath'], $refreshToken['sameSite']);
                CookieAndHeader::attachIdRefreshTokenToCookieAndHeader($response, $idRefreshToken['token'], $idRefreshToken['expiry'], $idRefreshToken['domain'], $idRefreshToken['cookieSecure'], $idRefreshToken['cookiePath'], $idRefreshToken['sameSite']);
                if (isset($newSession['antiCsrfToken'])) {
                    CookieAndHeader::attachAntiCsrfHeader($response, $newSession['antiCsrfToken']);
                }
            } else {
                $session->newAccessTokenInfo = $accessToken;
                $session->newRefreshTokenInfo = $refreshToken;
                $session->newIdRefreshTokenInfo = $idRefreshToken;
                if (isset($newSession['antiCsrfToken'])) {
                    $session->newAntiCsrfToken = $newSession['antiCsrfToken'];
                }
            }

            return $session;
        } catch (SuperTokensUnauthorisedException | SuperTokensTokenTheftException $e) {
            if (isset($response)) {
                $handshakeInfo = HandshakeInfo::getInstance();
                CookieAndHeader::clearSessionFromCookie($response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath, $handshakeInfo->sameSite);
            }
            throw $e;
        }
    }

    /**
     * @param $userId
     * @return int
     * @throws SuperTokensGeneralException
     * @return array | integer
     */
    public static function revokeAllSessionsForUser($userId)
    {
        return SessionHandlingFunctions::revokeAllSessionsForUser($userId);
    }

    /**
     * @param $userId
     * @return array
     * @throws SuperTokensGeneralException
     */
    public static function getAllSessionHandlesForUser($userId)
    {
        return SessionHandlingFunctions::getAllSessionHandlesForUser($userId);
    }


    /**
     * @param $sessionHandle
     * @return bool
     * @throws SuperTokensGeneralException
     * @return bool
     */
    public static function revokeSession($sessionHandle)
    {
        return SessionHandlingFunctions::revokeSession($sessionHandle);
    }

    /**
     * @param $sessionHandles
     * @return array
     * @throws SuperTokensGeneralException
     */
    public static function revokeMultipleSessions($sessionHandles)
    {
        return SessionHandlingFunctions::revokeMultipleSessions($sessionHandles);
    }

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
    public static function setRelevantHeadersForOptionsAPI(Response $response)
    {
        CookieAndHeader::setOptionsAPIHeader($response);
    }

    /**
     * @return array
     */
    public static function getCORSAllowedHeaders()
    {
        return CookieAndHeader::getCORSAllowedHeaders();
    }

    /**
     * @param string $sessionHandle
     * @throws SuperTokensGeneralException
     * @throws SuperTokensUnauthorisedException
     */
    public static function getJWTPayload($sessionHandle)
    {
        return SessionHandlingFunctions::getJWTPayload($sessionHandle);
    }

    /**
     * @param string $sessionHandle
     * @param array $newJWTPayload
     * @throws SuperTokensGeneralException
     * @throws SuperTokensUnauthorisedException
     */
    public static function updateJWTPayload($sessionHandle, $newJWTPayload)
    {
        SessionHandlingFunctions::updateJWTPayload($sessionHandle, $newJWTPayload);
    }

    /**
     * @param Request $request
     * @param Throwable $exception
     * @param array $callbacks
     * @return mixed
     * @throws Throwable
     */
    public static function handleError(Request $request, Throwable $exception, $callbacks = [])
    {
        if ($exception instanceof SuperTokensUnauthorisedException) {
            $response = new Response();
            $handshakeInfo = HandshakeInfo::getInstance();
            CookieAndHeader::clearSessionFromCookie($response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath, $handshakeInfo->sameSite);
            if (array_key_exists('onUnauthorised', $callbacks)) {
                return $callbacks['onUnauthorised']($exception, $request, $response);
            } else {
                return $response->setStatusCode($handshakeInfo->sessionExpiredStatusCode)->setContent("unauthorised");
            }
        } elseif ($exception instanceof SuperTokensTryRefreshTokenException) {
            $response = new Response();
            $handshakeInfo = HandshakeInfo::getInstance();
            if (array_key_exists('onTryRefreshToken', $callbacks)) {
                return $callbacks['onTryRefreshToken']($exception, $request, $response);
            } else {
                return $response->setStatusCode($handshakeInfo->sessionExpiredStatusCode)->setContent("try refresh token");
            }
        } elseif ($exception instanceof SuperTokensTokenTheftException) {
            $response = new Response();
            $handshakeInfo = HandshakeInfo::getInstance();
            CookieAndHeader::clearSessionFromCookie($response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath, $handshakeInfo->sameSite);
            if (array_key_exists('onTokenTheftDetected', $callbacks)) {
                return $callbacks['onTokenTheftDetected']($exception->getSessionHandle(), $exception->getUserId(), $request, $response);
            } else {
                SuperTokens::revokeSession($exception->getSessionHandle());
                return $response->setStatusCode($handshakeInfo->sessionExpiredStatusCode)->setContent("token theft detected");
            }
        } else {
            throw $exception;
        }
    }
}
