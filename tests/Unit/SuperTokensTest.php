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
use PHPUnit\Framework\Error\Error;
use SuperTokens\Exceptions\SuperTokensException;
use SuperTokens\Exceptions\SuperTokensGeneralException;
use SuperTokens\Exceptions\SuperTokensTokenTheftException;
use SuperTokens\Exceptions\SuperTokensTryRefreshTokenException;
use SuperTokens\Exceptions\SuperTokensUnauthorisedException;
use SuperTokens\Helpers\Constants;
use SuperTokens\SessionHandlingFunctions;
use SuperTokens\SuperTokens;
use SuperTokens\Helpers\Querier;

// TODO: add tests for JWT and session data null, empty checks
// TODO: add tests for cookie expiry checking

// TODO: add tests for middleware

class SuperTokensTest extends TestCase
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

    public function testCookieAndHeaderValuesAntiCsrfEnabled(): void
    {
        Utils::startST();
        $sameSiteSupported = Querier::getInstance()->getApiVersion() !== "1.0";
        Utils::reset();
        Utils::cleanST();
        Utils::setupST();
        $expectedSameSite = Constants::SAME_SITE_COOKIE_DEFAULT_VALUE;
        if ($sameSiteSupported) {
            Utils::setKeyValueInConfig(Utils::TEST_COOKIE_SAME_SITE_CONFIG_KEY, Utils::TEST_COOKIE_SAME_SITE_VALUE);
            $expectedSameSite = Utils::TEST_COOKIE_SAME_SITE_VALUE;
        }
        Utils::setKeyValueInConfig(Utils::TEST_ACCESS_TOKEN_MAX_AGE_CONFIG_KEY, Utils::TEST_ACCESS_TOKEN_MAX_AGE_VALUE);
        Utils::setKeyValueInConfig(Utils::TEST_ACCESS_TOKEN_PATH_CONFIG_KEY, Utils::TEST_ACCESS_TOKEN_PATH_VALUE);
        Utils::setKeyValueInConfig(Utils::TEST_COOKIE_DOMAIN_CONFIG_KEY, Utils::TEST_COOKIE_DOMAIN_VALUE);
        Utils::setKeyValueInConfig(Utils::TEST_REFRESH_TOKEN_MAX_AGE_CONFIG_KEY, Utils::TEST_REFRESH_TOKEN_MAX_AGE_VALUE);
        Utils::setKeyValueInConfig(Utils::TEST_REFRESH_TOKEN_PATH_CONFIG_KEY, Utils::TEST_REFRESH_TOKEN_PATH_KEY_VALUE);
        Utils::setKeyValueInConfig(Utils::TEST_COOKIE_SECURE_CONFIG_KEY, Utils::TEST_COOKIE_SECURE_VALUE);
        Utils::startST();

        $response1 = new Response();
        SuperTokens::createNewSession($response1, "userId", [], []);
        $responseData1 = Utils::extractInfoFromResponse($response1);
        $this->assertEquals(Utils::TEST_ACCESS_TOKEN_MAX_AGE_VALUE, $responseData1['accessTokenMaxAge']);
        $this->assertEquals(Utils::TEST_ACCESS_TOKEN_PATH_VALUE, $responseData1['accessTokenPath']);
        $this->assertEquals(Utils::TEST_COOKIE_DOMAIN_VALUE, $responseData1['accessTokenDomain']);
        $this->assertEquals(Utils::TEST_COOKIE_DOMAIN_VALUE, $responseData1['refreshTokenDomain']);
        $this->assertEquals(Utils::TEST_COOKIE_DOMAIN_VALUE, $responseData1['idRefreshTokenDomain']);
        $this->assertEquals(Utils::TEST_COOKIE_SECURE_VALUE, $responseData1['accessTokenSecure']);
        $this->assertEquals(Utils::TEST_COOKIE_SECURE_VALUE, $responseData1['refreshTokenSecure']);
        $this->assertEquals(Utils::TEST_COOKIE_SECURE_VALUE, $responseData1['idRefreshTokenSecure']);
        $this->assertEquals(true, $responseData1['accessTokenHttpOnly']);
        $this->assertEquals(true, $responseData1['refreshTokenHttpOnly']);
        $this->assertEquals(true, $responseData1['idRefreshTokenHttpOnly']);
        $this->assertEquals(Utils::TEST_REFRESH_TOKEN_MAX_AGE_VALUE * 60, $responseData1['refreshTokenMaxAge']);
        $this->assertEquals(Utils::TEST_REFRESH_TOKEN_PATH_KEY_VALUE, $responseData1['refreshTokenPath']);
        $this->assertEquals($expectedSameSite, $responseData1['accessTokenSameSite']);
        $this->assertEquals($expectedSameSite, $responseData1['refreshTokenSameSite']);
        $this->assertEquals($expectedSameSite, $responseData1['idRefreshTokenSameSite']);
        $this->assertEquals($responseData1['idRefreshToken'].';', substr($responseData1['idRefreshTokenFromHeader'], 0, -13)); // doing substring as expiry extracted from cookie will not include milliseconds part
        $expiryTime = (int)(substr($responseData1['idRefreshTokenFromHeader'], -13, -3));
        $c1 = $responseData1['idRefreshTokenExpiry'] === $expiryTime;
        $c2 = ($responseData1['idRefreshTokenExpiry'] + 1) === $expiryTime;
        $c3 = $responseData1['idRefreshTokenExpiry'] === ($expiryTime + 1);
        $this->assertTrue($c1 || $c2 || $c3);
        $this->assertEquals(Utils::ACCESS_CONTROL_EXPOSE_HEADER_ANTI_CSRF_ENABLE, $responseData1['accessControlExposeHeader']);
        $this->assertNotNull($responseData1['antiCsrf']);

        $request2 = new Request([], [], [], [
            'sAccessToken' => $responseData1['accessToken'],
            'sIdRefreshToken' => $responseData1['idRefreshToken']
        ]);
        $request2->headers->set("anti-csrf", $responseData1['antiCsrf']);
        $response2 = new Response();
        SuperTokens::getSession($request2, $response2, true);
        $responseData2 = Utils::extractInfoFromResponse($response1);
        $this->assertEquals($responseData1['accessToken'], $responseData2['accessToken']);
        $this->assertEquals($responseData1['refreshToken'], $responseData2['refreshToken']);
        $this->assertEquals($responseData1['idRefreshToken'], $responseData2['idRefreshToken']);
        $this->assertEquals($responseData1['antiCsrf'], $responseData2['antiCsrf']);

        $request3 = new Request([], [], [], [
            'sRefreshToken' => $responseData2['refreshToken']
        ]);
        $request3->headers->set("anti-csrf", $responseData2['antiCsrf']);
        $response3 = new Response();
        SuperTokens::refreshSession($request3, $response3);
        $responseData3 = Utils::extractInfoFromResponse($response3);
        $this->assertNotEquals($responseData2['accessToken'], $responseData3['accessToken']);
        $this->assertNotEquals($responseData2['refreshToken'], $responseData3['refreshToken']);
        $this->assertNotEquals($responseData2['idRefreshToken'], $responseData3['idRefreshToken']);
        $this->assertNotEquals($responseData2['antiCsrf'], $responseData3['antiCsrf']);
        $this->assertEquals(Utils::TEST_ACCESS_TOKEN_MAX_AGE_VALUE, $responseData3['accessTokenMaxAge']);
        $this->assertEquals(Utils::TEST_ACCESS_TOKEN_PATH_VALUE, $responseData3['accessTokenPath']);
        $this->assertEquals(Utils::TEST_COOKIE_DOMAIN_VALUE, $responseData3['accessTokenDomain']);
        $this->assertEquals(Utils::TEST_COOKIE_DOMAIN_VALUE, $responseData3['refreshTokenDomain']);
        $this->assertEquals(Utils::TEST_COOKIE_DOMAIN_VALUE, $responseData3['idRefreshTokenDomain']);
        $this->assertEquals(Utils::TEST_COOKIE_SECURE_VALUE, $responseData3['accessTokenSecure']);
        $this->assertEquals(Utils::TEST_COOKIE_SECURE_VALUE, $responseData3['refreshTokenSecure']);
        $this->assertEquals(Utils::TEST_COOKIE_SECURE_VALUE, $responseData3['idRefreshTokenSecure']);
        $this->assertEquals(true, $responseData3['accessTokenHttpOnly']);
        $this->assertEquals(true, $responseData3['refreshTokenHttpOnly']);
        $this->assertEquals(true, $responseData3['idRefreshTokenHttpOnly']);
        $this->assertEquals(Utils::TEST_REFRESH_TOKEN_MAX_AGE_VALUE * 60, $responseData3['refreshTokenMaxAge']);
        $this->assertEquals(Utils::TEST_REFRESH_TOKEN_PATH_KEY_VALUE, $responseData3['refreshTokenPath']);
        $this->assertEquals($expectedSameSite, $responseData3['accessTokenSameSite']);
        $this->assertEquals($expectedSameSite, $responseData3['refreshTokenSameSite']);
        $this->assertEquals($expectedSameSite, $responseData3['idRefreshTokenSameSite']);
        $this->assertEquals($responseData3['idRefreshToken'].';'.$responseData3['idRefreshTokenExpiry'], substr($responseData3['idRefreshTokenFromHeader'], 0, -3)); // doing substring as expiry extracted from cookie will not include milliseconds part
        $this->assertEquals(Utils::ACCESS_CONTROL_EXPOSE_HEADER_ANTI_CSRF_ENABLE, $responseData3['accessControlExposeHeader']);
        $this->assertNotNull($responseData3['antiCsrf']);

        $request4 = new Request([], [], [], [
            'sAccessToken' => $responseData3['accessToken'],
            'sIdRefreshToken' => $responseData3['idRefreshToken']
        ]);
        $request4->headers->set("anti-csrf", $responseData3['antiCsrf']);
        $response4 = new Response();
        SuperTokens::getSession($request4, $response4, true);
        $responseData4 = Utils::extractInfoFromResponse($response4);
        $this->assertNotEquals($responseData3['accessToken'], $responseData4['accessToken']);
        $this->assertNotEquals($responseData1['accessToken'], $responseData4['accessToken']);
        $this->assertEquals(Utils::TEST_ACCESS_TOKEN_MAX_AGE_VALUE, $responseData4['accessTokenMaxAge']);
        $this->assertEquals(Utils::TEST_ACCESS_TOKEN_PATH_VALUE, $responseData4['accessTokenPath']);
        $this->assertEquals(Utils::TEST_COOKIE_DOMAIN_VALUE, $responseData4['accessTokenDomain']);
        $this->assertEquals(Utils::TEST_COOKIE_SECURE_VALUE, $responseData4['accessTokenSecure']);
        $this->assertEquals(true, $responseData4['accessTokenHttpOnly']);
        $this->assertEquals($expectedSameSite, $responseData4['accessTokenSameSite']);

        $request5 = new Request([], [], [], [
            'sAccessToken' => $responseData4['accessToken'],
            'sIdRefreshToken' => $responseData3['idRefreshToken']
        ]);
        $request5->headers->set("anti-csrf", $responseData3['antiCsrf']);
        $response5 = new Response();
        $session = SuperTokens::getSession($request5, $response5, true);
        $responseData5 = Utils::extractInfoFromResponse($response5);
        $this->assertNull($responseData5['accessTokenCookie']);
        $this->assertNull($responseData5['refreshTokenCookie']);
        $this->assertNull($responseData5['idRefreshTokenCookie']);
        $this->assertNull($responseData5['antiCsrf']);

        $session->revokeSession();
        $responseData5 = Utils::extractInfoFromResponse($response5);
        $this->assertEquals(0, $responseData5['accessTokenExpiry']);
        $this->assertEquals("", $responseData5['accessToken']);
        $this->assertEquals("", $responseData5['refreshToken']);
        $this->assertEquals("", $responseData5['idRefreshToken']);
        $this->assertEquals(Utils::TEST_ACCESS_TOKEN_PATH_VALUE, $responseData5['accessTokenPath']);
        $this->assertEquals(Utils::TEST_COOKIE_DOMAIN_VALUE, $responseData5['accessTokenDomain']);
        $this->assertEquals(Utils::TEST_COOKIE_DOMAIN_VALUE, $responseData5['refreshTokenDomain']);
        $this->assertEquals(Utils::TEST_COOKIE_DOMAIN_VALUE, $responseData5['idRefreshTokenDomain']);
        $this->assertEquals(Utils::TEST_COOKIE_SECURE_VALUE, $responseData5['accessTokenSecure']);
        $this->assertEquals(Utils::TEST_COOKIE_SECURE_VALUE, $responseData5['refreshTokenSecure']);
        $this->assertEquals(Utils::TEST_COOKIE_SECURE_VALUE, $responseData5['idRefreshTokenSecure']);
        $this->assertEquals(true, $responseData5['accessTokenHttpOnly']);
        $this->assertEquals(true, $responseData5['refreshTokenHttpOnly']);
        $this->assertEquals(true, $responseData5['idRefreshTokenHttpOnly']);
        $this->assertEquals(0, $responseData5['refreshTokenExpiry']);
        $this->assertEquals(Utils::TEST_REFRESH_TOKEN_PATH_KEY_VALUE, $responseData5['refreshTokenPath']);
        $this->assertEquals($expectedSameSite, $responseData5['accessTokenSameSite']);
        $this->assertEquals($expectedSameSite, $responseData5['refreshTokenSameSite']);
        $this->assertEquals($expectedSameSite, $responseData5['idRefreshTokenSameSite']);
        $this->assertEquals("remove", $responseData5['idRefreshTokenFromHeader']);
        $this->assertNull($responseData5['antiCsrf']);
    }

    public function testCookieAndHeaderValuesAntiCsrfDisabled(): void
    {
        Utils::startST();
        $sameSiteSupported = Querier::getInstance()->getApiVersion() !== "1.0";
        Utils::reset();
        Utils::cleanST();
        Utils::setupST();
        $expectedSameSite = Constants::SAME_SITE_COOKIE_DEFAULT_VALUE;
        if ($sameSiteSupported) {
            Utils::setKeyValueInConfig(Utils::TEST_COOKIE_SAME_SITE_CONFIG_KEY, Utils::TEST_COOKIE_SAME_SITE_VALUE);
            $expectedSameSite = Utils::TEST_COOKIE_SAME_SITE_VALUE;
        }
        Utils::setKeyValueInConfig(Utils::ENABLE_ANTI_CSRF_CONFIG_KEY, false);
        Utils::setKeyValueInConfig(Utils::TEST_ACCESS_TOKEN_MAX_AGE_CONFIG_KEY, Utils::TEST_ACCESS_TOKEN_MAX_AGE_VALUE);
        Utils::setKeyValueInConfig(Utils::TEST_ACCESS_TOKEN_PATH_CONFIG_KEY, Utils::TEST_ACCESS_TOKEN_PATH_VALUE);
        Utils::setKeyValueInConfig(Utils::TEST_COOKIE_DOMAIN_CONFIG_KEY, Utils::TEST_COOKIE_DOMAIN_VALUE);
        Utils::setKeyValueInConfig(Utils::TEST_REFRESH_TOKEN_MAX_AGE_CONFIG_KEY, Utils::TEST_REFRESH_TOKEN_MAX_AGE_VALUE);
        Utils::setKeyValueInConfig(Utils::TEST_REFRESH_TOKEN_PATH_CONFIG_KEY, Utils::TEST_REFRESH_TOKEN_PATH_KEY_VALUE);
        Utils::setKeyValueInConfig(Utils::TEST_COOKIE_SECURE_CONFIG_KEY, Utils::TEST_COOKIE_SECURE_VALUE);
        Utils::startST();

        $response1 = new Response();
        SuperTokens::createNewSession($response1, "userId", [], []);
        $responseData1 = Utils::extractInfoFromResponse($response1);
        $this->assertEquals(Utils::TEST_ACCESS_TOKEN_MAX_AGE_VALUE, $responseData1['accessTokenMaxAge']);
        $this->assertEquals(Utils::TEST_ACCESS_TOKEN_PATH_VALUE, $responseData1['accessTokenPath']);
        $this->assertEquals(Utils::TEST_COOKIE_DOMAIN_VALUE, $responseData1['accessTokenDomain']);
        $this->assertEquals(Utils::TEST_COOKIE_DOMAIN_VALUE, $responseData1['refreshTokenDomain']);
        $this->assertEquals(Utils::TEST_COOKIE_DOMAIN_VALUE, $responseData1['idRefreshTokenDomain']);
        $this->assertEquals(Utils::TEST_COOKIE_SECURE_VALUE, $responseData1['accessTokenSecure']);
        $this->assertEquals(Utils::TEST_COOKIE_SECURE_VALUE, $responseData1['refreshTokenSecure']);
        $this->assertEquals(Utils::TEST_COOKIE_SECURE_VALUE, $responseData1['idRefreshTokenSecure']);
        $this->assertEquals(true, $responseData1['accessTokenHttpOnly']);
        $this->assertEquals(true, $responseData1['refreshTokenHttpOnly']);
        $this->assertEquals(true, $responseData1['idRefreshTokenHttpOnly']);
        $this->assertEquals(Utils::TEST_REFRESH_TOKEN_MAX_AGE_VALUE * 60, $responseData1['refreshTokenMaxAge']);
        $this->assertEquals(Utils::TEST_REFRESH_TOKEN_PATH_KEY_VALUE, $responseData1['refreshTokenPath']);
        $this->assertEquals($expectedSameSite, $responseData1['accessTokenSameSite']);
        $this->assertEquals($expectedSameSite, $responseData1['refreshTokenSameSite']);
        $this->assertEquals($expectedSameSite, $responseData1['idRefreshTokenSameSite']);
        $this->assertEquals($responseData1['idRefreshToken'].';'.$responseData1['idRefreshTokenExpiry'], substr($responseData1['idRefreshTokenFromHeader'], 0, -3)); // doing substring as expiry extracted from cookie will not include milliseconds part
        $this->assertEquals(Utils::ACCESS_CONTROL_EXPOSE_HEADER_ANTI_CSRF_DISABLE, $responseData1['accessControlExposeHeader']);
        $this->assertNull($responseData1['antiCsrf']);

        $request2 = new Request([], [], [], [
            'sAccessToken' => $responseData1['accessToken'],
            'sIdRefreshToken' => $responseData1['idRefreshToken']
        ]);
        $response2 = new Response();
        SuperTokens::getSession($request2, $response2, false);
        $responseData2 = Utils::extractInfoFromResponse($response1);
        $this->assertEquals($responseData1['accessToken'], $responseData2['accessToken']);
        $this->assertEquals($responseData1['refreshToken'], $responseData2['refreshToken']);
        $this->assertEquals($responseData1['idRefreshToken'], $responseData2['idRefreshToken']);
        $this->assertEquals($responseData1['antiCsrf'], $responseData2['antiCsrf']);

        $request3 = new Request([], [], [], [
            'sRefreshToken' => $responseData2['refreshToken']
        ]);
        $response3 = new Response();
        SuperTokens::refreshSession($request3, $response3);
        $responseData3 = Utils::extractInfoFromResponse($response3);
        $this->assertNotEquals($responseData2['accessToken'], $responseData3['accessToken']);
        $this->assertNotEquals($responseData2['refreshToken'], $responseData3['refreshToken']);
        $this->assertNotEquals($responseData2['idRefreshToken'], $responseData3['idRefreshToken']);
        $this->assertEquals($responseData2['antiCsrf'], $responseData3['antiCsrf']);
        $this->assertEquals(Utils::TEST_ACCESS_TOKEN_MAX_AGE_VALUE, $responseData3['accessTokenMaxAge']);
        $this->assertEquals(Utils::TEST_ACCESS_TOKEN_PATH_VALUE, $responseData3['accessTokenPath']);
        $this->assertEquals(Utils::TEST_COOKIE_DOMAIN_VALUE, $responseData3['accessTokenDomain']);
        $this->assertEquals(Utils::TEST_COOKIE_DOMAIN_VALUE, $responseData3['refreshTokenDomain']);
        $this->assertEquals(Utils::TEST_COOKIE_DOMAIN_VALUE, $responseData3['idRefreshTokenDomain']);
        $this->assertEquals(Utils::TEST_COOKIE_SECURE_VALUE, $responseData3['accessTokenSecure']);
        $this->assertEquals(Utils::TEST_COOKIE_SECURE_VALUE, $responseData3['refreshTokenSecure']);
        $this->assertEquals(Utils::TEST_COOKIE_SECURE_VALUE, $responseData3['idRefreshTokenSecure']);
        $this->assertEquals(true, $responseData3['accessTokenHttpOnly']);
        $this->assertEquals(true, $responseData3['refreshTokenHttpOnly']);
        $this->assertEquals(true, $responseData3['idRefreshTokenHttpOnly']);
        $this->assertEquals(Utils::TEST_REFRESH_TOKEN_MAX_AGE_VALUE * 60, $responseData3['refreshTokenMaxAge']);
        $this->assertEquals(Utils::TEST_REFRESH_TOKEN_PATH_KEY_VALUE, $responseData3['refreshTokenPath']);
        $this->assertEquals($expectedSameSite, $responseData3['accessTokenSameSite']);
        $this->assertEquals($expectedSameSite, $responseData3['refreshTokenSameSite']);
        $this->assertEquals($expectedSameSite, $responseData3['idRefreshTokenSameSite']);
        $this->assertEquals($responseData3['idRefreshToken'].';', substr($responseData3['idRefreshTokenFromHeader'], 0, -13)); // doing substring as expiry extracted from cookie will not include milliseconds part
        $expiryTime = (int)(substr($responseData3['idRefreshTokenFromHeader'], -13, -3));
        $c1 = $responseData3['idRefreshTokenExpiry'] === $expiryTime;
        $c2 = ($responseData3['idRefreshTokenExpiry'] + 1) === $expiryTime;
        $c3 = $responseData3['idRefreshTokenExpiry'] === ($expiryTime + 1);
        $this->assertTrue($c1 || $c2 || $c3);
        $this->assertEquals(Utils::ACCESS_CONTROL_EXPOSE_HEADER_ANTI_CSRF_DISABLE, $responseData3['accessControlExposeHeader']);
        $this->assertNull($responseData3['antiCsrf']);

        $request4 = new Request([], [], [], [
            'sAccessToken' => $responseData3['accessToken'],
            'sIdRefreshToken' => $responseData3['idRefreshToken']
        ]);
        $response4 = new Response();
        SuperTokens::getSession($request4, $response4, false);
        $responseData4 = Utils::extractInfoFromResponse($response4);
        $this->assertNotEquals($responseData3['accessToken'], $responseData4['accessToken']);
        $this->assertNotEquals($responseData1['accessToken'], $responseData4['accessToken']);
        $this->assertEquals(Utils::TEST_ACCESS_TOKEN_MAX_AGE_VALUE, $responseData4['accessTokenMaxAge']);
        $this->assertEquals(Utils::TEST_ACCESS_TOKEN_PATH_VALUE, $responseData4['accessTokenPath']);
        $this->assertEquals(Utils::TEST_COOKIE_DOMAIN_VALUE, $responseData4['accessTokenDomain']);
        $this->assertEquals(Utils::TEST_COOKIE_SECURE_VALUE, $responseData4['accessTokenSecure']);
        $this->assertEquals(true, $responseData4['accessTokenHttpOnly']);
        $this->assertEquals($expectedSameSite, $responseData4['accessTokenSameSite']);

        $request5 = new Request([], [], [], [
            'sAccessToken' => $responseData4['accessToken'],
            'sIdRefreshToken' => $responseData3['idRefreshToken']
        ]);
        $response5 = new Response();
        $session = SuperTokens::getSession($request5, $response5, true);
        $responseData5 = Utils::extractInfoFromResponse($response5);
        $this->assertNull($responseData5['accessTokenCookie']);
        $this->assertNull($responseData5['refreshTokenCookie']);
        $this->assertNull($responseData5['idRefreshTokenCookie']);
        $this->assertNull($responseData5['antiCsrf']);

        $session->revokeSession();
        $responseData5 = Utils::extractInfoFromResponse($response5);
        $this->assertEquals(0, $responseData5['accessTokenExpiry']);
        $this->assertEquals("", $responseData5['accessToken']);
        $this->assertEquals("", $responseData5['refreshToken']);
        $this->assertEquals("", $responseData5['idRefreshToken']);
        $this->assertEquals(Utils::TEST_ACCESS_TOKEN_PATH_VALUE, $responseData5['accessTokenPath']);
        $this->assertEquals(Utils::TEST_COOKIE_DOMAIN_VALUE, $responseData5['accessTokenDomain']);
        $this->assertEquals(Utils::TEST_COOKIE_DOMAIN_VALUE, $responseData5['refreshTokenDomain']);
        $this->assertEquals(Utils::TEST_COOKIE_DOMAIN_VALUE, $responseData5['idRefreshTokenDomain']);
        $this->assertEquals(Utils::TEST_COOKIE_SECURE_VALUE, $responseData5['accessTokenSecure']);
        $this->assertEquals(Utils::TEST_COOKIE_SECURE_VALUE, $responseData5['refreshTokenSecure']);
        $this->assertEquals(Utils::TEST_COOKIE_SECURE_VALUE, $responseData5['idRefreshTokenSecure']);
        $this->assertEquals(true, $responseData5['accessTokenHttpOnly']);
        $this->assertEquals(true, $responseData5['refreshTokenHttpOnly']);
        $this->assertEquals(true, $responseData5['idRefreshTokenHttpOnly']);
        $this->assertEquals(0, $responseData5['refreshTokenExpiry']);
        $this->assertEquals(Utils::TEST_REFRESH_TOKEN_PATH_KEY_VALUE, $responseData5['refreshTokenPath']);
        $this->assertEquals($expectedSameSite, $responseData5['accessTokenSameSite']);
        $this->assertEquals($expectedSameSite, $responseData5['refreshTokenSameSite']);
        $this->assertEquals($expectedSameSite, $responseData5['idRefreshTokenSameSite']);
        $this->assertEquals("remove", $responseData5['idRefreshTokenFromHeader']);
        $this->assertNull($responseData5['antiCsrf']);
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
        SuperTokens::createNewSession($response1, "userId", [], []);
        $responseData1 = Utils::extractInfoFromResponse($response1);
        $this->assertNotNull($responseData1["idRefreshTokenFromHeader"]);
        $this->assertNotNull($responseData1["accessToken"]);
        $this->assertNotNull($responseData1["refreshToken"]);
        $this->assertNotNull($responseData1["idRefreshToken"]);
        $this->assertNotNull($responseData1["antiCsrf"]);
        $this->assertTrue($responseData1["accessTokenCookie"]->getPath() === "/");
        $this->assertContains($responseData1["accessTokenCookie"]->getDomain(), ["localhost", "supertokens.io"]);
        $this->assertTrue($responseData1["accessTokenCookie"]->getSameSite() === "none");
        $this->assertTrue($responseData1["accessTokenCookie"]->isHttpOnly());
        $this->assertTrue(!$responseData1["accessTokenCookie"]->isSecure());

        $this->assertTrue($responseData1["refreshTokenCookie"]->getPath() === "/refresh");
        $this->assertContains($responseData1["refreshTokenCookie"]->getDomain(), ["localhost", "supertokens.io"]);
        $this->assertTrue($responseData1["refreshTokenCookie"]->getSameSite() === "none");
        $this->assertTrue($responseData1["refreshTokenCookie"]->isHttpOnly());
        $this->assertTrue(!$responseData1["refreshTokenCookie"]->isSecure());

        $this->assertTrue($responseData1["idRefreshTokenCookie"]->getPath() === "/");
        $this->assertContains($responseData1["idRefreshTokenCookie"]->getDomain(), ["localhost", "supertokens.io"]);
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

        self::assertFalse(SessionHandlingFunctions::$TEST_SERVICE_CALLED);

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
        $this->assertContains($responseData2["accessTokenCookie"]->getDomain(), ["localhost", "supertokens.io"]);
        $this->assertTrue($responseData2["accessTokenCookie"]->getSameSite() === "none");
        $this->assertTrue($responseData2["accessTokenCookie"]->isHttpOnly());
        $this->assertTrue(!$responseData2["accessTokenCookie"]->isSecure());

        $this->assertTrue($responseData2["refreshTokenCookie"]->getPath() === "/refresh");
        $this->assertContains($responseData2["refreshTokenCookie"]->getDomain(), ["localhost", "supertokens.io"]);
        $this->assertTrue($responseData2["refreshTokenCookie"]->getSameSite() === "none");
        $this->assertTrue($responseData2["refreshTokenCookie"]->isHttpOnly());
        $this->assertTrue(!$responseData2["refreshTokenCookie"]->isSecure());

        $this->assertTrue($responseData2["idRefreshTokenCookie"]->getPath() === "/");
        $this->assertContains($responseData2["idRefreshTokenCookie"]->getDomain(), ["localhost", "supertokens.io"]);
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
        $this->assertContains($responseData3["accessTokenCookie"]->getDomain(), ["localhost", "supertokens.io"]);
        $this->assertTrue($responseData3["accessTokenCookie"]->getSameSite() === "none");
        $this->assertTrue($responseData3["accessTokenCookie"]->isHttpOnly());
        $this->assertTrue(!$responseData3["accessTokenCookie"]->isSecure());

        $this->assertNull($responseData3["idRefreshTokenFromHeader"]);
        $this->assertNull($responseData3["refreshToken"]);
        $this->assertNull($responseData3["idRefreshToken"]);
        $this->assertNull($responseData3["antiCsrf"]);
        self::assertTrue(SessionHandlingFunctions::$TEST_SERVICE_CALLED);

        $request5 = new Request([], [], [], [
            'sAccessToken' => $responseData3['accessToken'],
            'sIdRefreshToken' => $responseData2['idRefreshToken']
        ]);
        $request5->headers->set("anti-csrf", $responseData2['antiCsrf']);
        $response5 = new Response();
        $session = SuperTokens::getSession($request5, $response5, true);
        self::assertFalse(SessionHandlingFunctions::$TEST_SERVICE_CALLED);

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
    public function testSupertokensManipulatingJwtData(): void
    {
        Utils::startST();
        if (Querier::getInstance()->getApiVersion() !== "1.0") {
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
            try {
                $session1->updateJWTPayload(["key" => "value"], null);
                $this->assertTrue(false);
            } catch (\Throwable $e) {
                $this->assertTrue(true);
            }

            $session1->updateJWTPayload(["key" => "value"], $response2);

            $request3 = new Request([], [], [], [
                'sAccessToken' => $session1->getAccessToken(),
                'sIdRefreshToken' => $responseData1['idRefreshToken']
            ]);
            $request3->headers->set("anti-csrf", $responseData1['antiCsrf']);
            $response3 = new Response();
            $session2 = SuperTokens::getSession($request3, $response3, true);
            $jwtData1 = $session2->getJWTPayload();
            $this->assertArrayHasKey("key", $jwtData1);
            $this->assertEquals("value", $jwtData1["key"]);

            $request4 = new Request([], [], [], [
                'sAccessToken' => $session2->getAccessToken(),
                'sIdRefreshToken' => $responseData1['idRefreshToken']
            ]);
            $request4->headers->set("anti-csrf", $responseData1['antiCsrf']);
            $response4 = new Response();
            $session3 = SuperTokens::getSession($request4, $response4, true);
            $session3->updateJWTPayload(["key" => "value2"], $response4);
            $request5 = new Request([], [], [], [
                'sAccessToken' => $session3->getAccessToken(),
                'sIdRefreshToken' => $responseData1['idRefreshToken']
            ]);
            $request5->headers->set("anti-csrf", $responseData1['antiCsrf']);
            $response5 = new Response();
            $session4 = SuperTokens::getSession($request5, $response5, true);
            $jwtData2 = $session4->getJWTPayload();
            $this->assertArrayHasKey("key", $jwtData2);
            $this->assertEquals("value2", $jwtData2["key"]);

            try {
                SuperTokens::updateJWTPayload("invalidSessionHandle", ["key" => "value"]);
                $this->assertTrue(false);
            } catch (SuperTokensUnauthorisedException $e) {
                $this->assertTrue(true);
            }
        } else {
            $response1 = new Response();
            SuperTokens::createNewSession($response1, "userId", ["key1" => "value1"], []);
            $responseData1 = Utils::extractInfoFromResponse($response1);

            $request2 = new Request([], [], [], [
                'sAccessToken' => $responseData1['accessToken'],
                'sIdRefreshToken' => $responseData1['idRefreshToken']
            ]);
            $request2->headers->set("anti-csrf", $responseData1['antiCsrf']);
            $response2 = new Response();
            $session1 = SuperTokens::getSession($request2, $response2, true);

            try {
                $session1->updateJWTPayload(["key2" => "value2"], $response2);
                $this->assertTrue(false);
            } catch (SuperTokensGeneralException $e) {
                $this->assertTrue(true);
            }

            $jwtData1 = $session1->getJWTPayload();
            $this->assertArrayHasKey("key1", $jwtData1);
            $this->assertEquals("value1", $jwtData1["key1"]);
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

    public function testSetOptionsHeadersApi(): void
    {
        $response = new Response();
        SuperTokens::setRelevantHeadersForOptionsAPI($response);
        $this->assertEquals("anti-csrf, supertokens-sdk-name, supertokens-sdk-version", $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertEquals("true", $response->headers->get('Access-Control-Allow-Credentials'));
    }
}
