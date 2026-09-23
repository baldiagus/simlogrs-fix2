<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE temuan_audit ALTER COLUMN bukti_perbaikan TYPE jsonb USING CASE WHEN bukti_perbaikan IS NOT NULL THEN jsonb_build_array(jsonb_build_object('path', bukti_perbaikan, 'nama', 'Dokumen Bukti')) ELSE '[]'::jsonb END");
        DB::statement("ALTER TABLE temuan_audit ALTER COLUMN bukti_perbaikan SET DEFAULT '[]'::jsonb");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE temuan_audit ALTER COLUMN bukti_perbaikan TYPE varchar(255) USING NULL");
    }
};
