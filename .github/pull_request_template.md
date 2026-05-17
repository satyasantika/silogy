## Deskripsi

<!-- Jelaskan perubahan, modul yang disentuh, dan task ID (mis. S2-05) -->

## Tipe perubahan

- [ ] `feat` — fitur baru
- [ ] `fix` — perbaikan bug
- [ ] `refactor` / `chore` / `docs` / `test` / `ci` / `perf`

## Screenshot UI

<!-- WAJIB untuk perubahan Filament/Livewire. Tempel gambar atau link. -->

| Sebelum | Sesudah |
|---|---|
| | |

## Checklist Definition of Done (§10.1 PreVibeCoding)

- [ ] **Code** — perubahan di modul Vertical Slice yang sesuai
- [ ] **Test** — minimal 1 happy path + 1 edge case (`php artisan test --filter=...`)
- [ ] **Migration & Seeder** — `migrate:fresh --seed` jalan tanpa error
- [ ] **Validation** — FormRequest / Filament Rule lengkap (tanpa validasi di controller)
- [ ] **Authorization** — Policy / Filament Shield gate ada & ter-test
- [ ] **Error handling** — domain exception ter-render dengan envelope §5.1
- [ ] **Logging** — operasi penting ter-log dengan `request_id`
- [ ] **Pint clean** — `vendor/bin/pint --test` exit 0
- [ ] **Larastan clean** — `vendor/bin/phpstan analyse` exit 0 (level 6)
- [ ] **UI screenshot** — dilampirkan di PR (jika ada perubahan Filament)
- [ ] **Docs** — README/CHANGELOG diperbarui bila perilaku publik berubah
- [ ] **Review** — ≥ 1 reviewer approve

## Cara uji

```bash
# contoh
php artisan test --filter=NamaTest
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

## Catatan reviewer

<!-- Dependensi, risiko migrasi, atau hal yang perlu diperhatikan -->
