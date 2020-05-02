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
namespace SuperTokens\Helpers;

use Exception;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use SuperTokens\Exceptions\SuperTokensException;
use SuperTokens\Exceptions\SuperTokensGeneralException;
use function PHPUnit\Framework\assertEquals;

class Querier
{
    /**
     * @var Querier
     */
    private static $instance;

    /**
     * @var array
     */
    private $hosts;

    /**
     * @var int
     */
    private $lastTriedIndex;

    /**
     * @var array
     */
    private $hostAliveForTesting;

    /**
     * Querier constructor.
     */
    private function __construct()
    {
        $this->hosts = Config::get('supertokens.hosts');
        $this->lastTriedIndex = 0;
        $this->hostAliveForTesting = [];
    }

    /**
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    public static function reset()
    {
        if (App::environment("testing")) {
            self::$instance = null;
        } else {
            throw SuperTokensException::generateGeneralException("calling testing function in non testing env");
        }
    }

    /**
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    public function getHostsAliveForTesting()
    {
        if (App::environment("testing")) {
            return $this->hostAliveForTesting;
        }
        throw SuperTokensException::generateGeneralException("calling testing function in non testing env");
    }

    /**
     * @return Querier
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new Querier();
        }
        if (count(self::$instance->hosts) === 0) {
            throw SuperTokensException::generateGeneralException("Please provide at least one SuperTokens' core address");
        }
        return self::$instance;
    }

    /**
     * @return string
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */

    public function getApiVersion()
    {
        $apiVersion = Utils::getFromCache(Constants::API_VERSION_CACHE_KEY);
        if (is_null($apiVersion)) {
            $coreVersionsResponse = $this->sendRequest(Constants::API_VERSION, "GET", [], function ($url, $data) {
                return Http::get($url);
            });
            $coreVersions = $coreVersionsResponse['versions'];
            $supportedAPIVersions = Constants::SUPPORTED_CDI_VERSIONS;
            $apiVersion = Utils::findMaxVersion($supportedAPIVersions, $coreVersions);
            if (is_null($apiVersion)) {
                throw SuperTokensException::generateGeneralException(Constants::DRIVER_NOT_COMPATIBLE_MESSAGE);
            }
            Utils::storeInCache(Constants::API_VERSION_CACHE_KEY, $apiVersion, Constants::API_VERSION_CACHE_TTL_SECONDS);
            // TODO: No need to do tryHello thing. Just see if the error is from request and is 404, then set it to 1.0
        }
        return $apiVersion;
    }

    /**
     * @param $path
     * @param $body
     * @param boolean $test
     * @return mixed
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    public function sendPostRequest($path, $body, $test=false)
    {
        $pathsToAddDeviceInfoFor = [Constants::SESSION, Constants::SESSION_VERIFY, Constants::SESSION_REFRESH, Constants::HANDSHAKE];
        if (in_array($path, $pathsToAddDeviceInfoFor)) {
            $deviceDriverInfo = [
                'frontendSDK' => DeviceInfo::getInstance()->getFrontendSDKs(),
                'driver' => [
                    'name' => 'laravel',
                    'version' => Constants::VERSION
                ]
            ];
            $body['deviceDriverInfo'] = $deviceDriverInfo;
        }

        if ($test && App::environment("testing")) {
            return $body;
        }

        return $this->sendRequest($path, "POST", $body, function ($url, $data) {
            return Http::withHeaders([
                Constants::API_VERSION_HEADER => $this->getApiVersion()
            ])->post($url, $data);
        });
    }

    /**
     * @param $path
     * @param $body
     * @return mixed
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    public function sendPutRequest($path, $body)
    {
        return $this->sendRequest($path, "PUT", $body, function ($url, $data) {
            return Http::withHeaders([
                Constants::API_VERSION_HEADER => $this->getApiVersion()
            ])->put($url, $data);
        });
    }

    /**
     * @param $path
     * @param $body
     * @return mixed
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    public function sendDeleteRequest($path, $body)
    {
        return $this->sendRequest($path, "DELETE", $body, function ($url, $data) {
            return Http::withHeaders([
                Constants::API_VERSION_HEADER => $this->getApiVersion()
            ])->delete($url, $data);
        });
    }

    /**
     * @param $path
     * @param $query
     * @return mixed
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    public function sendGetRequest($path, $query)
    {
        return $this->sendRequest($path, "GET", $query, function ($url, $data) {
            return Http::withHeaders([
                Constants::API_VERSION_HEADER => $this->getApiVersion()
            ])->get($url, $data);
        });
    }

    /**
     * @param string $path
     * @param string $method
     * @param array $data
     * @param $httpFunction
     * @param int $numberOfRetries
     * @return mixed
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    private function sendRequest($path, $method, $data, $httpFunction, $numberOfRetries = -1)
    {
        if ($numberOfRetries == -1) {
            $numberOfRetries = count($this->hosts);
        }
        if ($numberOfRetries == 0) {
            throw SuperTokensException::generateGeneralException("No SuperTokens core available to query");
        }
        $currentHost = $this->hosts[$this->lastTriedIndex];
        $this->lastTriedIndex += 1;
        $this->lastTriedIndex = $this->lastTriedIndex % count($this->hosts);
        try {
            $response = $httpFunction($currentHost['hostname'] . ":" . $currentHost["port"] . $path, $data);
            if ($response->serverError()) {
                return $this->sendRequest($path, $method, $data, $httpFunction, $numberOfRetries - 1);
            }
            if (App::environment("testing")) {
                array_push($this->hostAliveForTesting, $currentHost['hostname'].':'.$currentHost['port']);
                $this->hostAliveForTesting = array_unique($this->hostAliveForTesting);
            }
            if ($response->clientError()) {
                if ($path === Constants::API_VERSION && $response->status() === 404) {
                    return ["versions" =>["1.0"]];
                }
                throw SuperTokensException::generateGeneralException("SuperTokens core threw an error for a " . $method . " request to path: '" . $path . "' with status code: " . $response->status() . " and message: " . $response->body());
            }
            $responseData = $response->json();
            if (is_null($responseData)) {
                return $response->body();
            }
            return $responseData;
        } catch (ConnectionException $e) { //phpstorm might say to remove this catch clause, but don't!!
            if (App::environment("testing") && $numberOfRetries === 1) {
                throw SuperTokensException::generateGeneralException($e);
            }
            return $this->sendRequest($path, $method, $data, $httpFunction, $numberOfRetries - 1);
        } catch (RequestException $e) {
            if (App::environment("testing") && $numberOfRetries === 1) {
                throw SuperTokensException::generateGeneralException($e);
            }
            return $this->sendRequest($path, $method, $data, $httpFunction, $numberOfRetries - 1);
        } catch (Exception $e) {
            throw SuperTokensException::generateGeneralException($e);
        }
    }
}
