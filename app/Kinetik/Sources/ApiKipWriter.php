<?php

namespace App\Kinetik\Sources;

use App\Kinetik\Exceptions\KipApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The kipApp calls that write a member's kegiatan, with the member's own token.
 * Payloads were verified on 2026-10-08 (create, update, delete). kipApp does
 * not return the new id, so a created row is found by listing before and after.
 */
class ApiKipWriter
{
    public function __construct(#[\SensitiveParameter] private readonly string $token) {}

    /**
     * The member's SKP of one quarter, or null when it was not created yet.
     *
     * @return array<string, mixed>|null
     */
    public function quarterSkp(string $pegawaiId, int $periodeId, int $quarter): ?array
    {
        $rows = $this->rows($this->get('v1/skp', ['pegawaiid' => $pegawaiId, 'jenis' => 2]), 'skp');

        return collect($rows)->first(fn (array $r) => (int) ($r['periodeid'] ?? 0) === $periodeId && (int) ($r['periodepenilaianid'] ?? 0) === $quarter);
    }

    /** @return list<array<string, mixed>> */
    public function rks(string $skpId): array
    {
        return $this->rows($this->get('v1/skp/rk', ['skpid' => $skpId]), 'skp/rk');
    }

    /** @return list<array<string, mixed>> */
    public function activities(string $skpId): array
    {
        return $this->rows($this->get('v1/kegiatan', ['skpid' => $skpId]), 'kegiatan');
    }

    /**
     * Create a kegiatan and return its kegiatanperhariid.
     *
     * @param  array{skpid: string, rkid: string, rencanakinerja: string, kegiatan: string, tanggal: string, tanggalselesai: string, progres?: int}  $data
     */
    public function createActivity(array $data): string
    {
        $before = collect($this->activities($data['skpid']))->pluck('kegiatanperhariid')->map(fn ($id) => (string) $id);

        $this->send('post', 'v1/kegiatan', $this->payload($data), 'create kegiatan');

        $new = collect($this->activities($data['skpid']))
            ->first(fn (array $r) => ! $before->contains((string) $r['kegiatanperhariid']) && ($r['kegiatan'] ?? null) === $data['kegiatan']);

        if ($new === null) {
            throw new KipApiException('kipApp menerima kegiatan, tetapi barisnya tidak ditemukan kembali.');
        }

        return (string) $new['kegiatanperhariid'];
    }

    /**
     * Update progres, capaian and evidence. kipApp wants the full row again.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateActivity(string $id, array $data): void
    {
        $this->send('put', 'v1/kegiatan', ['id' => $id, ...$this->payload($data)], 'update kegiatan');
    }

    public function deleteActivity(string $id): void
    {
        $this->send('delete', 'v1/kegiatan', ['id' => $id], 'delete kegiatan');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        return [
            'skpid' => $data['skpid'], 'rkid' => $data['rkid'], 'rencanakinerja' => $data['rencanakinerja'],
            'kegiatan' => $data['kegiatan'], 'tanggal' => $data['tanggal'], 'tanggalselesai' => $data['tanggalselesai'],
            'progres' => $data['progres'] ?? 0, 'butirid' => null,
            'jammulai' => $data['jammulai'] ?? '08:00', 'jamselesai' => $data['jamselesai'] ?? '16:00',
            'capaian' => $data['capaian'] ?? '', 'datadukung' => $data['datadukung'] ?? '',
            'iscapaianskp' => 1, 'parentid' => null,
        ];
    }

    /** @param  array<string, mixed>  $query */
    private function get(string $path, array $query): Response
    {
        $response = $this->client()->get($path, $query);
        if (! $response->successful()) {
            throw KipApiException::fromResponse($response, $path);
        }

        return $response;
    }

    /** @param  array<string, mixed>  $body */
    private function send(string $method, string $path, array $body, string $context): void
    {
        $response = $this->client()->{$method}($path, $body);
        if (! $response->successful()) {
            throw KipApiException::fromResponse($response, $context);
        }
        if ($response->json('status') === false) {
            throw new KipApiException("kipApp menolak {$context}: ".($response->json('message') ?: 'tanpa pesan'), $response);
        }
    }

    /** @return list<array<string, mixed>> */
    private function rows(Response $response, string $context): array
    {
        $json = $response->json();
        $rows = is_array($json) && array_is_list($json) ? $json : ($json['data'] ?? []);
        if (! is_array($rows)) {
            throw new KipApiException("kipApp mengirim data {$context} yang tidak dikenal.", $response);
        }

        return array_values($rows);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('kinetik.kip.base_url'))
            ->timeout((int) config('kinetik.kip.timeout'))
            ->acceptJson()
            ->withHeaders(['x-auth' => 'Bearer '.$this->token]);
    }
}
