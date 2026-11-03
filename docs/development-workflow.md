# WAS-PRO development workflow

## Active project

- Directory: `F:\was-pro`.
- Routine development branch: local `main`.
- Edit and validate the application running from this directory. Passing CI in
  another checkout does not complete a task.
- Do not create additional worktrees, hidden previews, second application copies,
  or unnecessary PRs by default. Use another branch/PR only for substantial or
  risky changes, or when the owner explicitly requests it.

## Routine delivery

1. Inspect branch, HEAD, remote, status, and existing local work.
2. Preserve unrelated edits. Use retained stashes, file backups, or a clearly
   scoped checkpoint before synchronizing; never silently discard or commit them.
3. Edit source directly in the active directory and inspect the running app.
4. Run affected automated tests using the marked disposable test database only.
5. Run applicable TypeScript, lint, formatter, and build checks.
6. Create a focused commit containing only the task's intended changes.
7. Push to `origin/main` after validation if repository permissions/protections
   permit it. Stop and explain protection constraints instead of bypassing them.
8. Verify GitHub CI on the exact pushed commit. Fix failures and retest before
   reporting completion.

## Local assets and services

Inspect `public/hot` before deciding whether Laravel serves Vite dev assets or
`public/build` assets. Build from `F:\was-pro`, clear applicable caches, and restart
only necessary local services. Verify the owner's existing application URL.
Preserve the owner's existing database and environment settings.

## Recovery and boundaries

- Prefer `git revert` to roll back published commits; never force-push shared history.
- Do not use destructive reset/clean to force synchronization.
- Keep unrelated changes out of commits and retain recovery references.
- Do not delete existing worktrees without first verifying and preserving their work.
- Do not modify production data, run destructive production migrations, or deploy
  production without explicit owner authorization.
