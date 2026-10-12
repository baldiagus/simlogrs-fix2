<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait AuthorizesForensicAccess
{
    /**
     * Guard otorisasi akses data forensik & kepatuhan audit.
     */
    protected function authorizeForensicAccess(Request $request): ?JsonResponse
    {
        $user = $request->user();
        $canAccess = $user && (
            $user->hasRole(['super_admin', 'admin_logistik']) ||
            $user->can('view_any_audit_manajemen::audit::trail') ||
            $user->can('view_audit_manajemen::audit::trail')
        );

        if (! $canAccess) {
            return $this->jsonError('Akses ditolak: Data forensik hanya untuk Auditor Internal SPI & Manajemen.', 403);
        }

        return null;
    }
}
