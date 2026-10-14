<?php

namespace App\Events;

use App\Models\Document;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Tells everyone else viewing the document that a newer version exists.
 * Only metadata is sent; clients fetch the content through the API, which
 * re-checks access.
 */
class DocumentSaved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public int $documentId;

    public int $version;

    public string $title;

    public array $savedBy;

    public string $savedAt;

    public function __construct(Document $document, User $by)
    {
        $this->documentId = $document->id;
        $this->version = $document->version;
        $this->title = $document->title;
        $this->savedBy = ['id' => $by->id, 'name' => $by->name];
        $this->savedAt = $document->updated_at->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [new PresenceChannel('documents.'.$this->documentId)];
    }

    public function broadcastAs(): string
    {
        return 'document.saved';
    }

    public function broadcastWith(): array
    {
        return [
            'document_id' => $this->documentId,
            'version' => $this->version,
            'title' => $this->title,
            'saved_by' => $this->savedBy,
            'saved_at' => $this->savedAt,
        ];
    }
}
