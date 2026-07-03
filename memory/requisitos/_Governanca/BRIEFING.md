# BRIEFING — _Governanca (porta de entrada)

> **O que é esta área:** a casa dos artefatos de governança **executável** do oimpresso —
> planos e roadmaps que dirigem a máquina de gates/catracas/sentinelas (ADR 0256/0264/0271/0275/0294),
> distinta de `Modules/Governance` (o módulo Laravel que implementa dashboards/grades) e de
> `memory/decisions/` (as ADRs em si).

## Estado consolidado (2026-07-02)

A governança do projeto tem **22 checks required** protegendo Tier-0 (multi-tenant, PII,
secrets, append-only, domínio) e um framework de durabilidade (catraca + sentinela + gate +
cadência, ADR 0256). O débito conhecido está na camada de **correção de valor** (cálculo de
dinheiro sem teste — camada do incidente `num_uf`) e **paridade de migração** (31 telas
Blade→React sem verificação) — é o que o programa de ondas ataca.

## O que vive aqui

- **`roadmap/`** — etapas P01-P10 do programa SDD (suíte de testes/gates de Governance:
  read-side floor, baseline full-suite, nightly verde, PCOV, métricas GT). Convenção: 1
  arquivo por etapa `PNN-slug.md`.
- **`programa-ondas/`** — o programa de ondas por módulo de negócio (adversário concorrente →
  gaps/backlog → régua por tela com comportamento → catraca). Status vivo no
  `programa-ondas/PLANO-MESTRE.md` (ADR 0294). Piloto: Sells.
- **`RUNBOOK-transporte-sentinel.md`** — sentinela de transporte Cowork→código.

## Fronteiras (pra não duplicar)

- Régua/notas de módulo → ADR 0155 (`module:grade`), scorecards em `memory/governance/`.
- Régua/notas de tela → `_DesignSystem/SCREEN-GRADE-METODO.md`.
- Gates de CI e required set → ADR 0271/0314 + `.github/workflows/`.
- Tasks executáveis → MCP (`tasks-*`, ADR 0070) — nunca status em markdown daqui.

**Última atualização:** 2026-07-02 — porta criada junto com o `programa-ondas/` (a área
passou a ter 12+ docs e precisava de entrada auto-contida; front-door coverage GT).
