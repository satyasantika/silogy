<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NIP/NUPTK jadi identitas resmi tambahan (nullable, unique — pola sama
 * seperti nidn). Username dilonggarkan jadi opsional karena email kini
 * satu-satunya identitas wajib untuk pembuatan/impor pengguna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nip', 20)->nullable()->unique()->after('nidn');
            $table->string('nuptk', 20)->nullable()->unique()->after('nip');

            $table->index('nip', 'idx_users_nip');
            $table->index('nuptk', 'idx_users_nuptk');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_nip');
            $table->dropIndex('idx_users_nuptk');
            $table->dropColumn(['nip', 'nuptk']);
        });
    }
};
