<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BikeResource extends JsonResource
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
            'brand' => $this->brand,
            'model' => $this->model,
            'year' => $this->year,
            'nickname' => $this->nickname,
            'engine_cc' => $this->engine_cc,
            'photo_url' => $this->photo_url,
            'is_default' => (bool) $this->is_default,
            'created_at' => $this->created_at,
        ];
    }
}
