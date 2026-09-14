# Conventional Commits & Git Workflow Rules

This project enforces Conventional Commits, message formatting, and mandatory user confirmation rules defined in [.agents/rules/commit_type.yml](file:///d:/mallow/.agents/rules/commit_type.yml).

## Format
`<type>(<scope>): <short description>`

- **Format**: Lowercase description, imperative wording (e.g. `add`, not `added`), concise, <= 72 characters, no trailing period.
- **Allowed Types**: `feat`, `fix`, `refactor`, `perf`, `docs`, `test`, `style`, `chore`, `build`, `ci`, `revert`.
- **Preferred Scopes**: `auth`, `api`, `user`, `role`, `database`, `migration`, `model`, `controller`, `route`, `middleware`, `config`, `docker`, `sail`.

## Mandatory Workflow & Security
1. **User Confirmation**:
   - Never create or checkout a new git branch without explicit user confirmation.
   - Always ask and obtain explicit confirmation from the user before executing `git commit`.
   - Never execute `git push` without explicit user confirmation.
2. **Review Before Commit**:
   - Check `git status` and `git diff` before staging.
   - Never commit sensitive files (`.env`, credentials, tokens, keys).
   - Only stage files related to the logical change.
