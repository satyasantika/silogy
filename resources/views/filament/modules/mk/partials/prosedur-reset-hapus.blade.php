@php
    $untukMk = ($jenis ?? 'mk-unit') === 'mk';
@endphp
<div
    data-silogy="prosedur-reset-hapus"
    style="margin-bottom:1rem;padding:12px 14px;border:1px solid rgba(217,119,6,.45);border-radius:10px;background:rgba(245,158,11,.08);font-size:13px;line-height:1.55;"
>
    <div style="font-weight:700;margin-bottom:6px;">Prosedur reset dan hapus mata kuliah</div>
    <ol style="margin:0 0 8px 18px;padding:0;">
        <li>
            <strong>Reset data</strong> — tombol <em>{{ $untukMk ? 'Reset penawaran' : 'Reset data MK' }}</em> di kanan atas menghapus
            seluruh <strong>kontrak kelas, peserta, dan nilai/penilaian</strong>
            {{ $untukMk ? 'pada semua penawaran mata kuliah ini yang berada di lingkup prodi Anda' : 'pada penawaran mata kuliah ini' }}.
            Anda diminta mengetik {{ $untukMk ? 'nama mata kuliah' : 'kode MK unit' }} sebagai konfirmasi.
        </li>
        <li>
            <strong>Hapus</strong> — tombol <em>Hapus</em> baru tersedia setelah semua data kontrak dan penilaian kosong.
            Penghapusan ikut menghapus seluruh relasinya{{ $untukMk ? ' (penawaran, CPMK, Sub-CPMK, komponen asesmen, dan pemetaan CPL/BoK)' : ' dan menghitung ulang capaian CPL' }}.
        </li>
    </ol>
    <div style="opacity:.85;">
        Reset dan hapus <strong>tidak dapat dibatalkan</strong>; keduanya tercatat di log aktivitas.
        @if ($untukMk)
            Penawaran milik prodi lain yang masih berisi data harus direset oleh Tim Kurikulum prodi tersebut sebelum MK dapat dihapus.
        @endif
    </div>
</div>
