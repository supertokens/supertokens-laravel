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
use SuperTokens\Helpers\CookieAndHeader;
use SuperTokens\Helpers\HandshakeInfo;

class SessionMiddleware
{
    /**
     * @var
     */
    private $sessionHandle;

    /**
     * @var string
     */
    private $userId;

    /**
     * @var array
     */
    private $userDataInJWT;

    /**
     * SuperTokens constructor.
     * @param $sessionHandle
     * @param $userId
     * @param array $userDataInJWT
     */
    public function __construct($sessionHandle, $userId, $userDataInJWT)
    {
        $this->sessionHandle = $sessionHandle;
        $this->userId = $userId;
        $this->userDataInJWT = $userDataInJWT;
    }

    /**
     * @param Response $response
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    public function revokeSession($response)
    {
        if (SessionHandlingFunctions::revokeSessionUsingSessionHandle($this->sessionHandle)) {
            $handshakeInfo = HandshakeInfo::getInstance();
            CookieAndHeader::clearSessionFromCookie($response, $handshakeInfo->cookieDomain, $handshakeInfo->cookieSecure, $handshakeInfo->accessTokenPath, $handshakeInfo->refreshTokenPath, $handshakeInfo->sameSite);
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
        return SessionHandlingFunctions::getSessionData($this->sessionHandle);
    }

    /**
     * @param array $newSessionData
     * @throws SuperTokensGeneralException
     * @throws SuperTokensUnauthorisedException
     * @throws SuperTokensException
     */
    public function updateSessionData($newSessionData)
    {
        if (!isset($newSessionData) || count($newSessionData) === 0) {
            $newSessionData = new ArrayObject();
        }
        SessionHandlingFunctions::updateSessionData($this->sessionHandle, $newSessionData);
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
}
