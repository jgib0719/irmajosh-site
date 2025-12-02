# AI Instructions & Context

**Target Audience:** AI Agents (GitHub Copilot, etc.) working on the IrmaJosh.com codebase.

## Purpose
This directory contains high-level context, rules, and lessons learned to help you navigate and modify the codebase safely and effectively.

## Files
1.  **`CODEBASE_GUIDE.md`**: The "Source of Truth" for architecture, database schema, critical rules, and code patterns. **Read this first.**
2.  **`LESSONS_LEARNED.md`**: A collection of past bugs, edge cases, and specific implementation details that have caused issues before. Check this to avoid repeating mistakes.
3.  **`CLI_COMMANDS.md`**: A reference for the utility scripts available in `scripts/`. Use these instead of writing custom one-off scripts when possible.

## Workflow for AI Agents
1.  **Ingest Context**: Read `CODEBASE_GUIDE.md` to understand the stack and rules.
2.  **Check History**: Glance at `LESSONS_LEARNED.md` if you are working on Database, JS, or Calendar features.
3.  **Plan**: Break down your task. Check `CLI_COMMANDS.md` if you need to run migrations or clear caches.
4.  **Execute**: Make changes.
5.  **Cleanup**: If you create temporary test scripts, delete them.
6.  **Update Docs**: If you solve a tricky bug, add it to `LESSONS_LEARNED.md`.

## Key Reminders
- **Cache**: Always run `./scripts/bust_cache.sh` after frontend changes.
- **Database**: Use `migrations/` for schema changes.
- **Security**: Respect `csrfToken` and `cspNonce`.
