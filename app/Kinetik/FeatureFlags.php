<?php

namespace App\Kinetik;

use App\Models\FeatureFlag;
use Illuminate\Support\Facades\Cache;

/**
 * The kipApp feature flags an admin can switch from the "Fitur" page. Each one is a `kinetik.kip.<key>` config
 * value read all over the app; a saved choice overrides the .env value when the app boots, so no caller changes.
 */
final class FeatureFlags
{
    private const CACHE_KEY = 'kinetik-feature-flags';

    /** @return array<string, array{label: string, description: string, tested: bool}> */
    public static function definitions(): array
    {
        return [
            'send_to_atasan' => [
                'label' => 'Kirim kegiatan ke atasan',
                'description' => 'Tombol untuk mengirim kegiatan yang sudah dikirim ke kipApp agar diperiksa atasan, dan menariknya kembali.',
                'tested' => true,
            ],
            'edit_after_push' => [
                'label' => 'Ubah dan hapus kegiatan setelah dikirim',
                'description' => 'Kegiatan yang sudah ada di kipApp boleh diubah atau dihapus selama belum dikirim ke atasan. Perubahan ikut ke kipApp.',
                'tested' => true,
            ],
            'atasan_rating' => [
                'label' => 'Nilai SKP oleh atasan di Kinetik',
                'description' => 'Halaman Nilai SKP: nilai dan umpan balik tiap RK dan perilaku, lalu Selesai menilai. Mematikannya mengembalikan tombol Nilai ke kipApp.',
                'tested' => false,
            ],
            'iki_write' => [
                'label' => 'Tambah dan hapus IKI di SKP Saya',
                'description' => 'Anggota menambah IKI pada RK di SKP yang masih Sedang dibuat, supaya SKP bisa dikirim tanpa membuka kipApp. Mematikannya menyembunyikan tombol IKI.',
                'tested' => false,
            ],
            'structure_write' => [
                'label' => 'Buat RK Ketua, proyek dan RK Anggota di kipApp',
                'description' => 'Proyek dan RK yang dibuat di Kinetik ikut dibuat di kipApp dengan akun PJ. Mematikannya membuat proyek dan RK hanya tersimpan di Kinetik.',
                'tested' => false,
            ],
        ];
    }

    /** Put the saved choices over the config values. Safe before the table exists. */
    public static function apply(): void
    {
        $saved = rescue(fn () => Cache::rememberForever(self::CACHE_KEY, fn () => FeatureFlag::pluck('enabled', 'key')->all()), [], false);

        foreach (self::definitions() as $key => $_) {
            if (array_key_exists($key, $saved)) {
                config(["kinetik.kip.{$key}" => (bool) $saved[$key]]);
            }
        }
    }

    public static function set(string $key, bool $enabled, ?int $userId): void
    {
        abort_unless(array_key_exists($key, self::definitions()), 404);

        FeatureFlag::updateOrCreate(['key' => $key], ['enabled' => $enabled, 'changed_by' => $userId]);
        Cache::forget(self::CACHE_KEY);
        config(["kinetik.kip.{$key}" => $enabled]);
    }

    /** Drop the admin choice so the .env value stands again. */
    public static function reset(string $key): void
    {
        abort_unless(array_key_exists($key, self::definitions()), 404);

        FeatureFlag::where('key', $key)->delete();
        Cache::forget(self::CACHE_KEY);
    }
}
