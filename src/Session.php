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

use ArrayObject;
use Illuminate\Http\Response;
use SuperTokens\Exceptions\SuperTokensException;
use SuperTokens\Exceptions\SuperTokensGeneralException;
use SuperTokens\Exceptions\SuperTokensUnauthorisedException;
use SuperTokens\Helpers\Constants;
use SuperTokens\Helpers\CookieAndHeader;
use SuperTokens\Helpers\HandshakeInfo;
use SuperTokens\Helpers\Querier;

class Session
{
    /**
     * @var
     */
    private $sessionHandle;

    /**
     * @var
     */
    private $accessToken;

    /**
     * @var string
     */
    private $userId;

    /**
     * @var array
     */
    private $userDataInJWT;

    /**
     * @var Response
     */
    private $response;

    /**
     * SuperTokens constructor.
     * @param $accessToken
     * @param $sessionHandle
     * @param $userId
     * @param array $userDataInJWT
     * @param $response
     */
    public function __construct($accessToken, $sessionHandle, $userId, $userDataInJWT, $response = null)
    {
        $this->sessionHandle = $sessionHandle;
        $this->userId = $userId;
        $this->userDataInJWT = $userDataInJWT;
        $this->response = $response;
        $this->accessToken = $accessToken;
    }

    /**
     * @param Response $response
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    public function revokeSession(Response $response)
    {
        if (SessionHandlingFunctions::revokeSessionUsingSessionHandle($this->sessionHandle)) {
            if (!isset($response)) {
                throw SuperTokensGeneralException::generateGeneralException("function requires a response object");
            }
            $this->response = $response;
            $handshakeInfo = HandshakeInfo::getInstance();
            CookieAndHeader::clearSessionFromCookie($this->response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath, $handshakeInfo->sameSite);
        }
    }

    /**
     * @return array
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     * @throws SuperTokensUnauthorisedException
     */
    public function getSessionData()
    {
        try {
            return SessionHandlingFunctions::getSessionData($this->sessionHandle);
        } catch (SuperTokensUnauthorisedException $e) {
            if (isset($this->response)) {
                $handshakeInfo = HandshakeInfo::getInstance();
                CookieAndHeader::clearSessionFromCookie($this->response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath, $handshakeInfo->sameSite);
            }
            throw $e;
        }
    }

    /**
     * @param array $newSessionData
     * @throws SuperTokensGeneralException
     * @throws SuperTokensUnauthorisedException
     * @throws SuperTokensException
     */
    public function updateSessionData($newSessionData)
    {
        if (!isset($newSessionData) || is_null($newSessionData)) {
            throw SuperTokensGeneralException::generateGeneralException("session data passed to the function can't be null. Please pass empty array instead.");
        }
        try {
            if (count($newSessionData) === 0) {
                $newSessionData = new ArrayObject();
            }
            SessionHandlingFunctions::updateSessionData($this->sessionHandle, $newSessionData);
        } catch (SuperTokensUnauthorisedException $e) {
            if (isset($this->response)) {
                $handshakeInfo = HandshakeInfo::getInstance();
                CookieAndHeader::clearSessionFromCookie($this->response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath, $handshakeInfo->sameSite);
            }
            throw $e;
        }
    }

    /**
     * @return string
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @return array
     */
    public function getJWTPayload()
    {
        return $this->userDataInJWT;
    }

    public function getHandle()
    {
        return $this->sessionHandle;
    }

    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * @param array $newJWTPayload
     * @param Response $response
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     * @throws SuperTokensUnauthorisedException
     */
    public function updateJWTPayload($newJWTPayload, Response $response)
    {
        if (Querier::getInstance()->getApiVersion() === "1.0") {
            throw SuperTokensException::generateGeneralException("the current function is not supported for the core");
        }
        if (!isset($response)) {
            throw SuperTokensGeneralException::generateGeneralException("function requires a response object");
        }
        if (!isset($newJWTPayload) || is_null($newJWTPayload)) {
            throw SuperTokensGeneralException::generateGeneralException("jwt data passed to the function can't be null. Please pass empty array instead.");
        }
        $this->response = $response;
        if (count($newJWTPayload) === 0) {
            $newJWTPayload = new ArrayObject();
        }
        $queryResponse = Querier::getInstance()->sendPostRequest(Constants::SESSION_REGENERATE, [
            'accessToken' => $this->accessToken,
            'userDataInJWT' => $newJWTPayload
        ]);
        if ($queryResponse['status'] === Constants::EXCEPTION_UNAUTHORISED) {
            $handshakeInfo = HandshakeInfo::getInstance();
            CookieAndHeader::clearSessionFromCookie($this->response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath, $handshakeInfo->sameSite);
            throw new SuperTokensUnauthorisedException($queryResponse['message']);
        }
        $this->userDataInJWT = $queryResponse['session']['userDataInJWT'];
        if (isset($queryResponse['accessToken'])) {
            $accessToken = $queryResponse['accessToken'];
            $this->accessToken = $accessToken['token'];
            $accessTokenSameSite = Constants::SAME_SITE_COOKIE_DEFAULT_VALUE;
            if (Querier::getInstance()->getApiVersion() !== "1.0") {
                $accessTokenSameSite = $accessToken['sameSite'];
            }
            CookieAndHeader::attachAccessTokenToCookie($this->response, $accessToken['token'], $accessToken['expiry'], $accessToken['domain'], $accessToken['cookieSecure'], $accessToken['cookiePath'], $accessTokenSameSite);
        }
    }
}
