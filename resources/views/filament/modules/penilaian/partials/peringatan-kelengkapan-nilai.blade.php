{{-- Banner "Data belum lengkap" untuk satu kelas. Muncul hanya bila
     sebagian (bukan nol, bukan semua) nilai sudah diisi. Nilai 0 dihitung
     terisi; hanya sel kosong yang dihitung belum. --}}
@if (($kelengkapan['status_kelengkapan'] ?? null) === 'sebagian')
    <div
        data-silogy="peringatan-kelengkapan-nilai"
        role="alert"
        class="silogy-tone-warning"
        style="padding:10px 14px;border-radius:8px;font-size:13px;line-height:1.5;margin:12px 0;"
    >
        <strong>Data belum lengkap:</strong>
        {{ $kelengkapan['nilai_total'] - $kelengkapan['nilai_terisi'] }} dari {{ $kelengkapan['nilai_total'] }} nilai belum diisi
        ({{ $kelengkapan['mahasiswa_belum_lengkap'] }} mahasiswa).
        Ketercapaian dihitung hanya dari nilai yang sudah ada — sel kosong tidak dianggap 0.
    </div>
@endif
