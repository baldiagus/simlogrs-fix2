<?php

namespace App\Livewire\Aset;

use App\Enums\StatusAset;
use App\Enums\StatusPeminjaman;
use App\Livewire\Concerns\AuthorizesPeminjaman;
use App\Livewire\Aset\Concerns\HasPenyediaPeminjaman;
use App\Livewire\Concerns\HasConfirmationModal;
use App\Livewire\Concerns\HasPortalLayout;
use App\Livewire\Concerns\HasTableFilters;
use App\Livewire\Concerns\HasToast;
use App\Models\Aset;
use App\Models\Barang;
use App\Models\Gudang;
use App\Models\PermintaanPeminjaman;
use App\Models\PermintaanPeminjamanDetail;
use App\Models\Unit;
use App\Support\Generator\CodeGenerator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PeminjamanAsetIndex extends Component
{
    use HasTableFilters, HasToast, HasPortalLayout, HasConfirmationModal, AuthorizesPeminjaman, HasPenyediaPeminjaman;

    #[Url(history: true)]
    public string $activeTab = 'aktif';

    #[Url(history: true)]
    public string $filterStatus = '';

    #[Url(history: true)]
    public string $filterPerspektif = 'semua';
    public string $filterKatalogLokasi = '';
    public ?int $filterKatalogUnitId = null;
    public ?int $filterKatalogGudangId = null;

    public bool $showPinjamModal = false;
    public bool $showDetailModal = false;
    public ?PermintaanPeminjaman $selectedPinjam = null;
    public ?int $selected_barang_id = null;
    public ?string $selected_penyedia_key = null;
    public string $searchPenyedia = '';
    public string $tipe_penyedia = 'RUANGAN';
    public ?int $unit_penyedia_id = null;
    public ?int $gudang_penyedia_id = null;
    public ?int $selected_aset_id = null;
    public int $quantity = 1;
    public string $tanggal_pinjam = '';
    public string $tanggal_kembali_rencana = '';
    public string $keperluan = '';
    public string $catatan = '';

    public bool $showPerpanjangModal = false;
    public ?int $perpanjangId = null;
    public string $tanggal_kembali_baru = '';
    public string $alasan_perpanjang = '';

    public bool $showQrModal = false;
    public ?Aset $selectedAsetQr = null;
    public string $qrCodeSvg = '';

    public function mount(): void
    {
        $this->tanggal_pinjam = date('Y-m-d');
        $this->tanggal_kembali_rencana = date('Y-m-d', strtotime('+7 days'));

        if ($this->tipe_penyedia === 'RUANGAN' && ! $this->unit_penyedia_id) {
            $this->unit_penyedia_id = Unit::where('status', 'AKTIF')->orderBy('nama')->value('id');
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = in_array($tab, ['aktif', 'katalog'], true) ? $tab : 'aktif';
        $this->resetPage();
    }

    public function updatedActiveTab(): void
    {
        $this->resetPage();
    }
    public function updatedFilterPerspektif(): void
    {
        $this->resetPage();
    }

    public function getPageTitle(): string
    {
        $user = auth()->user();
        $portal = $this->getPortalName();

        if ($user?->unit_id && $user->unit) {
            return "Peminjaman Aset Ruangan ({$user->unit->nama})";
        }

        if ($user?->gudang_id && $user->gudang) {
            return "Peminjaman Aset Gudang ({$user->gudang->nama})";
        }

        if ($portal === 'kepala-gudang' || $portal === 'petugas-gudang') {
            return 'Peminjaman Aset Gudang';
        }

        if ($portal === 'kepala-unit' || $portal === 'petugas-unit') {
            return 'Peminjaman Aset Ruangan';
        }

        return 'Sirkulasi Aset Global (Logistik Rumah Sakit)';
    }

    public function getPageSubtitle(): string
    {
        $user = auth()->user();

        if ($user?->unit_id) {
            return 'Pengelolaan inventaris ruangan dan sirkulasi peminjaman aset antar-ruangan.';
        }

        if ($user?->gudang_id) {
            return 'Pengelolaan sirkulasi peminjaman aset inventaris fasilitas gudang logistik.';
        }

        return 'Pengawasan perputaran seluruh inventaris dan deteksi keterlambatan se-rumah sakit.';
    }



    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterKatalogLokasi(): void
    {
        $this->filterKatalogUnitId = null;
        $this->filterKatalogGudangId = null;
        $this->resetPage();
    }

    public function updatedFilterKatalogUnitId(): void
    {
        $this->resetPage();
    }

    public function updatedFilterKatalogGudangId(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedBarangId(): void
    {
        $this->selected_penyedia_key = null;
        $this->selected_aset_id = null;
        $this->unit_penyedia_id = null;
        $this->gudang_penyedia_id = null;

        $providers = $this->getPenyediaAsetList();
        $validProviders = array_values(array_filter($providers, fn ($p) => ! $p['is_own']));
        if (count($validProviders) === 1) {
            $this->selectPenyediaKey($validProviders[0]['key']);
        }
    }

    public function updatedSelectedPenyediaKey($value): void
    {
        $this->selectPenyediaKey($value);
    }

    public function updatedQuantity($value): void
    {
        $max = $this->maxQuantity;
        if ((int) $value > $max) {
            $this->addError('quantity', "Jumlah unit melebihi stok tersedia (maksimal {$max} unit).");
        } else {
            $this->resetErrorBag('quantity');
        }
    }


    public function createPinjam(): void
    {
        abort_unless(auth()->user()?->can('create_layanan_unit::peminjaman::aset'), 403);

        $this->resetPinjamForm();
        $this->tanggal_pinjam = date('Y-m-d');
        $this->tanggal_kembali_rencana = date('Y-m-d', strtotime('+7 days'));
        $this->showPinjamModal = true;
    }

    public function pinjamAset(int $asetId): void
    {
        abort_unless(auth()->user()?->can('create_layanan_unit::peminjaman::aset'), 403);

        $aset = Aset::with('barang')->findOrFail($asetId);
        $isMaintenance = \App\Models\PermintaanMaintenance::where('aset_id', $aset->id)
            ->whereNotIn('status', ['SELESAI', 'DIBATALKAN'])
            ->exists();
        if ($isMaintenance || $aset->status === StatusAset::DalamMaintenance->value || $aset->kondisi !== 'BAIK') {
            $this->toast("Aset '{$aset->kode_aset}' sedang dalam laporan pemeliharaan/perbaikan IPSRS dan tidak dapat dipinjam.", 'warning');
            return;
        }
        $this->resetPinjamForm();
        $this->selected_barang_id = $aset->barang_id;
        $key = $aset->unit_id ? "RUANGAN_{$aset->unit_id}" : "GUDANG_{$aset->gudang_id}";
        $this->selectPenyediaKey($key);
        $this->selected_aset_id = $aset->id;

        $this->tanggal_pinjam = date('Y-m-d');
        $this->tanggal_kembali_rencana = date('Y-m-d', strtotime('+7 days'));
        $this->showPinjamModal = true;
    }

    public function resetPinjamForm(): void
    {
        $this->resetValidation();
        $this->reset([
            'selected_barang_id',
            'selected_penyedia_key',
            'selected_aset_id',
            'unit_penyedia_id',
            'gudang_penyedia_id',
            'keperluan',
            'catatan',
        ]);
        $this->searchPenyedia = '';
        $this->quantity = 1;
        $this->tipe_penyedia = 'RUANGAN';
    }

    public function savePinjam(): void
    {
        abort_unless(auth()->user()?->can('create_layanan_unit::peminjaman::aset'), 403);

        $user = auth()->user();
        $unitPeminjamId = $user->unit_id;
        if (! $unitPeminjamId) {
            $this->toast('Anda harus terdaftar pada unit/ruangan untuk mengajukan peminjaman aset.', 'danger');
            return;
        }

        $rules = [
            'selected_barang_id'      => ['required', Rule::exists('barang', 'id')->where('status', 'AKTIF')],
            'selected_penyedia_key'   => 'required|string',
            'tanggal_pinjam'          => 'required|date',
            'tanggal_kembali_rencana' => 'required|date|after_or_equal:tanggal_pinjam',
            'keperluan'               => 'required|string|max:1000',
            'catatan'                 => 'nullable|string|max:1000',
        ];

        $messages = [
            'selected_barang_id.required'            => 'Aset inventaris wajib dipilih.',
            'selected_penyedia_key.required'         => 'Lokasi unit atau gudang penyedia wajib dipilih.',
            'tanggal_pinjam.required'                => 'Tanggal pinjam wajib diisi.',
            'tanggal_kembali_rencana.required'       => 'Tanggal rencana pengembalian wajib diisi.',
            'tanggal_kembali_rencana.after_or_equal' => 'Tanggal rencana kembali tidak boleh sebelum tanggal pinjam.',
            'keperluan.required'                     => 'Keperluan peminjaman wajib dicantumkan.',
        ];

        $this->validate($rules, $messages);

        // Query seluruh aset yang tersedia di lokasi penyedia
        $assetsQuery = Aset::where('barang_id', $this->selected_barang_id)
            ->where('status', StatusAset::Tersedia->value)
            ->where('kondisi', 'BAIK')
            ->whereHas('barang', fn ($b) => $b->where('status', 'AKTIF'))
            ->whereDoesntHave('peminjamanDetail.permintaanPeminjaman', function ($sub) {
                $sub->whereIn('status', StatusPeminjaman::aktif());
            })
            ->whereDoesntHave('permintaanMaintenance', function ($sub) {
                $sub->whereNotIn('status', ['SELESAI', 'DIBATALKAN']);
            });

        if ($this->tipe_penyedia === 'RUANGAN') {
            $assetsQuery->where('unit_id', $this->unit_penyedia_id)
                ->whereHas('unit', fn ($u) => $u->where('status', 'AKTIF'));
        } else {
            $assetsQuery->where('gudang_id', $this->gudang_penyedia_id)
                ->whereHas('gudang', fn ($g) => $g->where('status', 'AKTIF'));
        }

        $availableCount = $assetsQuery->count();
        if ($availableCount === 0) {
            $this->addError('selected_penyedia_key', 'Tidak ada aset yang tersedia pada lokasi penyedia.');
            $this->addError('selected_aset_id', 'Aset tidak tersedia atau sedang dalam perbaikan.');
            return;
        }

        $this->validate([
            'quantity' => "required|integer|min:1|max:{$availableCount}",
        ], [
            'quantity.required' => 'Jumlah unit peminjaman wajib diisi.',
            'quantity.min'      => 'Jumlah unit minimal 1 unit.',
            'quantity.max'      => "Jumlah unit melebihi stok tersedia (maksimal {$availableCount} unit).",
        ]);

        $allocatedAssets = $assetsQuery->take($this->quantity)->get();
        if ($allocatedAssets->isEmpty()) {
            $this->addError('selected_aset_id', 'Aset yang dipilih tidak tersedia atau tidak berada pada lokasi penyedia.');
            return;
        }

        // Kunci integritas: periksa apakah aset sedang dalam transaksi peminjaman aktif lain
        $isSedangDipinjam = PermintaanPeminjaman::whereIn('status', StatusPeminjaman::aktif())
            ->whereHas('detail', fn ($d) => $d->where('aset_id', $aset->id))
            ->exists();

        if ($isSedangDipinjam) {
            $this->addError('selected_aset_id', "Aset '{$aset->kode_aset}' ({$aset->barang?->nama}) sedang dalam proses pengajuan atau sedang aktif dipinjam oleh unit lain.");
            return;
        }

        $isMaintenance = \App\Models\PermintaanMaintenance::where('aset_id', $aset->id)
            ->whereNotIn('status', ['SELESAI', 'DIBATALKAN'])
            ->exists();
        if ($isMaintenance || $aset->status === StatusAset::DalamMaintenance->value) {
            $this->addError('selected_aset_id', "Aset '{$aset->kode_aset}' ({$aset->barang?->nama}) sedang dalam penanganan perbaikan/pemeliharaan IPSRS dan tidak dapat dipinjam.");
            return;
        }
        if ($this->tipe_penyedia === 'RUANGAN' && $this->unit_penyedia_id === $unitPeminjamId) {
            $this->addError('selected_penyedia_key', 'Unit penyedia tidak boleh sama dengan unit peminjam.');
            return;
        }

        $kode = app(\App\Services\Aset\PeminjamanAsetService::class)->ajukanPeminjaman([
            'tipe_penyedia'           => $this->tipe_penyedia,
            'unit_penyedia_id'        => $this->unit_penyedia_id,
            'gudang_penyedia_id'      => $this->gudang_penyedia_id,
            'unit_id'                 => $unitPeminjamId,
            'tanggal_pinjam'          => $this->tanggal_pinjam,
            'tanggal_kembali_rencana' => $this->tanggal_kembali_rencana,
            'keperluan'               => $this->keperluan,
            'catatan'                 => $this->catatan ?: null,
        ], $user, $allocatedAssets);

        $this->showPinjamModal = false;
        $this->resetPinjamForm();
        $this->toast("Permintaan peminjaman '{$kode}' berhasil diajukan.", 'success');
    }

    public function openDetail(int $id): void
    {
        $this->selectedPinjam = PermintaanPeminjaman::with([
            'unitPenyedia',
            'gudangPenyedia',
            'unitPeminjam',
            'pengaju',
            'penyetuju',
            'detail.aset.barang.satuan',
            'pengembalian.detail.aset',
            'pengembalian.pemeriksa',
        ])->findOrFail($id);

        $this->showDetailModal = true;
    }

    public function openPerpanjang(int $id): void
    {
        abort_unless(auth()->user()?->can('update_layanan_unit::peminjaman::aset') || auth()->user()?->can('create_layanan_unit::peminjaman::aset'), 403);

        $pinjam = PermintaanPeminjaman::findOrFail($id);
        if (! in_array($pinjam->status, StatusPeminjaman::aktif(), true)) {
            $this->toast('Hanya peminjaman aktif yang dapat diperpanjang.', 'error');
            return;
        }

        $this->perpanjangId = $pinjam->id;
        $this->tanggal_kembali_baru = $pinjam->tanggal_kembali_rencana
            ? $pinjam->tanggal_kembali_rencana->copy()->addDays(7)->toDateString()
            : date('Y-m-d');
        $this->alasan_perpanjang = '';
        $this->showPerpanjangModal = true;
    }

    public function submitPerpanjang(): void
    {
        abort_unless(auth()->user()?->can('update_layanan_unit::peminjaman::aset') || auth()->user()?->can('create_layanan_unit::peminjaman::aset'), 403);

        $this->validate([
            'tanggal_kembali_baru' => 'required|date',
            'alasan_perpanjang'    => 'required|string|max:500',
        ], [
            'tanggal_kembali_baru.required' => 'Tanggal rencana pengembalian baru wajib diisi.',
            'alasan_perpanjang.required'    => 'Alasan perpanjangan wajib dicantumkan.',
        ]);

        $pinjam = PermintaanPeminjaman::findOrFail($this->perpanjangId);
        $user = auth()->user();
        $isGlobalAdmin = $user && $user->hasRole(['super_admin', 'admin_logistik']);

        if (! $isGlobalAdmin && (int) $user?->unit_id !== (int) $pinjam->unit_id) {
            abort(403, 'Anda tidak berwenang memperpanjang permohonan peminjaman dari unit lain.');
        }

        if ($this->tanggal_kembali_baru <= $pinjam->tanggal_pinjam->toDateString()) {
            $this->addError('tanggal_kembali_baru', 'Tanggal kembali baru harus lebih lambat dari tanggal pinjam.');
            return;
        }

        $catatanLama = $pinjam->catatan ? $pinjam->catatan . "\n" : '';
        $user = auth()->user();
        $logPerpanjangan = '[Perpanjangan ' . now()->format('d/m/Y H:i') . " oleh {$user->nama}]: " .
            'Rencana kembali diperpanjang dari ' . $pinjam->tanggal_kembali_rencana->format('d/m/Y') .
            ' menjadi ' . Carbon::parse($this->tanggal_kembali_baru)->format('d/m/Y') .
            '. Alasan: ' . $this->alasan_perpanjang;

        $pinjam->update([
            'tanggal_kembali_rencana' => $this->tanggal_kembali_baru,
            'catatan'                 => $catatanLama . $logPerpanjangan,
        ]);

        $this->showPerpanjangModal = false;
        $this->toast("Rencana pengembalian '{$pinjam->kode}' berhasil diperpanjang.", 'success');
    }

    public function confirmBatal(int $id): void
    {
        $pinjam = PermintaanPeminjaman::findOrFail($id);
        if ($pinjam->status !== StatusPeminjaman::Diajukan->value) {
            $this->toast('Hanya peminjaman berstatus DIAJUKAN yang dapat dibatalkan.', 'error');
            return;
        }

        $this->askConfirmation(
            target: $id,
            action: 'batalkanPinjam',
            title: 'Batalkan Pengajuan Peminjaman',
            description: "Apakah Anda yakin ingin membatalkan pengajuan peminjaman {$pinjam->kode}?",
            buttonText: 'Ya, Batalkan',
            variant: 'danger'
        );
    }

    public function batalkanPinjam(int $id): void
    {
        abort_unless(auth()->user()?->can('create_layanan_unit::peminjaman::aset'), 403);

        $pinjam = PermintaanPeminjaman::findOrFail($id);
        if ($pinjam->status !== StatusPeminjaman::Diajukan->value) {
            $this->toast('Hanya peminjaman berstatus DIAJUKAN yang dapat dibatalkan.', 'error');
            return;
        }

        $user = auth()->user();
        $isGlobalAdmin = $user && $user->hasRole(['super_admin', 'admin_logistik']);

        if (! $isGlobalAdmin && (int) $user?->unit_id !== (int) $pinjam->unit_id) {
            abort(403, 'Anda tidak berwenang membatalkan permohonan peminjaman dari unit lain.');
        }

        $pinjam->update([
            'status'  => StatusPeminjaman::Dibatalkan->value,
            'catatan' => ($pinjam->catatan ? $pinjam->catatan . "\n" : '') . '[Dibatalkan oleh pemohon pada ' . now()->format('d/m/Y H:i') . ']',
        ]);

        $this->toast("Pengajuan peminjaman '{$pinjam->kode}' berhasil dibatalkan.", 'success');
    }
    public function confirmSerahTerima(int $id): void
    {
        $pinjam = PermintaanPeminjaman::with(['unitPeminjam', 'detail.aset'])->findOrFail($id);
        $this->authorizePeminjaman($pinjam, 'serah_terima', 'Hanya fasilitas penyedia dengan izin yang berhak melakukan serah terima fisik.');
        if ($pinjam->status !== StatusPeminjaman::Disetujui->value) {
            $this->toast('Hanya peminjaman berstatus DISETUJUI yang dapat diserahterimakan.', 'danger');
            return;
        }

        $this->askConfirmation(
            target: $id,
            action: 'serahTerima',
            title: 'Konfirmasi Serah Terima Fisik Aset',
            description: "Lakukan serah terima fisik aset untuk {$pinjam->kode} ke unit {$pinjam->unitPeminjam?->nama}? Tanggung jawab aset akan beralih ke peminjam dan status menjadi DIPINJAM.",
            buttonText: 'Ya, Serah Terima Fisik',
            variant: 'primary'
        );
    }

    public function serahTerima(int $id): void
    {
        $user = auth()->user();
        $pinjam = PermintaanPeminjaman::with('detail.aset')->findOrFail($id);

        $this->authorizePeminjaman($pinjam, 'serah_terima', 'Hanya fasilitas penyedia dengan izin yang berhak melakukan serah terima fisik.');

        if ($pinjam->status !== StatusPeminjaman::Disetujui->value) {
            $this->toast('Hanya peminjaman berstatus DISETUJUI yang dapat diserahterimakan.', 'danger');
            return;
        }

        try {
            app(\App\Services\Aset\PeminjamanAsetService::class)->serahTerimaFisik($pinjam, $user);
            $this->showDetailModal = false;
            $this->toast("Serah terima fisik {$pinjam->kode} berhasil. Aset resmi berstatus DIPINJAM.", 'success');
        } catch (\Throwable $e) {
            $this->toast($e->getMessage(), 'danger');
        }
    }


    public function showQr(int $asetId): void
    {
        $this->selectedAsetQr = Aset::with(['barang', 'unit', 'gudang'])->findOrFail($asetId);
        $this->qrCodeSvg = (string) QrCode::size(200)->format('svg')->generate($this->selectedAsetQr->kode_aset);
        $this->showQrModal = true;
    }

    public function render()
    {
        $user = auth()->user();
        abort_unless($user && ($user->can('view_any_layanan_unit::peminjaman::aset') || $user->can('view_layanan_unit::peminjaman::aset')), 403);

        $isGlobalAdmin = $user && $user->hasRole(['super_admin', 'admin_logistik']);

        [$countAktif, $countKatalog] = $this->hitungStatistikPeminjaman($user, $isGlobalAdmin);
        $daftarPeminjaman = $this->activeTab === 'aktif' ? $this->queryDaftarPeminjaman($user, $isGlobalAdmin) : collect();
        $daftarKatalogAset = $this->activeTab === 'katalog' ? $this->queryDaftarKatalogAset($user, $isGlobalAdmin) : collect();

        $daftarUnit = Unit::where('status', 'AKTIF')->orderBy('nama')->get();
        $daftarGudang = Gudang::where('status', 'AKTIF')->orderBy('nama')->get();
        $daftarBarangAsetTersedia = $this->getDaftarBarangAsetTersedia();
        $daftarPenyediaAset = $this->getPenyediaAsetList();

        return view('livewire.aset.peminjaman-aset-index', [
            'activeTab'               => $this->activeTab,
            'filterStatus'            => $this->filterStatus,
            'filterKatalogLokasi'     => $this->filterKatalogLokasi,
            'filterKatalogUnitId'     => $this->filterKatalogUnitId,
            'filterKatalogGudangId'   => $this->filterKatalogGudangId,
            'showPinjamModal'         => $this->showPinjamModal,
            'showDetailModal'         => $this->showDetailModal,
            'selectedPinjam'          => $this->selectedPinjam,
            'tipe_penyedia'           => $this->tipe_penyedia,
            'unit_penyedia_id'        => $this->unit_penyedia_id,
            'gudang_penyedia_id'      => $this->gudang_penyedia_id,
            'selected_aset_id'        => $this->selected_aset_id,
            'tanggal_pinjam'          => $this->tanggal_pinjam,
            'tanggal_kembali_rencana' => $this->tanggal_kembali_rencana,
            'keperluan'               => $this->keperluan,
            'catatan'                 => $this->catatan,
            'showPerpanjangModal'     => $this->showPerpanjangModal,
            'perpanjangId'            => $this->perpanjangId,
            'tanggal_kembali_baru'    => $this->tanggal_kembali_baru,
            'alasan_perpanjang'       => $this->alasan_perpanjang,
            'showQrModal'             => $this->showQrModal,
            'selectedAsetQr'          => $this->selectedAsetQr,
            'qrCodeSvg'               => $this->qrCodeSvg,
            'confirmTitle'            => $this->confirmTitle,
            'confirmDescription'      => $this->confirmDescription,
            'confirmButtonText'       => $this->confirmButtonText,
            'confirmButtonVariant'    => $this->confirmButtonVariant,
            'showConfirmModal'        => $this->showConfirmModal,
            'daftarPeminjaman'        => $daftarPeminjaman,
            'daftarKatalogAset'       => $daftarKatalogAset,
            'daftarUnit'              => $daftarUnit,
            'daftarGudang'            => $daftarGudang,
            'daftarBarangAsetTersedia' => $daftarBarangAsetTersedia,
            'daftarPenyediaAset'       => $daftarPenyediaAset,
            'selected_barang_id'       => $this->selected_barang_id,
            'selected_penyedia_key'    => $this->selected_penyedia_key,
            'isGlobalAdmin'           => $isGlobalAdmin,
            'countAktif'              => $countAktif,
            'countKatalog'            => $countKatalog,
            'userUnitId'              => $user?->unit_id,
            'userGudangId'            => $user?->gudang_id,
            'izin'                    => $this->izinPeminjaman($daftarPeminjaman),
            'izinSelected'            => $this->selectedPinjam ? $this->izinPeminjaman([$this->selectedPinjam])[$this->selectedPinjam->id] : null,
            'pageTitle'               => $this->getPageTitle(),
            'pageSubtitle'            => $this->getPageSubtitle(),
        ])->layout('layouts.portal', [
            'portal' => $this->getPortalName(),
            'title'  => $this->getPageTitle(),
        ]);
    }
}
