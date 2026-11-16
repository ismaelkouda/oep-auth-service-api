<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class ReadinessCheck
{
    public function isReady(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
