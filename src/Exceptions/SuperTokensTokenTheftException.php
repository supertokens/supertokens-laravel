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
namespace SuperTokens\Exceptions;

use Exception;

/**
 * Class SuperTokensTokenTheftException
 * @package SuperTokens\Laravel\Exceptions
 */
class SuperTokensTokenTheftException extends SuperTokensException
{
    private $userId;

    private $sessionHandle;

    /**
     * SuperTokensTryRefreshTokenException constructor.
     * @param $userId
     * @param $sessionHandle
     */
    public function __construct($userId, $sessionHandle)
    {
        $message = "Token Theft Detected";
        parent::__construct($message);
        $this->userId = $userId;
        $this->sessionHandle = $sessionHandle;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function getSessionHandle()
    {
        return $this->sessionHandle;
    }
}
