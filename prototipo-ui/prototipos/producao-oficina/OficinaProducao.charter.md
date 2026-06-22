---
page: Oimpresso · Produção / Kanban da Oficina · window.OficinaPage
component: oficina-page.jsx (+ oficina-page.css, oficina-forms.jsx)
register_irmao: OficinaProducao.decisoes.md
charter_canonico: charter.md
owner: wagner
last_update: 2026-06-22
related_adr: 0293-governanca-decisao-design-responsavel-registro-veredito
pii: false
---

# Page Charter (par IT2) — Produção / Kanban da Oficina

> **Ponteiro de pareamento `<Stem>.charter.md` ↔ `<Stem>.decisoes.md`** (ADR 0293 D-B · `integrity-check` IT2).
> O **corpo canônico** do charter desta tela permanece em [`charter.md`](./charter.md) — este arquivo NÃO o reescreve.
> O **Decision Register** no schema ADR 0293 está em [`OficinaProducao.decisoes.md`](./OficinaProducao.decisoes.md).
>
> Existe porque o IT2 pareia por **stem**: `OficinaProducao.charter.md` ↔ `OficinaProducao.decisoes.md`.
> A ADR 0293 já citava `OficinaProducao.charter.md`/`.decisoes.md` como o par esperado; aqui ele vira real.

## O que fica onde
- **Placar / drawer travado / modelo (A) reparo / anti-patterns** → [`charter.md`](./charter.md) (canônico, backfill 2026-06-02, nota 9.5 [W]).
- **Debate vivo (anéis Radar, D-01…D-08, notas funcionais do painel [W])** → [`decisoes.md`](./decisoes.md) (histórico).
- **Decisões no schema ADR 0293 (responsável/detecção/padrão/opções/status)** → [`OficinaProducao.decisoes.md`](./OficinaProducao.decisoes.md).

## Trilha do tempo
- 2026-06-22 · [CC] criou o ponteiro de par IT2 (stem `OficinaProducao`). Corpo do charter intacto em `charter.md`.
