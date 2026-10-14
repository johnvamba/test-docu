<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

/**
 * Access model:
 *  - owner:  everything
 *  - editor: view, edit content and title, import into the document
 *  - viewer: view and export only
 * Sharing and deletion stay with the owner.
 */
class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        return $document->roleFor($user) !== null;
    }

    public function update(User $user, Document $document): bool
    {
        return (bool) $document->roleFor($user)?->canEdit();
    }

    public function share(User $user, Document $document): bool
    {
        return $document->owner_id === $user->id;
    }

    public function delete(User $user, Document $document): bool
    {
        return $document->owner_id === $user->id;
    }
}
