# Task 1 report: penanda rincian dan layanannya

Dibuat: migrasi `rincian_nilai_kelas`, model `RincianNilaiKelas`, `RincianNilaiService` (rincianUntukKelas, bisaDirinci, nyalakan, matikan, nilaiAsesmen), entri `MODEL_TURUNAN` + komentar CASCADE, helper `siapkanKelasDuaSubcpmk` di fixture, 6 tes di `RincianNilaiServiceTest.php`.
Tes: berkas ini + tests/Feature/Simulasi = 213 lulus; suite penuh 1263 lulus; phpstan bersih; pint bersih.
Keputusan: `bisaDirinci` menghitung semua pivot asesmen (lintas semester); `rincianUntukKelas` menyaring per semester kelas via `komponenUntukKelas`. Peserta tanpa angka (null) dilewati saat menulis. Layanan tidak menyentuh `nilai_angka` (tanggung jawab tugas lain).
Catatan: implementasi ditulis bersama tes sebelum tes dijalankan, jadi fase "merah" tidak diamati.

## Laporan perbaikan (tinjauan)
Berubah di `RincianNilaiService`: `nyalakan` kembali lebih awal bila penanda sudah ada (`wasRecentlyCreated` salah), jadi tidak menimpa angka per Sub-CPMK; `bisaDirinci(KelasMk, KomponenPenilaian)` kini menghitung pivot pada semester kelas (set yang sama dengan `rincianUntukKelas`), dan `nyalakan` memakainya.
Tes baru/ubah di `RincianNilaiServiceTest.php`: nyalakan dua kali setelah sunting sub-skor; asesmen 2 pivot total tetapi 1 di semester kelas ditolak (kunci `rincian`); matikan 80/60 bobot 10/30 menjadi 65.0 di semua pivot; tes urutan pivot kini dibuat dengan urutan buat berbeda dari urutan kode. Tes baru gagal dahulu (TypeError/ketidakcocokan) sebelum perbaikan.
Perintah: `vendor/bin/pest tests/Feature/Penilaian/RincianNilaiServiceTest.php tests/Feature/Simulasi` -> 216 lulus (1062 assertion). phpstan: No errors. pint: bersih.
