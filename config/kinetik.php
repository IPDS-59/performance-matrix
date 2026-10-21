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

        // Separate key (base64, 32 bytes) for stored member SSO passwords and tokens.
        // Generate with: php -r "echo 'base64:'.base64_encode(random_bytes(32));"
        'credential_key' => env('KIP_CREDENTIAL_KEY'),

        // Structure sync (Tim/Projek/Anggota) — enumerates all teams of a unit
        // kerja via /v1/monitoring/hirarki/daerah, then pulls each team's
        // projects + members.
        'admin_niplama' => env('KIP_ADMIN_NIPLAMA'),

        // CA bundle used for every outgoing HTTPS call (kipApp, BPS SSO). The hosting's own bundle is old (September
        // 2025) and sits on OpenSSL 1.1.1, so a current Mozilla bundle ships with the app. Missing file: the server's own
        // bundle is used. Refresh with `php artisan kinetik:update-ca-bundle` (also scheduled monthly).
        'ca_bundle' => env('KIP_CA_BUNDLE', resource_path('certs/cacert.pem')),

        // Office scope for the team directory (monitoring/hirarki/daerah).
        'unitkerja_id' => env('KIP_UNITKERJA_ID', '100'),
        'wilayah_id' => env('KIP_WILAYAH_ID', '7200_11'),

        // kipApp period identifier used by the structure endpoints (not the quarter).
        'periode_id' => (int) env('KIP_PERIODE_ID', 8),

        'tahun' => (int) env('KIP_TAHUN', (int) date('Y')),
        // BPS SSO login for staff. Off by default on a local machine so developers can sign in with the
        // local password. Also the switch to turn SSO login off for everyone (for example when BPS
        // closes the old SSO). Accounts then keep their local password and kipApp features stay off.
        'sso_login' => filter_var(env('KIP_SSO_LOGIN', env('APP_ENV') !== 'local'), FILTER_VALIDATE_BOOLEAN),
        // "Kirim ke atasan" and "Batalkan kirim" (POST /kegiatan/kirim and /batal). Tested live on
        // 2026-10-19 on a throwaway kegiatan: send, cancel and delete all worked.
        'send_to_atasan' => (bool) env('KIP_SEND_TO_ATASAN', false),

        // Item 2 of docs/plans/2026-10-17-kipapp-entry-flow.md. On: a one-day kegiatan is pushed with
        // tanggalselesai null and a kegiatan without a clock time with jammulai/jamselesai null, like kipApp's
        // own form. Off: tanggalselesai is always sent and the times default to 08:00 and 16:00 (today).
        // Never tested live: check once that PUT /kegiatan accepts null times before turning it on.
        'null_times' => (bool) env('KIP_NULL_TIMES', false),
        // Items 7 and 8. On: a kegiatan already in kipApp (not sent) can be edited and deleted from Kinetik
        // (PUT and DELETE /kegiatan), and a sent one can change "Masukan ke capaian SKP". Off: edit and
        // cancel stay possible only while the kegiatan is not in kipApp yet. PUT and DELETE /kegiatan were
        // verified live (2026-10-08 and 2026-10-19).
        'edit_after_push' => (bool) env('KIP_EDIT_AFTER_PUSH', false),

        // The member adds and removes IKI of the RK in a SKP that is Sedang dibuat, on SKP Saya. The calls are read from
        // kipApp's own code and never run live; keep it off until the first IKI is added and checked in kipApp.
        'iki_write' => (bool) env('KIP_IKI_WRITE', false),

        // A new RK Ketua, proyek and RK Anggota made in Kinetik is also created in kipApp, with the tim leader's own
        // token. The calls are read from kipApp's own code and never run live (no safe throwaway test), so keep it off
        // until the first one is made together with a PJ and checked in kipApp.
        'structure_write' => (bool) env('KIP_STRUCTURE_WRITE', false),

        // The atasan rates a quarter SKP inside Kinetik (Nilai SKP page): feedback per RK and per behaviour, then
        // "selesai menilai" (flag 7). The calls are read from kipApp's own code and never run live, because a test
        // would rate a real person's official SKP. Keep it off until the first rating is done together with the atasan.
        'atasan_rating' => (bool) env('KIP_ATASAN_RATING', false),

        // Login accounts for synced employees. kipApp does not expose employee
        // emails, so they are derived as firstname@<domain> (same pattern as
        // UserSeeder; second name appended on collision). Password "password".
        'create_logins' => (bool) env('KIP_CREATE_LOGINS', true),
        'email_domain' => env('KIP_EMAIL_DOMAIN', 'bpssulteng.id'),
        'real_email_domain' => env('KIP_REAL_EMAIL_DOMAIN', 'bps.go.id'),
        'username_map_path' => env('KIP_USERNAME_MAP_PATH', database_path('data/kipapp-usernames.json')),
        'default_password' => env('KIP_DEFAULT_PASSWORD', 'password'),
        // Last day (Y-m-d, WITA) staff can still first-login with the default password
        // and connect their SSO password. Empty = no deadline.
        'default_password_until' => env('KIP_DEFAULT_PASSWORD_UNTIL'),

        // Employees processed per chunk during the no-queue activity sync.
        'activity_chunk' => (int) env('KIP_ACTIVITY_CHUNK', 5),
    ],
];
