<?php

namespace Tests\Feature;

use App\Events\DocumentAccessChanged;
use App\Events\DocumentSaved;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentSharingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $teammate;

    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['email' => 'owner@ajaia.test']);
        $this->teammate = User::factory()->create(['email' => 'teammate@ajaia.test']);
        $this->document = Document::factory()->for($this->owner, 'owner')->create(['title' => 'Roadmap']);
    }

    #[Test]
    public function guests_cannot_reach_documents(): void
    {
        $this->getJson('/api/documents')->assertUnauthorized();
        $this->getJson("/api/documents/{$this->document->id}")->assertUnauthorized();
    }

    #[Test]
    public function a_document_is_private_until_shared(): void
    {
        $this->actingAs($this->teammate)
            ->getJson("/api/documents/{$this->document->id}")
            ->assertForbidden();

        $this->actingAs($this->teammate)
            ->getJson('/api/documents')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function the_owner_shares_a_document_and_the_list_distinguishes_owned_from_shared(): void
    {
        $this->actingAs($this->owner)
            ->postJson("/api/documents/{$this->document->id}/shares", [
                'email' => 'Teammate@Ajaia.test ',
                'role' => 'viewer',
            ])
            ->assertOk()
            ->assertJsonPath('data.collaborators.0.email', 'teammate@ajaia.test')
            ->assertJsonPath('data.collaborators.0.role', 'viewer');

        $this->actingAs($this->owner)
            ->getJson('/api/documents?filter=owned')
            ->assertJsonPath('data.0.role', 'owner')
            ->assertJsonPath('data.0.collaborators_count', 1);

        $this->actingAs($this->teammate)
            ->getJson('/api/documents?filter=shared')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Roadmap')
            ->assertJsonPath('data.0.role', 'viewer')
            ->assertJsonPath('data.0.owner.id', $this->owner->id)
            ->assertJsonPath('meta.counts', ['all' => 1, 'owned' => 0, 'shared' => 1]);
    }

    #[Test]
    public function viewers_can_read_but_not_edit_while_editors_can_edit(): void
    {
        $this->document->collaborators()->attach($this->teammate->id, ['role' => 'viewer']);

        $this->actingAs($this->teammate)
            ->getJson("/api/documents/{$this->document->id}")
            ->assertOk()
            ->assertJsonPath('data.role', 'viewer')
            ->assertJsonPath('data.permissions', ['edit' => false, 'share' => false, 'delete' => false]);

        $this->actingAs($this->teammate)
            ->patchJson("/api/documents/{$this->document->id}", ['version' => 1, 'content' => '<p>Hijack</p>'])
            ->assertForbidden();

        $this->document->collaborators()->updateExistingPivot($this->teammate->id, ['role' => 'editor']);

        $this->actingAs($this->teammate)
            ->patchJson("/api/documents/{$this->document->id}", ['version' => 1, 'content' => '<p>Edited by a teammate</p>'])
            ->assertOk()
            ->assertJsonPath('data.content', '<p>Edited by a teammate</p>')
            ->assertJsonPath('data.last_edited_by', $this->teammate->name);
    }

    #[Test]
    public function only_the_owner_can_share_or_delete(): void
    {
        $this->document->collaborators()->attach($this->teammate->id, ['role' => 'editor']);
        $outsider = User::factory()->create();

        $this->actingAs($this->teammate)
            ->postJson("/api/documents/{$this->document->id}/shares", ['email' => $outsider->email, 'role' => 'viewer'])
            ->assertForbidden();

        $this->actingAs($this->teammate)
            ->deleteJson("/api/documents/{$this->document->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('documents', ['id' => $this->document->id]);
    }

    #[Test]
    public function sharing_validates_the_recipient(): void
    {
        $this->actingAs($this->owner)
            ->postJson("/api/documents/{$this->document->id}/shares", ['email' => 'nobody@ajaia.test', 'role' => 'viewer'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'No Ajaia account uses that email address.']);

        $this->actingAs($this->owner)
            ->postJson("/api/documents/{$this->document->id}/shares", ['email' => 'owner@ajaia.test', 'role' => 'viewer'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'You already own this document.']);

        $this->actingAs($this->owner)
            ->postJson("/api/documents/{$this->document->id}/shares", ['email' => 'teammate@ajaia.test', 'role' => 'owner'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    #[Test]
    public function sharing_again_changes_the_role_instead_of_duplicating(): void
    {
        $share = fn (string $role) => $this->actingAs($this->owner)
            ->postJson("/api/documents/{$this->document->id}/shares", ['email' => 'teammate@ajaia.test', 'role' => $role]);

        $share('viewer')->assertOk();
        $share('editor')->assertOk()->assertJsonCount(1, 'data.collaborators')->assertJsonPath('data.collaborators.0.role', 'editor');

        $this->assertDatabaseCount('document_shares', 1);
    }

    #[Test]
    public function revoking_access_removes_the_document_from_the_teammates_view(): void
    {
        $this->document->collaborators()->attach($this->teammate->id, ['role' => 'editor']);

        $this->actingAs($this->owner)
            ->deleteJson("/api/documents/{$this->document->id}/shares/{$this->teammate->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data.collaborators');

        $this->actingAs($this->teammate)
            ->getJson("/api/documents/{$this->document->id}")
            ->assertForbidden();
    }

    #[Test]
    public function access_changes_and_saves_are_broadcast_to_the_right_people(): void
    {
        Event::fake([DocumentAccessChanged::class, DocumentSaved::class]);
        $url = "/api/documents/{$this->document->id}";

        $this->actingAs($this->owner)->postJson("{$url}/shares", ['email' => 'teammate@ajaia.test', 'role' => 'viewer']);
        $this->actingAs($this->owner)->postJson("{$url}/shares", ['email' => 'teammate@ajaia.test', 'role' => 'editor']);
        $this->actingAs($this->teammate)->patchJson($url, ['version' => 1, 'content' => '<p>Hi</p>'])->assertOk();
        $this->actingAs($this->owner)->deleteJson("{$url}/shares/{$this->teammate->id}");

        $changes = [];
        Event::assertDispatched(DocumentAccessChanged::class, function (DocumentAccessChanged $e) use (&$changes) {
            $changes[] = [$e->change, $e->role];

            return $e->broadcastOn()[0]->name === 'private-App.Models.User.'.$this->teammate->id;
        });
        $this->assertSame([['granted', 'viewer'], ['updated', 'editor'], ['revoked', null]], $changes);

        Event::assertDispatched(DocumentSaved::class, fn (DocumentSaved $e) => $e->version === 2
            && $e->broadcastOn()[0]->name === 'presence-documents.'.$this->document->id
            && ! array_key_exists('content', $e->broadcastWith()));
    }
}
