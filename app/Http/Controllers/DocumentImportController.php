<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportFileRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Services\Import\DocumentImporter;
use Illuminate\Http\JsonResponse;

class DocumentImportController extends Controller
{
    public function __construct(private readonly DocumentImporter $importer) {}

    /** Upload a file and turn it into a new document owned by the uploader. */
    public function store(ImportFileRequest $request): JsonResponse
    {
        $imported = $this->importer->import($request->file('file'));

        $document = new Document([
            'title' => $imported->title,
            'content' => $imported->html,
            'source_filename' => $imported->sourceFilename,
        ]);
        $document->owner()->associate($request->user());
        $document->last_edited_by = $request->user()->id;
        $document->save();

        return (new DocumentResource($document->load(['owner', 'lastEditor', 'collaborators'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Convert a file for insertion into an open document. The editor inserts
     * the HTML at the cursor and saves through the normal versioned save, so
     * imports never bypass conflict detection.
     */
    public function convert(ImportFileRequest $request, Document $document): JsonResponse
    {
        $imported = $this->importer->import($request->file('file'));

        return response()->json([
            'html' => $imported->html,
            'source_filename' => $imported->sourceFilename,
        ]);
    }
}
