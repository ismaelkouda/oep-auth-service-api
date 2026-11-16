<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'type' => $user->type->value,
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'role' => $user->role?->value,
            'profile_id' => $user->profile_id,
            'organization_id' => $user->organization_id,
            'territory_id' => $user->territory_id,
            'status' => $user->status->value,
        ];
    }
}
