<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        throw_if(empty($tableNames), Exception::class, 'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        Schema::create($tableNames['permissions'], function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 125);
            $table->string('guard_name', 125);
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['roles'], function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 125);
            $table->string('guard_name', 125);
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->foreignUuid('permission_id')
                ->constrained($tableNames['permissions'])
                ->cascadeOnDelete();
            $table->string('model_type');
            $table->uuid($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'mhp_model_id_model_type_index');
            $table->primary(['permission_id', $columnNames['model_morph_key'], 'model_type'], 'mhp_pri');
        });

        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames, $columnNames) {
            $table->foreignUuid('role_id')
                ->constrained($tableNames['roles'])
                ->cascadeOnDelete();
            $table->string('model_type');
            $table->uuid($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'mhr_model_id_model_type_index');
            $table->primary(['role_id', $columnNames['model_morph_key'], 'model_type'], 'mhr_pri');
        });

        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames) {
            $table->foreignUuid('permission_id')
                ->constrained($tableNames['permissions'])
                ->cascadeOnDelete();
            $table->foreignUuid('role_id')
                ->constrained($tableNames['roles'])
                ->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id'], 'rhp_pri');
        });

        app('cache')->store(config('permission.cache.store') != 'default'
            ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        throw_if(empty($tableNames), Exception::class, 'Error: config/permission.php not found and defaults could not be merged. Please publish the package configuration before proceeding, or drop the tables manually.');

        Schema::drop($tableNames['role_has_permissions']);
        Schema::drop($tableNames['model_has_roles']);
        Schema::drop($tableNames['model_has_permissions']);
        Schema::drop($tableNames['roles']);
        Schema::drop($tableNames['permissions']);
    }
};
