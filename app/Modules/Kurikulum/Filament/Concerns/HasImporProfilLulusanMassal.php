<?php

namespace App\Modules\Kurikulum\Filament\Concerns;

use App\Modules\Kurikulum\Models\ProfilIndikator;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\Support\ProfilLulusanImporParser;

/**
 * Impor massal profil lulusan + indikator berformat (1), (2), …
 * Kode profil diisi otomatis; duplikat dideteksi dari nama.
 */
trait HasImporProfilLulusanMassal
{
    protected function importColumns(): array
    {
        return [
            ['key' => 'urutan', 'label' => 'urutan', 'wajib' => false],
            ['key' => 'nama', 'label' => 'nama', 'wajib' => true],
            ['key' => 'deskripsi', 'label' => 'deskripsi profil', 'wajib' => false],
            ['key' => 'indikator', 'label' => 'indikator', 'wajib' => false],
        ];
    }

    protected function importInstructionsExtra(): array
    {
        return [
            'Kode profil diisi otomatis oleh sistem; tidak perlu kolom kode.',
            'Urutan profil opsional; kosongkan bila belum ditetapkan.',
            'Kolom indikator opsional; tulis semua butir indikator dalam satu sel.',
            'Setiap indikator diawali penomoran (1), (2), (3), … lalu teks indikator.',
            'Pratinjau menghitung jumlah indikator dari nomor yang tertera.',
            'Penomoran harus berurutan mulai (1) tanpa nomor ganda atau loncat.',
        ];
    }

    /**
     * @return list<string>
     */
    protected function importExampleRows(): array
    {
        $indikator = '(1) Menguasai konsep teoritis tentang konsep-konsep dasar matematika '
            .'(2) Menguasai dan mengaplikasikan strategi dan metode pembelajaran dasar yang efektif untuk menyampaikan materi matematika '
            .'(3) Mampu memanfaatkan teknologi untuk mendukung proses dan evaluasi pembelajaran '
            .'(4) Mampu mendesain pengelolaan kelas yang baik untuk terciptanya lingkungan belajar yang kondusif';

        return [
            '1|Pendidik|Menjadi pendidik matematika profesional|'.$indikator,
            '2|Peneliti||',
        ];
    }

    abstract protected function kurikulumIdUntukImporProfil(array $context): ?string;

    protected function resolveImportRow(array $data, array $context): array
    {
        $kurikulumId = $this->kurikulumIdUntukImporProfil($context);

        if (blank($kurikulumId)) {
            return ['status' => 'invalid', 'keterangan' => 'Pilih kurikulum terlebih dahulu.'];
        }

        if ($data['urutan'] !== '' && ! ctype_digit($data['urutan'])) {
            return ['status' => 'invalid', 'keterangan' => 'Urutan harus angka.'];
        }

        $pesanIndikator = ProfilLulusanImporParser::validateIndikator($data['indikator']);

        if ($pesanIndikator !== null) {
            return ['status' => 'invalid', 'keterangan' => $pesanIndikator];
        }

        $ringkasanIndikator = ProfilLulusanImporParser::ringkasanIndikator($data['indikator']);
        $namaNormal = mb_strtolower(trim($data['nama']));

        $existing = ProfilLulusan::query()
            ->where('kurikulum_id', $kurikulumId)
            ->whereRaw('LOWER(TRIM(nama)) = ?', [$namaNormal])
            ->first();

        if ($existing) {
            return [
                'status' => 'duplikat',
                'keterangan' => "Profil dengan nama ini sudah ada. {$ringkasanIndikator}.",
                'existing_id' => $existing->id,
                'dedup' => $namaNormal,
            ];
        }

        return [
            'status' => 'baru',
            'keterangan' => $ringkasanIndikator,
            'dedup' => $namaNormal,
        ];
    }

    protected function createImportRow(array $data, array $context): void
    {
        $kurikulumId = $this->kurikulumIdUntukImporProfil($context);

        $profil = ProfilLulusan::query()->create([
            'kurikulum_id' => $kurikulumId,
            'kode' => $this->generateKodeProfil($kurikulumId),
            'nama' => $data['nama'],
            'deskripsi' => $data['deskripsi'] !== '' ? $data['deskripsi'] : '',
            'urutan' => $data['urutan'] !== '' ? (int) $data['urutan'] : null,
        ]);

        $this->simpanIndikatorsProfil($profil, $data['indikator'], gantiSemua: true);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        $profil = ProfilLulusan::query()->findOrFail($existingId);

        $profil->update([
            'nama' => $data['nama'],
            'deskripsi' => $data['deskripsi'] !== '' ? $data['deskripsi'] : '',
            'urutan' => $data['urutan'] !== '' ? (int) $data['urutan'] : $profil->urutan,
        ]);

        if (trim($data['indikator']) !== '') {
            $this->simpanIndikatorsProfil($profil, $data['indikator'], gantiSemua: true);
        }
    }

    protected function generateKodeProfil(string $kurikulumId): string
    {
        $nomor = ProfilLulusan::query()->where('kurikulum_id', $kurikulumId)->count() + 1;

        do {
            $kode = 'PL-'.$nomor;
            $nomor++;
        } while (ProfilLulusan::query()->where('kurikulum_id', $kurikulumId)->where('kode', $kode)->exists());

        return $kode;
    }

    protected function simpanIndikatorsProfil(ProfilLulusan $profil, string $rawIndikator, bool $gantiSemua = false): void
    {
        $items = ProfilLulusanImporParser::parseIndikators($rawIndikator);

        if ($items === []) {
            return;
        }

        if ($gantiSemua) {
            $profil->indikators()->delete();
        }

        foreach ($items as $index => $nama) {
            ProfilIndikator::query()->create([
                'profil_id' => $profil->id,
                'nama' => $nama,
                'deskripsi' => null,
                'urutan' => $index + 1,
            ]);
        }
    }
}
