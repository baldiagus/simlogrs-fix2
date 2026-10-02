<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Menjalankan seluruh pipeline seeder resmi secara berurutan.
     */
    public function run(): void
    {
        $this->call([
            UnitSeeder::class,
            GudangSeeder::class,
            PeranSeeder::class,
            ShieldRolePermissionSeeder::class,
            PenggunaSeeder::class,
            AnggaranPengadaanSeeder::class,
            MasterDataSeeder::class,
            PengadaanDummySeeder::class,
            DistribusiDummySeeder::class,
            PeminjamanAsetDummySeeder::class,
            IpsrsDummySeeder::class,
            PersediaanDummySeeder::class,
            AuditDummySeeder::class,
        ]);
    }
}
