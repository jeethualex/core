<?php

return [

    // Config for the LUIS service
    'luis_config' => [
        'app_url'          => env('LUIS_APP_URL'),
        'app_id'           => env('LUIS_APP_ID'),
        'subscription_key' => env('LUIS_SUBSCRIPTION_KEY'),
        'staging'          => env('LUIS_STAGING', 'false'),
        'timezone_offset'  => env('LUIS_TIMEZONE_OFFSET', 0),
        'verbose'          => env('LUIS_VERBOSE', 'true'),
        'spellcheck'       => env('LUIS_SPELLCHECK', 'true')
    ],

    // Config for the QnA service
    'qna_config' => [
        'app_url'      => env('QNA_APP_URL'),
        'endpoint_key' => env('QNA_ENDPOINT_KEY'),
    ],

    'rasa_config' => [
        'app_url'      => env('RASA_APP_URL'),
    ],

    'dialogflow_config' => [
        'project_ids' => [],
        'credentials' => [],
    ],

    // Register the application interfaces
    'available_interpreters' => [
        OpenDialogAi\InterpreterEngine\Interpreters\RasaInterpreter::class,
        OpenDialogAi\InterpreterEngine\Interpreters\LuisInterpreter::class,
        OpenDialogAi\InterpreterEngine\Interpreters\QnAInterpreter::class,
        OpenDialogAi\InterpreterEngine\Interpreters\DialogflowInterpreter::class,
        OpenDialogAi\InterpreterEngine\Interpreters\CallbackInterpreter::class
    ],

    'default_interpreter' => 'interpreter.core.callbackInterpreter',

    'default_cache_time' => 60,

    'supported_callbacks' => [
        'callback_example' => 'intent.core.exampleIntent',
        'chat_open' => 'intent.core.chatOpen'
    ]
];
