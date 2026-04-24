---
module: Officeimpresso1
status: deprecated
superseded_by: Officeimpresso
deprecated_at: 2026-04-23
---

# Officeimpresso1 — DEPRECATED

> Este arquivo refere-se a um **backup** do módulo Officeimpresso 3.7 que ficou
> residual no filesystem após a migração 3.7 → 6.7. Causava conflito de namespace
> (mesmo `name: Officeimpresso` no `module.json`) impedindo Laravel de carregar
> traduções novas.

## Resolução

Em 2026-04-23 o diretório `Modules/Officeimpresso1/` foi **movido para `~/Officeimpresso1-3.7-BACKUP/`** no servidor de produção. Todo o código relevante foi restaurado dentro do módulo principal `Modules/Officeimpresso/` via ADR 0017.

## Ver

- `Modules/Officeimpresso/` — implementação atual (v1.3.0)
- `memory/requisitos/Officeimpresso.md` — requisitos funcionais vigentes
- `memory/decisions/0017-officeimpresso-restaurado-superadmin-exclusivo.md`
- `origin/3.7-com-nfe` — snapshot original (fonte da restauração)

---
_Este arquivo existe apenas pra manter trail de auditoria. Não há mais código Officeimpresso1 ativo._
