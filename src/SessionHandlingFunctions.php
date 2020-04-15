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
use DateTime;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use SuperTokens\Session\Exceptions\SuperTokensException;
use SuperTokens\Session\Exceptions\SuperTokensGeneralException;
use SuperTokens\Session\Exceptions\SuperTokensTokenTheftException;
use SuperTokens\Session\Exceptions\SuperTokensTryRefreshTokenException;
use SuperTokens\Session\Exceptions\SuperTokensUnauthorizedException;
use SuperTokens\Session\Helpers\Constants;
use SuperTokens\Session\Helpers\HandshakeInfo;
use SuperTokens\Session\Helpers\Querier;
use SuperTokens\Session\Helpers\Utils;
use SuperTokens\Session\Db\RefreshTokenDb;
use SuperTokens\Session\Helpers\AccessTokenSigningKey;
use SuperTokens\Session\Helpers\RefreshTokenSigningKey;
use SuperTokens\Session\Helpers\AccessToken;
use SuperTokens\Session\Helpers\RefreshToken;

/**
 * Class SessionHandlingFunctions
 * @package SuperTokens\SessionHandlingFunctions
 */
class SessionHandlingFunctions
{

    /**
     * @var bool
     */
    public static $SERVICE_CALLED = false; // for testing purpose

    /**
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    public static function reset()
    {
        if (App::environment("testing")) {
            self::$SERVICE_CALLED = false;
        } else {
            throw SuperTokensException::generateGeneralException("calling testing function in non testing env");
        }
    }

    /**
     * SessionHandlingFunctions constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param string $userId
     * @param array | null $jwtPayload
     * @param array | null $sessionData
     * @return array
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    public static function createNewSession($userId, $jwtPayload, $sessionData)
    {
        if (!isset($jwtPayload) || count($jwtPayload) === 0) {
            $jwtPayload = new ArrayObject();
        }
        if (!isset($sessionData) || count($sessionData) === 0) {
            $sessionData = new ArrayObject();
        }
        $response = Querier::getInstance()->sendPostRequest(Constants::SESSION, [
            'userId' => $userId,
            'userDataInJWT' => $jwtPayload,
            'userDataInDatabase' => $sessionData
        ]);

        $instance = HandshakeInfo::getInstance();
        $instance->updateJwtSigningPublicKeyInfo($response['jwtSigningPublicKey'], $response['jwtSigningPublicKeyExpiryTime']);
        unset($response['status']);
        unset($response['jwtSigningPublicKey']);
        unset($response['jwtSigningPublicKeyExpiryTime']);

        return $response;
    }

    /**
     * @param string $accessToken
     * @param string | null $antiCsrfToken
     * @param boolean $doAntiCsrfCheck
     * @param string | null $idRefreshToken
     * @return array
     * @throws SuperTokensGeneralException
     * @throws SuperTokensUnauthorizedException
     * @throws SuperTokensTryRefreshTokenException
     * @throws SuperTokensException
     */
    public static function getSession($accessToken, $antiCsrfToken, $doAntiCsrfCheck, $idRefreshToken)
    {
        if (!isset($idRefreshToken)) {
            throw new SuperTokensUnauthorizedException('idRefreshToken missing');
        }
        $handshakeInfo = HandshakeInfo::getInstance();

        try {
            if ($handshakeInfo->jwtSigningPublicKeyExpiryTime > Utils::getCurrentTimestamp()) {
                $accessTokenInfo = AccessToken::getInfoFromAccessToken($accessToken, $handshakeInfo->jwtSigningPublicKey, $handshakeInfo->enableAntiCsrf && $doAntiCsrfCheck);

                if (
                    $handshakeInfo->enableAntiCsrf &&
                    $doAntiCsrfCheck &&
                    (!isset($antiCsrfToken) || $antiCsrfToken !==  $accessTokenInfo['antiCsrfToken'])
                ) {
                    if (!isset($antiCsrfToken)) {
                        throw SuperTokensException::generateTryRefreshTokenException("provided antiCsrfToken is undefined. If you do not want anti-csrf check for this API, please set doAntiCsrfCheck to false");
                    }
                    throw SuperTokensException::generateTryRefreshTokenException("anti-csrf check failed");
                }
                if (
                    !$handshakeInfo->accessTokenBlacklistingEnabled &&
                    !isset($accessTokenInfo['parentRefreshTokenHash1'])
                ) {
                    self::$SERVICE_CALLED = false; // for testing purpose
                    return [
                        'session' => [
                            'handle' => $accessTokenInfo['sessionHandle'],
                            'userId' => $accessTokenInfo['userId'],
                            'userDataInJWT' => $accessTokenInfo['userData']
                        ]
                    ];
                }
            }
        } catch (SuperTokensTryRefreshTokenException $e) {
            // we continue to call the service
        } catch (Exception $e) {
            throw $e;
        }

        self::$SERVICE_CALLED = true; // for testing purpose

        $requestBody = [
            'accessToken' => $accessToken,
            'doAntiCsrfCheck' => $doAntiCsrfCheck
        ];
        if (isset($antiCsrfToken)) {
            $requestBody['antiCsrfToken'] = $antiCsrfToken;
        }
        $response = Querier::getInstance()->sendPostRequest(Constants::SESSION_VERIFY, $requestBody);

        if ($response['status'] === "OK") {
            $instance = HandshakeInfo::getInstance();
            $instance->updateJwtSigningPublicKeyInfo($response['jwtSigningPublicKey'], $response['jwtSigningPublicKeyExpiryTime']);
            unset($response['status']);
            unset($response['jwtSigningPublicKey']);
            unset($response['jwtSigningPublicKeyExpiryTime']);

            return $response;
        } elseif ($response['status'] === "UNAUTHORISED") {
            throw SuperTokensException::generateUnauthorisedException($response['message']);
        } else {
            throw SuperTokensException::generateTryRefreshTokenException($response['message']);
        }
    }

