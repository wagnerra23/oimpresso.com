---
page: /crm · window.CrmPage
component: crm-page.jsx
register_irmao: Crm.decisoes.md
charter_canonico: charter.md
owner: wagner
last_update: 2026-06-22
related_adr: 0293-governanca-decisao-design-responsavel-registro-veredito
pii: false
---

# Page Charter (par IT2) — /crm (Funil comercial / kanban)

> **Ponteiro de pareamento `<Stem>.charter.md` ↔ `<Stem>.decisoes.md`** (ADR 0293 D-B · `integrity-check` IT2).
> O **corpo canônico** do charter desta tela permanece em [`charter.md`](./charter.md) — este arquivo NÃO o reescreve.
> O **Decision Register** no schema ADR 0293 está em [`Crm.decisoes.md`](./Crm.decisoes.md).
> Os **casos de uso** (UC-C01…C06, narrativa Dado/Quando/Então) vivem no histórico [`decisoes.md`](./decisoes.md) (suíte de casos, não confundir com este Register).

## O que fica onde
- **Charter canônico (proposta Cowork, nota 8.6 · tela do repo ainda Blade legado L-26)** → [`charter.md`](./charter.md).
- **Casos de uso & aceite (6 UCs grounded em `crm-page.jsx`)** → [`decisoes.md`](./decisoes.md) (histórico).
- **Decisões no schema ADR 0293 (responsável/detecção/padrão/opções/status)** → [`Crm.decisoes.md`](./Crm.decisoes.md).

## Trilha do tempo
- 2026-06-22 · [CC] criou o ponteiro de par IT2 (stem `Crm`). Corpo do charter intacto em `charter.md`.
