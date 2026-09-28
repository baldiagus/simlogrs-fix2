<?php

namespace Database\Seeders;

use App\Models\Gudang;
use App\Models\PenggunaPeran;
use App\Models\Peran;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class PenggunaSeeder extends Seeder
{
    /**
     * Menanam 10 akun resmi dinas sistem dengan format autentikasi EMAIL.
     */
    public function run(): void
    {
        $defaultPassword = Hash::make(env('SEEDER_DEFAULT_PASSWORD', 'SimlogRS@2026!'));

        $users = [
            [
                'nama' => 'Bambang Supriyadi, S.Kom',
                'nip' => '198501152010011001',
                'username' => 'superadmin',
                'email' => 'superadmin@simlogrs.local',
                'password' => $defaultPassword,
                'no_telepon' => '081234567890',
                'status' => 'AKTIF',
                'role' => 'super_admin',
                'unit_kode' => null,
                'gudang_kode' => null,
            ],
            [
                'nama' => 'Dewi Anggraini, S.E.',
                'nip' => '198804202012022002',
                'username' => 'adminlogistik',
                'email' => 'adminlogistik@simlogrs.local',
                'password' => $defaultPassword,
                'no_telepon' => '081234567891',
                'status' => 'AKTIF',
                'role' => 'admin_logistik',
                'unit_kode' => null,
                'gudang_kode' => 'GUD_SENTRAL',
            ],
            [
                'nama' => 'Hendra Kusuma, S.T.',
                'nip' => '198207102008011003',
                'username' => 'kepalagudang',
                'email' => 'kepalagudang@simlogrs.local',
                'password' => $defaultPassword,
                'no_telepon' => '081234567892',
                'status' => 'AKTIF',
                'role' => 'kepala_gudang',
                'unit_kode' => null,
                'gudang_kode' => 'GUD_SENTRAL',
            ],
            [
                'nama' => 'Joko Wahyudi, A.Md.',
                'nip' => '199211052015031004',
                'username' => 'petugasgudang',
                'email' => 'petugasgudang@simlogrs.local',
                'password' => $defaultPassword,
                'no_telepon' => '081234567893',
                'status' => 'AKTIF',
                'role' => 'petugas_gudang',
                'unit_kode' => null,
                'gudang_kode' => 'GUD_SENTRAL',
            ],
            [
                'nama' => 'Siti Nurhaliza, S.E.',
                'nip' => '199009182014022005',
                'username' => 'pengadaan',
                'email' => 'pengadaan@simlogrs.local',
                'password' => $defaultPassword,
                'no_telepon' => '081234567894',
                'status' => 'AKTIF',
                'role' => 'pengadaan',
                'unit_kode' => 'LOGISTIK',
                'gudang_kode' => null,
            ],
            [
                'nama' => 'dr. Pratama, Sp.EM',
                'nip' => '198003122006041006',
                'username' => 'kepalaunit',
                'email' => 'kepalaunit@simlogrs.local',
                'password' => $defaultPassword,
                'no_telepon' => '081234567895',
                'status' => 'AKTIF',
                'role' => 'kepala_unit',
                'unit_kode' => 'IGD',
                'gudang_kode' => null,
            ],
            [
                'nama' => 'Ns. Budi Santoso, S.Kep',
                'nip' => '199406252018011007',
                'username' => 'petugasunit',
                'email' => 'petugasunit@simlogrs.local',
                'password' => $defaultPassword,
                'no_telepon' => '081234567896',
                'status' => 'AKTIF',
                'role' => 'petugas_unit',
                'unit_kode' => 'IGD',
                'gudang_kode' => null,
            ],
            [
                'nama' => 'Agus Priyono, A.Md.T.',
                'nip' => '199102142014031008',
                'username' => 'teknisi',
                'email' => 'teknisi@simlogrs.local',
                'password' => $defaultPassword,
                'no_telepon' => '081234567897',
                'status' => 'AKTIF',
                'role' => 'teknisi',
                'unit_kode' => 'IPSRS',
                'gudang_kode' => 'GUD_IPSRS',
            ],
            [
                'nama' => 'Drs. H. Mulyono, M.Kes',
                'nip' => '197505081999031009',
                'username' => 'manajemen',
                'email' => 'manajemen@simlogrs.local',
                'password' => $defaultPassword,
                'no_telepon' => '081234567898',
                'status' => 'AKTIF',
                'role' => 'manajemen',
                'unit_kode' => null,
                'gudang_kode' => null,
            ],
            [
                'nama' => 'Rina Kartika, S.E., Ak., CA',
                'nip' => '198708302011012010',
                'username' => 'auditor',
                'email' => 'auditor@simlogrs.local',
                'password' => $defaultPassword,
                'no_telepon' => '081234567899',
                'status' => 'AKTIF',
                'role' => 'auditor',
                'unit_kode' => null,
                'gudang_kode' => null,
            ],
        ];

        foreach ($users as $u) {
            $unitId = ! empty($u['unit_kode']) ? Unit::where('kode', $u['unit_kode'])->value('id') : null;
            $gudangId = ! empty($u['gudang_kode']) ? Gudang::where('kode', $u['gudang_kode'])->value('id') : null;

            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'unit_id' => $unitId,
                    'gudang_id' => $gudangId,
                    'nama' => $u['nama'],
                    'nip' => $u['nip'],
                    'username' => $u['username'],
                    'password' => $u['password'],
                    'no_telepon' => $u['no_telepon'],
                    'status' => $u['status'],
                ]
            );

            // Kaitkan role Spatie
            $user->syncRoles([$u['role'], 'panel_user']);

            // Kaitkan ke tabel pivot pengguna_peran
            $peran = Peran::where('kode', $u['role'])->first();
            if ($peran) {
                PenggunaPeran::updateOrCreate(
                    [
                        'pengguna_id' => $user->id,
                        'peran_id' => $peran->id,
                    ],
                    [
                        'unit_id' => $unitId,
                        'gudang_id' => $gudangId,
                        'dibuat_pada' => now(),
                    ]
                );
            }
        }
    }
}
