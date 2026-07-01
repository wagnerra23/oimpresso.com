---
date: "2026-07-01"
hour: "20:45 UTC"
topic: "P11 KL: E2b re-seed Meilisearch EXECUTADO no CT100 (com prova) + E3 1º dry-run do distiller (crash GLOB_BRACE achado e corrigido) — skim Wagner pendente pro run real"
authors: [C]
outcomes:
  - "E2b deixou de ser ILUSÓRIO: re-seed rodado no CT100 com snapshot de rollback + artefato de prova commitado (governance/reseed-meilisearch-manifest.json)"
  - "E3 1º dry-run REAL desenterrou bug: GLOB_BRACE (glibc-only) crashava jana:distill-module-truth em musl/Alpine — fix + teste de regressão em PR"
  - "dry-run --all completo: 76 portas, 49 com eventos, 0 refused_pii; lote 1 proposto (Financeiro/Whatsapp/Governance/OficinaAuto/Sells) aguarda skim Wagner (R10)"
---

# P11 KL — E2b re-seed Meilisearch + E3 1ª destilação (execução do que nunca tinha rodado)

> **Gatilho:** [avaliação adversarial 2026-07-01](2026-07-01-sdd-avaliacao-adversarial.md) classificou E2b (45/100) e E3 (30/100) como ILUSÓRIOS — "zero artefato provando execução no CT100" e "cron agendado ≠ cron rodado". Esta sessão executa os dois trilhos de CONTEÚDO do [P11](../requisitos/_Governanca/roadmap/P11-kl-e2-renames-reseed-distiller.md), sem tocar no trilho do floor (outra sessão).

## E2b — re-seed Meilisearch (CT100) ✅ EXECUTADO com prova

**Onde:** CT100 (`tailscale ssh root@ct100-mcp`), container `oimpresso-mcp` @ `dd3ed7c31` (origin/main de hoje), Meilisearch v1.43.0. Secrets via `_INDEX-SECRETS.md` → key lida da config do próprio container (nunca impressa).

**Passo-a-passo (comandos literais):**

1. **Snapshot de rollback** (P11 §3): `POST /snapshots` → task 1138911 `succeeded` → `/meili_data/snapshots/data.ms.snapshot` (290.843.369 bytes, 2026-07-01T20:23Z).
2. **Cura de settings:** `php artisan jana:meilisearch-setup --index=mcp_memory_documents` → taskUid 1138931 (embedder `qwen3_local` + filterable `status,type,module,slug`).
3. **Re-seed:** `php artisan scout:import "Modules\Jana\Entities\Mcp\McpMemoryDocument"` → 2026-07-01T20:23:57Z → 20:24:18Z, até ID 4155.
4. **Pós:** fila drenada; índice `numberOfDocuments=1415`, `numberOfEmbeddedDocuments=1415`, `isIndexing=false`. DB=1446; delta 31 = `shouldBeSearchable()` (superseded/deprecated/rascunho) — esperado, não drift.

**Contagem de nomes mortos servidos pela busca (phrase query `"Modules/<Nome>"`):**

| Nome fantasma | Antes | Depois | Leitura |
|---|---:|---:|---|
| `Modules/MemCofre` | 16 | 16 | 100% tombstones (7 ADR, 2 session, 1 handoff, 1 comparativo) + 5 docs vivos FORA do escopo E2a |
| `Modules/Copiloto` | 44 | 44 | idem (maioria ADR/session/handoff) |
| `Modules/DocVault` | 0 | 0 | zero — rename transitivo completo |
| `Modules/PontoWr2` | 28 | 28 | idem MemCofre |
| `"Modules/SRS"` (controle positivo) | — | 14 | top hit `spec-srs` ✓ |

