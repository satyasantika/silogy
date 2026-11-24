# Panduan Validator Nilai

Alamat SILOGY: **https://silogy.unsil.ac.id/login**

Validator Nilai memeriksa nilai per kelas dan **memvalidasi**-nya. Pada kurikulum yang **mewajibkan validasi**, Laporan Lengkap sebuah kelas baru bisa dicetak setelah kelas itu divalidasi, sehingga cetakan menjadi bukti bahwa hasil penilaian dosen pengampu sudah diperiksa (misalnya oleh pimpinan prodi atau UPM). Kurikulum yang tidak mewajibkan validasi tidak melewati proses ini.

## Masuk

![Halaman masuk SILOGY](aset/login.png)

1. Buka alamat di atas, masukkan **Username** dan **Password** Anda, lalu klik **Masuk ke SILOGY**.
2. Bila akun Anda punya lebih dari satu peran, pilih peran **Validator Nilai**.

## Kewenangan utama

- Melihat daftar kelas dalam cakupan Anda, per semester, termasuk semester lampau.
- Membuka laporan kelas dalam tampilan **hanya baca**.
- **Memvalidasi** kelas, dan **membatalkan validasi**.
- Mencetak Laporan Lengkap kelas yang sudah tervalidasi.

Anda tidak mengubah nilai, bobot, maupun peserta. Bila ada yang janggal, cukup jangan validasi, lalu sampaikan ke dosen pengampu atau Koordinator MK di luar sistem.

## Siapa yang menjadi validator

Hak memvalidasi ditentukan **per kurikulum**, bukan lewat penugasan unit. Pada kurikulum yang validasinya menyala, yang berhak memvalidasi kelas kurikulum itu adalah:

1. **pimpinan prodi** pemilik kurikulum (otomatis);
2. **Tim Kurikulum prodi** pemilik kurikulum (otomatis);
3. **validator tambahan** yang dipilih Tim Kurikulum atau Admin pada kolom **Validator tambahan (mis. UPM)** di formulir kurikulum.

Penugasan pimpinan atau Tim Kurikulum harus berada pada **unit prodi kurikulum itu sendiri**; penugasan di fakultas atau universitas tidak otomatis berhak.

Peran **Validator Nilai** terpasang otomatis pada orang yang berhak dan dilepas otomatis bila ia tidak lagi berhak pada kurikulum mana pun yang validasinya menyala. Orang yang dipilih sebagai validator tambahan boleh belum berperan Validator Nilai; perannya diberikan saat kurikulum disimpan.

Peran ini tidak dapat diberikan lewat impor pengguna; impor dengan peran **Validator Nilai** ditolak dan diarahkan ke formulir kurikulum.

Pimpinan prodi dan Tim Kurikulum yang ingin memvalidasi harus memilih peran **Validator Nilai** pada pemilih peran saat masuk. Selama memakai peran Kaprodi atau Tim Kurikulum, menu Validasi Nilai tidak tampil.

## Kurikulum yang validasinya mati

Tim Kurikulum atau Admin dapat mematikan sakelar **Kurikulum ini divalidasi** kapan saja. Akibatnya untuk kurikulum itu:

- Laporan Lengkap semua kelasnya **bebas cetak**, tanpa tanda air.
- Tidak ada validasi baru yang bisa dilakukan dan perubahan data tidak lagi menggugurkan validasi.
- Riwayat validasi lama **tidak dihapus**: kelas yang sudah **Tervalidasi** atau **Perlu validasi ulang** tetap berlabel demikian sebagai catatan. Blok **Divalidasi oleh** tidak dicetak.

Menyalakan kembali sakelar membuat aturan cetak berlaku lagi; status dihitung ulang dari isi laporan saat kelas dibuka.

## Cakupan

Cakupan Anda adalah kelas dari **kurikulum yang menunjuk Anda** sebagai validator (otomatis atau tambahan), yang validasinya menyala.

- Anda memvalidasi semua kelas kurikulum itu, termasuk kelas dari mata kuliah milik fakultas atau universitas yang ditawarkan di kurikulum tersebut.
- Kelas di luar cakupan tidak tampil dan tidak bisa dibuka.

## Menu yang tersedia

- **Penilaian → Validasi Nilai**.

Menu ini hanya muncul untuk peran Validator Nilai.

![Menu Validator Nilai](aset/sidebar-validator-nilai.png)

## Tugas yang sering dilakukan

### 1. Memilih semester dan kelas
1. Buka **Penilaian → Validasi Nilai**.
2. Pilih **Semester**. Semester lampau juga tersedia.
3. Daftar menampilkan mata kuliah, kelas, dosen, jumlah nilai terisi (misalnya `31/40`), dan status validasi.

![Daftar kelas dalam cakupan validator](aset/validator-daftar.png)

Daftar hanya punya tombol **Lihat**. Tombol Validasi tidak ada di daftar, supaya kelas tidak bisa divalidasi tanpa membuka laporannya.

