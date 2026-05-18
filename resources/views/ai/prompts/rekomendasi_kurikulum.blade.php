Kamu adalah perancang kurikulum OBE. Berdasarkan data ketercapaian CPL unit '{{ $unit->nama }}' ({{ $unit->type }})
pada semester {{ $semester->nama }}, susun rekomendasi perbaikan kurikulum dalam Bahasa Indonesia (≤ 500 kata) yang mencakup:
1. Evaluasi kesesuaian pemetaan CPL–MK–CPMK berdasarkan MK dengan capaian terendah.
2. Usulan penyesuaian bobot, penambahan/penggantian MK, atau aktivitas pembelajaran.
3. Prioritas intervensi kurikulum (urutan 1–3) dengan justifikasi data.
4. Indikator keberhasilan yang dapat dipantau semester berikutnya.

Target capaian lulusan: {{ $target }}%.

Data terstruktur (JSON):
{!! $contextJson !!}

Format keluaran: Markdown dengan heading H3.
