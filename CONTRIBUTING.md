# Panduan Kontribusi SILOGY

Terima kasih telah berkontribusi pada SILOGY. Ikuti konvensi di bawah agar PR mudah direview dan selaras dengan [PreVibeCoding §4.6 & §6](docs/SILOGY_PreVibeCoding_v6.md).

## Branching Strategy

| Branch | Tujuan | Aturan |
|---|---|---|
| `main` | Production-ready | PR-only; protected; tag `vX.Y.Z` |
| `dev` | Integrasi sprint berjalan | Base branch default untuk PR fitur |
| `feature/<modul>-<kode-task>` | Pengerjaan task sprint | Squash-merge ke `dev` |
| `hotfix/<id>` | Patch produksi mendesak | Merge ke `main` **dan** `dev` |
| `release/<x.y.z>` | Stabilisasi sebelum rilis | Hanya bugfix & dokumentasi |

**Contoh nama branch:**

- `feature/kurikulum-S2-05`
- `feature/penilaian-S3-07`
- `hotfix/S4-01-null-nilai`

## Conventional Commits

Format: `<type>(<scope opsional>): <deskripsi imperatif>`

| Type | Kapan dipakai |
|---|---|
| `feat` | Fitur baru |
| `fix` | Perbaikan bug |
| `refactor` | Refactor tanpa ubah perilaku |
| `docs` | Dokumentasi saja |
| `test` | Penambahan/perbaikan test |
| `chore` | Tooling, dependensi, config |
| `ci` | Perubahan pipeline CI |
| `perf` | Optimasi performa |

**Contoh:**

```
feat(kurikulum): tambah state setdosenmk
fix(nilai): hindari pembagian nol di SubcpmkCalculator
test(cpl): tambah edge case nilai null
```

## Pull Request

1. Buat branch dari `dev`: `feature/<modul>-<kode-task>`.
2. 1 PR = 1 task (target ≤ 400 baris diff murni). Lebih besar → split PR.
3. Isi template PR (`.github/pull_request_template.md`) **lengkap**.
4. **Wajib** lampirkan screenshot UI jika ada perubahan Filament/Livewire.
5. Centang checklist **Definition of Done (DoD) §10.1** di template PR.
6. Pastikan `vendor/bin/pint --test` dan `vendor/bin/phpstan analyse` lulus.
7. Minta review minimal **1 reviewer** di luar pembuat PR.
8. Merge ke `dev` dengan **squash merge**.

## Definition of Done (ringkasan)

Lihat checklist lengkap di PR template dan [PreVibeCoding §10](docs/SILOGY_PreVibeCoding_v6.md#10-definition-of-done-dod).

Setiap PR wajib memenuhi minimal:

- Kode di modul Vertical Slice yang benar
- Test happy path + edge case
- Policy/authorization ter-cover
- Pint & Larastan level 6 bersih
- Screenshot UI (jika ada perubahan tampilan admin)

## Lingkungan Dev

- **Windows:** FlyEnv — akses via `http://silogy.test`
- **Docker (opsional):** lihat `docker-compose.yml` dan PreVibeCoding §4

## Pertanyaan

Bila ada konflik antar dokumen referensi di `docs/`, buka issue atau diskusikan di standup sebelum merge.
