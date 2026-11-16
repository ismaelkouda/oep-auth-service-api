<?php

namespace App\Http\Controllers;

use App\Services\ReadinessCheck;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(ReadinessCheck $check): JsonResponse
    {
        return $check->isReady()
            ? response()->json(['status' => 'ok'])
            : response()->json(['status' => 'unavailable'], 503);
    }
}
