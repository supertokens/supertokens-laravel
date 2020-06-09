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
namespace SuperTokens\Tests;

use Exception;
use Illuminate\Http\Response;
use SuperTokens\Exceptions\SuperTokensException;
use SuperTokens\Exceptions\SuperTokensGeneralException;
use SuperTokens\Helpers\Constants;
use SuperTokens\Helpers\HandshakeInfo;
use SuperTokens\SuperTokens;
use SuperTokens\Helpers\Querier;
use SuperTokens\Helpers\Utils as SuperTokensUtils;

class HandshakeTest extends TestCase
{
    /**
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
            SuperTokens::createNewSession(new Response(), "abc", [], []);
            $this->assertTrue(false);
        } catch (SuperTokensGeneralException $e) {
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
        $this->assertContains($info->cookieDomain, ["supertokens.io", "localhost"]);
        $this->assertIsString($info->jwtSigningPublicKey);
        $this->assertFalse($info->cookieSecure);
        $this->assertEquals("/refresh", $info->refreshTokenPath);
        $this->assertTrue($info->enableAntiCsrf);
        $this->assertFalse($info->accessTokenBlacklistingEnabled);
        $this->assertIsNumeric($info->jwtSigningPublicKeyExpiryTime);
        $this->assertFalse(HandshakeInfo::$TEST_READ_FROM_CACHE);
        $info->updateJwtSigningPublicKeyInfo("hello", 100);
        HandshakeInfo::reset();
        $updatedInfo = HandshakeInfo::getInstance();
        $this->assertTrue(HandshakeInfo::$TEST_READ_FROM_CACHE);
        $this->assertEquals("hello", $updatedInfo->jwtSigningPublicKey);
        $this->assertEquals(100, $updatedInfo->jwtSigningPublicKeyExpiryTime);
    }

    /**
     * @throws SuperTokensGeneralException
     */
    public function testCustomConfig(): void
    {
        Utils::startST();
        $statusCodeSupported = Querier::getInstance()->getApiVersion() !== "1.0";
        Utils::reset();
        Utils::cleanST();
        Utils::setupST();
        $expectedStatusCode = Constants::SESSION_EXPIRED_STATUS_CODE;
        if ($statusCodeSupported) {
            Utils::setKeyValueInConfig(Utils::TEST_SESSION_EXPIRED_STATUS_CODE_CONFIG_KEY, Utils::TEST_SESSION_EXPIRED_STATUS_CODE_VALUE);
            $expectedStatusCode = Utils::TEST_SESSION_EXPIRED_STATUS_CODE_VALUE;
        }
        Utils::startST();
        $this->assertEquals($expectedStatusCode, HandshakeInfo::getInstance()->sessionExpiredStatusCode);
    }
}
