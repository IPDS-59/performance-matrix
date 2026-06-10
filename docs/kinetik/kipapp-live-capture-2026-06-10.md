# kipApp — Live API Capture (2026-06-10)

> Captured by logging into kipApp via SSO (account: Sukma Nirmala Dewi,
> niplama `340060924`) and reading the SPA's own authenticated XHR traffic.
> **No token values are stored in this doc.** The working bearer token was
> written to the Laravel app's `.env` as `KIP_TOKEN` (gitignored) for dev use.
> Supersedes the "unconfirmed" notes in `kipapp-integration.md` where marked.

---

## 1. Authentication (confirmed live)

| Item | Value |
|---|---|
| Auth header | `x-auth: Bearer <JWT>` (NOT a cookie; `credentials: include` returns 401) |
| SSO entry | `https://sso.bps.go.id/auth/realms/pegawai-bps/protocol/openid-connect/auth` |
| OIDC client_id | `03340-kipapp-h0m` |
| scope | `profile-pegawai,email` · response_type `code` |
| redirect_uri | `https://kipapp.bps.go.id/api/login` |
| API base | `https://kipapp.bps.go.id/api` (endpoints under `/api/v1/...`) |
| Token storage in SPA | localStorage keys `ka-p-*` are **encrypted**; token is decrypted and injected as `x-auth` at request time (so it is not directly readable from storage) |
| Token TTL | ~24–31h. The captured token's `exp` was ~30.8h out. |

### Role inside the token (confirms centralized-sync premise)
The JWT payload `roles` was an **object** with keys `{"0":..., "3":...}`.
Per prior analysis, **role index `3` = admin unit kerja**. Sukma's account
therefore has admin scope and **can pull other employees' activities by
`niplama`** — this is the basis for "1 admin token drives the whole sync"
(Option B already implemented in `KipCredential` + `ConfigBearerAuthenticator`).

---

## 2. Live endpoint inventory (loaded by the home dashboard)

All returned `200` with a valid `x-auth`:

| Endpoint | Purpose |
|---|---|
| `GET /v1/user` | **current user identity + role flags** (see §3) |
| `GET /v1/switch?niplama=` | switch acting context |
| `GET /v1/notifikasi?niplama=` | notifications |
| `GET /v1/dashboard/rkpegawai?niplama=` | per-employee RK summary (count) |
| `GET /v1/dashboard/rkpegawai/tanpakegiatan?niplama=` | RK with no activity |
| `GET /v1/dashboard/kegiatanpegawai/belumkirim?niplama=` | unsent-activity SKP groups (step 1 of activity fetch) |
| `GET /v1/dashboard/rktimkerja?niplamaketua=` | team-lead RK view (keyed by **leader** niplama) |
| `GET /v1/dashboard/skpbulanini?niplama=` | this-month SKP header |
| `GET /v1/mt/notification`, `GET /v1/mt/status` | misc app status |

Previously documented (still valid): `/v1/kegiatan?skpid=` (step 2),
`/v1/proyek?timkerjaid=`, `/v1/proyek/anggota?proyekid=`,
`/v1/timkerja/anggota?id=`, `/v1/skp?skpid=`, `/v1/skp/rk?skpid=`,
`/v1/skp/iki?skpid=&rkid=`, `/v1/pegawai?timkerjaid=`, `/v1/pegawai/lokasi`.

---

## 3. `GET /v1/user` — identity + native leader flag (NEW, important)

Returns the logged-in employee with rich master fields **and a native team-lead
signal**:

```jsonc
{
  "id": "191642",
  "niplama": "340060924",
  "nipbaru": "200001142022012003",
  "nama": "Sukma Nirmala Dewi S.Tr.Stat.",
  "jabatanid": "58", "namajabatan": "Pranata Komputer Ahli Pertama",
  "golongan": "III/b", "pangkat": "Penata Muda Tk. I",
  "unitkerjaid": "42", "namaunitkerja": "BPS Provinsi",
  "wilayahid": "7200_11", "namawilayah": "Sulawesi Tengah",
  "isketuatim": true,                    // <-- kipApp KNOWS who is a team leader
  "riwayatketuatim": { "2023_3": [ { "tahun": 2023, "periodeid": 3, ... } ] }
}
```

**Implication:** the **PJ / Ketua Tim** actor can be derived from kipApp
(`isketuatim` + `riwayatketuatim`) instead of being maintained manually in
Kinetik. Resolves RFC-001 Open Question #6 ("PJ = Ketua Tim?") with a data source.

---

## 4. Data-availability caveat (NEW)

For Sukma's **current period** the endpoints disagreed:

| Endpoint | Response |
|---|---|
| `/dashboard/rkpegawai` | `{ "jumlahrk": 0 }` |
| `/dashboard/rktimkerja` | `[]` |
| `/dashboard/skpbulanini` | `{ "skp": null, "tahun": null }` |
| `/dashboard/kegiatanpegawai/belumkirim` | **1 activity** under `skpid 1285321` (periode I/2026) |

So `rkpegawai` can report **0 RK** while `belumkirim` still has unsent
activities. **Lesson for sync:** do not gate the activity pull on `rkpegawai`
being populated; drive it from `belumkirim` → `kegiatan?skpid` as already
implemented. Expect employees whose SKP for the active period is still
"Sedang dibuat" / unset to return empty master rows but non-empty activities.

---

## 5. Raw artifacts

Saved under `docs/.playwright-mcp/` (**gitignored**), for dev reference until
the token expires:
- `resp-user.json`, `resp-belumkirim.json`, `resp-rkpegawai.json`,
  `resp-rktimkerja.json`, `resp-skpbulanini.json`
- `req-headers.txt` (contains the live bearer token — gitignored)
