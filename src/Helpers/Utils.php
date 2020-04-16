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
    public static function getCurrentTimestamp()
    {
        $date = new DateTime();
        return $date->getTimestamp();
    }
}
