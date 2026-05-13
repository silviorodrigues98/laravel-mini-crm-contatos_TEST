<?php

namespace App\Http\Resources;

use Domain\Entities\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property-read Contact $resource */
class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id(),
            'name' => $this->resource->name(),
            'email' => $this->resource->email()->value,
            'phone' => $this->resource->phone()->value,
            'score' => $this->resource->score()->value,
            'status' => $this->resource->status()->value,
            'processed_at' => $this->resource->processedAt()?->format('Y-m-d\TH:i:s\Z'),
            'created_at' => $this->resource->createdAt()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $this->resource->updatedAt()?->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
