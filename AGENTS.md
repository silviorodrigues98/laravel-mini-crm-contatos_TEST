# AGENTS.md

## Project

Greenfield Laravel REST API — Mini CRM de Contatos (DDD & TDD challenge).
See `README.md` for the full spec; it is the single source of truth for requirements.

## Key constraints from the spec

- **DDD layers**: Domain (entities, value objects, domain services, agnostic to framework), Application (use cases/actions), Infrastructure (Laravel controllers, Eloquent repositories, jobs, events, listeners, form requests, API resources).
- **TDD expected**: unit tests for Domain/Application (mock infrastructure), feature/integration tests for endpoints + DB + queues.
- **Value objects**: Email, Phone, Status (not raw strings in entities).
- **Repository pattern**: interfaces in Domain, Eloquent implementations in Infrastructure, wired via Laravel's service container.
- **Score calculation**: Strategy pattern on email domain/country, name length, phone DDD rules.
- **Async processing**: Queue job (Redis) with `sleep(1-2)` to simulate. Status transitions: `pending -> processing -> active|failed`.
- **Reverb**: broadcast score updates on `contacts.{id}` channel. Include a basic HTML/JS listener example in README.
- **Soft deletes**, timestamps, `processed_at` on Contact model.
- **Contact status enum**: `pending`, `processing`, `active`, `failed`.

## Dev workflow

```bash
# Full test suite
php artisan test
# Single test class
php artisan test --filter=SomeTestClass
# Single test method
php artisan test --filter="test_method_name"
```

## Architecture notes

- Domain layer must NOT import Laravel facades or ORM.
- Use Case constructor injection for repository interfaces — Laravel service container auto-resolves bindings.
- Form Requests for input validation, API Resources for JSON output.
- `Observer` on Contact model (e.g., `saving` to normalize phone).
- `ContactScoreProcessed` domain event → listener logs to `storage/logs/contact.log` + broadcasts via Reverb.

## Commands reference

| Action | Command |
|--------|---------|
| Migrate | `php artisan migrate` |
| Queue work | `php artisan queue:work` |
| Reverb serve | `php artisan reverb:start` |
| Fresh DB + seed | `php artisan migrate:fresh --seed` |

<!-- GSD:project-start source:PROJECT.md -->
## Project

**Mini CRM de Contatos**

A REST API for managing contacts with real-time score tracking. Users create contacts, trigger async score processing (based on email domain, name length, and phone area code rules), and receive live updates via WebSocket as the score evolves through `pending → processing → active|failed` states. Built as a technical challenge demonstrating DDD, SOLID, and TDD in Laravel.

**Core Value:** Contacts are created and their scores are calculated asynchronously with real-time status updates broadcast to the client.

### Constraints

- **Tech stack**: Laravel, Redis (queue), Laravel Reverb (WebSocket)
- **Architecture**: DDD with strict layer separation — Domain must NOT import Laravel facades or ORM
- **Testing**: TDD expected — unit tests (mock infra) + feature/integration tests
- **Async**: Queue job must include `sleep(1-2)` simulation
- **Database**: MySQL/SQLite with soft deletes
- **Status enum**: `pending`, `processing`, `active`, `failed`
<!-- GSD:project-end -->

<!-- GSD:stack-start source:STACK.md -->
## Technology Stack

Technology stack not yet documented. Will populate after codebase mapping or first phase.
<!-- GSD:stack-end -->

<!-- GSD:conventions-start source:CONVENTIONS.md -->
## Conventions

### Documentation

- **Original spec**: `docs/original/README.md` — imutável. NÃO editar. É a fonte da verdade dos requisitos.
- **Root README**: `README.md` — documenta o projeto real em português brasileiro. Deve conter instruções de setup, arquitetura, exemplos de uso, e como rodar testes/filas/reverb.
- **Reprodutibilidade**: O projeto deve ser fácil de clonar, configurar e testar por quem for avaliar.

### Spec Compliance

- Toda regra de negócio do `docs/original/README.md` deve ser implementada.
- Nenhum endpoint, comportamento, ou requisito arquitetural pode ser omitido.
- Se algo no spec não estiver claro, preservar o comportamento descrito.
<!-- GSD:conventions-end -->

<!-- GSD:architecture-start source:ARCHITECTURE.md -->
## Architecture

Architecture not yet mapped. Follow existing patterns found in the codebase.
<!-- GSD:architecture-end -->

<!-- GSD:skills-start source:skills/ -->
## Project Skills

No project skills found. Add skills to any of: `.claude/skills/`, `.agents/skills/`, `.cursor/skills/`, `.github/skills/`, or `.codex/skills/` with a `SKILL.md` index file.
<!-- GSD:skills-end -->

<!-- GSD:workflow-start source:GSD defaults -->
## GSD Workflow Enforcement

Before using Edit, Write, or other file-changing tools, start work through a GSD command so planning artifacts and execution context stay in sync.

Use these entry points:
- `/gsd-quick` for small fixes, doc updates, and ad-hoc tasks
- `/gsd-debug` for investigation and bug fixing
- `/gsd-execute-phase` for planned phase work

Do not make direct repo edits outside a GSD workflow unless the user explicitly asks to bypass it.
<!-- GSD:workflow-end -->

<!-- GSD:profile-start -->
## Developer Profile

> Profile not yet configured. Run `/gsd-profile-user` to generate your developer profile.
> This section is managed by `generate-claude-profile` -- do not edit manually.
<!-- GSD:profile-end -->