### 2. Memeriksa dan memvalidasi
1. Tekan **Lihat** pada kelas yang akan diperiksa.
2. Telaah laporan kelas pada tab-tabnya: nilai mahasiswa, ketercapaian CPL, distribusi nilai, rencana evaluasi, dan Analisis & RTL.
3. Bila sudah yakin, tekan **Validasi** lalu konfirmasi.
4. Status kelas berubah menjadi **Tervalidasi**.

![Laporan kelas pada tampilan validator](aset/validator-laporan.png)

**Validasi** hanya bisa ditekan bila:
- nilai kelas sudah **lengkap** (tidak ada sel kosong), dan
- penugasan asesmen sudah selesai (bobot komponen berjumlah 100% dan seluruhnya terpetakan ke Sub-CPMK).

Bila syarat belum terpenuhi, tombol **Validasi** tidak muncul; alasannya tampil sebagai catatan pada halaman laporan kelas.

Anda **tidak dapat** memvalidasi kelas yang Anda ampu, maupun kelas dari mata kuliah yang Anda koordinatori, walaupun Anda pimpinan prodi atau Tim Kurikulum yang otomatis berhak, karena koordinator yang menentukan bobot dan pemetaan yang menjadi dasar laporan. Super Admin pun tidak otomatis boleh memvalidasi.

Jika data berubah setelah laporan Anda buka (misalnya dosen menyimpan nilai saat Anda memeriksa), validasi ditolak dengan pesan agar Anda memeriksa kembali. Laporan dimuat ulang; periksa lalu validasi lagi.

### 3. Membatalkan validasi
1. Buka kelas lewat **Lihat**.
2. Tekan **Batalkan validasi** lalu konfirmasi.
3. Status kembali menjadi **Belum divalidasi** dan laporan tidak bisa dicetak sampai divalidasi lagi.

### 4. Mencetak
Pada kelas yang **Tervalidasi**, tombol **Cetak** di tab Laporan Lengkap aktif. Pada kurikulum yang validasinya menyala, cetakan memuat blok **Divalidasi oleh** berisi nama Anda, jabatan penugasan (bila ada), serta tanggal dan jam validasi.

## Arti status validasi

| Status | Arti | Cetak |
|---|---|---|
| **Belum divalidasi** | Kelas belum pernah divalidasi, atau validasinya dibatalkan. | Tombol terkunci. Cetakan apa pun bertanda air **BELUM DIVALIDASI**. |
| **Tervalidasi** | Isi laporan sama persis dengan yang Anda validasi. | Tombol aktif. Cetakan memuat blok **Divalidasi oleh**. |
| **Perlu validasi ulang** | Pernah divalidasi, tetapi isi laporan berubah sesudahnya. | Tombol terkunci. Cetakan bertanda air **BELUM DIVALIDASI**. |

## Kapan validasi gugur

Validasi gugur otomatis, menjadi **Perlu validasi ulang**, bila isi laporan berubah, yaitu:

- nilai mahasiswa diubah, diimpor, atau dihapus;
- bobot komponen penilaian berubah;
- pemetaan komponen ke Sub-CPMK/CPMK/CPL berubah;
- peserta kelas bertambah atau berkurang;
- isi **Analisis & RTL** (analisis, rencana tindak lanjut, catatan umum) berubah;
- penugasan asesmen tidak lagi selesai;
- identitas kelas berubah: dosen pengampu, nama/kode MK, SKS, atau semester.

Menekan **Simpan** di Input Nilai tanpa mengubah satu nilai pun **tidak** menggugurkan validasi. Nilai tidak dikunci setelah validasi; dosen tetap bisa mengubahnya, hanya saja kelas itu lalu perlu divalidasi ulang. Selama ada nilai yang sudah diketik atau ditempel di Input Nilai tetapi belum disimpan, tombol **Cetak** terkunci dan cetakan bertanda air **BELUM DIVALIDASI**; simpan perubahannya, lalu minta validasi ulang.

Catatan tentang daftar: perubahan nilai langsung tampak di daftar. Perubahan lain (bobot, pemetaan, peserta, Analisis & RTL, identitas kelas) baru terdeteksi saat laporan kelas itu dibuka, sehingga daftar bisa masih menampilkan **Tervalidasi** sampai Anda membukanya. Cetakan tetap aman, sebab mencetak selalu lewat laporan yang sudah diperiksa terhadap data terkini.

## Berlaku untuk semua kelas

Pada kurikulum yang validasinya menyala, aturan cetak ini berlaku untuk **semua kelas di semua semester**, termasuk kelas lama. Kelas lama berstatus **Belum divalidasi** sampai Anda memvalidasinya.

## Praktik baik
- Buka dan telaah laporan sebelum menekan Validasi; jangan memvalidasi dari daftar saja.
- Validasi setelah dosen dan Koordinator MK selesai mengubah data kelas, supaya tidak gugur berulang.
- Setelah dosen memperbaiki nilai atas permintaan Anda, buka kembali laporannya dan validasi ulang.
- Komunikasi perbaikan dilakukan di luar sistem; SILOGY tidak punya alur tolak dengan catatan.

## Catatan umum

Peran ini tidak menginput data kurikulum maupun nilai. Bila menemukan kekeliruan, koordinasikan dengan Dosen Pengampu atau Koordinator MK kelas terkait.
