<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role?->value,
            'status' => $user->status->value,
            'profile_id' => $user->profile_id,
            'organization_id' => $user->organization_id,
            'territory_id' => $user->territory_id,
            'locked_until' => $user->locked_until?->toIso8601String(),
            'created_at' => $user->created_at->toIso8601String(),
        ];
    }
}
