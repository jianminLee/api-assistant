<?php

namespace Orzlee\ApiAssistant;

use Illuminate\Support\ServiceProvider;

class ApiAssistantServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $configPath = __DIR__ . '/../config/api-assistant.php';
        if (function_exists('config_path')) {
            $publishPath = config_path('api-assistant.php');
        } else {
            $publishPath = base_path('config/api-assistant.php');
        }
        $this->publishes([$configPath => $publishPath], 'config');
    }

    public function register()
    {
        $configPath = __DIR__ . '/../config/ide-helper.php';
        $this->mergeConfigFrom($configPath, 'ide-helper');
    }
}
