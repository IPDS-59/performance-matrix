# Kinetik: system architecture, data flows and the e-Kinerja migration plan (2026-11-02)

Evidence for the external systems: [[myasn-ekinerja-integration-feasibility]] (public pages and shipped JS only). Everything about Kinetik is read from this repository and from prod on 2026-11-02.

## 1. System context

```mermaid
flowchart LR
  subgraph Users
    M[Anggota]; PJ[PJ / Ketua tim]; H[Pimpinan]; A[Admin]
  end
  K[Kinetik<br/>Laravel + Inertia + Vue]
  subgraph Today
    KIP[kipApp<br/>kipapp.bps.go.id]
    BSSO[BPS SSO<br/>sso.bps.go.id Keycloak<br/>behind Cloudflare/WAF]
  end
  subgraph From Jan 2027
    ASND[ASN Digital SSO<br/>sso-siasn.bkn.go.id<br/>reCAPTCHA + MFA]
    EK[e-Kinerja BKN<br/>kinerja.bkn.go.id]
    MY[MyASN]
  end
  M & PJ & H & A --> K
  K -- "login with stored SSO password" --> BSSO
  BSSO --> KIP
  K <-- "read + write kegiatan, SKP, RK, projek (member token)" --> KIP
  ASND --> EK
  ASND --> MY
  K -. "no API yet: file import / export only" .-> EK
```

## 2. Infrastructure

```mermaid
flowchart TB
  subgraph GH[GitHub]
    PR[PR + CI: lint, tsc, tests, build, audit]
    PAGES[GitHub Pages: 2 Slidev decks]
  end
  DEV[Developer machine<br/>deploy.sh builds zip] -->|zip upload via hosting panel connector| HOST
  subgraph HOST[Shared hosting, no SSH]
    PHP[PHP 8.4 - host OpenSSL 1.1.1q EOL]
    SCHED[Task Scheduler every 5 min: schedule:run]
    QW[queue:work --stop-when-empty<br/>run by the scheduler, no daemon]
    FILES[(storage: sessions, logs, CA bundle)]
  end
  HOST --> PG[(PostgreSQL<br/>cache + queue + data)]
  SCHED --> QW
  PHP --> FILES
  PR --> DEV
```

Constraints that shape every design: no SSH and no long-running worker (jobs run at most every 5 minutes), jobs after a response use `dispatchAfterResponse`, cache and queue live in PostgreSQL, sessions in files, the outbound TLS bundle is shipped with the app, local and CI use SQLite so SQL must stay portable.

## 3. Data flow: kipApp to Kinetik (read) and back (write)

```mermaid
sequenceDiagram
  autonumber
  participant S as Scheduler (daily 04:30-05:30 WITA)
  participant K as Kinetik
  participant BS as BPS SSO
  participant KIP as kipApp API
  participant DB as PostgreSQL
  S->>K: sync-kip-structure (admin token)
  K->>KIP: proyek, anggota, rk/proyek per projek (167 calls)
  KIP-->>K: teams, projek, RK Ketua, RK Anggota rows
  K->>DB: upsert Team, Project, members, link RK to projek by TEXT
  S->>K: sync-members (each member)
  K->>BS: re-login with stored password if token expired
  BS-->>K: kipApp JWT (about 24 h)
  K->>KIP: skp/rk, kegiatan, skp status (member token)
  K->>DB: PlanItem, EmployeeRk, SkpStatus
  Note over K,KIP: write path: member saves a kegiatan
  K->>K: guard: RK needs a projek, SKP and RK must exist
  K->>KIP: POST kegiatan (skpid, rkid) with member token
  KIP-->>K: kegiatan id stored on PlanItem
```

## 4. Data model and where the relationships break

```mermaid
erDiagram
  TEAM ||--o{ PROJECT : "timkerjaid (ID)"
  PROJECT ||--o{ RK_ANGGOTA_KIPAPP : "proyekid -> rkproyekid (ID)"
  PROJECT }o--|| RK_KETUA : "rkketuaid (ID)"
  TEAM ||--o{ PERFORMANCE_PLAN : "team_id (ID, can be null)"
  PERFORMANCE_PLAN }o--o| PROJECT : "project_id: filled by TEXT match, 469 still empty"
  PERFORMANCE_PLAN ||--o{ PLAN_ITEM : "performance_plan_id = SKP rkid (ID)"
  EMPLOYEE ||--o{ PLAN_ITEM : "employee_id"
  EMPLOYEE ||--o{ EMPLOYEE_RK : "kip_rk_id (ID)"
  RK_ANGGOTA_KIPAPP }o..o{ PERFORMANCE_PLAN : "NO shared ID: rkproyekid vs rkid"
```

