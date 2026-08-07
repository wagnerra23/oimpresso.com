---
date: "2026-08-06"
time: "13:03 BRT"
slug: "d0-trilha-d-invocador-e-permissoes-orfas"
tldr: "A D0 da Trilha D virou cascata: medir quem invoca as máquinas achou um medidor de permissões órfão, ligá-lo publicou 43 achados, triá-los mostrou que 5 eram o próprio detector lendo comentário como código, e sobraram 2 bugs de acesso reais. Junto, dois documentos lidos toda sessão ainda mandavam rodar Pest em biz=1 — empresa real. 6 PRs mergeados."
cycle: null
prs: [5342, 5343, 5347, 5348, 5351, 5352]
decided_by: [W]
related_adrs:
  - "0358-doutrina-de-teste-tenant-98-supersede-0101"
  - "0155-module-grade-v3-sub-dimensoes-gate-ci"
  - "0256-knowledge-survival-meia-vida-catraca-sentinela"
next_steps:
  - "Decidir o conserto de fiscal.inutilizar (classe A caso 2): hasRole com sufixo #{business_id} OU declarar permissão e concedê-la à role no seeder — está escrito na US-GOV-059"
  - "Classe B da triagem: 5 permissões de features cujo módulo não existe nesta árvore (código legado no core) — provável remoção"
  - "Classe C: ~13 permissões de módulo nosso nunca declaradas — conferir ANTES fora das 5 fontes que o detector lê (seeder de módulo não entra)"
---

# Handoff 2026-08-06 13:03 BRT — D0 da Trilha D e a cascata das permissões órfãs

## Estado no momento do fechamento

MCP **indisponível** nesta estação (token ausente em `.claude/settings.local.json` — mesmo bloqueio registrado no handoff de 2026-08-05 12:11). Usado o fallback canônico: `Glob memory/handoffs/`, `git log`, `gh pr view`.

- **6 PRs mergeados** por [W]: #5342, #5343, #5347, #5348, #5351, #5352 — todos verificados presentes em `origin/main` por `git show`.
- Handoffs irmãos do dia anterior: `2026-08-05-2130`, `-1835`, `-1624`, `-1438`, `-0949`.
- ADR aceita na janela (por outra sessão): 0370 (promove `module-surface` e `catalog-graph` a required, #5318).

## O que aconteceu

O pedido foi "fazer o plano de documentação". **O plano já existia** — a Trilha D (ondas D0–D8) foi escrita e mergeada em 05/08 no `PLANO-MESTRE.md`, com `US-INFRA-048` em `doing`. Escrever outro seria duplicar o dono (§5 2026-08-03). Executei a **D0**.

A D0 pede a matriz `máquina → invocador → owner → documento → evidência`. Entreguei o eixo **invocador**, derivado, estendendo o dono (`maquinas-inventario.mjs`): o censo dizia *quais máquinas existem*, passou a dizer *quais rodam*. 150 scripts, 4 sem invocador (2,7%), FP zero.

Daí a cascata: um dos 4 era o `permission-drift.mjs` — **selftest verde em fixture hermética e nenhum invocador**. Ligado no CI, publicou **43 permissões órfãs**. Triadas, **5 eram o próprio detector lendo comentário como código** (`x` vindo de `str_repeat('x', 1MB)`; um ponto final de frase virando parte do nome). Sobraram 38, e dessas **2 são bugs de acesso reais**.

Em paralelo, a caçada a links mortos revelou que `proibicoes.md` e `PROTOCOLO-WAGNER-SEMPRE.md` — lidos em toda sessão — ainda prescreviam **Pest em `biz=1`**, que é a WR2 Sistemas, empresa real, num banco que não se limpa entre runs. Doutrina revogada pela ADR 0358 em favor do tenant fictício 98.

## Artefatos gerados

| PR | Entrega | Onde |
|---|---|---|
| #5342 | eixo `Invocador` derivado (+104 ln de lógica) | `scripts/governance/maquinas-inventario.mjs` · `memory/reference/MAQUINAS-INVENTARIO.md` |
| #5343 | trava `VozDoCliente` 46→50 (v3.6.2) — PR de [W]; fechei meu #5344 duplicado | `governance/module-grades-baseline.json` |
| #5347 | liga o medidor no CI (advisory, report-only) | `.github/workflows/governance-script-tests.yml` |
| #5348 | doutrina `biz=1` → `biz=98` (3+4 pontos) | `memory/proibicoes.md` · `memory/reference/PROTOCOLO-WAGNER-SEMPRE.md` |
| #5351 | detector para de ler comentário (43→38) + 5 asserts FP-4 + 2 controles | `scripts/governance/permission-drift.mjs` + `.test.mjs` |
| #5352 | fix `kb.ai`→`kb.ai.ask` + triagem completa registrada | `Modules/KB/Http/Controllers/KbController.php` · `memory/requisitos/Governance/SPEC.md` |

## Persistência

- **Git:** 6 PRs em `origin/main`, confirmados por `git show origin/main:<path>` (5/5 marcadores presentes).
- **MCP:** não sincronizado — token ausente. A triagem foi registrada **na US-GOV-059 do SPEC** (dono do tema), então o webhook propaga quando o SPEC for indexado.
- **BRIEFING:** não atualizado de propósito — o trabalho foi governança/scripts, não mudou capacidade de negócio de módulo.

## Próximos passos pra retomar

```
gh pr view 5352 --json body -q .body   # a triagem completa está aqui e na US-GOV-059
```

A decisão pendente é **uma só**: qual conserto para `fiscal.inutilizar`.

## Lições catalogadas

1. **Documentar ausência citando o path cria a referência podre.** Escrevi que dois módulos "não existem" usando `Modules/<X>` e a catraca anti-ghost contou 2 ghosts novos (`ghost_count 12→14`, required). O gate estava certo — o path nunca foi necessário.
2. **`FAILURE == 0` não é verde.** Contei ausência de falha num run ainda em execução. Verificação honesta conta `SUCCESS` explicitamente e separa "rodando".
3. **Antes de abrir PR que toca arquivo hot-path, conferir PRs abertos.** Abri o #5344 sem saber que o #5343 fazia o mesmo 12 min antes; o `dup-detector` pegou. A atribuição de causa do outro PR era melhor que a minha — fechei o meu.
4. **Varredura em `Modules/<X>/` não é o módulo inteiro.** Atribuí um ganho de nota a commits de código quando a causa era um `SPEC.md` de 88 linhas em `memory/requisitos/<X>/` — a rubrica lê os dois.
5. **O denominador do `permission-drift` são 5 fontes, e seeder de módulo não entra.** Foi o que fez `fiscal.inutilizar` aparecer como órfã sendo role.

## Pointers detalhados

- Trilha D e as ondas D0–D8: `memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md` §Trilha D
- Triagem das 38 com as 4 classes e os 2 casos da classe A: `memory/requisitos/Governance/SPEC.md` §US-GOV-059 → "Triagem executada — 2026-08-06"
- Por que `biz=1` saiu: [ADR 0358](../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md) + `governance/adr-tombstones.json`
