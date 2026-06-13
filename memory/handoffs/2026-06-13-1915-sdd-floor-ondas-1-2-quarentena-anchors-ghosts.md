---
date: "2026-06-13"
slug: sdd-floor-ondas-1-2-quarentena-anchors-ghosts
tldr: "Frente SDD floor executada em thread forte: era-sqlite quarentena (Onda 1 CORE + Onda 2 massa) derrubou o floor do nightly 1928→1570 (−18,6%, errors −446) medido em run completo; + SA anchors + SPEC-compliance + KL ghost_count 27→15. 6 PRs mergeados, cada um com revisão adversarial. Floor agora é mensurável."
hour: "19:15 BRT"
topic: "SDD floor: quarentena era-sqlite (corruptores que dropam tabela CORE/real em MySQL persistente) + frentes SA (anchors) e KL (ghosts) + compliance de SPECs. Medição: 1928→1570."
duration: "~5h"
authors: [W, C]
---

# Handoff — SDD floor caiu 1928→1570 (Onda 1+2 era-sqlite) + SA/KL

> **TL;DR:** [W] "thread-all do era-sqlite, termine essas semanas urgente, faça em thread". Execução paralela pesada (ultracode) com **adversário em cada PR**. O floor do nightly full-suite **caiu de 1928 (failed 404+errors 1524) pra 1570 (failed 492+errors 1078)** — **errors −446** = as cascatas "Base table not found" dos drops de tabela CORE, eliminadas. Tudo medido num **run completo** (10.449 testes, sha a83475f1).

## Estado MCP no momento
- Cycle **CYCLE-08** (Receita — Onda A), 46% decorrido, 15d. Goals de receita NÃO tocados nesta sessão (drift consciente — trabalho foi infra SDD/governança, off-cycle).
- `my-work`: 30 tasks (4 review / 6 blocked-dormentes Gold / 20 todo). Nada desta sessão estava em task MCP (frente SDD vive em `memory/sessions/` + scorecard).
- Handoffs irmãos hoje: `2026-06-13-1730-sdd-floor-frente-c-era-sqlite.md` (a investigação que provou o lever = isolamento era-sqlite) e `-1810-auditor-channel-access` (sessão paralela).

## O que aconteceu
1. **Onda 1 (#2676)** — 17 testes era-sqlite param de dropar tabelas **CORE** (business/users/activity_log/permissions/roles/contacts): remove drops + `Schema::create` guardado por `if(!hasTable)` + seed idempotente `updateOrInsert`. Adversário 5/6 safe (pegou 1 regressão: `InvoiceGen:292` `R$ 100,00` corrompido por redação Tier-0 → corrigido na Onda 2).
2. **Descoberta PHPUnit 12.5**: `afterEach` roda **mesmo em teste pulado** (`$hasMetRequirements` já true antes do `markTestSkipped`) → guard de teardown por driver. Confirmado lendo `runBare()` no vendor.
3. **Onda 2 (#2684)** — quarentena-lite em massa de **~139 corruptores** (skip-guard driver no beforeEach + teardown guardado): pula no MySQL, roda em sqlite. Por construção não quebra CI sqlite nem nightly. Adversário 4/4 safe.
4. **#2688** limpezas (4 guards `&&`→`||` + IndexarMemory quarentena). **#2690** SA anchor (Compras+Financeiro, 5 promovidos). **#2692** SPEC-compliance (7 SPECs: frontmatter + anchors + PII). **#2693** KL codemod ghost **27→15** (Copiloto→Jana, PontoWr2→Ponto, CV→ComunicacaoVisual + 9 lápides + 4 core; ADRs append-only NÃO tocados).
5. **Medição (CT 100 fullsuite)**: 1ª run crashou flaky (exit 2, ordem-aleatória); a 2ª completou → **floor 1570**.

## Artefatos gerados
- 6 PRs mergeados: #2676, #2684, #2688, #2690, #2692, #2693. Net: ~160 testes era-sqlite tratados + 9 SPECs schema-compliant + 60 docs de-ghost.
- Scorecard SDD movido: `full_suite floor 1928→1570`, `ghost_count 27→15`, anchors promovidos.
- Run nightly `/opt/oimpresso-fullsuite/runs/20260613-174707/summary.json` (sha a83475f1).

## Persistência
- git: 6 PRs no `origin/main` (até `8bfdb9a39`). MCP: webhook propaga em ~2min. Floor: summary.json no CT 100.

## Próximos passos pra retomar
`brief-fetch` → escolher: **(A) burn-down** dos 1570 fails + 1636 skipped (converter quarentena→cobertura real, por módulo) OU **(B) sweep de compliance dos ~40 SPECs restantes** (prereq-raiz que destrava todo doc-work). Comando: re-rodar `ct100-fullsuite.sh` pra re-medir após cada onda de burn-down.

## Lições catalogadas
- **`git add -A` em worktree compartilhada = contaminação** (sessão paralela escreve junto) → SEMPRE staging por path explícito + verificar `diff --cached` antes de commit. Causou 1 commit sujo (2 controllers) reconstruído.
- **Tocar 1 SPEC.md faz o gate validar o arquivo INTEIRO** → débito pré-existente (frontmatter `owner`/`version`/`last_updated`, seções, CPF/CNPJ literal) aflora. Por isso o sweep de compliance é prereq das frentes SA/KL (bati no muro 4×).
- **PHPUnit 12.5 roda afterEach em teste pulado** — corruptor invisível, agora coberto.
- **Floor measurement é flaky** (1 run crashou exit-2 por ordem-aleatória) — frente FV precisa estabilizar o runner pra medição repetível.
- **Eventos de CI "stale" pós-force-push**: o main anda rápido + rebase/force-push → run antigo notifica atrasado com erros já corrigidos. Sempre conferir o **commit mergeado real**, não o run do monitor.
- `pii-scan.sh` só checa CPF/CNPJ **com pontos** (não cartão/UUID); fake canônico `11.222.333/0001-81` ainda precisa `# pii-allowlist` (o scanner não tem whitelist).

## Pointers detalhados (on-demand)
- Investigação do lever: `handoffs/2026-06-13-1730-sdd-floor-frente-c-era-sqlite.md`.
- Scorecard/regime: [ADR 0275](../decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) · anchor format [ADR 0273](../decisions/0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md) · alias map [ADR 0274](../decisions/0274-referencia-adr-por-slug-alias-map-13-colisoes.md).
- Plano-mãe: `sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md`.
