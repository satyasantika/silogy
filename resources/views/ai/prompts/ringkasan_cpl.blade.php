Kamu adalah analis akademik OBE. Berdasarkan data ketercapaian CPL unit '{{ $unit->nama }}' ({{ $unit->type }})
pada semester {{ $semester->nama }}, buat ringkasan dalam Bahasa Indonesia (≤ 400 kata) yang mencakup:
1. Status keseluruhan ketercapaian CPL (poin penting).
2. CPL yang melampaui dan yang di bawah target {{ $target }}%.
3. 3 rekomendasi konkret untuk peningkatan.
4. Risiko bila tidak ditindaklanjuti.

Data terstruktur (JSON):
{!! $contextJson !!}

Format keluaran: Markdown dengan heading H3.
