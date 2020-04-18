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
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SuperTokens\Exceptions\SuperTokensException;
use SuperTokens\Exceptions\SuperTokensGeneralException;
use SuperTokens\Exceptions\SuperTokensTokenTheftException;
use SuperTokens\Exceptions\SuperTokensTryRefreshTokenException;
use SuperTokens\Exceptions\SuperTokensUnauthorisedException;
use SuperTokens\SessionHandlingFunctions;
use SuperTokens\SuperTokens;

// TODO: add tests for JWT and session data null, empty checks
// TODO: add tests for cookie expiry checking

class SuperTokensTest extends TestCase
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
    public function testSupertokensTokenTheftDetection(): void
    {
        Utils::startST();

        $response1 = new Response();
        SuperTokens::createNewSession($response1, "userId", [], []);
        $responseData1 = Utils::extractInfoFromResponse($response1);
        $this->assertEquals(3600, $responseData1['accessTokenMaxAge']);

        $request2 = new Request([], [], [], [
            'sRefreshToken' => $responseData1['refreshToken']
        ]);
        $response2 = new Response();
        SuperTokens::refreshSession($request2, $response2);
        $responseData2 = Utils::extractInfoFromResponse($response2);

        $request3 = new Request([], [], [], [
            'sAccessToken' => $responseData2['accessToken'],
            'sIdRefreshToken' => $responseData2['idRefreshToken']
        ]);
        $request3->headers->set("anti-csrf", $responseData2['antiCsrf']);
        $response3 = new Response();
        SuperTokens::getSession($request3, $response3, true);

        $response4 = new Response();
        try {
            SuperTokens::refreshSession($request2, $response4);
            $this->assertTrue(false);
        } catch (SuperTokensTokenTheftException $e) {
            $responseData3 = Utils::extractInfoFromResponse($response4);
            $this->assertNull($responseData3["antiCsrf"]);
            $this->assertEquals("", $responseData3["accessToken"]);
            $this->assertEquals("", $responseData3["refreshToken"]);
            $this->assertEquals("remove", $responseData3["idRefreshTokenFromHeader"]);
            $this->assertEquals("", $responseData3["idRefreshToken"]);
            $this->assertEquals(0, $responseData3["accessTokenExpiry"]);
            $this->assertEquals(0, $responseData3["refreshTokenExpiry"]);
            $this->assertEquals(0, $responseData3["idRefreshTokenExpiry"]);
        }
    }

    /**
     * @throws Exception
     */
    public function testTestBasicSupertokensUsage(): void
    {
        Utils::startST();
        $response1 = new Response();
        SuperTokens::createNewSession($response1, "userId", null, []);
        $responseData1 = Utils::extractInfoFromResponse($response1);
        $this->assertNotNull($responseData1["idRefreshTokenFromHeader"]);
        $this->assertNotNull($responseData1["accessToken"]);
        $this->assertNotNull($responseData1["refreshToken"]);
        $this->assertNotNull($responseData1["idRefreshToken"]);
        $this->assertNotNull($responseData1["antiCsrf"]);
        $this->assertTrue($responseData1["accessTokenCookie"]->getPath() === "/");
        $this->assertTrue($responseData1["accessTokenCookie"]->getDomain() === "supertokens.io");
        $this->assertTrue($responseData1["accessTokenCookie"]->getSameSite() === "none");
        $this->assertTrue($responseData1["accessTokenCookie"]->isHttpOnly());
        $this->assertTrue(!$responseData1["accessTokenCookie"]->isSecure());

        $this->assertTrue($responseData1["refreshTokenCookie"]->getPath() === "/refresh");
        $this->assertTrue($responseData1["refreshTokenCookie"]->getDomain() === "supertokens.io");
        $this->assertTrue($responseData1["refreshTokenCookie"]->getSameSite() === "none");
        $this->assertTrue($responseData1["refreshTokenCookie"]->isHttpOnly());
        $this->assertTrue(!$responseData1["refreshTokenCookie"]->isSecure());

        $this->assertTrue($responseData1["idRefreshTokenCookie"]->getPath() === "/");
        $this->assertTrue($responseData1["idRefreshTokenCookie"]->getDomain() === "supertokens.io");
        $this->assertTrue($responseData1["idRefreshTokenCookie"]->getSameSite() === "none");
        $this->assertTrue($responseData1["idRefreshTokenCookie"]->isHttpOnly());
        $this->assertTrue(!$responseData1["idRefreshTokenCookie"]->isSecure());

        $request2 = new Request([], [], [], [
            'sAccessToken' => $responseData1['accessToken'],
            'sIdRefreshToken' => $responseData1['idRefreshToken']
        ]);
        $request2->headers->set("anti-csrf", $responseData1['antiCsrf']);
        $response2 = new Response();
        SuperTokens::getSession($request2, $response2, true);
        $emptyResponseData1 = Utils::extractInfoFromResponse($response2);
        $this->assertNull($emptyResponseData1["idRefreshTokenFromHeader"]);
        $this->assertNull($emptyResponseData1["accessToken"]);
        $this->assertNull($emptyResponseData1["refreshToken"]);
        $this->assertNull($emptyResponseData1["idRefreshToken"]);
        $this->assertNull($emptyResponseData1["antiCsrf"]);

        self::assertFalse(SessionHandlingFunctions::$SERVICE_CALLED);

        $request3 = new Request([], [], [], [
            'sRefreshToken' => $responseData1['refreshToken']
        ]);
        $response3 = new Response();
        SuperTokens::refreshSession($request3, $response3);
        $responseData2 = Utils::extractInfoFromResponse($response3);
        $this->assertNotNull($responseData2["idRefreshTokenFromHeader"]);
        $this->assertNotNull($responseData2["accessToken"]);
        $this->assertNotNull($responseData2["refreshToken"]);
        $this->assertNotNull($responseData2["idRefreshToken"]);
        $this->assertNotNull($responseData2["antiCsrf"]);
        $this->assertTrue($responseData2["accessTokenCookie"]->getPath() === "/");
        $this->assertTrue($responseData2["accessTokenCookie"]->getDomain() === "supertokens.io");
        $this->assertTrue($responseData2["accessTokenCookie"]->getSameSite() === "none");
        $this->assertTrue($responseData2["accessTokenCookie"]->isHttpOnly());
        $this->assertTrue(!$responseData2["accessTokenCookie"]->isSecure());

        $this->assertTrue($responseData2["refreshTokenCookie"]->getPath() === "/refresh");
        $this->assertTrue($responseData2["refreshTokenCookie"]->getDomain() === "supertokens.io");
        $this->assertTrue($responseData2["refreshTokenCookie"]->getSameSite() === "none");
        $this->assertTrue($responseData2["refreshTokenCookie"]->isHttpOnly());
        $this->assertTrue(!$responseData2["refreshTokenCookie"]->isSecure());

        $this->assertTrue($responseData2["idRefreshTokenCookie"]->getPath() === "/");
        $this->assertTrue($responseData2["idRefreshTokenCookie"]->getDomain() === "supertokens.io");
        $this->assertTrue($responseData2["idRefreshTokenCookie"]->getSameSite() === "none");
        $this->assertTrue($responseData2["idRefreshTokenCookie"]->isHttpOnly());
        $this->assertTrue(!$responseData2["idRefreshTokenCookie"]->isSecure());

        $request4 = new Request([], [], [], [
            'sAccessToken' => $responseData2['accessToken'],
            'sIdRefreshToken' => $responseData2['idRefreshToken']
        ]);
        $request4->headers->set("anti-csrf", $responseData2['antiCsrf']);
        $response4 = new Response();
        SuperTokens::getSession($request4, $response4, true);
        $responseData3 = Utils::extractInfoFromResponse($response4);
        $this->assertNotNull($responseData3["accessToken"]);
        $this->assertTrue($responseData3["accessTokenCookie"]->getPath() === "/");
        $this->assertTrue($responseData3["accessTokenCookie"]->getDomain() === "supertokens.io");
        $this->assertTrue($responseData3["accessTokenCookie"]->getSameSite() === "none");
        $this->assertTrue($responseData3["accessTokenCookie"]->isHttpOnly());
        $this->assertTrue(!$responseData3["accessTokenCookie"]->isSecure());

        $this->assertNull($responseData3["idRefreshTokenFromHeader"]);
        $this->assertNull($responseData3["refreshToken"]);
        $this->assertNull($responseData3["idRefreshToken"]);
        $this->assertNull($responseData3["antiCsrf"]);
        self::assertTrue(SessionHandlingFunctions::$SERVICE_CALLED);

        $request5 = new Request([], [], [], [
            'sAccessToken' => $responseData3['accessToken'],
            'sIdRefreshToken' => $responseData2['idRefreshToken']
        ]);
        $request5->headers->set("anti-csrf", $responseData2['antiCsrf']);
        $response5 = new Response();
        $session = SuperTokens::getSession($request5, $response5, true);
        self::assertFalse(SessionHandlingFunctions::$SERVICE_CALLED);

        $session->revokeSession();
        $responseData4 = Utils::extractInfoFromResponse($response5);
        $this->assertNull($responseData3["antiCsrf"]);
        $this->assertEquals("", $responseData4["accessToken"]);
        $this->assertEquals("", $responseData4["refreshToken"]);
        $this->assertEquals("remove", $responseData4["idRefreshTokenFromHeader"]);
        $this->assertEquals("", $responseData4["idRefreshToken"]);
        $this->assertEquals(0, $responseData4["accessTokenExpiry"]);
        $this->assertEquals(0, $responseData4["refreshTokenExpiry"]);
        $this->assertEquals(0, $responseData4["idRefreshTokenExpiry"]);
    }

    /**
     * @throws Exception
     */
    public function testVerifySupertokensWithAnticsrfPresent(): void
    {
        Utils::startST();

        $response1 = new Response();
        SuperTokens::createNewSession($response1, "userId", [], []);
        $responseData1 = Utils::extractInfoFromResponse($response1);

        $request2 = new Request([], [], [], [
            'sAccessToken' => $responseData1['accessToken'],
            'sIdRefreshToken' => $responseData1['idRefreshToken']
        ]);
        $request2->headers->set("anti-csrf", $responseData1['antiCsrf']);
        $response2 = new Response();
        $session1 = SuperTokens::getSession($request2, $response2, true);
        $this->assertEquals("userId", $session1->getUserId());

        $request3 = new Request([], [], [], [
            'sAccessToken' => $responseData1['accessToken'],
            'sIdRefreshToken' => $responseData1['idRefreshToken']
        ]);
        $request3->headers->set("anti-csrf", $responseData1['antiCsrf']);
        $response3 = new Response();
        $session2 = SuperTokens::getSession($request3, $response3, false);
        $this->assertEquals("userId", $session2->getUserId());
    }

    /**
     * @throws Exception
     */
    public function testVerifySupertokensWithoutAnticsrfPresent(): void
    {
        Utils::startST();

        $response1 = new Response();
        SuperTokens::createNewSession($response1, "userId", [], []);
        $responseData1 = Utils::extractInfoFromResponse($response1);

        $request2 = new Request([], [], [], [
            'sAccessToken' => $responseData1['accessToken'],
            'sIdRefreshToken' => $responseData1['idRefreshToken']
        ]);
        $response2 = new Response();
        $session1 = SuperTokens::getSession($request2, $response2, false);
        $this->assertEquals("userId", $session1->getUserId());

        $request3 = new Request([], [], [], [
            'sAccessToken' => $responseData1['accessToken'],
            'sIdRefreshToken' => $responseData1['idRefreshToken']
        ]);
        $response3 = new Response();
        try {
            SuperTokens::getSession($request3, $response3, true);
            $this->assertTrue(false);
        } catch (SuperTokensTryRefreshTokenException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * @throws Exception
     */
    public function testSupertokensRevokingOfSessions(): void
    {
        Utils::startST();

        SessionHandlingFunctions::revokeAllSessionsForUser("userId");
        $this->assertCount(0, SessionHandlingFunctions::getAllSessionHandlesForUser("userId"));
        $response1 = new Response();
        SuperTokens::createNewSession($response1, "userId", [], []);
        $responseData1 = Utils::extractInfoFromResponse($response1);

        $request2 = new Request([], [], [], [
            'sAccessToken' => $responseData1['accessToken'],
            'sIdRefreshToken' => $responseData1['idRefreshToken']
        ]);
        $request2->headers->set("anti-csrf", $responseData1['antiCsrf']);
        $response2 = new Response();
        $session1 = SuperTokens::getSession($request2, $response2, true);
        $session1->revokeSession();
        $responseData2 = Utils::extractInfoFromResponse($response2);
        $this->assertNull($responseData2["antiCsrf"]);
        $this->assertEquals("", $responseData2["accessToken"]);
        $this->assertEquals("", $responseData2["refreshToken"]);
        $this->assertEquals("remove", $responseData2["idRefreshTokenFromHeader"]);
        $this->assertEquals("", $responseData2["idRefreshToken"]);
        $this->assertEquals(0, $responseData2["accessTokenExpiry"]);
        $this->assertEquals(0, $responseData2["refreshTokenExpiry"]);
        $this->assertEquals(0, $responseData2["idRefreshTokenExpiry"]);

        SuperTokens::createNewSession(new Response(), "userId", [], []);
        $response3 = new Response();
        SuperTokens::createNewSession($response3, "userId", [], []);
        $responseData3 = Utils::extractInfoFromResponse($response3);

        $request4 = new Request([], [], [], [
            'sAccessToken' => $responseData3['accessToken'],
            'sIdRefreshToken' => $responseData3['idRefreshToken']
        ]);
        $request4->headers->set("anti-csrf", $responseData3['antiCsrf']);
        $response4 = new Response();
        $session2 = SuperTokens::getSession($request4, $response4, true);
        SuperTokens::revokeAllSessionsForUser($session2->getUserId());
        $this->assertCount(0, SuperTokens::getAllSessionHandlesForUser($session2->getUserId()));
    }

    /**
     * @throws Exception
     */
    public function testSupertokensManipulatingSessionData(): void
    {
        Utils::startST();

        $response1 = new Response();
        SuperTokens::createNewSession($response1, "userId", [], []);
        $responseData1 = Utils::extractInfoFromResponse($response1);

        $request2 = new Request([], [], [], [
            'sAccessToken' => $responseData1['accessToken'],
            'sIdRefreshToken' => $responseData1['idRefreshToken']
        ]);
        $request2->headers->set("anti-csrf", $responseData1['antiCsrf']);
        $response2 = new Response();
        $session1 = SuperTokens::getSession($request2, $response2, true);
        $session1->updateSessionData(["key" => "value"]);

        $request3 = new Request([], [], [], [
            'sAccessToken' => $responseData1['accessToken'],
            'sIdRefreshToken' => $responseData1['idRefreshToken']
        ]);
        $request3->headers->set("anti-csrf", $responseData1['antiCsrf']);
        $response3 = new Response();
        $session2 = SuperTokens::getSession($request3, $response3, true);
        $sessionData1 = $session2->getSessionData();
        $this->assertArrayHasKey("key", $sessionData1);
        $this->assertEquals("value", $sessionData1["key"]);

        $request4 = new Request([], [], [], [
            'sAccessToken' => $responseData1['accessToken'],
            'sIdRefreshToken' => $responseData1['idRefreshToken']
        ]);
        $request4->headers->set("anti-csrf", $responseData1['antiCsrf']);
        $response4 = new Response();
        $session3 = SuperTokens::getSession($request4, $response4, true);
        $session3->updateSessionData(["key" => "value2"]);

        $request5 = new Request([], [], [], [
            'sAccessToken' => $responseData1['accessToken'],
            'sIdRefreshToken' => $responseData1['idRefreshToken']
        ]);
        $request5->headers->set("anti-csrf", $responseData1['antiCsrf']);
        $response5 = new Response();
        $session4 = SuperTokens::getSession($request5, $response5, true);
        $sessionData2 = $session4->getSessionData();
        $this->assertArrayHasKey("key", $sessionData2);
        $this->assertEquals("value2", $sessionData2["key"]);

        try {
            SuperTokens::updateSessionData("invalidSessionHandle", ["key" => "value"]);
            $this->assertTrue(false);
        } catch (SuperTokensUnauthorisedException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * @throws Exception
     */
    public function testSupertokensAnticsrfDisabledForCore(): void
    {
        Utils::setKeyValueInConfig(Utils::ENABLE_ANTI_CSRF_CONFIG_KEY, false);
        Utils::startST();

        $response1 = new Response();
        SuperTokens::createNewSession($response1, "userId", [], []);
        $responseData1 = Utils::extractInfoFromResponse($response1);

        $request2 = new Request([], [], [], [
            'sAccessToken' => $responseData1['accessToken'],
            'sIdRefreshToken' => $responseData1['idRefreshToken']
        ]);
        $response2 = new Response();
        $session1 = SuperTokens::getSession($request2, $response2, true);
        $this->assertEquals("userId", $session1->getUserId());

        $request3 = new Request([], [], [], [
            'sAccessToken' => $responseData1['accessToken'],
            'sIdRefreshToken' => $responseData1['idRefreshToken']
        ]);
        $response3 = new Response();
        $session2 = SuperTokens::getSession($request3, $response3, false);
        $this->assertEquals("userId", $session2->getUserId());
    }
}
