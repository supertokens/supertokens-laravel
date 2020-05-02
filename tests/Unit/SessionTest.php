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
use Illuminate\Support\Facades\Config;
use SuperTokens\Exceptions\SuperTokensException;
use SuperTokens\Exceptions\SuperTokensGeneralException;
use SuperTokens\Exceptions\SuperTokensTokenTheftException;
use SuperTokens\Exceptions\SuperTokensTryRefreshTokenException;
use SuperTokens\Exceptions\SuperTokensUnauthorisedException;
use SuperTokens\Helpers\Constants;
use SuperTokens\Helpers\HandshakeInfo;
use SuperTokens\Helpers\Querier;
use SuperTokens\SessionHandlingFunctions;
use SuperTokens\SuperTokens;

class SessionTest extends TestCase
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
    public function testTokenTheftDetection(): void
    {
        Utils::startST();
        $session = SessionHandlingFunctions::createNewSession("userId", [], []);
        $refreshedSession = SessionHandlingFunctions::refreshSession($session['refreshToken']['token']);
        SessionHandlingFunctions::getSession($refreshedSession['accessToken']['token'], $refreshedSession['antiCsrfToken'], true, $refreshedSession['idRefreshToken']['token']);
        try {
            SessionHandlingFunctions::refreshSession($session['refreshToken']['token']);
            $this->assertTrue(false);
        } catch (SuperTokensTokenTheftException $e) {
            $this->assertTrue($e->getUserId() === "userId");
            $this->assertTrue($e->getSessionHandle() === $session['session']['handle']);
            $this->assertTrue(true);
        }
    }

    /**
     * @throws Exception
     */
    public function testBasicUsageOfSessions(): void
    {
        Utils::startST();
        $session = SessionHandlingFunctions::createNewSession("userId", [], []);
        $this->assertArrayHasKey("session", $session);
        $this->assertArrayHasKey("accessToken", $session);
        $this->assertArrayHasKey("refreshToken", $session);
        $this->assertArrayHasKey("idRefreshToken", $session);
        $this->assertArrayHasKey("antiCsrfToken", $session);
        $this->assertCount(5, array_keys($session));

        SessionHandlingFunctions::getSession($session['accessToken']['token'], $session['antiCsrfToken'], true, $session['idRefreshToken']['token']);
        self::assertFalse(SessionHandlingFunctions::$TEST_SERVICE_CALLED);

        $refreshedSession = SessionHandlingFunctions::refreshSession($session['refreshToken']['token']);
        $this->assertArrayHasKey("session", $refreshedSession);
        $this->assertArrayHasKey("accessToken", $refreshedSession);
        $this->assertArrayHasKey("refreshToken", $refreshedSession);
        $this->assertArrayHasKey("idRefreshToken", $refreshedSession);
        $this->assertArrayHasKey("antiCsrfToken", $refreshedSession);
        $this->assertCount(5, array_keys($refreshedSession));

        $refreshedSessionNew = SessionHandlingFunctions::getSession($refreshedSession['accessToken']['token'], $refreshedSession['antiCsrfToken'], true, $refreshedSession['idRefreshToken']['token']);
        self::assertTrue(SessionHandlingFunctions::$TEST_SERVICE_CALLED);
        $this->assertArrayHasKey("session", $refreshedSessionNew);
        $this->assertArrayHasKey("accessToken", $refreshedSessionNew);
        $this->assertCount(2, array_keys($refreshedSessionNew));

        $refreshedSessionNew2 = SessionHandlingFunctions::getSession($refreshedSessionNew['accessToken']['token'], $refreshedSession['antiCsrfToken'], true, $refreshedSession['idRefreshToken']['token']);
        self::assertFalse(SessionHandlingFunctions::$TEST_SERVICE_CALLED);
        $this->assertArrayHasKey("session", $refreshedSessionNew2);
        $this->assertArrayNotHasKey("accessToken", $refreshedSessionNew2);
        $this->assertCount(1, array_keys($refreshedSessionNew2));

        $revokedSession = SessionHandlingFunctions::revokeSessionUsingSessionHandle($refreshedSessionNew2['session']['handle']);
        $this->assertTrue($revokedSession);
    }

    /**
     * @throws Exception
     */
    public function testSessionVerifyWithAnticsrfPresent(): void
    {
        Utils::startST();
        $session = SessionHandlingFunctions::createNewSession("userId", [], []);

        $sessionGet1 = SessionHandlingFunctions::getSession($session['accessToken']['token'], $session['antiCsrfToken'], true, $session['idRefreshToken']['token']);
        $this->assertArrayHasKey('session', $sessionGet1);
        $this->assertCount(3, $sessionGet1['session']);

        $sessionGet2 = SessionHandlingFunctions::getSession($session['accessToken']['token'], $session['antiCsrfToken'], false, $session['idRefreshToken']['token']);
        $this->assertArrayHasKey('session', $sessionGet2);
        $this->assertCount(3, $sessionGet2['session']);
    }

    /**
     * @throws Exception
     */
    public function testSessionVerifyWithoutAnticsrfPresent(): void
    {
        Utils::startST();
        $session = SessionHandlingFunctions::createNewSession("userId", [], []);

        //passing anti-csrf token as null and anti-csrf check as false
        $sessionGet1 = SessionHandlingFunctions::getSession($session['accessToken']['token'], null, false, $session['idRefreshToken']['token']);
        $this->assertArrayHasKey('session', $sessionGet1);
        $this->assertCount(3, $sessionGet1['session']);

        //passing anti-csrf token as null and anti-csrf check as true
        try {
            SessionHandlingFunctions::getSession($session['accessToken']['token'], null, true, $session['idRefreshToken']['token']);
            $this->assertTrue(false);
        } catch (SuperTokensTryRefreshTokenException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * @throws Exception
     */
    public function testRevokingOfSessions(): void
    {
        Utils::startST();
        SessionHandlingFunctions::revokeAllSessionsForUser("userId");
        if (Querier::getInstance()->getApiVersion() === "1.0") {
            $this->assertEquals("1.0", SessionHandlingFunctions::$TEST_FUNCTION_VERSION);
            $this->assertCount(0, SessionHandlingFunctions::getAllSessionHandlesForUser("userId"));
            $session = SessionHandlingFunctions::createNewSession("userId", [], []);
            $this->assertCount(1, SessionHandlingFunctions::getAllSessionHandlesForUser("userId"));
            $this->assertTrue(SessionHandlingFunctions::revokeSessionUsingSessionHandle($session['session']['handle']));
            $this->assertCount(0, SessionHandlingFunctions::getAllSessionHandlesForUser("userId"));
            SessionHandlingFunctions::createNewSession("userId", [], []);
            SessionHandlingFunctions::createNewSession("userId", [], []);
            $this->assertCount(2, SessionHandlingFunctions::getAllSessionHandlesForUser("userId"));
            $this->assertEquals(2, SessionHandlingFunctions::revokeAllSessionsForUser("userId"));
            $this->assertCount(0, SessionHandlingFunctions::getAllSessionHandlesForUser("userId"));
            SessionHandlingFunctions::reset();
            $this->assertFalse(SessionHandlingFunctions::revokeSessionUsingSessionHandle("random"));
            $this->assertEquals("1.0", SessionHandlingFunctions::$TEST_FUNCTION_VERSION);
            $this->assertEquals(0, SessionHandlingFunctions::revokeAllSessionsForUser("randomUserId"));
        } else {
            $this->assertEquals("2.0", SessionHandlingFunctions::$TEST_FUNCTION_VERSION);
            $this->assertCount(0, SessionHandlingFunctions::getAllSessionHandlesForUser("userId"));
            $session = SessionHandlingFunctions::createNewSession("userId", [], []);
            $this->assertCount(1, SessionHandlingFunctions::getAllSessionHandlesForUser("userId"));
            $this->assertTrue(SessionHandlingFunctions::revokeSessionUsingSessionHandle($session['session']['handle']));
            $this->assertCount(0, SessionHandlingFunctions::getAllSessionHandlesForUser("userId"));
            SessionHandlingFunctions::createNewSession("userId", [], []);
            SessionHandlingFunctions::createNewSession("userId", [], []);
            $this->assertCount(2, SessionHandlingFunctions::getAllSessionHandlesForUser("userId"));
            $this->assertCount(2, SessionHandlingFunctions::revokeAllSessionsForUser("userId"));
            $this->assertCount(0, SessionHandlingFunctions::getAllSessionHandlesForUser("userId"));
            SessionHandlingFunctions::reset();
            $this->assertFalse(SessionHandlingFunctions::revokeSessionUsingSessionHandle("random"));
            $this->assertEquals("2.0", SessionHandlingFunctions::$TEST_FUNCTION_VERSION);
            $this->assertCount(0, SessionHandlingFunctions::revokeAllSessionsForUser("randomUserId"));
        }
    }

    /**
     * @throws Exception
     */
    public function testManipulatingSessionData(): void
    {
        Utils::startST();
        $session = SessionHandlingFunctions::createNewSession("userId", [], []);

        $sessionData0 = SessionHandlingFunctions::getSessionData($session['session']['handle']);
        $this->assertTrue(count($sessionData0) === 0);
        $this->assertTrue(count($session['session']['userDataInJWT']) === 0);

        SessionHandlingFunctions::updateSessionData($session['session']['handle'], ["key" => "value"]);
        $sessionData1 = SessionHandlingFunctions::getSessionData($session['session']['handle']);
        $this->assertArrayHasKey("key", $sessionData1);
        $this->assertEquals("value", $sessionData1["key"]);

        SessionHandlingFunctions::updateSessionData($session['session']['handle'], ["key" => "value2"]);
        $sessionData1 = SessionHandlingFunctions::getSessionData($session['session']['handle']);
        $this->assertArrayHasKey("key", $sessionData1);
        $this->assertEquals("value2", $sessionData1["key"]);

        //passing invalid session handle when updating session data
        try {
            SessionHandlingFunctions::updateSessionData("incorrect", ["key" => "value2"]);
            $this->assertTrue(false);
        } catch (SuperTokensUnauthorisedException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * @throws Exception
     */
    public function testManipulatingJwtData(): void
    {
        Utils::startST();
        $session1 = SessionHandlingFunctions::createNewSession("userId", [], []);
        $session2 = SessionHandlingFunctions::createNewSession("userId", [], []);
        if (Querier::getInstance()->getApiVersion() !== "1.0") {
            $jwtData0 = SessionHandlingFunctions::getJWTPayload($session1['session']['handle']);
            $this->assertTrue(count($jwtData0) === 0);
            $this->assertTrue(count($session1['session']['userDataInJWT']) === 0);
            $jwtData1 = SessionHandlingFunctions::getJWTPayload($session2['session']['handle']);
            $this->assertTrue(count($jwtData1) === 0);
            $this->assertTrue(count($session2['session']['userDataInJWT']) === 0);

            SessionHandlingFunctions::updateJWTPayload($session1['session']['handle'], ["key" => "value"]);
            $jwtData2 = SessionHandlingFunctions::getJWTPayload($session1['session']['handle']);
            $this->assertArrayHasKey("key", $jwtData2);
            $this->assertEquals("value", $jwtData2["key"]);
            $jwtData3 = SessionHandlingFunctions::getJWTPayload($session2['session']['handle']);
            $this->assertArrayNotHasKey("key", $jwtData3);
            $this->assertTrue(count($jwtData3) === 0);

            //passing invalid session handle when updating session data
            try {
                SessionHandlingFunctions::updateJWTPayload("incorrect", ["key" => "value2"]);
                $this->assertTrue(false);
            } catch (SuperTokensUnauthorisedException $e) {
                $this->assertTrue(true);
            }

            //passing invalid jwt data when updating session data
            try {
                SessionHandlingFunctions::updateJWTPayload($session1['session']['handle'], null);
                $this->assertTrue(false);
            } catch (SuperTokensGeneralException $e) {
                $this->assertTrue(true);
            }
        } else {
            try {
                SessionHandlingFunctions::getJWTPayload($session1['session']['handle']);
                $this->assertTrue(false);
            } catch (SuperTokensGeneralException $e) {
                $this->assertTrue(true);
            }
            try {
                SessionHandlingFunctions::updateJWTPayload($session1['session']['handle'], []);
                $this->assertTrue(false);
            } catch (SuperTokensGeneralException $e) {
                $this->assertTrue(true);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function testAnticsrfDisabledForCore(): void
    {
        Utils::setKeyValueInConfig(Utils::ENABLE_ANTI_CSRF_CONFIG_KEY, false);
        Utils::startST();

        $session = SessionHandlingFunctions::createNewSession("userId", [], []);
        $sessionGet1 = SessionHandlingFunctions::getSession($session['accessToken']['token'], null, false, $session['idRefreshToken']['token']);
        $this->assertArrayHasKey('session', $sessionGet1);
        $this->assertCount(3, $sessionGet1['session']);

        $sessionGet2 = SessionHandlingFunctions::getSession($session['accessToken']['token'], null, true, $session['idRefreshToken']['token']);
        $this->assertArrayHasKey('session', $sessionGet2);
        $this->assertCount(3, $sessionGet2['session']);
    }
}
