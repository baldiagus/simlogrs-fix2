<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;

trait HasDashboardFormatting
{
    /**
     * Format waktu lampau ramah pengguna.
     */
    protected function diffForHumans($date): string
    {
        return $date ? Carbon::parse($date)->diffForHumans() : 'Baru saja';
    }
}