Finding: kipApp has IDs for team, projek and RK Ketua, but the SKP-side RK row (`skp/rk`) carries only `rkid`, `timkerjaid` and the leader RK as text. The projek-side row (`rk/proyek`) carries `rkproyekid`. No field joins the two, so Kinetik joins on normalized RK text. Result on prod: 1,006 RK without a projek, 537 linked by text, 469 not in any projek (RK tambahan or ambiguous).

## 5. Authentication and token lifecycle

```mermaid
stateDiagram-v2
  [*] --> NotConnected
  NotConnected --> Connected: first login with SSO password (stored encrypted)
  NotConnected --> OtpPending: account has OTP
  OtpPending --> Connected: valid code
  Connected --> TokenValid: kipApp JWT (24 h)
  TokenValid --> TokenExpired
  TokenExpired --> TokenValid: silent re-login with stored password
  TokenExpired --> Failed: SSO rejects (password changed)
  TokenExpired --> Retry: WAF/Cloudflare or timeout (30 s)
  Failed --> NotConnected: member enters password again
```

Lessons already paid for: WAF blocks `curl/` User-Agents; Cloudflare challenges Laravel's default JSON headers; login steps needed 30 s. After January the same flow cannot be copied to ASN Digital (reCAPTCHA + MFA on every login).

## 6. Target architecture: provider adapters

```mermaid
flowchart LR
  UI[Kinetik UI + actions] --> PORT[Ports: ActivitySource, StructureSource,<br/>SkpFlow, Writer contracts]
  PORT --> KA[kipApp adapter - exists]
  PORT --> MOCK[Mock adapter - exists]
  PORT --> FA[File adapter - build now:<br/>Arsip Excel export, e-Kinerja import]
  PORT -.-> EA[e-Kinerja API adapter<br/>only after BKN grants a client]
  KA --> KIP[kipApp]
  FA --> FILES[(Excel in / out)]
  EA -.-> EK[e-Kinerja]
  PORT --> DB[(Kinetik DB = system of record for history)]
```

The contracts already exist (`KipActivitySource`, `KipStructureSource`, `StructureFlow`, mock and API implementations bound in `KinetikServiceProvider`). Migration means adding adapters, not rewriting features.

## 7. Risks

| Risk | Impact | Mitigation |
|---|---|---|
| kipApp retired in Jan 2027 | Push and sync stop | Arsip export now, file adapter, keep Kinetik as record |
| No BKN API access | No automatic sync to e-Kinerja | Ask BKN for an OIDC client; meanwhile file bridge |
| ASN Digital login has reCAPTCHA and MFA | Cannot store passwords | Do not store; identity via OIDC if a client is granted |
| RK to projek joined by text | 469 RK without projek, mismatches | Integritas data page, push guard, manual pick |
| Shared hosting limits | No daemon, 5 min jobs, EOL OpenSSL | Keep jobs short; plan move to a VPS if BKN integration needs long jobs |
| SSO fragility (WAF/Cloudflare, timeouts) | Login and sync failures | Browser-style headers, 30 s timeouts, logs with user |

## 8. Action plan to January 2027

| When | Action | Owner | Done when |
|---|---|---|---|
| Nov wk1 | Arsip kipApp export live for every member (done in PR) | Dev | Every member can download history |
| Nov wk1 | Letter/ticket to BKN (support-siasn) asking for an OIDC client and API partner access for Kinetik | Admin + Kepala | Written answer received |
| Nov wk2 | Fix 426 RK without projek via Integritas data and sync | Admin | rk_no_project below 50 |
| Nov wk2 | Capture e-Kinerja data model from the Buku Panduan (support-siasn) and from one member's own screens | Dev + 1 pilot member | Field mapping RHK/kegiatan harian written |
| Nov wk3 | File adapter: export in the e-Kinerja column order; import from e-Kinerja reports | Dev | Round trip on pilot data |
| Dec wk1 | Pilot: 3 members move one team's SKP 2027 into e-Kinerja, Kinetik runs recap from imports | Pilot team | Weekly recap works without kipApp |
| Dec wk2 | Freeze kipApp write features (feature flags) when BKN confirms the cut-off | Admin | Flags off |
| Dec wk3 | Decision: e-Kinerja API adapter (if client granted) or stay on file bridge; hosting move decision | Admin + Kepala | ADR written |
| Jan | Go live on e-Kinerja for SKP 2027; Kinetik = recap, paraphrase, monitoring, FRA, angka kredit, archive | All | First weekly recap on new data |
