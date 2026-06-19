<?php

namespace App\Kinetik\Sources;

use App\Kinetik\Contracts\KipActivitySource;
use App\Kinetik\Data\KipActivityData;
use App\Kinetik\Data\KipPlanData;
use Illuminate\Support\Collection;

/**
 * Returns hard-coded fixture data so the sync pipeline can be exercised
 * without a live kipApp connection.
 *
 * Activate by setting KIP_SOURCE=mock in your .env.
 */
class MockKipActivitySource implements KipActivitySource
{
    public function fetchActivities(string $nipLama): Collection
    {
        return collect([
            KipActivityData::fromApiRow([
                'kegiatanperhariid' => "mock-act-{$nipLama}-001",
                'rkid' => "mock-rk-{$nipLama}-001",
                'rencanakinerja' => 'Terlaksananya Penyusunan Laporan Bulanan BRS',
                'kegiatan' => 'Penyusunan laporan bulanan BRS',
                'tanggal' => '2026-06-01',
                'tanggalselesai' => '2026-06-01',
                'jammulai' => '08:00',
                'jamselesai' => '12:00',
                'progres' => 100,
                'capaian' => 'Laporan bulanan BRS selesai disusun',
                'datadukung' => 'https://example.com/bukti/001.pdf',
                'tanggalkirim' => null,
                'periodeid' => '8',
                'tahun' => 2026,
                '_mock' => true,
                '_niplama' => $nipLama,
            ]),
            KipActivityData::fromApiRow([
                'kegiatanperhariid' => "mock-act-{$nipLama}-002",
                'rkid' => "mock-rk-{$nipLama}-001",
                'rencanakinerja' => 'Terlaksananya Penyusunan Laporan Bulanan BRS',
                'kegiatan' => 'Rapat koordinasi tim statistik',
                'tanggal' => '2026-06-02',
                'tanggalselesai' => '2026-06-02',
                'jammulai' => '09:00',
                'jamselesai' => '11:00',
                'progres' => 75,
                'capaian' => 'Rapat berjalan lancar',
                'datadukung' => null,
                'tanggalkirim' => null,
                'periodeid' => '8',
                'tahun' => 2026,
                '_mock' => true,
                '_niplama' => $nipLama,
            ]),
            KipActivityData::fromApiRow([
                'kegiatanperhariid' => "mock-act-{$nipLama}-003",
                'rkid' => "mock-rk-{$nipLama}-002",
                'rencanakinerja' => 'Terlaksananya Pengolahan Data Survei',
                'kegiatan' => 'Pengolahan data survei harga konsumen',
                'tanggal' => '2026-06-03',
                'tanggalselesai' => '2026-06-04',
                'jammulai' => null,
                'jamselesai' => null,
                'progres' => 50,
                'capaian' => 'Pengolahan data tahap awal selesai',
                'datadukung' => 'https://example.com/bukti/003.pdf',
                'tanggalkirim' => null,
                'periodeid' => '8',
                'tahun' => 2026,
                '_mock' => true,
                '_niplama' => $nipLama,
            ]),
        ]);
    }

    public function fetchPlans(string $nipLama): Collection
    {
        return collect([
            KipPlanData::fromApiRow([
                'id' => "mock-rk-{$nipLama}-001",
                'uraian' => 'Penyusunan publikasi statistik daerah',
                '_mock' => true,
                '_niplama' => $nipLama,
            ]),
            KipPlanData::fromApiRow([
                'id' => "mock-rk-{$nipLama}-002",
                'uraian' => 'Koordinasi dan supervisi lapangan',
                '_mock' => true,
                '_niplama' => $nipLama,
            ]),
        ]);
    }
}
