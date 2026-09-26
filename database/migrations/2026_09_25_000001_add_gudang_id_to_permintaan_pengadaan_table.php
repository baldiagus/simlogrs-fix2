<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permintaan_pengadaan', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->change();
            $table->foreignId('gudang_id')->nullable()->after('unit_id')->constrained('gudang')->nullOnDelete();
        });

        // Check Constraint XOR: Pemohon PR wajib berasal dari Unit ATAU Gudang (salah satu, eksklusif)
        DB::statement('ALTER TABLE permintaan_pengadaan ADD CONSTRAINT chk_pr_pemohon_xor CHECK ((unit_id IS NOT NULL AND gudang_id IS NULL) OR (unit_id IS NULL AND gudang_id IS NOT NULL));');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE permintaan_pengadaan DROP CONSTRAINT IF EXISTS chk_pr_pemohon_xor;');

        Schema::table('permintaan_pengadaan', function (Blueprint $table) {
            $table->dropForeign(['gudang_id']);
            $table->dropColumn('gudang_id');
            $table->foreignId('unit_id')->nullable(false)->change();
        });
    }
};
