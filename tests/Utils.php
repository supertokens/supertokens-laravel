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
use SuperTokens\Helpers\DeviceInfo;
use SuperTokens\Helpers\HandshakeInfo;
use SuperTokens\Helpers\Querier;
use SuperTokens\SessionHandlingFunctions;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class Utils
{
    const LICENSE_FILE_PATH = "/licenseKey";
    const CONFIG_YAML_FILE_PATH = "/config.yaml";
    const ORIGINAL_LICENSE_FILE_PATH = "/temp/licenseKey";
    const ORIGINAL_CONFIG_YAML_FILE_PATH = "/temp/config.yaml";
    const SUPERTOKENS_PROCESS_DIR = "/.started";
    const ENABLE_ANTI_CSRF_CONFIG_KEY = "enable_anti_csrf";
    const WEB_SERVER_TEMP_DIR = "/.webserver-temp-*";
    const SIGTERM = 15;
    const API_VERSION_TEST_NON_SUPPORTED_SV = ["0.0", "1.0", "1.1", "2.1"];
    const API_VERSION_TEST_NON_SUPPORTED_CV = ["0.1", "0.2", "1.2", "2.0", "3.0"];
    const API_VERSION_TEST_MULTIPLE_SUPPORTED_SV = ["0.0", "1.0", "1.1", "2.1"];
    const API_VERSION_TEST_MULTIPLE_SUPPORTED_CV = ["0.1", "0.2", "1.1", "2.1", "3.0"];
    const API_VERSION_TEST_MULTIPLE_SUPPORTED_RESULT = "2.1";
    const API_VERSION_TEST_SINGLE_SUPPORTED_SV = ["0.0", "1.0", "1.1", "2.0"];
    const API_VERSION_TEST_SINGLE_SUPPORTED_CV = ["0.1", "0.2", "1.1", "2.1", "3.0"];
    const API_VERSION_TEST_SINGLE_SUPPORTED_RESULT = "1.1";
    const API_VERSION_TEST_BASIC_RESULT = "2.0";
    const SUPPORTED_CORE_DRIVER_INTERFACE_FILE = "./coreDriverInterfaceSupported.json";

    /**
     * @param string $key
     * @param string $value
     */
    public static function setKeyValueInConfig($key, $value)
    {
        $installationPath = env("SUPERTOKENS_PATH");
        $configYamlFilePath = $installationPath.self::CONFIG_YAML_FILE_PATH;
        $configData = Yaml::parse(file_get_contents($configYamlFilePath));
        $configData[$key] = $value;
        $updatedConfig = Yaml::dump($configData);
        file_put_contents($configYamlFilePath, $updatedConfig);
    }

    public static function setupST()
    {
        $installationPath = env("SUPERTOKENS_PATH");
        $licenseFilePath = $installationPath.self::LICENSE_FILE_PATH;
        $configYamlFilePath = $installationPath.self::CONFIG_YAML_FILE_PATH;
        $originalLicenseFilePath = $installationPath.self::ORIGINAL_LICENSE_FILE_PATH;
        $originalConfigYamlFilePath = $installationPath.self::ORIGINAL_CONFIG_YAML_FILE_PATH;
        copy($originalLicenseFilePath, $licenseFilePath);
        copy($originalConfigYamlFilePath, $configYamlFilePath);
    }

    public static function cleanST()
    {
        $installationPath = env("SUPERTOKENS_PATH");
        $licenseFilePath = $installationPath.self::LICENSE_FILE_PATH;
        $configYamlFilePath = $installationPath.self::CONFIG_YAML_FILE_PATH;
        $processDir = $installationPath.self::SUPERTOKENS_PROCESS_DIR;
        $webServerTempDir = $installationPath.self::WEB_SERVER_TEMP_DIR;
        try {
            unlink($licenseFilePath);
            unlink($configYamlFilePath);
            self::rmrf($processDir);
            self::rmrf($webServerTempDir);
        } catch (Exception $e) {
        }
    }

    /**
     * @param int $try
     * @throws Exception
     */
    private static function stopST($try=50)
    {
        $pIds = self::getListOfProcessIds();
        foreach ($pIds as $pId) {
            posix_kill($pId, self::SIGTERM);
        }
        $pIds = self::getListOfProcessIds();
        if (count($pIds) !== 0) {
            if ($try === 0) {
                throw new Exception("error in stopping processes: ".implode($pIds));
            }
            usleep(250 * 1000);
            self::stopST($try-1);
        }
    }

    /**
     * @param string $dir
     */
    private static function rmrf($dir)
    {
        foreach (glob($dir) as $file) {
            if (is_dir($file)) {
                self::rmrf("$file/*");
                rmdir($file);
            } else {
                unlink($file);
            }
        }
    }

    /**
     * @param string $host
     * @param int $port
     * @throws Exception
     */
    public static function startST($host = "localhost", $port = 3567)
    {
        $installationPath = env("SUPERTOKENS_PATH");
        $process = Process::fromShellCommandline('java -Djava.security.egd=file:/dev/urandom -classpath "./core/*:./plugin-interface/*" io.supertokens.Main ./ DEV host='.$host.' port='.$port.' &');
        $process->setWorkingDirectory($installationPath);
        $process->disableOutput();
        $pIdsBefore = self::getListOfProcessIds();
        $process->run();
        $pIdsAfter = $pIdsBefore;
        /**
         * wait for 5 seconds before throwing error that the supertokens service did not start
         * check will be made every 250ms
         */
        for ($i = 0; $i < 20; $i++) {
            $pIdsAfter = self::getListOfProcessIds();
            if (count($pIdsAfter) !== count($pIdsBefore)) {
                break;
            }
            usleep(500 * 1000);
        }
        if (count($pIdsAfter) === count($pIdsBefore)) {
            throw new Exception("could not start ST process");
        }
    }

    /**
     * @return array
     */
    public static function getListOfProcessIds()
    {
        $installationPath = env("SUPERTOKENS_PATH");
        $processDir = $installationPath.self::SUPERTOKENS_PROCESS_DIR.'/';
        $pIds = [];
        try {
            $processes = scandir($processDir);
            foreach ($processes as $dir) {
                if ($dir === "." || $dir === "..") {
                    continue;
                }
                array_push($pIds, file_get_contents($processDir.$dir));
            }
        } catch (Exception $e) {
            // file dir reading error, because path doesn't exists
        }
        return $pIds;
    }

    /**
     * @throws SuperTokensException
     * @throws SuperTokensGeneralException
     */
    public static function reset()
    {
        self::stopST();
        HandshakeInfo::reset();
        DeviceInfo::reset();
        Querier::reset();
        SessionHandlingFunctions::reset();
    }

    /**
     * @param Response $response
     * @return array
     */
    public static function extractInfoFromResponse(Response $response)
    {
        $accessToken = null;
        $refreshToken = null;
        $idRefreshToken = null;
        $antiCsrfToken = $response->headers->get('anti-csrf');
        $idRefreshTokenFromHeader = $response->headers->get('id-refresh-token');
        $accessTokenExpiry = null;
        $refreshTokenExpiry = null;
        $idRefreshTokenExpiry = null;
        $accessTokenCookie = null;
        $refreshTokenCookie = null;
        $idRefreshTokenCookie = null;

        $cookies = $response->headers->getCookies();

        for ($i = 0; $i < count($cookies); $i++) {
            $cookie = $cookies[$i];
            $cookieName = $cookie->getName();
            $cookieValue = $cookie->getValue();
            $cookieExpiry = $cookie->getExpiresTime();

            if ($cookieName === "sAccessToken") {
                $accessToken = $cookieValue;
                $accessTokenExpiry = $cookieExpiry;
                $accessTokenCookie = $cookie;
            } elseif ($cookieName === "sRefreshToken") {
                $refreshToken = $cookieValue;
                $refreshTokenExpiry = $cookieExpiry;
                $refreshTokenCookie = $cookie;
            } elseif ($cookieName === "sIdRefreshToken") {
                $idRefreshToken = $cookieValue;
                $idRefreshTokenExpiry = $cookieExpiry;
                $idRefreshTokenCookie = $cookie;
            }
        }
        return [
            'antiCsrf' => $antiCsrfToken,
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
            'idRefreshTokenFromHeader' => $idRefreshTokenFromHeader,
            'idRefreshToken' => $idRefreshToken,
            'accessTokenExpiry' => $accessTokenExpiry,
            'refreshTokenExpiry' => $refreshTokenExpiry,
            'idRefreshTokenExpiry' => $idRefreshTokenExpiry,
            'accessTokenCookie' => $accessTokenCookie,
            'refreshTokenCookie' => $refreshTokenCookie,
            'idRefreshTokenCookie' => $idRefreshTokenCookie

        ];
    }
}
