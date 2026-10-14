<?php

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return $user->id === $id;
});

// Presence: who has this document open. Returning member info authorizes the join.
Broadcast::channel('documents.{document}', function (User $user, Document $document) {
    $role = $document->roleFor($user);

    return $role ? ['id' => $user->id, 'name' => $user->name, 'role' => $role->value] : false;
});
