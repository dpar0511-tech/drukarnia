<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'changes' => $this->changes,
            'user' => [
                'name' => $this->user ? $this->user->imie.' '.$this->user->nazwisko : 'System',
            ],
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
