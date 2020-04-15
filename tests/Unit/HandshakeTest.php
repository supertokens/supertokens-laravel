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
namespace SuperTokens\Session\Tests;

use Exception;
use Illuminate\Http\Response;
use SuperTokens\Session\Exceptions\SuperTokensException;
use SuperTokens\Session\Exceptions\SuperTokensGeneralException;
use SuperTokens\Session\Helpers\HandshakeInfo;
use SuperTokens\Session\SuperToken;

class HandshakeTest extends TestCase
{
    /**
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    protected function setUp(): void
    {
        parent::setUp();
        Utils::reset();
        Utils::cleanST();
        Utils::setupST();
    }

    /**
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    protected function tearDown(): void
    {
        Utils::reset();
        Utils::cleanST();
        parent::tearDown();
    }

    /**
     * @throws Exception
     */
    public function testCoreNotAvailable(): void
    {
        try {
            SuperToken::createNewSession(new Response(), "abc", [], []);
            $this->assertTrue(false);
        } catch (SuperTokensException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * @throws Exception
     */
    public function testSuccessfulHandshakeAndUpdateJwt(): void
    {
        Utils::startST();
        $info = HandshakeInfo::getInstance();
        $this->assertEquals("/", $info->accessTokenPath);
        $this->assertEquals("supertokens.io", $info->cookieDomain);
        $this->assertIsString($info->jwtSigningPublicKey);
        $this->assertFalse($info->cookieSecure);
        $this->assertEquals("/refresh", $info->refreshTokenPath);
        $this->assertTrue($info->enableAntiCsrf);
        $this->assertFalse($info->accessTokenBlacklistingEnabled);
        $this->assertIsNumeric($info->jwtSigningPublicKeyExpiryTime);
        $info->updateJwtSigningPublicKeyInfo("hello", 100);
        $updatedInfo = HandshakeInfo::getInstance();
        $this->assertEquals("hello", $updatedInfo->jwtSigningPublicKey);
        $this->assertEquals(100, $updatedInfo->jwtSigningPublicKeyExpiryTime);
    }
}
