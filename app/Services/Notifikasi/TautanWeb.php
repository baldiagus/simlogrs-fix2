<?php

namespace App\Services\Notifikasi;

use App\Http\Middleware\PortalAccessMiddleware;
use App\Models\User;
use App\Support\Portal\PortalResolver;
use App\Support\Rbac\PagePermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use ReflectionProperty;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Mengubah tautan notifikasi (path portal tetap yang ditulis observer, mis. '/kepala-unit/spk')
 * menjadi URL web yang aman dibuka pengguna: lolos aturan PortalAccessMiddleware.
 */
class TautanWeb
{
    /** @var array<string,string>|null cache peta portal => permission dashboard */
    private static ?array $peta = null;

    /**
     * URL tujuan untuk tautan notifikasi. Bila portal pada tautan tidak boleh diakses pengguna,
     * prefix diganti portal pengguna (jika rute bernama sama ada); selain itu halaman daftar notifikasi.
     */
    public function resolve(?string $tautan, User $pengguna): string
    {
        $daftar = $this->daftarUrl($pengguna);

        $path = parse_url((string) $tautan, PHP_URL_PATH);
        if (! is_string($path) || $path === '' || ! str_starts_with($path, '/')) {
            return $daftar;
        }
        $query = parse_url((string) $tautan, PHP_URL_QUERY);
        $tambahan = $query ? '?' . $query : '';

        $segmen = explode('/', trim($path, '/'), 2);
        $portalAsal = $segmen[0];
        $sisa = $segmen[1] ?? '';
        if (! in_array($portalAsal, PortalResolver::VALID_PORTALS, true) || $sisa === '') {
            return $daftar;
        }

        // Rute asal harus benar-benar ada; namanya menentukan padanan di portal lain.
        $rute = $this->cocokkanRute($path);
        $nama = $rute?->getName();
        if (! $rute || ! $nama || ! str_starts_with($nama, $portalAsal . '.')) {
            return $daftar;
        }

        if ($this->bolehMengakses($pengguna, $portalAsal, $rute)) {
            return url($path) . $tambahan;
        }

        $akhiran = substr($nama, strlen($portalAsal) + 1);
        foreach ($this->portalPengguna($pengguna) as $portal) {
            $namaTujuan = $portal . '.' . $akhiran;
            if (! Route::has($namaTujuan)) {
                continue;
            }
            $tujuan = Route::getRoutes()->getByName($namaTujuan);
            if ($tujuan && $this->bolehMengakses($pengguna, $portal, $tujuan)) {
                return url('/' . $portal . '/' . $sisa) . $tambahan;
            }
        }

        return $daftar;
    }

    /**
     * URL halaman daftar notifikasi pada portal aktif pengguna.
     */
    public function daftarUrl(User $pengguna): string
    {
        $portal = $this->portalPengguna($pengguna)[0] ?? PortalResolver::resolve();

        return route($portal . '.notifikasi');
    }

    /**
     * Portal yang boleh diakses pengguna; portal aktif saat ini didahulukan.
     *
     * @return list<string>
     */
    public function portalPengguna(User $pengguna): array
    {
        $aktif = PortalResolver::resolve();
        $urut = array_unique([$aktif, ...PortalResolver::VALID_PORTALS]);

        return array_values(array_filter($urut, fn (string $p) => $this->izinPortal($pengguna, $p)));
    }

    private function izinPortal(User $pengguna, string $portal): bool
    {
        $permission = $this->peta()[$portal] ?? null;

        return $permission !== null && $pengguna->can($permission);
    }

    /**
     * Cerminan PortalAccessMiddleware: permission portal DAN permission halaman rute (bila terdaftar).
     */
    private function bolehMengakses(User $pengguna, string $portal, \Illuminate\Routing\Route $rute): bool
    {
        if (! $this->izinPortal($pengguna, $portal)) {
            return false;
        }

        $controller = $rute->getControllerClass();
        if ($controller) {
            $permission = 'page_' . class_basename($controller);
            if (in_array($permission, PagePermissionRegistry::getAllPermissions(), true) && ! $pengguna->can($permission)) {
                return false;
            }
        }

        return true;
    }

    private function cocokkanRute(string $path): ?\Illuminate\Routing\Route
    {
        try {
            return Route::getRoutes()->match(Request::create($path, 'GET'));
        } catch (HttpException) {
            return null;
        }
    }

    /**
     * Peta portal => permission diambil dari PortalAccessMiddleware agar satu sumber kebenaran.
     *
     * @return array<string,string>
     */
    private function peta(): array
    {
        return self::$peta ??= (new ReflectionProperty(PortalAccessMiddleware::class, 'portalPermissionMap'))
            ->getDefaultValue();
    }
}
