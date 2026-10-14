<?php

namespace App\Http\Resources;

use App\Enums\DocumentRole;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A row in the document list. Expects `my_share_role` and `collaborators_count` to be selected. */
class DocumentSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'excerpt' => HtmlSanitizer::toText($this->content),
            'role' => $this->roleValue($request),
            'owner' => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ],
            'last_edited_by' => $this->lastEditor?->name,
            'collaborators_count' => (int) ($this->collaborators_count ?? 0),
            'source_filename' => $this->source_filename,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function roleValue(Request $request): string
    {
        if ($this->owner_id === $request->user()->id) {
            return DocumentRole::Owner->value;
        }

        return $this->my_share_role ?? $this->resource->roleFor($request->user())?->value ?? DocumentRole::Viewer->value;
    }
}
