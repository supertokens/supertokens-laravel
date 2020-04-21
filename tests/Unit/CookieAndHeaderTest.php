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

    public function testSetCookie()
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

    public function testGetCookie()
    {
        $key = 'test';
        $value = 'value';

        $request = new Request([], [], [], [$key => $value]);

        $cookieValue = CookieAndHeader::getCookie($request, $key);
        $this->assertEquals($cookieValue, $value);
    }
}
