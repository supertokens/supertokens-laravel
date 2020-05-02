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
     * @var integer;
     */
    public $sessionExpiredStatusCode;

    /**
     * @var bool;
     */
    public static $TEST_READ_FROM_CACHE = false;

    /**
     * HandshakeInfo constructor.
     * @param $info
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
        $this->sessionExpiredStatusCode = Constants::SESSION_EXPIRED_STATUS_CODE;
        if (Querier::getInstance()->getApiVersion() !== "1.0") {
            $this->sessionExpiredStatusCode = $info['sessionExpiredStatusCode'];
        }
    }

    /**
     * @throws SuperTokensGeneralException
     */
    public static function reset()
    {
        if (App::environment("testing")) {
            self::$instance = null;
            self::$TEST_READ_FROM_CACHE = false;
        } else {
            throw SuperTokensException::generateGeneralException("calling testing function in non testing env");
        }
    }

    /**
     * @return HandshakeInfo
     * @throws SuperTokensGeneralException
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $response = Utils::getFromCache(Constants::HANDSHAKE_INFO_CACHE_KEY.Querier::getInstance()->getApiVersion());
            if (is_null($response)) {
                $response = Querier::getInstance()->sendPostRequest(Constants::HANDSHAKE, []);
                Utils::storeInCache(Constants::HANDSHAKE_INFO_CACHE_KEY.Querier::getInstance()->getApiVersion(), json_encode($response), Constants::HANDSHAKE_INFO_CACHE_TTL_SECONDS);
                self::$TEST_READ_FROM_CACHE = false;
            } else {
                $response = json_decode($response, true);
                self::$TEST_READ_FROM_CACHE = true;
            }
            self::$instance = new HandshakeInfo($response);
        }
        return self::$instance;
    }

    /**
     * @param string $newKey
     * @param integer $newExpiry
     * @throws SuperTokensGeneralException
     */
    public function updateJwtSigningPublicKeyInfo($newKey, $newExpiry)
    {
        $this->jwtSigningPublicKey = $newKey;
        $this->jwtSigningPublicKeyExpiryTime = $newExpiry;
        self::updateInCache([
            "jwtSigningPublicKey" => $newKey,
            "jwtSigningPublicKeyExpiryTime" => $newExpiry
        ]);
    }

    /**
     * @return integer
     */
    public function getSessionExpiredStatusCode()
    {
        return $this->sessionExpiredStatusCode;
    }

    /**
     * @param array $keyValuePairs
     * @throws SuperTokensGeneralException
     */
    private static function updateInCache(array $keyValuePairs)
    {
        try {
            $response = Querier::getInstance()->sendPostRequest(Constants::HANDSHAKE, []);
            if (App::environment("testing")) {
                foreach ($keyValuePairs as $key => $value) {
                    $response[$key] = $value;
                }
            }
            Utils::storeInCache(Constants::HANDSHAKE_INFO_CACHE_KEY.Querier::getInstance()->getApiVersion(), json_encode($response), Constants::HANDSHAKE_INFO_CACHE_TTL_SECONDS);
        } catch (\Exception $ignored) {
        }
    }
}
