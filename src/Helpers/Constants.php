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

class Constants
{
    const VERSION = '0.2.0';
    const API_VERSION = "/apiversion";
    const SESSION = "/session";
    const SESSION_REMOVE = "/session/remove";
    const SESSION_VERIFY = "/session/verify";
    const SESSION_REFRESH = "/session/refresh";
    const SESSION_USER = "/session/user";
    const SESSION_DATA = "/session/data";
    const HELLO = "/hello";
    const CONFIG = "/config";
    const HANDSHAKE = "/handshake";
    const DEV_PRODUCTION_MODE = "/devproductionmode";
    const JWT_DATA = "/jwt/data";
    const SESSION_REGENERATE = "/session/regenerate";
    const API_VERSION_HEADER = "cdi-version";
    const SUPPORTED_CDI_VERSIONS = ['1.0', '2.0'];
    const EXCEPTION_UNAUTHORISED = "UNAUTHORISED";
    const DRIVER_NOT_COMPATIBLE_MESSAGE = "Current driver version is not compatible with the core version on your host/s";
    const SAME_SITE_COOKIE_DEFAULT_VALUE = "none";
    const SESSION_EXPIRED_STATUS_CODE = 440;
    const HANDSHAKE_INFO_CACHE_KEY = "IO_SUPERTOKENS_HANDSHAKE_INFO";
    const HANDSHAKE_INFO_CACHE_TTL_SECONDS = 300; // 5 minutes
    const API_VERSION_CACHE_KEY = "IO_SUPERTOKENS_API_VERSION";
    const API_VERSION_CACHE_TTL_SECONDS = 300; // 5 minutes
}
