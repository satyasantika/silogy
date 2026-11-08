{{-- Blok F laporan kelas: Analisis & Rencana Tindak Lanjut per CPL yang
     tidak tercapai (+ catatan umum). Dosen pengampu mengisi lewat formulir
     (tidak tercetak); versi teks di bawahnya yang tercetak, dan yang tampil
     di layar bagi pembaca saja (koordinator MK). --}}
@php
    $boleh = $this->bolehMengisiRtl();
    $blok = $this->blokRtl;
    $kosong = '— belum diisi —';
@endphp

<section class="silogy-laporan-blok" data-silogy="laporan-blok-f">
    <div class="silogy-laporan-blok__judul">F. Analisis &amp; Rencana Tindak Lanjut</div>

    @if ($blok['semua_tercapai'])
        <p style="font-size:13px;margin:0 0 10px;">
            <strong>Semua CPL tercapai</strong> (rata-rata kelas ≥ target {{ $this->targetCapaianLulusan }}).
        </p>
    @elseif ($blok['baris'] === [])
        <p style="font-size:13px;opacity:.75;margin:0 0 10px;">Belum ada nilai CPL untuk dianalisis.</p>
    @endif

    @if ($boleh)
        <form wire:submit="simpanCatatanRtl" class="silogy-laporan-cetak-aksi" data-silogy="form-rtl" style="display:flex;flex-direction:column;gap:12px;margin-bottom:12px;">
            @foreach ($blok['baris'] as $baris)
                <div wire:key="form-rtl-{{ $baris['cpl_id'] }}" style="border:1px solid rgba(128,128,128,.25);border-radius:10px;padding:12px;">
                    <div style="font-size:13px;margin-bottom:8px;">
                        <strong>{{ $baris['kode'] }}</strong> — {{ $baris['deskripsi'] }}
                        <span class="silogy-tone-warning" style="display:inline-block;margin-left:6px;padding:1px 8px;border-radius:999px;font-size:11px;font-weight:600;">
                            {{ number_format($baris['rata_rata'], 2, ',', '.') }} &lt; target {{ $this->targetCapaianLulusan }}
                        </span>
                    </div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;" for="rtl-analisis-{{ $baris['cpl_id'] }}">Analisis penyebab</label>
                    <textarea id="rtl-analisis-{{ $baris['cpl_id'] }}" wire:model="isianRtl.cpl.{{ $baris['cpl_id'] }}.analisis" rows="3" class="fi-input" style="width:100%;font-size:13px;padding:8px;border:1px solid rgba(128,128,128,.35);border-radius:8px;background:transparent;"></textarea>
                    <label style="display:block;font-size:12px;font-weight:600;margin:8px 0 4px;" for="rtl-rencana-{{ $baris['cpl_id'] }}">Rencana tindak lanjut</label>
                    <textarea id="rtl-rencana-{{ $baris['cpl_id'] }}" wire:model="isianRtl.cpl.{{ $baris['cpl_id'] }}.rencana" rows="3" class="fi-input" style="width:100%;font-size:13px;padding:8px;border:1px solid rgba(128,128,128,.35);border-radius:8px;background:transparent;"></textarea>
                </div>
            @endforeach

            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;" for="rtl-umum">Catatan umum kelas (opsional)</label>
                <textarea id="rtl-umum" wire:model="isianRtl.umum" rows="2" style="width:100%;font-size:13px;padding:8px;border:1px solid rgba(128,128,128,.35);border-radius:8px;background:transparent;"></textarea>
            </div>

            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <x-filament::button type="submit" size="sm" icon="heroicon-o-check">Simpan catatan</x-filament::button>
                @if ($blok['terakhir'])
                    <span style="font-size:12px;opacity:.7;">Disimpan oleh {{ $blok['terakhir']['nama'] }} pada {{ $blok['terakhir']['waktu'] }}</span>
                @endif
            </div>
        </form>
    @endif

    {{-- Versi teks: tercetak selalu; di layar hanya bagi pembaca (bukan pengisi). --}}
    <div @class(['silogy-rtl-teks', 'silogy-hanya-cetak' => $boleh]) data-silogy="teks-rtl">
        @foreach ($blok['baris'] as $baris)
            <div data-silogy="blok-rtl-cpl-{{ $baris['kode'] }}" style="border:1px solid rgba(128,128,128,.25);border-radius:10px;padding:12px;margin-bottom:10px;font-size:13px;line-height:1.5;">
                <div style="margin-bottom:6px;">
                    <strong>{{ $baris['kode'] }}</strong> — {{ $baris['deskripsi'] }}
                    ({{ number_format($baris['rata_rata'], 2, ',', '.') }}; target {{ $this->targetCapaianLulusan }})
                </div>
                <div><strong>Analisis penyebab:</strong>
                    @if (filled($baris['analisis']))
                        <span style="white-space:pre-line;">{{ $baris['analisis'] }}</span>
                    @else
                        <span class="silogy-tone-warning" style="padding:0 6px;border-radius:4px;">{{ $kosong }}</span>
                    @endif
                </div>
                <div style="margin-top:4px;"><strong>Rencana tindak lanjut:</strong>
                    @if (filled($baris['rencana']))
                        <span style="white-space:pre-line;">{{ $baris['rencana'] }}</span>
                    @else
                        <span class="silogy-tone-warning" style="padding:0 6px;border-radius:4px;">{{ $kosong }}</span>
                    @endif
                </div>
            </div>
        @endforeach

        @if (filled($blok['umum']))
            <div style="font-size:13px;line-height:1.5;"><strong>Catatan umum:</strong> <span style="white-space:pre-line;">{{ $blok['umum'] }}</span></div>
        @endif

        @if ($blok['terakhir'] && ! $boleh)
            <div style="font-size:11px;opacity:.65;margin-top:6px;">Ditulis oleh {{ $blok['terakhir']['nama'] }} · {{ $blok['terakhir']['waktu'] }}</div>
        @endif
    </div>
</section>
