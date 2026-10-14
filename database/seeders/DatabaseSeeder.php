<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo accounts for reviewing the sharing flow. All passwords are "password".
 *
 *  Alice: owns "Q4 planning notes" (Bob can edit) and "Launch announcement draft" (private)
 *  Bob:   owns "Team onboarding checklist" (Alice can view)
 *  Carol: no documents yet; share something with her to see the flow from a clean slate
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $alice = $this->user('Alice Moreno', 'alice@ajaia.test');
        $bob = $this->user('Bob Tan', 'bob@ajaia.test');
        $this->user('Carol Reyes', 'carol@ajaia.test');

        $planning = $this->document($alice, 'Q4 planning notes', <<<'HTML'
            <h1>Q4 planning notes</h1>
            <p>Goals for the quarter, owners, and the open questions we still need to settle. <strong>Edit freely</strong>; this is a working doc.</p>
            <h2>Goals</h2>
            <ol><li><p>Ship the shared editor beta to two internal teams.</p></li><li><p>Cut weekly status meeting time in half.</p></li><li><p>Publish the <em>workspace conventions</em> guide.</p></li></ol>
            <h2>Open questions</h2>
            <ul><li><p>Do we need comments before beta, or after?</p></li><li><p>Who owns onboarding for new teams?</p></li></ul>
            HTML);
        $planning->collaborators()->syncWithoutDetaching([$bob->id => ['role' => 'editor']]);

        $this->document($alice, 'Launch announcement draft', <<<'HTML'
            <h1>Launch announcement</h1>
            <p>Draft copy for the internal launch post. <u>Not ready to share yet.</u></p>
            HTML);

        $onboarding = $this->document($bob, 'Team onboarding checklist', <<<'HTML'
            <h1>Team onboarding checklist</h1>
            <p>Everything a new teammate needs in their first week.</p>
            <h2>Day one</h2>
            <ul><li><p>Laptop, accounts and access requests</p></li><li><p>Read the <strong>engineering handbook</strong></p></li><li><p>Pair with a buddy on a small change</p></li></ul>
            <h2>Week one</h2>
            <ul><li><p>Ship something small to production</p></li><li><p>Write down three things that confused you</p></li></ul>
            HTML);
        $onboarding->collaborators()->syncWithoutDetaching([$alice->id => ['role' => 'viewer']]);
    }

    private function user(string $name, string $email): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => 'password'],
        );
    }

    private function document(User $owner, string $title, string $html): Document
    {
        $existing = Document::query()->where('owner_id', $owner->id)->where('title', $title)->first();
        if ($existing) {
            return $existing;
        }

        $document = new Document(['title' => $title, 'content' => preg_replace('/>\s+</', '><', trim($html))]);
        $document->owner()->associate($owner);
        $document->last_edited_by = $owner->id;
        $document->save();

        return $document;
    }
}
