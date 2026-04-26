# refactor

Refactor a target file with your two-agent loop.

## Usage

`/refactor <path-to-file>`

Example:
`/refactor app/Http/Controllers/PatientController.php`

## Task

Run a maximum 3-round loop using:

- `#file:.github/agents/senior-laravel-architect.agent.md` for refactoring
- `#file:.github/agents/ruthless-code-reviewer.agent.md` for verification

Process:

1. Resolve `<path-to-file>` from command arguments.
2. Architect refactors the file.
3. Reviewer returns `SATISFIED` or `UNSATISFIED`.
4. If unsatisfied, apply feedback and repeat.
5. Stop early on `SATISFIED`, or stop at round 3.

Output format for each round:

```text
ROUND: <1|2|3>
VERDICT: <SATISFIED|UNSATISFIED>
NOTES:
- ...
```

Constraints:

- Keep edits scoped to the target file and directly required supporting changes only.
- Preserve behavior unless a safe fix is required by reviewer feedback.
- If no valid target path is provided, ask for one concise example path.

Reference workflow for GitHub runs:
`#file:.github/workflows/controller-refactor-review-loop.md`
