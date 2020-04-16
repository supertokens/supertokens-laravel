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

use Illuminate\Support\Facades\App;
use SuperTokens\Exceptions\SuperTokensException;
use SuperTokens\Exceptions\SuperTokensGeneralException;

// TODO: CDI 2.0: cookieSameSite
class HandshakeInfo
{
    /**
     * @var HandshakeInfo
     */
    private static $instance;

    /**
     * @var string;
     */
    public $jwtSigningPublicKey;

    /**
     * @var string;
     */
    public $cookieDomain;

    /**
     * @var boolean;
     */
    public $cookieSecure;

    /**
     * @var string;
     */
    public $accessTokenPath;

    /**
     * @var string;
     */
    public $refreshTokenPath;

    /**
     * @var boolean;
     */
    public $enableAntiCsrf;

    /**
     * @var boolean;
     */
    public $accessTokenBlacklistingEnabled;

    /**
     * @var integer;
     */
    public $jwtSigningPublicKeyExpiryTime;

    /**
     * @var string;
     */
    public $sameSite;

    /**
     * HandshakeInfo constructor.
     * @param $info
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    private function __construct($info)
    {
        $this->accessTokenBlacklistingEnabled = $info['accessTokenBlacklistingEnabled'];
        $this->accessTokenPath = $info['accessTokenPath'];
        $this->cookieDomain = $info['cookieDomain'];
        $this->cookieSecure = $info['cookieSecure'];
        $this->enableAntiCsrf = $info['enableAntiCsrf'];
        $this->jwtSigningPublicKey = $info['jwtSigningPublicKey'];
        $this->jwtSigningPublicKeyExpiryTime = $info['jwtSigningPublicKeyExpiryTime'];
        $this->refreshTokenPath = $info['refreshTokenPath'];
        $this->sameSite = Constants::SAME_SITE_COOKIE_DEFAULT_VALUE;
        if (Querier::getInstance()->getApiVersion() !== "1.0") {
            $this->sameSite = $info['cookieSameSite'];
        }
    }

    /**
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    public static function reset()
    {
        if (App::environment("testing")) {
            self::$instance = null;
        } else {
            throw SuperTokensException::generateGeneralException("calling testing function in non testing env");
        }
    }

    /**
     * @return HandshakeInfo
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $response = Querier::getInstance()->sendPostRequest(Constants::HANDSHAKE, []);
            self::$instance = new HandshakeInfo($response);
        }
        return self::$instance;
    }

    /**
     * @param string $newKey
     * @param integer $newExpiry
     */
    public function updateJwtSigningPublicKeyInfo($newKey, $newExpiry)
    {
        $this->jwtSigningPublicKey = $newKey;
        $this->jwtSigningPublicKeyExpiryTime = $newExpiry;
    }
}
