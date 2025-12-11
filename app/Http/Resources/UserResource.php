<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'phone' => $this->phone,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'school' => $this->school ? [
                'id' => $this->school->id,
                'name' => $this->school->name,
                'logo_url' => $this->school->logo ? asset('storage/' . $this->school->logo) : null,
                'address' => $this->school->address,
                'phone' => $this->school->phone,
                'email' => $this->school->email,
            ] : null,
        ];
    }
}
