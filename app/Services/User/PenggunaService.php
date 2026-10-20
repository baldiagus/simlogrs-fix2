<?php

namespace App\Services\User;

use App\Models\Audit\LogAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PenggunaService
{
    /**
     * Pembuatan akun, penetapan peran, dan audit dalam satu transaksi (ADR 0002 B).
     */
    public function createPengguna(array $payload, string $role, User $actor, ?string $ip = null, ?string $device = null): User
    {
        return DB::transaction(function () use ($payload, $role, $actor, $ip, $device) {
            $user = User::create($payload);
            $user->assignRole($role);
            LogAudit::create([
                'pengguna_id'     => $actor->id,
                'peran_id'        => $actor->roles?->first()?->id,
                'referensi_jenis' => 'User',
                'referensi_id'    => $user->id,
                'aksi'            => 'TAMBAH',
                'data_sebelum'    => null,
                'data_sesudah'    => [
                    'nama'       => $user->nama,
                    'nip'        => $user->nip,
                    'username'   => $user->username,
                    'email'      => $user->email,
                    'no_telepon' => $user->no_telepon,
                    'unit_id'    => $user->unit_id,
                    'gudang_id'  => $user->gudang_id,
                    'status'     => $user->status,
                    'roles'      => [$role],
                ],
                'ip_address'      => $ip ?: request()->ip(),
                'device'          => substr($device ?: (request()->userAgent() ?? 'Web Browser'), 0, 255),
                'dibuat_pada'     => now(),
            ]);

            return $user;
        });
    }

    /**
     * Mutasi akun, peran, dan audit dalam satu transaksi (ADR 0002 B).
     */
    public function updatePengguna(User $user, array $payload, string $role, User $actor, ?string $ip = null, ?string $device = null): User
    {
        $oldData = [
            'nama'       => $user->nama,
            'nip'        => $user->nip,
            'username'   => $user->username,
            'email'      => $user->email,
            'no_telepon' => $user->no_telepon,
            'unit_id'    => $user->unit_id,
            'gudang_id'  => $user->gudang_id,
            'status'     => $user->status,
            'roles'      => $user->getRoleNames()->toArray(),
        ];

        return DB::transaction(function () use ($user, $payload, $role, $actor, $oldData, $ip, $device) {
            $user->update($payload);

            if (($payload['status'] ?? null) === 'NONAKTIF') {
                $user->tokens()->delete();
            }
            $user->syncRoles($role);
            $newData = [
                'nama'       => $user->nama,
                'nip'        => $user->nip,
                'username'   => $user->username,
                'email'      => $user->email,
                'no_telepon' => $user->no_telepon,
                'unit_id'    => $user->unit_id,
                'gudang_id'  => $user->gudang_id,
                'status'     => $user->status,
                'roles'      => [$role],
            ];

            LogAudit::create([
                'pengguna_id'     => $actor->id,
                'peran_id'        => $actor->roles?->first()?->id,
                'referensi_jenis' => 'User',
                'referensi_id'    => $user->id,
                'aksi'            => ($oldData['roles'] !== $newData['roles']) ? 'RBAC_MUTATION' : 'UBAH',
                'data_sebelum'    => $oldData,
                'data_sesudah'    => $newData,
                'ip_address'      => $ip ?: request()->ip(),
                'device'          => substr($device ?: (request()->userAgent() ?? 'Web Browser'), 0, 255),
                'dibuat_pada'     => now(),
            ]);

            return $user;
        });
    }

    /**
     * Reset sandi, pencabutan token, dan audit dalam satu transaksi (ADR 0002 B).
     * Mengembalikan kata sandi sementara plaintext.
     */
    public function resetPassword(User $user, User $actor, ?string $ip = null, ?string $device = null): string
    {
        $tempPassword = Str::password(10, true, true, false);
        $wajibGantiSebelum = (bool) $user->wajib_ganti_password;

        DB::transaction(function () use ($user, $tempPassword, $actor, $wajibGantiSebelum, $ip, $device) {
            $user->update([
                'password'             => Hash::make($tempPassword),
                'wajib_ganti_password' => true,
            ]);

            $user->tokens()->delete();

            LogAudit::create([
                'pengguna_id'     => $actor->id,
                'peran_id'        => $actor->roles?->first()?->id,
                'referensi_jenis' => 'User',
                'referensi_id'    => $user->id,
                'aksi'            => 'RESET_PASSWORD',
                'data_sebelum'    => [
                    'user_id'              => $user->id,
                    'username'             => $user->username,
                    'nama'                 => $user->nama,
                    'wajib_ganti_password' => $wajibGantiSebelum,
                ],
                'data_sesudah'    => [
                    'user_id'              => $user->id,
                    'username'             => $user->username,
                    'nama'                 => $user->nama,
                    'wajib_ganti_password' => true,
                    'direset_oleh'         => $actor->username,
                ],
                'ip_address'      => $ip ?: request()->ip(),
                'device'          => substr($device ?: (request()->userAgent() ?? 'Web Browser'), 0, 255),
                'dibuat_pada'     => now(),
            ]);
        });

        return $tempPassword;
    }

    /**
     * Mutasi status, pencabutan token, dan audit dalam satu transaksi (ADR 0002 B).
     * Mengembalikan status baru (AKTIF / NONAKTIF).
     */
    public function toggleStatus(User $user, User $actor, ?string $ip = null, ?string $device = null): string
    {
        $oldStatus = $user->status;
        $newStatus = $user->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';

        DB::transaction(function () use ($user, $actor, $oldStatus, $newStatus, $ip, $device) {
            $user->update(['status' => $newStatus]);

            if ($newStatus === 'NONAKTIF') {
                $user->tokens()->delete();
            }

            LogAudit::create([
                'pengguna_id'     => $actor->id,
                'peran_id'        => $actor->roles?->first()?->id,
                'referensi_jenis' => 'User',
                'referensi_id'    => $user->id,
                'aksi'            => 'STATUS_MUTATION',
                'data_sebelum'    => [
                    'user_id'  => $user->id,
                    'username' => $user->username,
                    'nama'     => $user->nama,
                    'status'   => $oldStatus,
                ],
                'data_sesudah'    => [
                    'user_id'  => $user->id,
                    'username' => $user->username,
                    'nama'     => $user->nama,
                    'status'   => $newStatus,
                ],
                'ip_address'      => $ip ?: request()->ip(),
                'device'          => substr($device ?: (request()->userAgent() ?? 'Web Browser'), 0, 255),
                'dibuat_pada'     => now(),
            ]);
        });

        return $newStatus;
    }

    /**
     * Pencabutan token, audit, dan penghapusan akun dalam satu transaksi (ADR 0002 B).
     */
    public function deletePengguna(User $user, User $actor, ?string $ip = null, ?string $device = null): void
    {
        $userData = [
            'id'       => $user->id,
            'nama'     => $user->nama,
            'username' => $user->username,
            'email'    => $user->email,
            'roles'    => $user->getRoleNames()->toArray(),
        ];

        DB::transaction(function () use ($user, $actor, $userData, $ip, $device) {
            $user->tokens()->delete();

            LogAudit::create([
                'pengguna_id'     => $actor->id,
                'peran_id'        => $actor->roles?->first()?->id,
                'referensi_jenis' => 'User',
                'referensi_id'    => $user->id,
                'aksi'            => 'HAPUS',
                'data_sebelum'    => $userData,
                'data_sesudah'    => null,
                'ip_address'      => $ip ?: request()->ip(),
                'device'          => substr($device ?: (request()->userAgent() ?? 'Web Browser'), 0, 255),
                'dibuat_pada'     => now(),
            ]);

            $user->delete();
        });
    }
}
