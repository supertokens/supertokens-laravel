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
use DateTime;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use SuperTokens\Exceptions\SuperTokensException;
use SuperTokens\Exceptions\SuperTokensGeneralException;
use SuperTokens\Exceptions\SuperTokensTokenTheftException;
use SuperTokens\Exceptions\SuperTokensTryRefreshTokenException;
use SuperTokens\Exceptions\SuperTokensUnauthorisedException;
use SuperTokens\Helpers\Constants;
use SuperTokens\Helpers\HandshakeInfo;
use SuperTokens\Helpers\Querier;
use SuperTokens\Helpers\Utils;
use SuperTokens\Db\RefreshTokenDb;
use SuperTokens\Helpers\AccessTokenSigningKey;
use SuperTokens\Helpers\RefreshTokenSigningKey;
use SuperTokens\Helpers\AccessToken;
use SuperTokens\Helpers\RefreshToken;

/**
 * Class SessionHandlingFunctions
 * @package SuperTokensHandlingFunctions
 */
class SessionHandlingFunctions
{

    /**
     * @var bool
     */
    public static $TEST_SERVICE_CALLED = false; // for testing purpose

    /**
     * @var string | null
     */
    public static $TEST_FUNCTION_VERSION = null; // for testing purpose

    /**
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    public static function reset()
    {
        if (App::environment("testing")) {
            self::$TEST_SERVICE_CALLED = false;
            self::$TEST_FUNCTION_VERSION = null;
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
     * @param array $jwtPayload
     * @param array $sessionData
     * @return array
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    public static function createNewSession(string $userId, array $jwtPayload, array $sessionData)
    {
        // TODO: Do not accept null in jwtPayload and sessionData? Also, add typings to functions?
        if (count($jwtPayload) === 0) {
            $jwtPayload = new ArrayObject();
        }
        if (count($sessionData) === 0) {
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
        // TODO: set sameSite to none in case of cdi 1.0 here. This is tha layer that is querying the API, so it should be the one to make the response "uniform"
        if (Querier::getInstance()->getApiVersion() === "1.0") {
            $response['accessToken']['sameSite'] = Constants::SAME_SITE_COOKIE_DEFAULT_VALUE;
            $response['refreshToken']['sameSite'] = Constants::SAME_SITE_COOKIE_DEFAULT_VALUE;
            $response['idRefreshToken']['sameSite'] = Constants::SAME_SITE_COOKIE_DEFAULT_VALUE;
            $response['idRefreshToken']['domain'] = $response['accessToken']['domain'];
            $response['idRefreshToken']['cookieSecure'] = $response['accessToken']['cookieSecure'];
            $response['idRefreshToken']['cookiePath'] = $response['accessToken']['cookiePath'];
        }
        return $response;
    }

    /**
     * @param string $accessToken
     * @param string | null $antiCsrfToken
     * @param boolean $doAntiCsrfCheck
     * @param string | null $idRefreshToken
     * @return array
     * @throws SuperTokensGeneralException
     * @throws SuperTokensUnauthorisedException
     * @throws SuperTokensTryRefreshTokenException
     * @throws SuperTokensException
     */
    public static function getSession($accessToken, $antiCsrfToken, $doAntiCsrfCheck, $idRefreshToken)
    {
        if (!isset($idRefreshToken)) {
            throw new SuperTokensUnauthorisedException('idRefreshToken missing');
        }
        $handshakeInfo = HandshakeInfo::getInstance();

        try {
            if ($handshakeInfo->jwtSigningPublicKeyExpiryTime > Utils::getCurrentTimestampMS()) {
                $accessTokenInfo = AccessToken::getInfoFromAccessToken($accessToken, $handshakeInfo->jwtSigningPublicKey, $handshakeInfo->enableAntiCsrf && $doAntiCsrfCheck);

                if (
                    $handshakeInfo->enableAntiCsrf &&
                    $doAntiCsrfCheck &&
                    (!isset($antiCsrfToken) || $antiCsrfToken !== $accessTokenInfo['antiCsrfToken'])
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
                    self::$TEST_SERVICE_CALLED = false; // for testing purpose
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

        self::$TEST_SERVICE_CALLED = true; // for testing purpose

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
            // TODO: if cdi 1.0, add none
            if (Querier::getInstance()->getApiVersion() === "1.0") {
                $response['accessToken']['sameSite'] = Constants::SAME_SITE_COOKIE_DEFAULT_VALUE;
            }
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
     * @throws SuperTokensUnauthorisedException
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
            if (Querier::getInstance()->getApiVersion() === "1.0") {
                $response['accessToken']['sameSite'] = Constants::SAME_SITE_COOKIE_DEFAULT_VALUE;
                $response['refreshToken']['sameSite'] = Constants::SAME_SITE_COOKIE_DEFAULT_VALUE;
                $response['idRefreshToken']['sameSite'] = Constants::SAME_SITE_COOKIE_DEFAULT_VALUE;
                $response['idRefreshToken']['domain'] = $response['accessToken']['domain'];
                $response['idRefreshToken']['cookieSecure'] = $response['accessToken']['cookieSecure'];
                $response['idRefreshToken']['cookiePath'] = $response['accessToken']['cookiePath'];
            }
            return $response;
        } elseif ($response['status'] === "UNAUTHORISED") {
            throw SuperTokensException::generateUnauthorisedException($response['message']);
        } else {
            throw SuperTokensException::generateTokenTheftException($response['session']['userId'], $response['session']['handle']);
        }
    }

    /**
     * @param string $userId
     * @return array | integer
     * @throws SuperTokensGeneralException
     * @throws SuperTokensException
     */
    public static function revokeAllSessionsForUser($userId)
    {
        if (Querier::getInstance()->getApiVersion() === "1.0") {
            $response = Querier::getInstance()->sendDeleteRequest(Constants::SESSION, [
                'userId' => $userId
            ]);
            self::$TEST_FUNCTION_VERSION = "1.0";
            return $response['numberOfSessionsRevoked'];
        } else {
            $response = Querier::getInstance()->sendPostRequest(Constants::SESSION_REMOVE, [
                'userId' => $userId
            ]);
            self::$TEST_FUNCTION_VERSION = "2.0";
            return $response['sessionHandlesRevoked'];
        }
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
    // TODO: change name to revokeSession - in docs and in SuperTokens.php
    public static function revokeSessionUsingSessionHandle($sessionHandle)
    {
        if (Querier::getInstance()->getApiVersion() === "1.0") {
            $response = Querier::getInstance()->sendDeleteRequest(Constants::SESSION, [
                'sessionHandles' => [$sessionHandle]
            ]);
            self::$TEST_FUNCTION_VERSION = "1.0";
            return $response['numberOfSessionsRevoked'] === 1;
        } else {
            $response = Querier::getInstance()->sendPostRequest(Constants::SESSION_REMOVE, [
                'sessionHandles' => [$sessionHandle]
            ]);
            self::$TEST_FUNCTION_VERSION = "2.0";
            return count($response['sessionHandlesRevoked']) === 1; // TODO: Is this fine? If it is, remove the comment below.
            // return (array_key_exists('sessionHandlesRevoked', $response) && in_array($sessionHandle, $response['sessionHandlesRevoked']));
        }
    }

    /**
     * @param array $sessionHandles
     * @return array
     * @throws SuperTokensGeneralException
     * @throws SuperTokensException
     */
    public static function revokeMultipleSessionsUsingSessionHandles($sessionHandles)
    {
        $response = Querier::getInstance()->sendPostRequest(Constants::SESSION_REMOVE, [
            'sessionHandles' => $sessionHandles
        ]);
        return $response['sessionHandlesRevoked'];
    }

    /**
     * @param string $sessionHandle
     * @return array
     * @throws Exception
     * @throws SuperTokensUnauthorisedException | SuperTokensGeneralException
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
     * @param array $newSessionData
     * @throws SuperTokensException
     * @throws SuperTokensUnauthorisedException | SuperTokensGeneralException
     */
    // TODO: add types to function params
    public static function updateSessionData($sessionHandle, $newSessionData)
    {
        if (!isset($newSessionData) || is_null($newSessionData)) {
            throw SuperTokensException::generateGeneralException("session data passed to the function can't be null. Please pass empty array instead.");
        }
        if (count($newSessionData) === 0) {
            $newSessionData = new ArrayObject();
        }
        $response = Querier::getInstance()->sendPutRequest(Constants::SESSION_DATA, [
            'sessionHandle' => $sessionHandle,
            'userDataInDatabase' => $newSessionData
        ]);
        if ($response['status'] === Constants::EXCEPTION_UNAUTHORISED) {
            throw new SuperTokensUnauthorisedException($response['message']);
        }
    }

    /**
     * @param string $sessionHandle
     * @param array $newJWTPayload
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     * @throws SuperTokensUnauthorisedException
     */
    // TODO: add types to function params
    // TODO: change name to updateJWTPayload - even in docs, and in SuperTokens.php
    public static function updateJWTPayload($sessionHandle, $newJWTPayload)
    {
        if (!isset($newJWTPayload) || is_null($newJWTPayload)) {
            throw SuperTokensException::generateGeneralException("jwt data passed to the function can't be null. Please pass empty array instead.");
        }
        if (count($newJWTPayload) === 0) {
            $newJWTPayload = new ArrayObject();
        }
        if (Querier::getInstance()->getApiVersion() === "1.0") {
            throw SuperTokensException::generateGeneralException("the current function is not supported for the core. Please upgrade the supertokens service.");
        }
        $response = Querier::getInstance()->sendPutRequest(Constants::JWT_DATA, [
            'sessionHandle' => $sessionHandle,
            'userDataInJWT' => $newJWTPayload
        ]);
        if ($response['status'] === Constants::EXCEPTION_UNAUTHORISED) {
            throw new SuperTokensUnauthorisedException($response['message']);
        }
    }

    /**
     * @param string $sessionHandle
     * @return array
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     * @throws SuperTokensUnauthorisedException
     */
    // TODO: change name to getJWTPayload - even in docs, and in SuperTokens.php
    public static function getJWTPayload($sessionHandle)
    {
        if (Querier::getInstance()->getApiVersion() === "1.0") {
            throw SuperTokensException::generateGeneralException("the current function is not supported for the core. Please upgrade the supertokens service.");
        }
        $response = Querier::getInstance()->sendGetRequest(Constants::JWT_DATA, [
            'sessionHandle' => $sessionHandle
        ]);
        if ($response['status'] === "OK") {
            return $response['userDataInJWT'];
        }
        throw SuperTokensException::generateUnauthorisedException($response['message']);
    }
}
