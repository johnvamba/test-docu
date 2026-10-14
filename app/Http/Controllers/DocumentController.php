<?php

namespace App\Http\Controllers;

use App\Events\DocumentSaved;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\DocumentSummaryResource;
use App\Models\Document;
use App\Support\HtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'filter' => ['nullable', Rule::in(['all', 'owned', 'shared'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $user = $request->user();

        $query = match ($validated['filter'] ?? 'all') {
            'owned' => Document::query()->where('owner_id', $user->id),
            'shared' => Document::query()->sharedWith($user),
            default => Document::query()->accessibleBy($user),
        };

        if (filled($validated['q'] ?? null)) {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $validated['q']).'%';
            $query->where('title', 'like', $term);
        }

        $documents = $query
            ->with(['owner:id,name', 'lastEditor:id,name'])
            ->withCount('collaborators')
            ->addSelect(['my_share_role' => DB::table('document_shares')
                ->select('role')
                ->whereColumn('document_shares.document_id', 'documents.id')
                ->where('document_shares.user_id', $user->id)
                ->limit(1)])
            ->latest('updated_at')
            ->limit(200)
            ->get();

        return DocumentSummaryResource::collection($documents)->additional([
            'meta' => [
                'counts' => [
                    'all' => Document::query()->accessibleBy($user)->count(),
                    'owned' => Document::query()->where('owner_id', $user->id)->count(),
                    'shared' => Document::query()->sharedWith($user)->count(),
                ],
            ],
        ]);
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $document = new Document([
            'title' => filled($request->input('title')) ? trim($request->input('title')) : Document::DEFAULT_TITLE,
            'content' => $this->sanitizer->clean($request->input('content')),
        ]);
        $document->owner()->associate($request->user());
        $document->last_edited_by = $request->user()->id;
        $document->save();

        return $this->detail($document)->response()->setStatusCode(201);
    }

    public function show(Request $request, Document $document): DocumentResource
    {
        $this->authorize('view', $document);

        return $this->detail($document);
    }

    /**
     * Saves are compare-and-swap on `version`: the row only changes if nobody
     * else saved since the client loaded it. Otherwise the client gets a 409
     * with the latest copy and decides whether to reload or overwrite.
     */
    public function update(UpdateDocumentRequest $request, Document $document): JsonResponse|DocumentResource
    {
        $changes = [];
        if ($request->has('title')) {
            $changes['title'] = $request->validated('title');
        }
        if ($request->has('content')) {
            $changes['content'] = $this->sanitizer->clean($request->validated('content'));
        }

        $expected = (int) $request->validated('version');

        $updated = Document::query()
            ->whereKey($document->id)
            ->where('version', $expected)
            ->update($changes + [
                'version' => DB::raw('version + 1'),
                'last_edited_by' => $request->user()->id,
                'updated_at' => now(),
            ]);

        $document->refresh();

        if ($updated === 0) {
            return response()->json([
                'message' => 'This document was changed by someone else since you opened it.',
                'document' => $this->detail($document)->resolve($request),
            ], 409);
        }

        broadcast(new DocumentSaved($document, $request->user()))->toOthers();

        return $this->detail($document);
    }

    public function destroy(Document $document): Response
    {
        $this->authorize('delete', $document);

        $document->delete();

        return response()->noContent();
    }

    private function detail(Document $document): DocumentResource
    {
        return new DocumentResource($document->load(['owner', 'lastEditor', 'collaborators']));
    }
}
