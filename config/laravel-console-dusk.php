<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel Console Dusk Paths
    |--------------------------------------------------------------------------
    |
    | Here you may configure the name of screenshots and logs directory as you wish.
    */
    'paths' => [
        'screenshots' => storage_path('browser/screenshots'), # will be configured in app service provider
        'log' => storage_path('browser/log'),
        'source' => storage_path('browser/source'),
    ],

    /*
    | --------------------------------------------------------------------------
    | Headless Mode
    | --------------------------------------------------------------------------
    |
    | When false it will show a Chrome window while running. Within production
    | it will be forced to run in headless mode.
    */
    'headless' => false,

    /*
    | --------------------------------------------------------------------------
    | Driver Configuration
    | --------------------------------------------------------------------------
    |
    | Here you may pass options to the browser driver being automated.
    |
    | A list of available Chromium command line switches is available at
    | https://peter.sh/experiments/chromium-command-line-switches/
    */
    'driver' => [
        'chrome' => [
            'options' => [
                '--disable-gpu',
            ],
        ],
    ],
];
