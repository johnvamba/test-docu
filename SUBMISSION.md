# Submission

## Links

| | |
|---|---|
| Live product | `<add your Render URL>` |
| Walkthrough video | in [`Loom`](https://www.loom.com/share/e2ac6d302578458ea62fe5718c7561d6) |
| Google Drive folder | [`Link`](https://drive.google.com/file/d/1OEH8D57XEtbzSPJFPfWUSO3lXA5NCwbs/view?usp=drive_link) |

## Reviewer accounts

Password for all: `password` (one-click buttons on the sign-in page).

- `alice@ajaia.test`: owns *Q4 planning notes* (Bob can edit) and a private draft
- `bob@ajaia.test`: owns *Team onboarding checklist* (Alice can view)
- `carol@ajaia.test`: nothing shared yet

## What's in this folder

| Item | Where |
|---|---|
| Source code | this repository (`app/`, `resources/`, `routes/`, `database/`, `tests/`, `docker/`, …) |
| Setup and run instructions | [`README.md`](README.md) |
| Architecture note | [`ARCHITECTURE.md`](ARCHITECTURE.md) |
| AI workflow note | [`AI_WORKFLOW.md`](AI_WORKFLOW.md) |
| This file | `SUBMISSION.md` |
| Walkthrough video URL | [`VIDEO_URL.txt`](VIDEO_URL.txt) |
| Screenshots | [`docs/screenshots/`](docs/screenshots) |
| Automated tests | `tests/Feature/` (20 PHPUnit tests) |
| Local environment | `compose.yaml`, `Makefile`, `docker/php`, `docker/nginx` |
| Production image and deploy config | `docker/prod/`, `render.yaml` |

## Scope

**Working end to end:** create, rename, edit, autosave, reopen; bold/italic/underline/strike, H1–H3, bulleted and numbered lists; import `.txt`, `.md` and `.docx` as a new document or at the cursor; share by email as viewer or editor, change roles, remove access; owned vs shared views; server-enforced permissions; conflict-safe saving. Stretch: live presence and updates, Markdown/HTML export, role-based permissions.

**Incomplete:** no committed `composer.lock` (generated on first install); whole-document conflict resolution instead of merging; `.docx` nested lists are flattened and images are skipped; no password reset.

**Next 2–4 hours:** version history with restore, section-level merge or Yjs co-editing, anchored comments, persistent storage on the demo host plus browser tests in CI.

Details for each of these are in the README and the architecture note.
