<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\Request;

trait ResolvesActor
{
    protected function actor(Request $request): User
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(401);
        }

        return $actor;
    }
}
