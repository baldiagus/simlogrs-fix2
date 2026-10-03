<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Memastikan hanya akun berstatus AKTIF yang dapat mengakses API operasional.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status !== 'AKTIF') {
            if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            }

            return response()->json([
                'sukses' => false,
                'pesan'  => 'Akun Anda dinonaktifkan oleh administrator.',
                'meta'   => [
                    'versi_api' => 'v1.0',
                ],
            ], 403);
        }

        if ($user && $user->wajib_ganti_password && ! $request->is('*/auth/ganti-password') && ! $request->is('*/auth/logout')) {
            return response()->json([
                'sukses'               => false,
                'pesan'                => 'Anda diwajibkan mengganti kata sandi sementara sebelum dapat mengakses API operasional.',
                'wajib_ganti_password' => true,
                'meta'                 => [
                    'versi_api' => 'v1.0',
                ],
            ], 403);
        }

        return $next($request);
    }
}
