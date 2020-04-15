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
namespace SuperTokens\Providers;

use Illuminate\Support\ServiceProvider;
use SuperTokens\SuperToken;

class SuperTokensServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerResources();
        $this->registerPublishing();
    }

    private function registerResources()
    {
        $this->registerSingleton();
    }

    private function registerPublishing()
    {
        $this->publishes([
            __DIR__.'/../../config/supertokens.php' => config_path('supertokens.php')
        ], 'supertokens-config');
    }

    private function registerSingleton()
    {
        $this->app->singleton("SuperTokens", function ($app) {
            return new SuperToken();
        });
    }
}
