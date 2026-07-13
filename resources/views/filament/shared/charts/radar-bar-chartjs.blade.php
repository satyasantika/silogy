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

        window.renderRadarSilogy = function (canvas, dataset, color) {
            if (! canvas || typeof Chart === 'undefined') {
                return;
            }

            if (canvas._radarChartInstance) {
                canvas._radarChartInstance.destroy();
            }

            canvas._radarChartInstance = new Chart(canvas, {
                type: 'radar',
                data: {
                    labels: dataset.labels,
                    datasets: [{
                        label: 'Nilai',
                        data: dataset.data,
                        backgroundColor: color + '33',
                        borderColor: color,
                        pointBackgroundColor: color,
                    }],
                },
                options: {
                    // Tinggi kanvas dihitung dari LEBAR kontainer dibagi
                    // aspectRatio (bukan tinggi tetap di CSS) — lebih
                    // tahan banting terhadap kanvas yang sempat dibuat
                    // saat kontainernya masih tersembunyi (di dalam tab
                    // atau modal yang belum aktif), karena hanya
                    // butuh lebar yang benar untuk menghitung ulang
                    // tinggi yang proporsional lewat resize().
                    aspectRatio: 1.3,
                    scales: {
                        r: {
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
