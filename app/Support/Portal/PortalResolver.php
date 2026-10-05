<?php

namespace App\Support\Portal;

class PortalResolver
{
    public const VALID_PORTALS = [
        'super-admin',
        'admin-logistik',
        'kepala-gudang',
        'petugas-gudang',
        'pengadaan',
        'kepala-unit',
        'petugas-unit',
        'teknisi',
        'manajemen',
        'auditor',
    ];

    /**
     * Resolves the current active portal slug reliably even during Livewire AJAX updates.
     */
    public static function resolve(?string $override = null): string
    {
        if ($override && in_array($override, self::VALID_PORTALS, true)) {
            return $override;
        }

        $seg1 = request()->segment(1);
        if ($seg1 && in_array($seg1, self::VALID_PORTALS, true)) {
            return $seg1;
        }

        // Handle Livewire AJAX requests (/livewire/update) where request()->segment(1) is 'livewire'
        $referer = request()->header('Referer');
        if ($referer) {
            $refererPath = parse_url($referer, PHP_URL_PATH);
            if ($refererPath) {
                $refSeg1 = explode('/', trim($refererPath, '/'))[0] ?? null;
                if ($refSeg1 && in_array($refSeg1, self::VALID_PORTALS, true)) {
                    return $refSeg1;
                }
            }
        }

        // Fallback based on user primary role if authenticated
        $user = auth()->user();
        if ($user) {
            $rolePortalMap = [
                'super_admin'    => 'super-admin',
                'admin_logistik' => 'admin-logistik',
                'kepala_gudang'  => 'kepala-gudang',
                'petugas_gudang' => 'petugas-gudang',
                'pengadaan'      => 'pengadaan',
                'kepala_unit'    => 'kepala-unit',
                'petugas_unit'   => 'petugas-unit',
                'kepala_teknisi' => 'teknisi',
                'teknisi'        => 'teknisi',
                'manajemen'      => 'manajemen',
                'auditor'        => 'auditor',
            ];
            foreach ($rolePortalMap as $roleName => $slug) {
                if ($user->hasRole($roleName)) {
                    return $slug;
                }
            }
        }

        return 'super-admin';
    }
}
