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
use Illuminate\Support\Facades\Config;
use SuperTokens\Session\Exceptions\SuperTokensException;
use SuperTokens\Session\Exceptions\SuperTokensGeneralException;
use SuperTokens\Session\Helpers\Constants;
use SuperTokens\Session\Helpers\HandshakeInfo;
use SuperTokens\Session\Helpers\Querier;
use SuperTokens\Session\SessionHandlingFunctions;
use SuperTokens\Session\SuperToken;

class QuerierTest extends TestCase
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
    public function testGetApiVersion(): void
    {
        // no host available
        try {
            Querier::getInstance()->getApiVersion();
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }
        Utils::startST();
        $this->assertEquals(Utils::API_VERSION_TEST_BASIC_RESULT, Querier::getInstance()->getApiVersion());
        Querier::$cv = Utils::API_VERSION_TEST_SINGLE_SUPPORTED_CV;
        Querier::$sv = Utils::API_VERSION_TEST_SINGLE_SUPPORTED_SV;
        $this->assertEquals(Utils::API_VERSION_TEST_SINGLE_SUPPORTED_RESULT, Querier::getInstance()->getApiVersion());
        Querier::$cv = Utils::API_VERSION_TEST_MULTIPLE_SUPPORTED_CV;
        Querier::$sv = Utils::API_VERSION_TEST_MULTIPLE_SUPPORTED_SV;
        $this->assertEquals(Utils::API_VERSION_TEST_MULTIPLE_SUPPORTED_RESULT, Querier::getInstance()->getApiVersion());
        Querier::$cv = Utils::API_VERSION_TEST_NON_SUPPORTED_CV;
        Querier::$sv = Utils::API_VERSION_TEST_NON_SUPPORTED_SV;
        try {
            Querier::getInstance()->getApiVersion();
            $this->assertTrue(false);
        } catch (SuperTokensGeneralException $e) {
            $this->assertEquals(Constants::DRIVER_NOT_COMPATIBLE_MESSAGE, $e->getMessage());
        }
        Querier::reset();
        Querier::$cv = Utils::API_VERSION_TEST_NON_SUPPORTED_CV;
        Querier::$sv = Utils::API_VERSION_TEST_NON_SUPPORTED_SV;
        try {
            SessionHandlingFunctions::createNewSession("userId", [], []);
            $this->assertTrue(false);
        } catch (SuperTokensGeneralException $e) {
            $this->assertEquals(Constants::DRIVER_NOT_COMPATIBLE_MESSAGE, $e->getMessage());
        }
    }

    public function testCheckSupportedCoreDriverInterfaceVersions(): void
    {
        $supportedVersionsInJSON = json_decode(file_get_contents(Utils::SUPPORTED_CORE_DRIVER_INTERFACE_FILE), true)['versions'];
        $this->assertSameSize(Constants::SUPPORTED_CDI_VERSIONS, array_intersect($supportedVersionsInJSON, Constants::SUPPORTED_CDI_VERSIONS));
    }

    /**
     * @throws Exception
     */
    public function testCoreNotAvailable(): void
    {
        try {
            $querier = Querier::getInstance();
            $querier->sendGetRequest("/", []);
            $this->assertTrue(false);
        } catch (SuperTokensException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * @throws Exception
     */
    public function testThreeCoresAndRoundRobin(): void
    {
        Utils::startST();
        Utils::startST("localhost", 3568);
        Utils::startST("localhost", 3569);
        Config::set('supertokens.hosts', [[
            'hostname' => 'localhost',
            'port' => 3567
        ], [
            'hostname' => 'localhost',
            'port' => 3568
        ], [
            'hostname' => 'localhost',
            'port' => 3569
        ]]);
        $querier = Querier::getInstance();
        $this->assertEquals("Hello\n", $querier->sendGetRequest(Constants::HELLO, []));
        $this->assertEquals("Hello\n", $querier->sendPostRequest(Constants::HELLO, []));
        $this->assertEquals("Hello\n", $querier->sendPutRequest(Constants::HELLO, []));
        $this->assertCount(3, $querier->getHostsAliveForTesting());
        $this->assertEquals("Hello\n", $querier->sendDeleteRequest(Constants::HELLO, []));
        $this->assertCount(3, $querier->getHostsAliveForTesting());
        $this->assertTrue(in_array("localhost:3567", $querier->getHostsAliveForTesting()));
        $this->assertTrue(in_array("localhost:3568", $querier->getHostsAliveForTesting()));
        $this->assertTrue(in_array("localhost:3569", $querier->getHostsAliveForTesting()));
    }

    /**
     * @throws Exception
     */
    public function testThreeCoresOneDeadAndRoundRobin(): void
    {
        Utils::startST();
        Utils::startST("localhost", 3568);
        Config::set('supertokens.hosts', [[
            'hostname' => 'localhost',
            'port' => 3567
        ], [
            'hostname' => 'localhost',
            'port' => 3568
        ], [
            'hostname' => 'localhost',
            'port' => 3569
        ]]);
        $querier = Querier::getInstance();
        $this->assertEquals("Hello\n", $querier->sendGetRequest(Constants::HELLO, []));
        $this->assertEquals("Hello\n", $querier->sendPostRequest(Constants::HELLO, []));
        $this->assertCount(2, $querier->getHostsAliveForTesting());
        $this->assertEquals("Hello\n", $querier->sendDeleteRequest(Constants::HELLO, []));
        $this->assertCount(2, $querier->getHostsAliveForTesting());
        $this->assertTrue(in_array("localhost:3567", $querier->getHostsAliveForTesting()));
        $this->assertTrue(in_array("localhost:3568", $querier->getHostsAliveForTesting()));
        $this->assertFalse(in_array("localhost:3569", $querier->getHostsAliveForTesting()));
    }
}
