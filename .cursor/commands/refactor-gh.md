# refactor-gh

Trigger the GitHub Agentic Workflow for refactoring from Cursor.

## Usage

`/refactor-gh <path-to-file>`

Example:
`/refactor-gh app/Http/Controllers/PatientController.php`

## Command

Run this in terminal:

```bash
gh workflow run "controller-refactor-review-loop" -f target_file="<path-to-file>"
```

Then monitor:

```bash
gh run list --workflow "controller-refactor-review-loop"
gh run view <run-id> --log
```

Workflow reference:
`#file:.github/workflows/controller-refactor-review-loop.md`
