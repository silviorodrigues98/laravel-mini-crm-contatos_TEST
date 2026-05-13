# Mini CRM de Contatos

## What This Is

A REST API for managing contacts with real-time score tracking. Users create contacts, trigger async score processing (based on email domain, name length, and phone area code rules), and receive live updates via WebSocket as the score evolves through `pending → processing → active|failed` states. Built as a technical challenge demonstrating DDD, SOLID, and TDD in Laravel.

## Core Value

Contacts are created and their scores are calculated asynchronously with real-time status updates broadcast to the client.

## Requirements

### Validated

(None yet — ship to validate)

### Active

- [ ] CRUD endpoints for contacts (create, list, show, update, soft-delete)
- [ ] Async score processing triggered via POST endpoint
- [ ] Score calculation using Strategy pattern (email domain/ccTLD, name length, phone DDD rules)
- [ ] Status machine: pending → processing → active|failed
- [ ] Domain event + listeners (log file + Reverb broadcast)
- [ ] DDD layers: Domain (entities, VOs, services), Application (use cases), Infrastructure (Laravel)
- [ ] Value Objects for Email, Phone, Status
- [ ] Repository pattern with interfaces in Domain, Eloquent in Infrastructure
- [ ] Form Requests for input validation, API Resources for JSON output
- [ ] Soft deletes on Contact model
- [ ] Unit tests for Domain/Application, feature tests for endpoints
- [ ] Basic HTML/JS listener example in README

### Out of Scope

- Authentication/authorization — not required by the spec
- Frontend UI (beyond the HTML/JS Reverb example)
- Persistent WebSocket reconnection handling
- Admin dashboard

## Context

This is a technical assessment evaluating DDD, SOLID, TDD, Laravel ecosystem fluency, and design patterns. The original README (now in `docs/original/README.md`) is the spec that must be followed to the letter. The root `README.md` will be rewritten in Brazilian Portuguese documenting the actual project. The project must be easily reproducible by whoever is evaluating it.

## Constraints

- **Tech stack**: Laravel, Redis (queue), Laravel Reverb (WebSocket)
- **Architecture**: DDD with strict layer separation — Domain must NOT import Laravel facades or ORM
- **Testing**: TDD expected — unit tests (mock infra) + feature/integration tests
- **Async**: Queue job must include `sleep(1-2)` simulation
- **Database**: MySQL/SQLite with soft deletes
- **Status enum**: `pending`, `processing`, `active`, `failed`

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| DDD over MVC | Spec explicitly requires DDD/Clean Architecture separation | — Pending |
| Strategy pattern for score | Enables easy extension of scoring rules | — Pending |
| Value Objects over primitives | Domain integrity for Email, Phone, Status | — Pending |
| Repository pattern + DI | Inversion of dependency, testability | — Pending |
| Redis for queue | Spec requires Redis | — Pending |
| Reverb for WebSocket | Spec requires Laravel Reverb | — Pending |
| Original spec preserved | `docs/original/README.md` — immutable source of truth | ✓ Good |
| README em PT-BR | Root README documents the actual project in Brazilian Portuguese | — Pending |
| Spec compliance | Every rule/endpoint/behavior from original spec must be implemented | — Pending |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd-transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `/gsd-complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-05-12 after initialization*
