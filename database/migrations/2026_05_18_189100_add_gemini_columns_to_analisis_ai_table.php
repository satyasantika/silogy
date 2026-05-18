<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analisis_ai', function (Blueprint $table) {
            $table->string('finish_reason', 50)->nullable()->after('durasi_ms');
            $table->boolean('safety_blocked')->default(false)->after('finish_reason');
        });
    }

    public function down(): void
    {
        Schema::table('analisis_ai', function (Blueprint $table) {
            $table->dropColumn(['finish_reason', 'safety_blocked']);
        });
    }
};
