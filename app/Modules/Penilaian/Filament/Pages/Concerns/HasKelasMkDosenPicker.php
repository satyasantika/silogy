<?php

namespace App\Modules\Penilaian\Filament\Pages\Concerns;

use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\Mk;
use App\Modules\Penilaian\Services\PenilaianDosenService;
use App\Modules\Penilaian\Support\PenilaianMkTerpilih;
use App\Modules\Penilaian\Support\PenilaianSemesterTerpilih;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pemilihan MK/kelas MK milik dosen pengampu yang sedang login (kartu kelas,
 * ringkasan seluruh kelas, resolusi mk/kelas dari query string atau sesi
 * terpilih) — dipakai bersama oleh halaman Input Nilai (bisa diedit) dan
 * Portofolio (laporan, hanya baca).
 */
trait HasKelasMkDosenPicker
{
    public ?string $mkId = null;

    public ?string $kelasMkId = null;

    public function mount(): void
    {
        $kelasMkIdParam = request()->query('kelas_mk_id');
        $mkIdParam = request()->query('mk_id');

        $kelas = is_string($kelasMkIdParam)
            ? $this->kelasMkQuery(null)->whereKey($kelasMkIdParam)->first()
            : null;

        if ($kelas !== null) {
            $this->mkId = $kelas->mkUnit?->mk_id;
            PenilaianSemesterTerpilih::set($kelas->semester_id);
        } elseif (is_string($mkIdParam)) {
            $this->mkId = $mkIdParam;
        } else {
            $this->mkId = PenilaianMkTerpilih::currentId();
        }

        if ($kelas === null && $this->mkId !== null) {
            $kelas = $this->kelasMkQueryUntukMk($this->mkId, PenilaianSemesterTerpilih::currentId())->first();
        }

        if ($kelas === null) {
            $kelas = $this->kelasMkQuery(PenilaianSemesterTerpilih::currentId())->first();

            if ($kelas !== null) {
                $this->mkId = $kelas->mkUnit?->mk_id;
            }
        }

        PenilaianMkTerpilih::set($this->mkId);

        if ($kelas !== null) {
            $this->kelasMkId = $kelas->id;
            $this->loadMatrix();
        }
    }

    public function updatedKelasMkId(): void
    {
        $this->afterKelasBerubah();
        $this->loadMatrix();
    }

    public function pilihKelas(string $kelasMkId): void
    {
        $kelas = $this->kelasMkQuery(null)->whereKey($kelasMkId)->first();

        if ($kelas === null) {
            return;
        }

        $this->kelasMkId = $kelas->id;
        $this->afterKelasBerubah();
        $this->loadMatrix();
    }

    /**
     * Hook opsional, dipanggil setiap kali kelas berpindah sebelum
     * loadMatrix() dijalankan ulang (mis. InputNilai mereset badge
     * kalkulasi).
     */
    protected function afterKelasBerubah(): void
    {
        //
    }

    public function getMkTerpilihProperty(): ?Mk
    {
        if ($this->mkId === null) {
            return null;
        }

        return Mk::query()->with('mkUnits')->find($this->mkId);
    }

    public function getSemesterTerpilihProperty(): string
    {
        return PenilaianSemesterTerpilih::label();
    }

    /**
     * @return list<array{id: string, kode_kelas: string, jumlah_mahasiswa: int, rata_rata: float|null, sudah_dinilai: bool}>
     */
    public function getKelasCardsProperty(): array
    {
        $mk = $this->getMkTerpilihProperty();
        $user = auth()->user();

        if ($mk === null || ! $user instanceof User) {
            return [];
        }

        return PenilaianDosenService::kelasUntukMk($mk, $user, PenilaianSemesterTerpilih::currentId())
            ->map(fn (KelasMk $kelas): array => array_merge(
                ['id' => $kelas->id],
                PenilaianDosenService::ringkasanKelas($kelas),
            ))
            ->values()
            ->all();
    }

    /**
     * @return array{jumlah_mahasiswa: int, rata_rata: float|null, sudah_dinilai: bool}
     */
    public function getRingkasanSeluruhKelasProperty(): array
    {
        $mk = $this->getMkTerpilihProperty();
        $user = auth()->user();

        if ($mk === null || ! $user instanceof User) {
            return ['jumlah_mahasiswa' => 0, 'rata_rata' => null, 'sudah_dinilai' => false];
        }

        return PenilaianDosenService::ringkasanSeluruhKelas($mk, $user, PenilaianSemesterTerpilih::currentId());
    }

    /**
     * @return Builder<KelasMk>
     */
    protected function kelasMkQueryUntukMk(string $mkId, ?string $semesterId): Builder
    {
        return $this->kelasMkQuery($semesterId)
            ->whereHas('mkUnit', fn (Builder $query): Builder => $query->where('mk_id', $mkId));
    }

    /**
     * @return Builder<KelasMk>
     */
    protected function kelasMkQuery(?string $semesterAktifId): Builder
    {
        $query = KelasMk::query()
            ->where('dosen_pengampu_id', auth()->id());

        if ($semesterAktifId !== null) {
            $query->where('semester_id', $semesterAktifId);
        }

        return $query->orderBy('kode_kelas');
    }

    abstract public function loadMatrix(): void;
}
