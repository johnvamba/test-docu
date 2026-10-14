<?php

namespace App\Events;

use App\Models\Document;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/** Sent to a person when they are given access, their role changes, or access is removed. */
class DocumentAccessChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public int $documentId;

    public string $title;

    public int $recipientId;

    public array $changedBy;

    /**
     * @param  string|null  $role  null when access was removed
     * @param  string  $change  granted | updated | revoked
     */
    public function __construct(Document $document, User $recipient, public ?string $role, User $by, public string $change = 'granted')
    {
        $this->documentId = $document->id;
        $this->title = $document->title;
        $this->recipientId = $recipient->id;
        $this->changedBy = ['id' => $by->id, 'name' => $by->name];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->recipientId)];
    }

    public function broadcastAs(): string
    {
        return 'document.access';
    }

    public function broadcastWith(): array
    {
        return [
            'document_id' => $this->documentId,
            'title' => $this->title,
            'role' => $this->role,
            'change' => $this->change,
            'changed_by' => $this->changedBy,
        ];
    }
}
