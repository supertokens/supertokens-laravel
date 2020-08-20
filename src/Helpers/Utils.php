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

use DateTime;
use Illuminate\Support\Facades\Cache;
use Psr\SimpleCache\InvalidArgumentException;

class Utils
{
    /**
     * @param $field
     * @return string|null
     */
    public static function sanitizeStringInput($field)
    {
        if ($field === "") {
            return "";
        }

        if (gettype($field) !== "string") {
            return null;
        }

        return trim($field);
    }

    /**
     * @param $field
     * @return string|null
     */
    public static function sanitizeNumberInput($field)
    {
        $type = gettype($field);
        if ($type === "integer" || $type === "double") {
            return $field;
        }

        if ($type !== "string") {
            return null;
        }

        return number_format(trim($field));
    }

    /**
     * @return int
     */
    public static function getCurrentTimestampMS()
    {
        return (int)round(microtime(true) * 1000);
    }

    /**
     * @param array $versions1
     * @param array $versions2
     * @return string | null
     */
    public static function findMaxVersion($versions1, $versions2)
    {
        $versions = array_values(array_intersect($versions1, $versions2));
        if (empty($versions)) {
            return null;
        }
        $maxV = $versions[0];
        for ($i = 1; $i < count($versions); $i++) {
            $version = $versions[$i];
            $maxV = self::compareVersions($maxV, $version);
        }
        return $maxV;
    }

    /**
     * @param string $v1
     * @param string $v2
     * @return mixed
     */
    public static function compareVersions($v1, $v2)
    {
        $v1Exploded = explode(".", $v1);
        $v2Exploded = explode(".", $v2);
        $maxLoop = min(count($v1Exploded), count($v2Exploded));
        for ($i = 0; $i < $maxLoop; $i++) {
            if ((int)$v1Exploded[$i] > (int)$v2Exploded[$i]) {
                return $v1;
            } elseif ((int)$v2Exploded[$i] > (int)$v1Exploded[$i]) {
                return $v2;
            }
        }
        if (count($v1Exploded) > count($v2Exploded)) {
            return $v1;
        }
        return $v2;
    }

    /**
     * @param string $key
     * @return string|null
     */
    public static function getFromCache(string $key)
    {
        try {
            if (Cache::store("file")->has($key)) {
                return Cache::store("file")->get($key);
            }
            return null;
        } catch (InvalidArgumentException $e) {
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param string $key
     * @param string $value
     * @param int $ttl
     */
    public static function storeInCache(string $key, string $value, int $ttl)
    {
        try {
            Cache::store("file")->put($key, $value, $ttl);
        } catch (\Exception $e) {
        }
    }

//    /**
//     * @param string $key
//     */
//    public static function removeFromCache(string $key)
//    {
//        try {
//            Cache::store("file")->forget($key);
//        } catch (\Exception $e) {
//        }
//    }
}
