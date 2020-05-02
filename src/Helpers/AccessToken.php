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
use SuperTokens\Exceptions\SuperTokensException;
use SuperTokens\Exceptions\SuperTokensTryRefreshTokenException;

class AccessToken
{

    /**
     * @param string $token
     * @param string $jwtSigningPublicKey
     * @param $doAntiCsrfCheck
     * @return array
     * @throws SuperTokensTryRefreshTokenException
     */
    public static function getInfoFromAccessToken($token, $jwtSigningPublicKey, $doAntiCsrfCheck)
    {
        try {
            $payload = Jwt::verifyJWTAndGetPayload($token, $jwtSigningPublicKey);
            $sessionHandle = Utils::sanitizeStringInput($payload['sessionHandle']);
            $userId = Utils::sanitizeStringInput($payload['userId']);
            $refreshTokenHash1 = Utils::sanitizeStringInput($payload['refreshTokenHash1']);
            $expiryTime = Utils::sanitizeNumberInput($payload['expiryTime']);
            $timeCreated = Utils::sanitizeNumberInput($payload['timeCreated']);
            $parentRefreshTokenHash1 = null;
            $antiCsrfToken = null;
            if (array_key_exists("antiCsrfToken", $payload)) {
                $antiCsrfToken = Utils::sanitizeStringInput($payload['antiCsrfToken']);
            }
            if (array_key_exists("parentRefreshTokenHash1", $payload)) {
                $parentRefreshTokenHash1 = Utils::sanitizeStringInput($payload['parentRefreshTokenHash1']);
            }
            $userData = $payload['userData'];

            if (!isset($sessionHandle) ||
                !isset($userId) ||
                !isset($refreshTokenHash1) ||
                !isset($userData) ||
                !isset($expiryTime) ||
                !isset($timeCreated) ||
                (!isset($antiCsrfToken) && $doAntiCsrfCheck)) {
                throw new Exception("invalid access token payload");
            }
            if ($expiryTime < Utils::getCurrentTimestampMS()) {
                throw new Exception("expired access token");
            }

            return [
                'sessionHandle' => $sessionHandle,
                'userId' => $userId,
                'refreshTokenHash1' => $refreshTokenHash1,
                'expiryTime' => $expiryTime,
                'timeCreated' => $timeCreated,
                'parentRefreshTokenHash1' => $parentRefreshTokenHash1,
                'userData' => $userData,
                'antiCsrfToken' => $antiCsrfToken
            ];
        } catch (Exception $e) {
            throw SuperTokensException::generateTryRefreshTokenException($e);
        }
    }
}
