<?php

namespace App\Modules\Auth\Support;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as PenggunaGoogle;

/**
 * Masuk dengan Google untuk dosen, tanpa permintaan aktivasi ke admin.
 *
 * Aturan:
 *  1. Hanya surel Google terverifikasi pada domain kampus (`services.google.domain`).
 *  2. Dicocokkan lewat `google_id`, lalu surel. Akun lama ber-peran langsung masuk.
 *  3. Akun yang belum punya peran diperiksa ke Sintesys: bila surelnya mengampu
 *     kelas pada tahun akademik aktif, otomatis diberi peran "Dosen Pengampu"
 *     dan direlasikan ke prodi kelasnya.
 *  4. Selain itu akun tetap dibuat TANPA peran supaya admin tinggal memberi
 *     peran, tetapi login ditolak sampai peran ada. Tidak ada peran yang
 *     diberikan bila Sintesys tak dapat dijangkau (gagal tertutup).
 */
class MasukDenganGoogle
{
    public const PERAN_DOSEN = 'Dosen Pengampu';

    public function __construct(private readonly VerifikasiDosenSintesys $sintesys) {}

    public function jalankan(PenggunaGoogle $google): User
    {
        $email = strtolower(trim((string) $google->getEmail()));
        $raw = $google instanceof AbstractUser ? $google->getRaw() : [];
        $terverifikasi = filter_var($raw['email_verified'] ?? $raw['verified_email'] ?? false, FILTER_VALIDATE_BOOL);

        if ($email === '' || ! $terverifikasi) {
            throw new MasukGoogleDitolak('Surel akun Google Anda belum terverifikasi.');
        }

        $domain = strtolower((string) config('services.google.domain'));

        if ($domain !== '') {
            $hd = strtolower((string) ($raw['hd'] ?? ''));

            if (! Str::endsWith($email, '@'.$domain) || ($hd !== '' && $hd !== $domain)) {
                throw new MasukGoogleDitolak("Gunakan akun Google @{$domain} untuk masuk.");
            }
        }

        $googleId = (string) $google->getId();

        $user = User::query()->where('google_id', $googleId)->first()
            ?? User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user !== null && $user->google_id !== null && $user->google_id !== $googleId) {
            throw new MasukGoogleDitolak('Akun ini sudah ditautkan ke akun Google lain. Hubungi admin.');
        }

        if ($user === null) {
            $user = User::query()->create([
                'email' => $email,
                'full_name' => $google->getName() ?: Str::before($email, '@'),
                'password' => Str::random(40),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        if ($user->google_id === null) {
            $user->forceFill(['google_id' => $googleId])->save();
        }

        if ($this->tanpaPeran($user)) {
            $this->berikanPeranDosenBilaTerbukti($user);
        }

        if ($this->tanpaPeran($user)) {
            throw new MasukGoogleDitolak(
                'Akun Anda sudah terdaftar, tetapi belum memiliki peran. '
                .'Hubungi admin program studi untuk pemberian peran, lalu masuk kembali.'
            );
        }

        return $user;
    }

    private function tanpaPeran(User $user): bool
    {
        return $user->roles()->doesntExist();
    }

    private function berikanPeranDosenBilaTerbukti(User $user): void
    {
        $hasil = $this->sintesys->periksa((string) $user->email);

        if ($hasil['status'] !== VerifikasiDosenSintesys::DOSEN) {
            return;
        }

        $user->assignRole(self::PERAN_DOSEN);

        if ($hasil['kode_prodi'] === []) {
            return;
        }

        $unitIds = AcademicUnit::query()
            ->whereIn('code', $hasil['kode_prodi'])
            ->where('type', 'study_program')
            ->pluck('id');

        foreach ($unitIds as $unitId) {
            AcademicUnitUser::query()->updateOrCreate(
                ['academic_unit_id' => $unitId, 'user_id' => $user->id],
                [
                    'status_pimpinan' => false,
                    'status_tim_kurikulum' => false,
                    'status_validator_nilai' => false,
                ],
            );
        }
    }
}
