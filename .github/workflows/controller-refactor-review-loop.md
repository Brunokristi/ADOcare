---
description: Refactor a target Laravel file with architect-reviewer loop (max 3 rounds).
on:
  workflow_dispatch:
    inputs:
      target_file:
        description: File path to refactor (usually a controller).
        required: true
        type: string
permissions: read-all
safe-outputs:
  create-pull-request:
    max: 1
  add-comment:
    max: 2
  noop:
    max: 1
---

# Controller Refactor Review Loop

You will refactor one target file, then validate the result with an explicit review gate.

Target file: `${{ github.event.inputs.target_file }}`

## Roles

- Refactor role definition: `#file:senior-laravel-architect.agent.md`
- Review role definition: `#file:ruthless-code-reviewer.agent.md`

## Process (Maximum 3 Rounds)

1. Resolve and validate the target file path.
2. Run a refactor pass using `#file:senior-laravel-architect.agent.md`.
3. Run a verification pass using `#file:ruthless-code-reviewer.agent.md`.
4. If reviewer verdict is **SATISFIED**, stop immediately and prepare final output.
5. If reviewer verdict is **UNSATISFIED**, apply reviewer feedback and continue to the next round.
6. Stop after 3 rounds maximum.

## Review Contract

For each round, produce:

- A short change summary.
- Reviewer verdict: `SATISFIED` or `UNSATISFIED`.
- If unsatisfied, an actionable checklist with concrete fixes.

Use this strict format:

```text
ROUND: <1|2|3>
VERDICT: <SATISFIED|UNSATISFIED>
NOTES:
- ...
```

## Completion Rules

- If verdict becomes `SATISFIED` within 3 rounds:
  - Create a pull request with a concise title/body describing the refactor and key review outcomes.
- If still unsatisfied after round 3:
  - Add a comment explaining why constraints were not satisfiable and what remains.
- If nothing needed to change (already compliant), call `noop` with the reason.

## Guardrails

- Keep scope focused on `${{ github.event.inputs.target_file }}` and directly required supporting edits only.
- Preserve behavior unless reviewer feedback explicitly requires safe, justified adjustments.
- Do not claim success without an explicit `SATISFIED` reviewer verdict.