**Leitura honesta (antes == depois):** o índice JÁ estava em sincronia com DB/git pós-renames E2a ([#3155](https://github.com/wagnerra23/oimpresso.com/pull/3155)) — o que faltava era a **prova**, não a cura. Os hits remanescentes são (a) append-only citando nome antigo como FATO histórico (ADR 0094 Art.3) ou (b) docs vivos **fora do escopo do detector** (`knowledge-drift.mjs` só varre `memory/requisitos/**`): `memory/what-oimpresso.md` (⚠️ @import do CLAUDE.md — carrega em TODA sessão citando `Modules/MemCofre`+`Modules/Copiloto`), `memory/governance/architecture.md`, `memory/governance/module-drift-migration-plan.md`, `memory/reference/*`, `memory/0X-*.md`. **Zero hit em doc vivo de `memory/requisitos/**` não-adr** — o DoD E2b do P11 fecha.

**Artefato de prova:** [`governance/reseed-meilisearch-manifest.json`](../../governance/reseed-meilisearch-manifest.json) (append-only, 1 entry por re-seed).

**Fila humana aberta (fora de escopo E2a, NÃO corrigida aqui):** renomes mortos em `memory/` raiz + `memory/reference/` + `memory/governance/` — em especial `what-oimpresso.md` que é lido por toda sessão. Candidato a item KL próprio (estender escopo do detector OU fix manual pontual).

## E3 — 1ª destilação: dry-run EXECUTADO, crash real achado, skim Wagner pendente

**Pré-condição do Kernel ([Kernel.php:229-236](../../app/Console/Kernel.php)) cumprida a primeira metade:** o cron `jana:distill-module-truth --all` daily 05:30 foi descomentado no #3155, mas o comentário condiciona a ligação ao fluxo `--dry-run → skim Wagner → run real`. **Nada disso tinha rodado** (0/76 portas com `distilled_at:`).

### Bug desenterrado pela 1ª execução (valor de rodar o que nunca rodou)

`php artisan jana:distill-module-truth --all --dry-run` no CT100 (container `oimpresso-staging`, checkout `dd3ed7c31`) **crashou de cara**: `Undefined constant "Modules\Jana\Console\Commands\GLOB_BRACE"` em `scanAudits()` — `GLOB_BRACE` é extensão glibc, **indefinida em musl/Alpine** (todo o CT100). O CI (ubuntu/glibc) nunca pegaria. Mesmo bug já catalogado no Module Grade v4 (`ScopedScorecardEvaluator::globBrace`, 2026-06-13).

- **Fix:** globs separados sem brace (`AUDIT*.md` cobre `AUDITORIA*`; elimina o double-match que duplicava evento) — PR [#3532](https://github.com/wagnerra23/oimpresso.com/pull/3532), com teste de regressão rodado VERDE no CT100 musl (`6 passed`, por path direto — `--filter` esbarra em conflito `uses()` pré-existente de `tests/Feature/Support/SuporteConcederCommandTest.php`, não relacionado).

### Dry-run --all (com o fix aplicado no checkout staging)

Janela 2026-07-01T20:32:43Z → 20:37:52Z (~5min). **76 portas: 49 `dry` (com eventos, LLM chamado), 27 `no_events`, 0 `refused_pii`, exit 0.** Log completo: CT100 `/root/p11-e3/dryrun-20260701.log`. Top eventos: Jana 40 · Financeiro 19 · Whatsapp 16 · Governance 11 · OficinaAuto 9 · Sells 9 · Auditoria 9.

⚠️ **Portas-fantasma detectadas no --all:** `Copiloto` (5 eventos), `MemCofre` (2), `FinanceiroAvancado` (1), `PontoWr2` (dir existe) — dirs legados em `memory/requisitos/` com BRIEFING.md que o `--all` re-escreveria, cimentando ghost-dirs. **Run real NÃO deve ser `--all`** enquanto essas portas não forem tombstonadas/redirecionadas (fila KL).

### Lote 1 proposto pro run real (gate Wagner — R10)

`Financeiro · Whatsapp · Governance · OficinaAuto · Sells` (5 módulos, alta atividade, `--module=<X>` um a um — nunca `--all`). Conteúdo proposto (dry) dos 5 gerado e entregue pra skim (CT100 `/root/p11-e3/skim-batch1-20260701.md`).

**Observações honestas de qualidade pro skim (motor v1, contrato ADR 0291):**

1. **H1 duplicado** — `montarBriefing()` põe `# BRIEFING — <Mod> (verdade destilada)` e a LLM repete o próprio H1 da porta antiga logo abaixo (cosmético).
2. **Metadados velhos copiados** — em Whatsapp/Governance/OficinaAuto a LLM reproduz o header `> Mantido por... Atualizado: 2026-05-16` da porta antiga DENTRO do corpo novo (conflita com `distilled_at: 2026-07-01`).
3. **Motor destila de TÍTULOS, não de corpos** — `userPrompt()` só passa filename+data dos eventos + porta antiga (4k chars). Números tipo "grade 49→84" (Governance) e "PR mergeado em 25 de maio" (Sells) vêm da porta velha ou são inferência — **é exatamente isso que o refutador G5 (amostra ≥30%) vai checar** no PR do lote.
4. Typo "credenciales" (Financeiro) — LLM `gpt-4o-mini` (provider default `config/ai.php`).

Se Wagner reprovar a qualidade → opções: (a) aceitar v1 + refutador como rede, (b) evoluir o prompt do motor (nova PR no contrato 0291 — ex.: passar excerpt dos eventos, proibir H1/headers copiados) ANTES do run real.

### Fluxo do run real (após skim aprovado — task pendente)

1. `--module=<X>` (sem `--dry-run`) por módulo do lote, no staging CT100 com checkout limpo em origin/main.
2. `git diff` do container → lote vira PR com os 5 BRIEFINGs re-escritos.
3. Entry no ledger G5 (`governance/sdd-verification-ledger.json`, `lote_id: KL-E3-distill-batch1`): gerador `gpt-4o-mini (≈haiku tier via laravel/ai)`, refutador **tier superior** em sessão fresca (protocolo + [#3530](https://github.com/wagnerra23/oimpresso.com/pull/3530)), amostra ≥30% prosa, PII scan (o `PiiRedactor` já recusa PII estruturada no write, mas o grep manual do §3 do protocolo roda igual).
4. Merge → `measureDistillerFreshness()` sai de `not_yet_measured` → `measured` (floor arma no 1º carimbo, ADR 0291 D-D). Verificar em `node scripts/governance/sdd-scorecard.mjs`.
5. Só então o cron descomentado do Kernel passa a ter o processo de skim "de pé" que o comentário exige.

## PRs da sessão

- [#3532](https://github.com/wagnerra23/oimpresso.com/pull/3532) — fix(jana) GLOB_BRACE musl-safe + teste regressão.
- (esta PR) — manifest E2b + session log + handoff + bookkeeping roadmap.

## Drift zerado

Checkout do `oimpresso-staging` restaurado (`git checkout --` nos 2 arquivos patcheados após o teste; o fix canônico vem por PR+merge+pull). Untracked pré-existente no staging (`.env.bak.openai.*`) anotado, não meu, não tocado.

## Adendo Passo 0 (incidente — descoberto após o corpo acima, mesmo dia ~21h UTC)

> Gatilho: briefing verificado da sessão paralela (workflow 4 agents) apontou contradição documental sobre o scheduler do Hostinger e mandou resolver com evidência. Resolvido — e o achado inverte uma conclusão minha anterior.

**RETRAÇÃO:** mais cedo concluí "o cron do distiller nunca disparou" com base em `crontab -l | grep` vazio + `git status` limpo. **Errado nos dois pontos:** (1) `crontab` **nem existe** no shell do Hostinger (`command not found`) — os crons são via hPanel, invisíveis ao shell; (2) `git status` limpo não prova não-execução, prova **write-loss** (deploy reseta a árvore).

**Evidência dura (SSH Hostinger, literal):** `grep -c "DistillerModuloVerdade" storage/logs/copiloto-ai-*.log` → **50/51/52/52/51/49/48/48 entries por dia em 22-27/jun, 29/jun e 01/jul** (todas `live.INFO: porta reescrita`, timestamps 05:30:xx). `APP_ENV="live"` casa com `environments(['live'])`. Ou seja: o cron descomentado no #3155 disparou **diariamente** desde o dia seguinte ao merge — ~500 reescritas LLM de BRIEFING.md na árvore deployada, não-skimadas, todas perdidas no deploy seguinte (por isso 0/76 `distilled_at:` no git). Custo LLM diário sem efeito durável + máquina violando o gate que o próprio comentário do Kernel exige.

**Hotfix:** [PR #3545](https://github.com/wagnerra23/oimpresso.com/pull/3545) re-comenta o bloco (kill-switch por design) com condição objetiva de religação: venue git-backed (clone + auto-PR bot, precedente #3442/#3485) + fluxo de skim rodando.

**Complemento E2b (passo que faltou na sequência do corpo acima):** o briefing apontou que o re-seed canônico é 3 comandos — faltou o `mcp:sync-memory` (FS→DB) antes do `scout:import`. Rodado `php artisan mcp:sync-memory --reason=manual` no `oimpresso-mcp` (exit 0) e — descoberta — **já existe `mcp:sync-memory --reason=cron` rodando agendado no próprio container** (visto vivo em `ps` às 21:11 UTC, com WARNINGs de frontmatter YAML inválido em docs legados no log). Ou seja: o FS→DB é automatizado no CT100, o que explica o DB já estar fresco na medição do corpo acima (DB LIKE == hits da busca). A conclusão do manifest não muda.

**Nota de processo:** o [#3532](https://github.com/wagnerra23/oimpresso.com/pull/3532) (fix GLOB_BRACE) foi fechado sem merge às 20:57Z junto do merge do #3534 — aparentemente acidental (o "merge" aprovado cobria ambos; o bug seguia em `origin/main:139`). Reaberto com justificativa; os required renomeados pelo P14 (#3535, sessão paralela) exigiram commit vazio pra re-disparar CI com os nomes novos.
