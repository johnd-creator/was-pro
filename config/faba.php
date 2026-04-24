<?php

return [
    'tps_capacity' => [
        'materials' => [
            'fly_ash' => 120.0,
            'bottom_ash' => 80.0,
        ],
        'total' => 200.0,
        'thresholds' => [
            'warning' => 80.0,
            'critical' => 95.0,
        ],
    ],
    'analysis_matrix' => [
        'segments' => [
            [
                'key' => 'semen_dan_batako',
                'label' => 'Semen dan Batako',
                'target_quantity' => 120.0,
                'purpose_slugs' => ['semen', 'batako'],
            ],
            [
                'key' => 'beton_dan_konstruksi',
                'label' => 'Beton dan Konstruksi',
                'target_quantity' => 90.0,
                'purpose_slugs' => ['beton', 'konstruksi'],
            ],
            [
                'key' => 'workshop_internal',
                'label' => 'Workshop Internal',
                'target_quantity' => 24.0,
                'movement_types' => ['utilization_internal'],
                'internal_destination_slugs' => ['workshop-faba', 'workshop-internal'],
            ],
            [
                'key' => 'lainnya',
                'label' => 'Lainnya',
                'target_quantity' => 36.0,
                'purpose_slugs' => ['lainnya', 'pemanfaatan-mitra'],
            ],
        ],
    ],
];
