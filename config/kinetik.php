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

        // Structure sync (Tim/Projek/Anggota) — enumerates all teams of a unit
        // kerja via /v1/monitoring/hirarki/daerah, then pulls each team's
        // projects + members.
        'admin_niplama' => env('KIP_ADMIN_NIPLAMA'),

        // Office scope for the team directory (monitoring/hirarki/daerah).
        'unitkerja_id' => env('KIP_UNITKERJA_ID', '100'),
        'wilayah_id' => env('KIP_WILAYAH_ID', '7200_11'),

        // kipApp period identifier used by the structure endpoints (not the quarter).
        'periode_id' => (int) env('KIP_PERIODE_ID', 8),

        'tahun' => (int) env('KIP_TAHUN', (int) date('Y')),

        // Login accounts for synced employees. kipApp does not expose employee
        // emails, so they are derived as firstname@<domain> (same pattern as
        // UserSeeder; second name appended on collision). Password "password".
        'create_logins' => (bool) env('KIP_CREATE_LOGINS', true),
        'email_domain' => env('KIP_EMAIL_DOMAIN', 'bpssulteng.id'),
        'default_password' => env('KIP_DEFAULT_PASSWORD', 'password'),

        // Employees processed per chunk during the no-queue activity sync.
        'activity_chunk' => (int) env('KIP_ACTIVITY_CHUNK', 5),
    ],
];
