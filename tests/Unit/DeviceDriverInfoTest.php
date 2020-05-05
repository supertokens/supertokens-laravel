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
use SuperTokens\Exceptions\SuperTokensTryRefreshTokenException;
use SuperTokens\Exceptions\SuperTokensUnauthorisedException;
use SuperTokens\Helpers\Constants;
use SuperTokens\Helpers\DeviceInfo;
use SuperTokens\Helpers\Querier;
use SuperTokens\SuperTokens;

class DeviceDriverInfoTest extends TestCase
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
    public function testDriverInfoCheckWithoutFrontendSdk(): void
    {
        Utils::startST();
        $requestBody = Querier::getInstance()->sendPostRequest(Constants::SESSION, ["userId" => "testing"], true);
        $this->assertEquals("testing", $requestBody["userId"]);
        $this->assertIsArray($requestBody["deviceDriverInfo"]);
        $this->assertEquals("laravel", $requestBody["deviceDriverInfo"]["driver"]["name"]);
        $this->assertEquals(Constants::VERSION, $requestBody["deviceDriverInfo"]["driver"]["version"]);
        $this->assertEmpty($requestBody["deviceDriverInfo"]["frontendSDK"]);
        $requestBody = Querier::getInstance()->sendPostRequest(Constants::HELLO, ["userId" => "testing"], true);
        $this->assertEquals("testing", $requestBody["userId"]);
        $this->assertArrayNotHasKey("deviceDriverInfo", $requestBody);
    }

    /**
     * @throws SuperTokensGeneralException
     * @throws SuperTokensTryRefreshTokenException
     * @throws SuperTokensUnauthorisedException
     * @throws Exception
     */
    public function testDriverInfoCheckWithFrontendSdk(): void
    {
        Utils::startST();
        $response = new Response();
        SuperTokens::createNewSession($response, "testing", [], []);
        $responseInfo = Utils::extractInfoFromResponse($response);
        $request1 = new Request([], [], [], [
            'sAccessToken' => $responseInfo['accessToken'],
            'sIdRefreshToken' => $responseInfo['idRefreshToken']
        ]);
        $request1->headers->set("anti-csrf", $responseInfo['antiCsrf']);
        $request1->headers->set("supertokens-sdk-name", 'ios');
        $request1->headers->set("supertokens-sdk-version", '0.0.0');
        SuperTokens::getSession($request1, new Response(), true);

        $request2 = new Request([], [], [], [
            'sAccessToken' => $responseInfo['accessToken'],
            'sIdRefreshToken' => $responseInfo['idRefreshToken']
        ]);
        $request2->headers->set("anti-csrf", $responseInfo['antiCsrf']);
        SuperTokens::getSession($request2, new Response(), true);

        $request1 = new Request([], [], [], [
            'sAccessToken' => $responseInfo['accessToken'],
            'sIdRefreshToken' => $responseInfo['idRefreshToken']
        ]);
        $request1->headers->set("anti-csrf", $responseInfo['antiCsrf']);
        $request1->headers->set("supertokens-sdk-name", 'android');
        $request1->headers->set("supertokens-sdk-version", Constants::VERSION);
        SuperTokens::getSession($request1, new Response(), true);

        $frontendSDKs = DeviceInfo::getInstance()->getFrontendSDKs();
        $found = 0;
        foreach ($frontendSDKs as $frontendSDK) {
            if ($frontendSDK['name'] === 'ios' && $frontendSDK['version'] === '0.0.0') {
                $found++;
            } elseif ($frontendSDK['name'] === 'android' && $frontendSDK['version'] === Constants::VERSION) {
                $found++;
            }
        }
        $this->assertEquals(2, $found);
    }
}
