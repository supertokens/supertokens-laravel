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

use Illuminate\Support\Facades\App;
use SuperTokens\Exceptions\SuperTokensException;
use SuperTokens\Exceptions\SuperTokensGeneralException;

// TODO: Do we also need to use caching for this?
class DeviceInfo
{
    /**
     * @var DeviceInfo
     */
    private static $instance;

    /**
     * @var array
     */
    private $frontendSDK;

    private function __construct()
    {
        $this->frontendSDK = [];
    }

    /**
     * @return DeviceInfo
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new DeviceInfo();
        }
        return self::$instance;
    }

    /**
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
     * @return array
     */
    public function getFrontendSDKs()
    {
        return $this->frontendSDK;
    }

    /**
     * @param $sdk
     */
    public function addToFrontendSDKs($sdk)
    {
        $alreadyExists = false;
        for ($i = 0; $i < count($this->frontendSDK); $i++) {
            if ($this->frontendSDK[$i]['name'] == $sdk['name'] && $this->frontendSDK[$i]['version'] == $sdk['version']) {
                $alreadyExists = true;
                break;
            }
        }
        if (!$alreadyExists) {
            $this->frontendSDK[] = $sdk;
        }
    }
}
