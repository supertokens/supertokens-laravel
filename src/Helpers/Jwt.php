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
namespace SuperTokens\Session\Helpers;

use Exception;

class Jwt
{

    /**
     * @return string
     */
    private static function getHeader()
    {
        return base64_encode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
            'version' => '1'
        ]));
    }

    /**
     * @param $jwt
     * @param $signingPublicKey
     * @return mixed
     * @throws Exception
     */
    public static function verifyJWTAndGetPayload($jwt, $signingPublicKey)
    {
        $splittedInput = explode(".", $jwt);
        $header = Jwt::getHeader();

        if (count($splittedInput) !== 3) {
            throw new Exception("invalid jwt");
        }

        if ($splittedInput[0] !== $header) {
            throw new Exception("jwt header mismatch");
        }

        $payload = $splittedInput[1];
        $publicKey = openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n".wordwrap($signingPublicKey, 64, "\n", true)."\n-----END PUBLIC KEY-----");
        $signature = $splittedInput[2];
        $verificationOk = openssl_verify($header.".".$payload, base64_decode($signature), $publicKey, "sha256");

        if ($verificationOk !== 1) {
            throw new Exception("jwt verification failed");
        }

        $payload = base64_decode($payload);
        return json_decode($payload, true);
    }
}
