---
paths:
  - 'app/Modules/**'
---

# Modules

## Module folder structure (docs/00 §3)
Feature code lives in `app/Modules/<Module>/` (Foundation, Catalog, Crm, Projects, Estimation, Sales, Purchases, Accounting, Hrm, Reports) with subfolders Models, Livewire, Policies, Services, Actions, Events, Listeners. Shared helpers (Money, NumberSequenceService, AuditTrail, Lookups, Exports) go in `app/Support/`. Migrations go in `database/migrations/<module>/`, seeders in `database/seeders/<module>/`, and routes in `routes/modules/<module>.php`, required from `routes/web.php`. Business logic belongs in Action classes, never in Livewire components or controllers. Modules talk to each other through domain events and Actions and never write another module's tables directly. Any Action that changes money runs inside `DB::transaction()`.
