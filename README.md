# dp-events — Events module

Extracted from `daems-platform` Phase 1, 2026-04-27. Pattern proven by Insights pilot + Forum extraction + Projects extraction.

## Structure

- `module.json` — manifest read by core's `ModuleRegistry`
- `backend/src/` — PHP code under namespace `DaemsModule\Events\`
- `backend/bindings.php` — production DI bindings
- `backend/bindings.test.php` — test container bindings (InMemory fakes)
- `backend/routes.php` — public + backstage HTTP route registrations
- `backend/migrations/` — `event_NNN_*.sql` migrations
- `frontend/public/`, `frontend/backstage/`, `frontend/assets/` — daem-society UI

## Conventions

- PHPStan level 9 = 0 errors
- Tests use core's `KernelHarness`; per-module `bindings.test.php` swaps InMemory fakes
- Domain `Daems\Domain\Event\*` MOVED into module (no cross-domain consumers in core)
- No `Co-Authored-By` in commits; identity is `Dev Team <dev@daems.fi>`
