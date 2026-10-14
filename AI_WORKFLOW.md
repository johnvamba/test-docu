# AI workflow note

> **Draft:** this records what happened while the project was built with Claude. Review it and add your own changes and judgement calls (marked ✎) before submitting.

## Tools

- **Claude (Opus 5.5, claude.ai)** with code execution, used as a pair programmer for the whole build: scaffolding, backend, frontend, tests, Docker, docs.
- **A saved Laravel + Vue + Docker skill**: my standard stack recipe (Sanctum cookie auth, Redis queues, Reverb, nginx), so the scaffold matched how I already build apps.
- **My AsclepXCare design system**: the visual language (type, tokens, components, motion) was taken from it with all healthcare content removed.
- **Playwright + headless Chromium** for scripted browser checks; **PHPUnit** and **vue-tsc** for automated verification.

✎ Add any other tools you used (IDE assistant, Copilot, etc.).

## Where AI materially sped things up

- **Scaffolding and wiring.** Docker Compose, Sanctum SPA auth, Reverb channels and typed API clients took minutes instead of an afternoon.
- **The `.docx` reader.** Mapping WordprocessingML styles, runs and numbering definitions to HTML is fiddly spec work; drafting it with AI and pinning it with a test fixture was much faster than reading the OOXML spec cold.
- **Tests.** Breadth of edge cases (outsider access, duplicate shares, stale versions, sanitizer bypasses, malformed files) came quickly once the behaviour was decided.
- **Porting the design system** into tokens and component classes for a new product.

## What was changed or rejected

Real issues from this build, each caught by a test or a browser run rather than by reading the code:

| AI output | Problem | What changed |
|---|---|---|
| Editor hydration called `setEditable(true)` | TipTap emits an update by default, so **just opening a document saved it and bumped the version**, giving the other editor a false conflict | Pass `emitUpdate: false`, and the session ignores content identical to what's stored |
| Text import used `nl2br()` | Left raw newlines inside paragraphs | Replace line breaks explicitly; the test asserts exact HTML |
| Seeder used `firstOrNew(['owner_id' => …])` | `owner_id` isn't mass-assignable, so it was silently dropped | Associate the owner explicitly |
| Websocket key compiled into the JS bundle via `VITE_*` | The deployed key must match a server secret generated later, so builds were tied to one environment | Served at runtime through a meta tag |
| Share/role-change/revoke used one event | A role change toasted "Alice shared…" | Event carries `granted / updated / revoked`; a test pins it |
| New-document title selection | Ran before the loaded title rendered, so typing produced "Untitled documentDesign review notes" | Wait a tick after hydrating |
| Mobile documents list | Import panel pushed the list below the fold; titles truncated to "De…" | Reordered on small screens; titles wrap |
| Dependency picks | TypeScript 7 (native compiler) isn't supported by `vue-tsc`; `lucide-vue-next` is deprecated | Pinned TypeScript 5.9; switched to `@lucide/vue` |
| A browser test's "Saved" check | Matched "Un**saved** changes" | Exact-match status assertions |

Rejected approaches: a general-purpose `.docx` library (its output didn't map cleanly to the editor schema), and storing ProseMirror JSON (HTML is what import, export and sanitizing all need).

✎ Add anything you personally rewrote or pushed back on after reviewing.

## How correctness and quality were verified

- **PHPUnit (20 tests, 117 assertions)**: access rules for every role, sharing validation, role changes and revocation, conflict detection, sanitization, all three import formats with a `.docx` built inside the test, rejected files, and which realtime events go to which channel.
- **Strict TypeScript** (`vue-tsc --noEmit`) and a production Vite build on every change.
- **Scripted browser runs** with three signed-in users against a running server: create, format and reload; share with bad and good emails; viewer read-only; a forced conflict; `.docx` and `.md` imports; Markdown export; no-access page; mobile width with no horizontal overflow; zero uncaught JS errors.
- **Realtime with a real Reverb server**: presence both ways, a teammate's edit arriving live, live downgrade to viewer, live revocation, and the shared list updating, repeated in production mode (cached config, database queue worker relaying broadcasts).
- **UX review from screenshots** at desktop and mobile widths, which is where the layout issues above were found.

✎ Add your own manual review notes (accessibility pass, copy edits, anything you tried by hand).
