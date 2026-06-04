<?php

return [
    'kip' => [
        // 'api' uses the live kipApp API; 'mock' uses MockKipActivitySource for offline development
        'source' => env('KIP_SOURCE', 'api'),

        'base_url' => env('KIP_BASE_URL', 'https://kipapp.bps.go.id/api'),

        // Bearer token for the x-auth header.
        // Current mechanism: per-user ~24h JWT minted by kipApp after Keycloak SSO.
        // Official server-to-server credentials are pending — paste here when granted.
        'token' => env('KIP_TOKEN'),

        'timeout' => (int) env('KIP_TIMEOUT', 15),
    ],
];
