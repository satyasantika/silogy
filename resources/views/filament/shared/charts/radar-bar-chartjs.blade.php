@once
    {{-- Chart.js bawaan Filament dibundel privat di dalam paketnya (tidak
        ter-expose sebagai window.Chart), jadi setiap halaman yang perlu
        grafik jaring laba-laba/batang memuat Chart.js sendiri lewat CDN. --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script>
        // Kanvas grafik bisa saja masih tersembunyi (display:none) saat
        // dibuat — mis. di dalam sub-tab yang belum aktif, atau di dalam
        // modal yang baru dimorph ke DOM sebelum benar-benar terbuka.
        // Chart.js membaca ukuran kontainer saat itu juga, jadi kalau masih
        // 0x0 grafiknya tampak kosong walau datanya benar — paksa resize
        // begitu frame berikutnya (setelah tab/modal terlihat) dan begitu
        // modal manapun terbuka, untuk berjaga-jaga.
        function paksaResizeSetelahTerlihat(chart) {
            requestAnimationFrame(() => chart.resize());
            window.addEventListener('open-modal', () => chart.resize(), { once: true });
        }

        function bungkusLabelRadarSilogy(label, maxLen) {
            if (Array.isArray(label)) {
                return label;
            }

            const text = String(label ?? '').trim();

            if (text === '' || text.length <= maxLen) {
                return text;
            }

            const words = text.split(/\s+/);
            const lines = [];
            let current = '';

            for (const word of words) {
                const next = current === '' ? word : `${current} ${word}`;

                if (next.length > maxLen && current !== '') {
                    lines.push(current);

                    if (word.length > maxLen) {
                        const chunks = word.match(new RegExp(`.{1,${maxLen}}`, 'g')) || [word];
                        lines.push(...chunks.slice(0, -1));
                        current = chunks[chunks.length - 1];
                    } else {
                        current = word;
                    }
                } else {
                    current = next;
                }
            }

            if (current !== '') {
                lines.push(current);
            }

            return lines.length > 1 ? lines : text;
        }

        window.renderRadarSilogy = function (canvas, dataset, color) {
            if (! canvas || typeof Chart === 'undefined') {
                return;
            }

            if (canvas._radarChartInstance) {
                canvas._radarChartInstance.destroy();
            }

            const maxLen = Number(dataset.label_max_len ?? dataset.labelMaxLen ?? 20);
            const labels = (dataset.labels || []).map((label) => bungkusLabelRadarSilogy(label, maxLen));

            canvas._radarChartInstance = new Chart(canvas, {
                type: 'radar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Nilai',
                        data: dataset.data,
                        backgroundColor: color + '33',
                        borderColor: color,
                        pointBackgroundColor: color,
                    }],
                },
                options: {
                    // Kotak plot 1:1; tinggi wrapper (aspect-ratio CSS)
                    // mengikuti lebar kartu agar E1–E4 proporsional sama.
                    // Padding memberi ruang label tanpa mengecilkan plot.
                    aspectRatio: 1,
                    maintainAspectRatio: true,
                    layout: {
                        padding: {
                            top: 14,
                            right: 18,
                            bottom: 14,
                            left: 18,
                        },
                    },
                    scales: {
                        r: {
                            min: 0,
                            max: 100,
                            pointLabels: {
                                font: {
                                    size: 11,
                                },
                            },
                        },
                    },
                    plugins: {
                        legend: {
                            display: false,
                        },
                    },
                },
            });

            paksaResizeSetelahTerlihat(canvas._radarChartInstance);
        };

        window.renderBarSilogy = function (canvas, dataset, color) {
            if (! canvas || typeof Chart === 'undefined') {
                return;
            }

            if (canvas._barChartInstance) {
                canvas._barChartInstance.destroy();
            }

            canvas._barChartInstance = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: dataset.labels,
                    datasets: [{
                        label: 'Nilai',
                        data: dataset.data,
                        backgroundColor: color + '55',
                        borderColor: color,
                        borderWidth: 1,
                    }],
                },
                options: {
                    aspectRatio: 2.2,
                    scales: {
                        y: {
                            min: 0,
                            max: 100,
                        },
                    },
                    plugins: {
                        legend: {
                            display: false,
                        },
                    },
                },
            });

            paksaResizeSetelahTerlihat(canvas._barChartInstance);
        };
    </script>
@endonce
