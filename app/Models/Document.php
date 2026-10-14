<?php

namespace App\Models;

use App\Enums\DocumentRole;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['title', 'content', 'source_filename'])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    public const DEFAULT_TITLE = 'Untitled document';

    protected $attributes = [
        'content' => '',
        'version' => 1,
    ];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    /** People the owner has granted access to, with their role on the pivot. */
    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'document_shares')
            ->withPivot('role')
            ->withTimestamps()
            ->orderBy('users.name');
    }

    /** Documents the user owns or has been granted access to. */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('owner_id', $user->id)
            ->orWhereHas('collaborators', fn (Builder $c) => $c->where('users.id', $user->id)));
    }

    public function scopeSharedWith(Builder $query, User $user): Builder
    {
        return $query->where('owner_id', '!=', $user->id)
            ->whereHas('collaborators', fn (Builder $c) => $c->where('users.id', $user->id));
    }

    /** The user's effective role on this document, or null when they have no access. */
    public function roleFor(User $user): ?DocumentRole
    {
        if ($this->owner_id === $user->id) {
            return DocumentRole::Owner;
        }

        $share = $this->relationLoaded('collaborators')
            ? $this->collaborators->firstWhere('id', $user->id)
            : $this->collaborators()->where('users.id', $user->id)->first();

        return $share ? DocumentRole::from($share->pivot->role) : null;
    }
}
