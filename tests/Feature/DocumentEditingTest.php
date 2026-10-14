<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentEditingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_new_document_gets_a_default_title_and_reopens_with_its_formatting(): void
    {
        $user = User::factory()->create();

        $id = $this->actingAs($user)
            ->postJson('/api/documents')
            ->assertCreated()
            ->assertJsonPath('data.title', 'Untitled document')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.role', 'owner')
            ->json('data.id');

        $html = '<h1>Plan</h1><p><strong>Bold</strong> <em>italic</em> <u>underlined</u></p><ul><li><p>One</p></li></ul><ol><li><p>First</p></li></ol>';

        $this->actingAs($user)
            ->patchJson("/api/documents/{$id}", ['version' => 1, 'title' => '  Launch   plan ', 'content' => $html])
            ->assertOk()
            ->assertJsonPath('data.title', 'Launch plan')
            ->assertJsonPath('data.version', 2);

        // Reopen: the structure survives the round trip through storage.
        $this->actingAs($user)
            ->getJson("/api/documents/{$id}")
            ->assertOk()
            ->assertJsonPath('data.content', $html);
    }

    #[Test]
    public function a_stale_save_is_rejected_with_the_latest_copy(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $document = Document::factory()->for($owner, 'owner')->sharedWith($editor, 'editor')->create();

        // Both open version 1. The editor saves first.
        $this->actingAs($editor)
            ->patchJson("/api/documents/{$document->id}", ['version' => 1, 'content' => '<p>Editor wins</p>'])
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        // The owner's save is based on version 1, so it must not silently overwrite.
        $this->actingAs($owner)
            ->patchJson("/api/documents/{$document->id}", ['version' => 1, 'content' => '<p>Owner overwrites</p>'])
            ->assertStatus(409)
            ->assertJsonPath('document.version', 2)
            ->assertJsonPath('document.content', '<p>Editor wins</p>');

        $this->assertSame('<p>Editor wins</p>', $document->fresh()->content);

        // Retrying against the version they were shown succeeds.
        $this->actingAs($owner)
            ->patchJson("/api/documents/{$document->id}", ['version' => 2, 'content' => '<p>Owner, after review</p>'])
            ->assertOk()
            ->assertJsonPath('data.version', 3);
    }

    #[Test]
    public function stored_html_is_sanitized(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user, 'owner')->create();

        $content = $this->actingAs($user)
            ->patchJson("/api/documents/{$document->id}", [
                'version' => 1,
                'content' => '<p onclick="steal()">Hi<script>alert(1)</script></p>'
                    .'<a href="javascript:alert(1)">bad</a><a href="https://ajaia.example">ok</a>'
                    .'<img src="x" onerror="alert(1)"><h2 style="color:red">Kept</h2>',
            ])
            ->assertOk()
            ->json('data.content');

        $this->assertStringNotContainsString('script', $content);
        $this->assertStringNotContainsString('onclick', $content);
        $this->assertStringNotContainsString('javascript:', $content);
        $this->assertStringNotContainsString('<img', $content);
        $this->assertStringNotContainsString('style=', $content);
        $this->assertStringContainsString('<a href="https://ajaia.example">ok</a>', $content);
        $this->assertStringContainsString('<h2>Kept</h2>', $content);
    }

    #[Test]
    public function saves_are_validated(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user, 'owner')->create();

        $this->actingAs($user)
            ->patchJson("/api/documents/{$document->id}", ['title' => '   ', 'content' => '<p>x</p>'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['version', 'title']);

        $this->actingAs($user)
            ->patchJson("/api/documents/{$document->id}", ['version' => 1, 'title' => str_repeat('a', 201)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title' => 'Titles can be up to 200 characters.']);
    }

    #[Test]
    public function search_matches_titles_only_within_accessible_documents(): void
    {
        $user = User::factory()->create();
        Document::factory()->for($user, 'owner')->create(['title' => 'Budget 2026']);
        Document::factory()->for($user, 'owner')->create(['title' => 'Hiring plan']);
        Document::factory()->create(['title' => 'Budget (someone else)']);

        $this->actingAs($user)
            ->getJson('/api/documents?q=budget')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Budget 2026');
    }

    #[Test]
    public function the_owner_can_delete_a_document(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->for($user, 'owner')->sharedWith(User::factory()->create())->create();

        $this->actingAs($user)->deleteJson("/api/documents/{$document->id}")->assertNoContent();

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        $this->assertDatabaseCount('document_shares', 0);
    }
}
