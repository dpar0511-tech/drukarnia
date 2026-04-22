<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'imie' => $this->imie,
            'nazwisko' => $this->nazwisko,
            'email' => $this->email,
            'aktywny' => $this->aktywny,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'role' => $this->when($this->relationLoaded('roles'), function () {
                return $this->role?->name;
            }),
            'klient' => $this->whenLoaded('klient', function () {
                return [
                    'id' => $this->klient->id,
                    'nazwa' => $this->klient->imie_nazwa,
                ];
            }),
            'audit_logs' => AuditLogResource::collection($this->whenLoaded('auditLogs')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
