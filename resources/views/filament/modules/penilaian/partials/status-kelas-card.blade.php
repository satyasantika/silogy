{{-- Baris status pada kartu kelas: jumlah mahasiswa, rata-rata, dan
     persentase nilai terisi bila belum lengkap. --}}
{{ $kelas['jumlah_mahasiswa'] }} mhs
@if ($kelas['sudah_dinilai'])
    · rata-rata {{ $kelas['rata_rata'] }}
    @if (($kelas['status_kelengkapan'] ?? null) === 'sebagian')
        · {{ (int) floor($kelas['nilai_terisi'] / max(1, $kelas['nilai_total']) * 100) }}% terisi
    @endif
@else
    · Belum dinilai
@endif
