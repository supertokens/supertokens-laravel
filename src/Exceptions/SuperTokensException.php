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
namespace SuperTokens\Session\Exceptions;

use Exception;
use Throwable;

/**
 * Class SuperTokensException
 * @package SuperTokens\Laravel\Exceptions
 */
abstract class SuperTokensException extends Exception
{

    /**
     * SuperTokensException constructor.
     * @param string $message
     * @param Throwable|null $previous
     */
    public function __construct($message = "", Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function generateGeneralException($anything, Throwable $previous = null)
    {
        if ($anything instanceof SuperTokensException) {
            return $anything;
        }
        return new SuperTokensGeneralException($anything, $previous);
    }

    public static function generateUnauthorisedException($anything)
    {
        if ($anything instanceof SuperTokensException) {
            return $anything;
        }
        return new SuperTokensUnauthorizedException($anything);
    }

    public static function generateTryRefreshTokenException($anything)
    {
        if ($anything instanceof SuperTokensException) {
            return $anything;
        }
        return new SuperTokensTryRefreshTokenException($anything);
    }

    public static function generateTokenTheftException($userId, $sessionHandle)
    {
        return new SuperTokensTokenTheftException($userId, $sessionHandle);
    }
}
