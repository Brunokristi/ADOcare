# commit-organize

Group current git changes into logical commits.

## Usage

`/commit-organize`

## Task

Use `#file:.github/agents/git-commit-organizer.agent.md`.

1. Inspect current git state:
   - `git status --short`
   - `git diff` (staged and unstaged)
   - `git log --oneline -n 20`
2. Propose logical commit groups:
   - files per group
   - rationale
   - proposed commit message
3. Ask for approval before any commit:
   - `Proceed with this commit plan? (Yes/No)`
4. If approved:
   - stage and commit one group at a time
   - print resulting commit SHAs and messages
   - print final `git status --short`

## Constraints

- Never commit without explicit approval.
- Never include likely secrets (`.env`, private keys, token files).
- Do not rewrite history (`--amend`, rebase, reset) unless explicitly requested.
- Do not push unless explicitly requested.
