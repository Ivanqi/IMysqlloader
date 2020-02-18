<?php
return [
    'backgrouds' => [
        'admin' => [    // app_name, agent_name, sever_id
            'template' => '%s_%s_%s',
            'separator' => '_',
            'parameter' => 3,
            'parameterCheck' => ['is_string', 'is_string', 'is_numeric']
        ], 
        'common' => [
            'template' => '%s_%s',
            'parameter' => 2,
            'separator' => '_',
            'parameterCheck' => ['is_string', 'is_string'],
            'last_default' => 'common'
        ]
    ],
    'handlerFunc' => [
        'admin' => [
            'func' => 'adminHandlerFunc',
            'need_parameter' => [
                'agent_id', 'server_id'
            ]
        ],
        'common' => [
            'func' => 'commonHandlerFunc'
        ]
    ]
];