    /**
     * @param $refreshToken
     * @return array
     * @throws SuperTokensGeneralException
     * @throws SuperTokensUnauthorizedException
     * @throws SuperTokensTokenTheftException
     * @throws SuperTokensException
      */
    public static function refreshSession($refreshToken)
    {
        $response = Querier::getInstance()->sendPostRequest(Constants::SESSION_REFRESH, [
            'refreshToken' => $refreshToken
        ]);
        if ($response['status'] === "OK") {
            unset($response['status']);
            return $response;
        } elseif ($response['status'] === "UNAUTHORISED") {
            throw SuperTokensException::generateUnauthorisedException($response['message']);
        } else {
            throw SuperTokensException::generateTokenTheftException($response['session']['userId'], $response['session']['handle']);
        }
    }

    /**
     * @param string $userId
     * @return integer
     * @throws SuperTokensGeneralException
     * @throws SuperTokensException
     */
    public static function revokeAllSessionsForUser($userId)
    {
        $response = Querier::getInstance()->sendPostRequest(Constants::SESSION_REMOVE, [
            'userId' => $userId
        ]);
        return $response['numberOfSessionsRevoked'];
    }

    /**
     * @param string $userId
     * @return array
     * @throws SuperTokensGeneralException
     * @throws SuperTokensException
     */
    public static function getAllSessionHandlesForUser($userId)
    {
        $response = Querier::getInstance()->sendGetRequest(Constants::SESSION_USER, [
            'userId' => $userId
        ]);
        return $response['sessionHandles'];
    }

    /**
     * @param $sessionHandle
     * @return bool
     * @throws SuperTokensGeneralException
     * @throws SuperTokensException
     */
    public static function revokeSessionUsingSessionHandle($sessionHandle)
    {
        $response = Querier::getInstance()->sendPostRequest(Constants::SESSION_REMOVE, [
            'sessionHandles' => [$sessionHandle]
        ]);
        return $response['numberOfSessionsRevoked'] === 1;
    }

//    /**
//     * @param array $sessionHandles
//     * @return bool
//     * @throws SuperTokensGeneralException
//     * @throws SuperTokensException
//     */
//    public static function revokeMultipleSessionsUsingSessionHandles($sessionHandles)
//    {
//        $response = Querier::getInstance()->sendPostRequest(Constants::SESSION_REMOVE, [
//            'sessionHandles' => $sessionHandles
//        ]);
//        // TODO: return type of this should be list of session handles revoked - change in CDI 2.0
//        return $response['numberOfSessionsRevoked'] === count($sessionHandles);
//    }

    /**
     * @param string $sessionHandle
     * @return array | null
     * @throws Exception
     * @throws SuperTokensUnauthorizedException | SuperTokensGeneralException
     */
    public static function getSessionData($sessionHandle)
    {
        $response = Querier::getInstance()->sendGetRequest(Constants::SESSION_DATA, [
            'sessionHandle' => $sessionHandle
        ]);
        if ($response['status'] === "OK") {
            return $response['userDataInDatabase'];
        }
        throw SuperTokensException::generateUnauthorisedException($response['message']);
    }

    /**
     * @param string $sessionHandle
     * @param array | null $newSessionData
     * @throws SuperTokensException
     * @throws SuperTokensUnauthorizedException | SuperTokensGeneralException
     */
    public static function updateSessionData($sessionHandle, $newSessionData)
    {
        if (!isset($newSessionData) || count($newSessionData) === 0) {
            $newSessionData = new ArrayObject();
        }
        $response = Querier::getInstance()->sendPutRequest(Constants::SESSION_DATA, [
            'sessionHandle' => $sessionHandle,
            'userDataInDatabase' => $newSessionData
        ]);
        if ($response['status'] === Constants::EXCEPTION_UNAUTHORISED) {
            throw new SuperTokensUnauthorizedException($response['message']);
        }
    }
}
