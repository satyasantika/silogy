<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * CPL, BoK, MK, dan mk_units diikat ke kurikulum_id agar tiap kurikulum
 * punya paket data sendiri (kurikulum baru kosong).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['cpl', 'bok', 'mk', 'mk_units'] as $table) {
            if (Schema::hasColumn($table, 'kurikulum_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignUuid('kurikulum_id')
                    ->nullable()
                    ->after('academic_unit_id')
                    ->constrained('kurikulum')
                    ->cascadeOnDelete();
            });
        }

        $kurikulumByUnit = $this->kurikulumByUnitMap();

        // Unit yang punya data CPL/BoK/MK tetapi belum punya kurikulum:
        // buat kurikulum placeholder agar backfill tidak gagal.
        $unitIdsDenganData = collect();

        foreach (['cpl', 'bok', 'mk', 'mk_units'] as $table) {
            $unitIdsDenganData = $unitIdsDenganData->merge(
                DB::table($table)->whereNull('kurikulum_id')->distinct()->pluck('academic_unit_id'),
            );
        }

        foreach ($unitIdsDenganData->unique()->filter() as $unitId) {
            if ($kurikulumByUnit->has($unitId)) {
                continue;
            }

            $unit = DB::table('academic_units')->where('id', $unitId)->first();
            $kurikulumId = (string) Str::uuid();
            $tahun = (int) date('Y');
            $namaUnit = $unit->nama ?? 'Unit';

            DB::table('kurikulum')->insert([
                'id' => $kurikulumId,
                'academic_unit_id' => $unitId,
                'nama' => 'Kurikulum Migrasi '.$namaUnit,
                'kode' => 'MIG-'.Str::upper(Str::substr(str_replace('-', '', $unitId), 0, 8)),
                'tahun' => $tahun,
                'target_capaian_lulusan' => 75,
                'deskripsi' => 'Dibuat otomatis saat migrasi kurikulum_id (unit belum punya kurikulum).',
                'state' => 'draft',
                'is_active' => true,
                'dibuat_oleh' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $kurikulumByUnit->put($unitId, (object) [
                'id' => $kurikulumId,
                'academic_unit_id' => $unitId,
            ]);
        }

        foreach (['cpl', 'bok', 'mk', 'mk_units'] as $table) {
            $rows = DB::table($table)->select(['id', 'academic_unit_id'])->whereNull('kurikulum_id')->get();

            foreach ($rows as $row) {
                $kurikulum = $kurikulumByUnit->get($row->academic_unit_id);

                if ($kurikulum === null) {
                    throw new \RuntimeException(
                        "Backfill kurikulum_id gagal: tidak ada kurikulum untuk unit {$row->academic_unit_id} (tabel {$table}, id {$row->id})."
                    );
                }

                DB::table($table)->where('id', $row->id)->update(['kurikulum_id' => $kurikulum->id]);
            }

            $orphan = DB::table($table)->whereNull('kurikulum_id')->count();

            if ($orphan > 0) {
                throw new \RuntimeException(
                    "Backfill kurikulum_id gagal: masih ada {$orphan} baris orphan di {$table}."
                );
            }

            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                continue;
            }

            DB::statement("ALTER TABLE {$table} MODIFY kurikulum_id CHAR(36) NOT NULL");
        }

        $this->ensureUniqueIndexes();
    }

    public function down(): void
    {
        if ($this->indexExists('cpl', 'uq_cpl_kur_kode')) {
            Schema::table('cpl', function (Blueprint $table): void {
                $table->dropUnique('uq_cpl_kur_kode');
            });
        }

        if ($this->indexExists('bok', 'uq_bok_kur_kode')) {
            Schema::table('bok', function (Blueprint $table): void {
                $table->dropUnique('uq_bok_kur_kode');
            });
        }

        if ($this->indexExists('mk_units', 'uq_mu_kur_mk')) {
            Schema::table('mk_units', function (Blueprint $table): void {
                $table->dropUnique('uq_mu_kur_mk');
                $table->dropUnique('uq_mu_kur_kode');
                $table->unique(['academic_unit_id', 'mk_id'], 'uq_mu_unit_mk');
                $table->unique(['academic_unit_id', 'kode'], 'uq_mu_unit_kode');
            });
        }

        foreach (['mk_units', 'mk', 'bok', 'cpl'] as $table) {
            if (! Schema::hasColumn($table, 'kurikulum_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('kurikulum_id');
            });
        }
    }

    /**
     * @return \Illuminate\Support\Collection<string, object>
     */
    protected function kurikulumByUnitMap()
    {
        return DB::table('kurikulum')
            ->select(['id', 'academic_unit_id', 'is_active', 'tahun', 'created_at'])
            ->orderByDesc('is_active')
            ->orderByDesc('tahun')
            ->orderByDesc('created_at')
            ->get()
            ->unique('academic_unit_id')
            ->keyBy('academic_unit_id');
    }

    protected function ensureUniqueIndexes(): void
    {
        if ($this->indexExists('mk_units', 'uq_mu_unit_mk')) {
            Schema::table('mk_units', function (Blueprint $table): void {
                $table->dropUnique('uq_mu_unit_mk');
                $table->dropUnique('uq_mu_unit_kode');
            });
        }

        if (! $this->indexExists('mk_units', 'uq_mu_kur_mk')) {
            Schema::table('mk_units', function (Blueprint $table): void {
                $table->unique(['kurikulum_id', 'mk_id'], 'uq_mu_kur_mk');
                $table->unique(['kurikulum_id', 'kode'], 'uq_mu_kur_kode');
            });
        }

        if (! $this->indexExists('cpl', 'uq_cpl_kur_kode')) {
            Schema::table('cpl', function (Blueprint $table): void {
                $table->unique(['kurikulum_id', 'kode'], 'uq_cpl_kur_kode');
            });
        }

        if (! $this->indexExists('bok', 'uq_bok_kur_kode')) {
            Schema::table('bok', function (Blueprint $table): void {
                $table->unique(['kurikulum_id', 'kode'], 'uq_bok_kur_kode');
            });
        }
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        if ($connection->getDriverName() === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list('{$table}')");

            return collect($indexes)->contains(fn ($index): bool => ($index->name ?? null) === $indexName);
        }

        $result = $connection->selectOne(
            'SELECT COUNT(*) AS aggregate FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $indexName],
        );

        return (int) ($result->aggregate ?? 0) > 0;
    }
};
