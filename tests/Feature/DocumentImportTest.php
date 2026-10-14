<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsDocx;
use Tests\TestCase;

class DocumentImportTest extends TestCase
{
    use BuildsDocx, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function a_text_file_becomes_a_new_document(): void
    {
        $file = UploadedFile::fake()->createWithContent('meeting_notes.txt', "First line\nsame paragraph\n\nSecond <b>paragraph</b>");

        $this->actingAs($this->user)
            ->postJson('/api/documents/import', ['file' => $file])
            ->assertCreated()
            ->assertJsonPath('data.title', 'meeting notes')
            ->assertJsonPath('data.role', 'owner')
            ->assertJsonPath('data.source_filename', 'meeting_notes.txt')
            ->assertJsonPath('data.content', '<p>First line<br />same paragraph</p><p>Second &lt;b&gt;paragraph&lt;/b&gt;</p>');
    }

    #[Test]
    public function markdown_keeps_headings_lists_and_emphasis_and_drops_raw_html(): void
    {
        $file = UploadedFile::fake()->createWithContent('plan.md', "# Plan\n\n**Bold** and *italic*\n\n- one\n- two\n\n1. first\n\n<script>alert(1)</script>");

        $content = $this->actingAs($this->user)
            ->postJson('/api/documents/import', ['file' => $file])
            ->assertCreated()
            ->json('data.content');

        $this->assertStringContainsString('<h1>Plan</h1>', $content);
        $this->assertStringContainsString('<strong>Bold</strong> and <em>italic</em>', $content);
        $this->assertMatchesRegularExpression('/<ul>\s*<li>one<\/li>\s*<li>two<\/li>\s*<\/ul>/', $content);
        $this->assertStringContainsString('<ol>', $content);
        $this->assertStringNotContainsString('script', $content);
    }

    #[Test]
    public function a_word_document_maps_headings_runs_and_lists(): void
    {
        $file = $this->docxUpload('Quarterly review.docx');

        $content = $this->actingAs($this->user)
            ->postJson('/api/documents/import', ['file' => $file])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Quarterly review')
            ->json('data.content');

        $this->assertSame(
            '<h1>Quarterly review</h1>'
            .'<p>Plain <strong>bold</strong> <em>italic</em> <u>underlined</u></p>'
            .'<ul><li><p>Bullet one</p></li><li><p>Bullet two</p></li></ul>'
            .'<ol><li><p>Step one</p></li></ol>'
            .'<h2>Next steps</h2>',
            $content,
        );
    }

    #[Test]
    public function an_editor_can_convert_a_file_for_insertion_but_a_viewer_cannot(): void
    {
        $owner = User::factory()->create();
        $document = Document::factory()->for($owner, 'owner')->sharedWith($this->user, 'viewer')->create();
        $file = UploadedFile::fake()->createWithContent('extra.md', '## Appendix');

        $this->actingAs($this->user)
            ->postJson("/api/documents/{$document->id}/import", ['file' => $file])
            ->assertForbidden();

        $this->actingAs($owner)
            ->postJson("/api/documents/{$document->id}/import", ['file' => $file])
            ->assertOk()
            ->assertJsonPath('html', '<h2>Appendix</h2>');

        // Converting never writes: the content changes only through a versioned save.
        $this->assertSame(1, $document->fresh()->version);
    }

    #[Test]
    public function unsupported_empty_or_broken_files_are_rejected_clearly(): void
    {
        $cases = [
            [UploadedFile::fake()->create('slides.pdf', 10, 'application/pdf'), 'Unsupported file type. Upload a .txt, .md or .docx file.'],
            [UploadedFile::fake()->createWithContent('blank.txt', "  \n\n "), 'That file has no readable text to import.'],
            [UploadedFile::fake()->createWithContent('fake.docx', 'not a zip'), 'That .docx file could not be opened. Is it a valid Word document?'],
            [UploadedFile::fake()->create('huge.txt', 6000), 'Files can be up to 5 MB.'],
        ];

        foreach ($cases as [$file, $message]) {
            $this->actingAs($this->user)
                ->postJson('/api/documents/import', ['file' => $file])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['file' => $message]);
        }

        $this->assertDatabaseCount('documents', 0);
    }
}
