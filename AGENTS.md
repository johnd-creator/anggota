# Agent instructions

This file is committed to the repo so any AI agent (and humans) can follow the same workflow.

## Operating rules

1. Before making changes, read the last entries in:
   - `development-notes/backend.md` (backend changes)
   - `development-notes/ux.md` (UI/UX changes)
   - `development-notes/security.md` (security/review notes)
   If these files do not exist locally, create them (they are intentionally ignored by git).
2. After completing work, append a short entry to every relevant note.
3. Never write secrets or sensitive data into notes:
   - No passwords, tokens, private keys, `.env` contents, production URLs, user PII, or database dumps.
   - If you must reference a secret, write “(stored in password manager)” and the variable name only.
4. Notes must be concise and actionable (no raw logs). Prefer summaries.

## Entry format (append to the bottom)

Use this template in each relevant note:

- Date: `YYYY-MM-DD HH:MM` (local)
- Scope: `backend` | `ux` | `security`
- Summary: 1–2 bullets describing what changed
- Files: list of touched paths (or “none”)
- Commands: key commands run (or “none”)
- Decisions/Risks: anything that could affect future work
- Next: concrete next step (or “none”)

## Mapping: what goes where

- `development-notes/backend.md`: Laravel, DB/migrations/seed, APIs, jobs/queue, config, tooling.
- `development-notes/ux.md`: views/components, styling, copy, flows, accessibility, performance UX.
- `development-notes/security.md`: auth/roles/permissions, data exposure, validation, dependencies, configs, threat notes.
