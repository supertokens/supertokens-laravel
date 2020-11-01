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

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SuperTokens\Helpers\CookieAndHeader;
use SuperTokens\Helpers\Querier;

class CookieAndHeaderTest extends TestCase
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

    public function testSetSingleCookie()
    {
        $response = new Response();

        $key = 'test';
        $value = 'value';
        $domain = 'localhost';
        $secure = false;
        $httpOnly = false;
        $path = '/';
        $minutes = 10;
        $sameSite = "none";
        
        CookieAndHeader::setCookie($response, $key, $value, $minutes, $path, $domain, $secure, $httpOnly, $sameSite);

        $this->assertIsObject($response->headers);
        $this->assertIsArray($response->headers->getCookies());
        $this->assertCount(1, $response->headers->getCookies());
        $this->assertIsObject($response->headers->getCookies()[0]);
        $cookie = $response->headers->getCookies()[0];

        $this->assertEquals($key, $cookie->getName());
        $this->assertEquals($value, $cookie->getValue());
        $this->assertEquals($domain, $cookie->getDomain());
        $this->assertEquals($cookie->getPath(), $path);
        $this->assertEquals($secure, $cookie->isSecure());
        $this->assertEquals($httpOnly, $cookie->isHttpOnly());
        $this->assertEquals($sameSite, $cookie->getSameSite());
    }

    public function testSetMultipleCookies()
    {
        $response = new Response();

        $key1 = 'test1';
        $value1 = 'value1';
        $key2 = 'test2';
        $value2 = 'value2';
        $value3 = 'value3';
        $domain = 'localhost';
        $secure = false;
        $httpOnly = false;
        $path = '/';
        $minutes = 10;
        $sameSite = "none";
        
        CookieAndHeader::setCookie($response, $key1, $value1, $minutes, $path, $domain, $secure, $httpOnly, $sameSite);
        CookieAndHeader::setCookie($response, $key2, $value2, $minutes, $path, $domain, $secure, $httpOnly, $sameSite);

        $this->assertIsObject($response->headers);
        $this->assertIsArray($response->headers->getCookies());
        $this->assertCount(2, $response->headers->getCookies());
        $this->assertIsObject($response->headers->getCookies()[0]);
        $this->assertIsObject($response->headers->getCookies()[1]);

        //check the first cookie
        $cookie1 = $response->headers->getCookies()[0];

        $this->assertEquals($key1, $cookie1->getName());
        $this->assertEquals($value1, $cookie1->getValue());
        $this->assertEquals($domain, $cookie1->getDomain());
        $this->assertEquals($cookie1->getPath(), $path);
        $this->assertEquals($secure, $cookie1->isSecure());
        $this->assertEquals($httpOnly, $cookie1->isHttpOnly());
        $this->assertEquals($sameSite, $cookie1->getSameSite());

        //check the second cookie
        $cookie2 = $response->headers->getCookies()[1];

        $this->assertEquals($key2, $cookie2->getName());
        $this->assertEquals($value2, $cookie2->getValue());
        $this->assertEquals($domain, $cookie2->getDomain());
        $this->assertEquals($cookie2->getPath(), $path);
        $this->assertEquals($secure, $cookie2->isSecure());
        $this->assertEquals($httpOnly, $cookie2->isHttpOnly());
        $this->assertEquals($sameSite, $cookie2->getSameSite());

        //overwrite the second cookie and check its value has changed
        CookieAndHeader::setCookie($response, $key2, $value3, $minutes, $path, $domain, $secure, $httpOnly, $sameSite);

        $this->assertIsArray($response->headers->getCookies());
        $this->assertCount(2, $response->headers->getCookies());

        $overwritten_cookie = $response->headers->getCookies()[1];

        $this->assertEquals($key2, $overwritten_cookie->getName());
        $this->assertEquals($value3, $overwritten_cookie->getValue());
    }

    public function testSetCookiesMultipleTimes()
    {
        $response = new Response();

        $key = 'test';
        $value1 = 'value1';
        $value2 = 'value2';
        $domain = 'localhost';
        $secure = false;
        $httpOnly = false;
        $path = '/';
        $minutes = 10;
        $sameSite = "none";
        
        CookieAndHeader::setCookie($response, $key, $value1, $minutes, $path, $domain, $secure, $httpOnly, $sameSite);
        CookieAndHeader::setCookie($response, $key, $value2, $minutes, $path, $domain, $secure, $httpOnly, $sameSite);

        $this->assertIsObject($response->headers);
        $this->assertIsArray($response->headers->getCookies());
        $this->assertCount(1, $response->headers->getCookies());
        $this->assertIsObject($response->headers->getCookies()[0]);

        $cookie = $response->headers->getCookies()[0];

        $this->assertEquals($key, $cookie->getName());
        $this->assertEquals($value2, $cookie->getValue());
        $this->assertEquals($domain, $cookie->getDomain());
        $this->assertEquals($cookie->getPath(), $path);
        $this->assertEquals($secure, $cookie->isSecure());
        $this->assertEquals($httpOnly, $cookie->isHttpOnly());
        $this->assertEquals($sameSite, $cookie->getSameSite());
    }

    public function testGetCookie()
    {
        $key = 'test';
        $value = 'value';

        $request = new Request([], [], [], [$key => $value]);

        $cookieValue = CookieAndHeader::getCookie($request, $key);
        $this->assertEquals($cookieValue, $value);
    }

    public function testSetOptionsHeadersApi(): void
    {
        $response = new Response();
        CookieAndHeader::setOptionsAPIHeader($response);
        $this->assertEquals("anti-csrf, supertokens-sdk-name, supertokens-sdk-version, front-token", $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertEquals("true", $response->headers->get('Access-Control-Allow-Credentials'));
    }
}
