@if ($import !== null)
    @php
        $konteks = ($import->academicUnit?->nama ?? $import->kode_prodi).' · '.$import->tahun_akademik;
        $kapan = $import->created_at->diffForHumans();
        $gagal = $import->status === 'failed';
    @endphp
    <div style="margin-bottom:16px;padding:12px 16px;border:1px solid rgba(128,128,128,.25);border-radius:12px;background:rgba(128,128,128,.04);font-size:13px;">
        @if ($gagal)
            <span style="color:#991b1b;">
                Percobaan terakhir mengambil data dari Sintesys <strong>gagal</strong>, {{ $kapan }} ({{ $konteks }}).
            </span>
            @if (filled($import->pesan_gagal))
                <div style="margin-top:4px;opacity:.85;">{{ $import->pesan_gagal }}</div>
            @endif
        @else
            <span>
                Terakhir mengambil data dari Sintesys: <strong>{{ $kapan }}</strong> ({{ $konteks }}).
            </span>
        @endif
    </div>
@endif
