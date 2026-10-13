<?php

namespace App\Support\Auth;

use App\Models\Master\Gudang;
use App\Models\Master\Unit;

class ContextManager
{
    /**
     * Memeriksa apakah Super Admin sedang dalam mode emulasi.
     */
    public function isEmulating(): bool
    {
        return session()->has('emulated_gudang_id') || session()->has('emulated_unit_id');
    }

    public function getGudangId(): ?int
    {
        return session('emulated_gudang_id');
    }

    public function getUnitId(): ?int
    {
        return session('emulated_unit_id');
    }

    public function setGudangId(?int $id): void
    {
        if ($id) {
            session(['emulated_gudang_id' => $id]);
        } else {
            session()->forget('emulated_gudang_id');
        }
    }

    public function setUnitId(?int $id): void
    {
        if ($id) {
            session(['emulated_unit_id' => $id]);
        } else {
            session()->forget('emulated_unit_id');
        }
    }

    public function resetGlobalContext(): void
    {
        session()->forget(['emulated_gudang_id', 'emulated_unit_id']);
    }

    public function getLabel(): string
    {
        $parts = [];
        if ($gudangId = $this->getGudangId()) {
            $gudang = Gudang::find($gudangId);
            if ($gudang) {
                $parts[] = "Gudang: {$gudang->nama}";
            }
        }
        if ($unitId = $this->getUnitId()) {
            $unit = Unit::find($unitId);
            if ($unit) {
                $parts[] = "Unit: {$unit->nama}";
            }
        }

        return ! empty($parts) ? implode(' | ', $parts) : 'Mode Global Seluruh RS';
    }

}
