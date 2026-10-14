<?php

namespace App\Http\Controllers;

use App\Enums\DocumentRole;
use App\Events\DocumentAccessChanged;
use App\Http\Requests\ShareDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentShareController extends Controller
{
    /** Grant access, or change the role if the person already has access. */
    public function store(ShareDocumentRequest $request, Document $document): DocumentResource
    {
        $recipient = User::query()->where('email', $request->validated('email'))->firstOrFail();
        $role = $request->validated('role');
        $alreadyShared = $document->collaborators()->whereKey($recipient->id)->exists();

        $document->collaborators()->syncWithoutDetaching([$recipient->id => ['role' => $role]]);

        broadcast(new DocumentAccessChanged($document, $recipient, $role, $request->user(), $alreadyShared ? 'updated' : 'granted'));

        return $this->detail($document);
    }

    public function update(Request $request, Document $document, User $user): DocumentResource
    {
        $this->authorize('share', $document);
        $role = $request->validate(['role' => ['required', Rule::in(DocumentRole::shareable())]])['role'];

        abort_unless($document->collaborators()->whereKey($user->id)->exists(), 404, 'That person does not have access.');

        $document->collaborators()->updateExistingPivot($user->id, ['role' => $role]);

        broadcast(new DocumentAccessChanged($document, $user, $role, $request->user(), 'updated'));

        return $this->detail($document);
    }

    public function destroy(Request $request, Document $document, User $user): DocumentResource
    {
        $this->authorize('share', $document);

        $document->collaborators()->detach($user->id);

        broadcast(new DocumentAccessChanged($document, $user, null, $request->user(), 'revoked'));

        return $this->detail($document);
    }

    private function detail(Document $document): DocumentResource
    {
        return new DocumentResource($document->load(['owner', 'lastEditor', 'collaborators']));
    }
}
