<?php

$story = 'Gina, a witty barista at The Busy Bean Coffee Shop, serves Tommy, a clumsy delivery guy, a triple shot espresso before he makes a delivery to Mrs. Wigglesworths mansion. At the mansion, Tommy delivers a life-sized stuffed peacock to the eccentric elderly';

return [
    'messages' => [
        ['role' => 'system', 'content' => 'Save all characters and locations.'],
        ['role' => 'user', 'content' => $story],
    ],
    'tools' => [
        [
            'type' => 'function',
            'function' => [
                'name' => 'save_character',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string'
                        ],
                        'role' => [
                            'type' => 'string'
                        ]
                    ],
                    'required' => ['name', 'role']
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'save_location',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string'
                        ]
                    ],
                    'required' => ['name']
                ]
            ]
        ]
    ]
];
