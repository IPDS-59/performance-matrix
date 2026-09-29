<?php

namespace App\Kinetik\Credit;

/**
 * Jenjang of a Jabatan Fungsional with its Angka Kredit figures, from
 * PermenPANRB Nomor 1 Tahun 2023, Lampiran A.
 */
enum FunctionalLevel: string
{
    case Pemula = 'Pemula';
    case Terampil = 'Terampil';
    case Mahir = 'Mahir';
    case Penyelia = 'Penyelia';
    case AhliPertama = 'Ahli Pertama';
    case AhliMuda = 'Ahli Muda';
    case AhliMadya = 'Ahli Madya';
    case AhliUtama = 'Ahli Utama';

    /**
     * The jenjang in a kipApp jabatan name, e.g. "Pranata Komputer Ahli
     * Pertama". Null for Pelaksana and structural positions.
     */
    public static function fromJabatan(?string $jabatan): ?self
    {
        if ($jabatan === null) {
            return null;
        }

        foreach (self::cases() as $level) {
            if (preg_match('/\b'.preg_quote($level->value, '/').'\s*$/i', trim($jabatan))) {
                return $level;
            }
        }

        return null;
    }

    /** Koefisien Angka Kredit tahunan. */
    public function yearlyCoefficient(): float
    {
        return match ($this) {
            self::Pemula => 3.75,
            self::Terampil => 5,
            self::Mahir, self::AhliPertama => 12.5,
            self::Penyelia, self::AhliMuda => 25,
            self::AhliMadya => 37.5,
            self::AhliUtama => 50,
        };
    }

    /** Angka Kredit kumulatif minimal for the next pangkat inside the jenjang. */
    public function pangkatTarget(): float
    {
        return match ($this) {
            self::Pemula => 15,
            self::Terampil => 20,
            self::Mahir, self::AhliPertama => 50,
            self::Penyelia, self::AhliMuda => 100,
            self::AhliMadya => 150,
            self::AhliUtama => 200,
        };
    }

    /** Angka Kredit kumulatif minimal for the next jenjang; null at the top. */
    public function jenjangTarget(): ?float
    {
        return match ($this) {
            self::Pemula => 15,
            self::Terampil => 60,
            self::Mahir, self::AhliPertama => 100,
            self::AhliMuda => 200,
            self::AhliMadya => 450,
            self::Penyelia, self::AhliUtama => null,
        };
    }

    public function nextLevel(): ?self
    {
        return match ($this) {
            self::Pemula => self::Terampil,
            self::Terampil => self::Mahir,
            self::Mahir => self::Penyelia,
            self::AhliPertama => self::AhliMuda,
            self::AhliMuda => self::AhliMadya,
            self::AhliMadya => self::AhliUtama,
            self::Penyelia, self::AhliUtama => null,
        };
    }

    /**
     * Golongan of the jenjang, lowest first.
     *
     * @return list<string>
     */
    public function golongan(): array
    {
        return match ($this) {
            self::Pemula => ['II/a'],
            self::Terampil => ['II/b', 'II/c', 'II/d'],
            self::Mahir, self::AhliPertama => ['III/a', 'III/b'],
            self::Penyelia, self::AhliMuda => ['III/c', 'III/d'],
            self::AhliMadya => ['IV/a', 'IV/b', 'IV/c'],
            self::AhliUtama => ['IV/d', 'IV/e'],
        };
    }

    /**
     * Predikat kinerja as a share of the coefficient (Pasal 37).
     * Null when kipApp has no predikat yet.
     */
    public static function predikatShare(?string $predikat): ?float
    {
        return match (strtolower(trim((string) $predikat))) {
            'sangat baik' => 1.5,
            'baik' => 1.0,
            'butuh perbaikan', 'cukup' => 0.75,
            'kurang' => 0.5,
            'sangat kurang' => 0.25,
            default => null,
        };
    }
}
