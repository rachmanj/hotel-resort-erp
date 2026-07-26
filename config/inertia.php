<?php

return [

    'ssr' => [
        'enabled' => false,
        'url' => 'http://127.0.0.1:13714',
    ],

    'pages' => [

        'ensure_pages_exist' => true,

        'paths' => [
            resource_path('js/Pages'),
        ],

        'extensions' => [
            'js',
            'jsx',
            'ts',
            'tsx',
            'vue',
            'svelte',
        ],

    ],

    'testing' => [

        'ensure_pages_exist' => true,

    ],

];
