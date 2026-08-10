<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\OpeningHour */
class OpeningHourResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'day_of_week' => $this->day_of_week,
            'day_name' => $this->day_name,
            'service' => $this->service,
            'opens_at' => $this->opens_at ? substr((string) $this->opens_at, 0, 5) : null,
            'closes_at' => $this->closes_at ? substr((string) $this->closes_at, 0, 5) : null,
            'is_closed' => $this->is_closed,
        ];
    }
}
