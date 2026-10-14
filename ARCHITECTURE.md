# Architecture note

## What I prioritized, and why

The brief rewards depth in a few areas over breadth, so I picked three things a team would actually trip over in a shared editor and made them solid:

1. **Never lose someone's work.** Autosave plus versioned saves: a stale save is rejected, never silently applied. This mattered more to me than features like comments, because silent overwrites are the bug that makes people stop trusting a shared doc.
2. **Access rules that hold on the server.** Owner, editor and viewer are enforced in one policy, and every endpoint goes through it. The UI only reflects what the server allows.
3. **Imports that keep structure.** A `.docx` that arrives as a flat wall of text isn't useful, so imports keep headings, emphasis and lists, and go through the same sanitizer as normal saves.

Realtime presence and export came last, as stretch work, once those three were tested.

## Stack

| Layer | Choice | Reason |
|---|---|---|
| API | Laravel 13, Sanctum (cookie SPA auth) | Mature validation, policies and queues; same-origin SPA means no CORS or token storage |
| UI | Vue 3 + TypeScript (strict), vue-router | Typed API contracts in one file (`resources/js/types/api.ts`) |
| Editor | TipTap 3 (ProseMirror) | Real schema, so output is predictable HTML; keyboard shortcuts and lists for free |
| Realtime | Laravel Reverb + Echo | First-party, self-hosted, free; no paid service for reviewers |
| Storage | MySQL locally, SQLite on the demo host | Zero add-ons on a free host; nothing database-specific in the code |
| Runtime | Docker Compose (dev), one supervised container (prod) | Reviewers need only Docker; one image deploys anywhere |

## Data model

```
users ─┬─< documents (owner_id)
       │     id, title, content (sanitized HTML), version, last_edited_by, source_filename, timestamps
       │
       └─< document_shares >─ documents
             document_id, user_id, role (viewer | editor), unique(document_id, user_id)
```

Ownership is a column, not a share row, so a document can't lose its owner and "shared with me" is a simple join.

**Why HTML and not ProseMirror JSON?** HTML is what imports produce, what exports need, and what a sanitizer can reason about. The editor's schema is small (paragraphs, H1–H3, lists, emphasis, quotes, links), and the server allowlist matches it, so the stored HTML is exactly the editor's vocabulary. JSON would only pay off with collaborative editing (Yjs), which is out of scope.

## Access control

`DocumentPolicy` is the single source of truth:

| | view | edit content/title | import into | share / change roles | delete |
|---|---|---|---|---|---|
| Owner | ✓ | ✓ | ✓ | ✓ | ✓ |
| Editor | ✓ | ✓ | ✓ | – | – |
| Viewer | ✓ | – | – | – | – |
| Anyone else | 403 | 403 | 403 | 403 | 403 |

The list endpoint resolves each row's role with one sub-select instead of a query per row. Presence channels use the same `roleFor()` check, so you can only see who is in a document you can open.

## Saving and conflicts

```
client                                   server
──────                                   ──────
type … (debounce 800 ms)
PATCH /documents/7 {version: 4, content}
                                         UPDATE … SET content=?, version=version+1
                                         WHERE id=7 AND version=4
                                         rows = 1 → 200 {version: 5}
                                         rows = 0 → 409 {document: latest}
```

- The compare-and-swap happens in a single `UPDATE`, so two simultaneous saves can't both win.
- The client never has two saves in flight; keystrokes typed during a save are queued and sent next.
- A `409` stops autosave and shows **Load their version** / **Keep mine and overwrite**. Nothing is written until the user chooses.
- With realtime on, a teammate's save is pulled in automatically when you have no unsaved edits, so most "conflicts" never happen. The prompt remains for true simultaneous edits.
- Editor no-ops (loading, toggling read-only) don't create versions. That one was a real bug found in browser testing: TipTap's `setEditable()` emits an update by default, so simply opening a document produced a save and a false conflict for the other editor.

## Import pipeline

`DocumentImporter` validates the extension (`.txt`, `.md`, `.markdown`, `.docx`) and size (5 MB), converts, then sanitizes:

- **.txt**: blank lines become paragraphs, single newlines become line breaks, Windows-1252 is converted to UTF-8.
- **.md**: CommonMark with raw HTML stripped and unsafe links disabled.
- **.docx**: a small purpose-built reader for WordprocessingML instead of a general library. It maps `Heading 1–3`/`Title` styles (by style name, so localized style IDs still work), `b/i/u/strike` runs, numbering definitions to bulleted or numbered lists, and table cell text into the flow. It refuses oversized XML parts (zip-bomb guard) and parses with network access disabled. Writing it was cheaper than taming a general converter's output into the editor's schema.

"Import into an open document" only converts on the server; the editor inserts the HTML at the cursor, and it is saved through the normal versioned save, so imports can't bypass conflict detection.

## Security notes

- Stored HTML is sanitized on every write with an allowlist (HTMLPurifier): no scripts, event handlers, styles, images or `javascript:` links. This matters because documents render in other people's browsers.
- Same-origin cookie auth with CSRF protection; login and register are rate-limited, as are imports.
- Realtime events carry metadata only; document content is always fetched through the API, which re-checks access.
- The websocket key reaches the browser through a meta tag at runtime rather than being compiled into the JS bundle, so one image works in any environment.

## Design

The interface uses the **AsclepXCare design system** with its domain content removed: Source Serif 4 headings over Barlow body text, a flat zero-radius grid with 2 px rules, blueprint corner marks on key objects (the import panel, the document page, empty states), a slate-blue accent scale, shape-coded status markers (circle = saved, diamond = warning, triangle = error) so status never relies on color alone, Lucide icons at 1.5 stroke, and restrained motion (enter 240 ms ease-out, exit 160 ms, nothing bounces, reduced-motion respected). Tokens and components live in `resources/css/app.css`.

## Deliberate cuts

| Cut | Why |
|---|---|
| Character-level co-editing (CRDT/OT) | A large problem on its own; versioned saves plus live reload covers the brief safely |
| Comments, suggestions, version history UI | Valuable, but each is a feature-sized slice; history is the first thing I'd add |
| Images and tables in documents | Expands the sanitizer, import and export surface for little demo value |
| Public share links, groups, transfer of ownership | The brief asks for user-to-user sharing |
| Password reset, email verification | Not needed to evaluate the product; seeded accounts instead |
