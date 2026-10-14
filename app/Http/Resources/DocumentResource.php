<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The full document, as the editor needs it. */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $role = $this->resource->roleFor($user);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'version' => $this->version,
            'role' => $role?->value,
            'permissions' => [
                'edit' => $user->can('update', $this->resource),
                'share' => $user->can('share', $this->resource),
                'delete' => $user->can('delete', $this->resource),
            ],
            'owner' => new UserResource($this->owner),
            'last_edited_by' => $this->lastEditor?->name,
            'collaborators' => $this->collaborators->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'role' => $c->pivot->role,
            ])->values(),
            'source_filename' => $this->source_filename,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
