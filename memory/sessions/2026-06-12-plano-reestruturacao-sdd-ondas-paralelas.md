---
date: "2026-06-12"
topic: "Plano executável da reestruturação SDD — backfill dos dados antigos, 4 ondas paralelas corrigidas por crítica adversarial, sistema de garantia com 3 linhas de defesa, matriz de IA por etapa"
authors: [W, C]
related_adrs: ["0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento", "0271-revisao-gates-ci-estado-real-dos-required"]
prs: []
---

# Plano de reestruturação do Sistema SDD — ondas paralelas + garantia + IA por etapa

> Origem: auditoria 2026-06-12 (nota composta 59/100, ver [audit](2026-06-12-audit-sdd-pesquisa-reclassificacao.md)). Wagner: "como implementar e ajustar os dados antigos? a reestruturação pode ser medida testada garantida? só essas ondas seria suficiente? planeje as etapas em paralelo e quais IA pra cada etapa."
> Método: workflow 10 agents (4 inventários read-only do repo real → 4 designers → 2 críticos adversariais). Críticos acharam 7 lacunas + 14 erros de DAG — **este doc já incorpora as correções**. Números abaixo vêm de medição no repo, não estimativa.

## 0. Números reais medidos (corrigem o audit anterior)

- O claim "0/43" estava defasado: quadro real = **42/57 SPECs sem campo** `Implementado em` + **48/84 campos placeholder** + **36 preenchidos** (não tocar).
- ~14 dos 48 placeholders estão CORRETOS (tela nunca construída) → sentinela `_pendente_` é estado de 1ª classe; backfill cego inventaria anchor falso.
- Charters como fonte de inferência: `component:` 104/136, `page:` 121/136, `us:` só 3/136 → join US→tela precisa heurística + fila humana.
- US-ids: 900 em SPECs · 481 no git log · interseção 413 (46% — ruído de commits docs()).
- Full-suite: **nenhum run full-repo MySQL jamais foi salvo** (artefato 0 bytes); estimativa 1.500-3.000 fails É EXTRAPOLAÇÃO — 1º número real vem da nightly diagnóstica.
- 1.413 `markTestSkipped` defensivos; 78 arquivos com `Business::first()` cru; ghost-names: 39 módulos citantes / 27 nomes.
- Branch protection real: 16 required contexts verificados via gh api (regras citam 17 — baseline congela a lista real).

## 1. Como ajustar os DADOS ANTIGOS (backfill, por classe)

**SPECs legados (funil 3 camadas, idempotente, re-rodável):**
1. **Mecânica sem IA** — 23 placeholders com path embutido: existsSync→promove com sha7 provenance; senão `_pendente_`.
2. **Batch IA com evidência dura** — 42 SPECs sem campo: 1 subagent Haiku por módulo cruza charter frontmatter + árvore Pages/ + git log --grep=US filtrado por feat|fix com diff tocando o path. Regra: anchor só entra com path existente + ≥1 evidência independente; na dúvida → `pendente` ou fila.
3. **Fila humana só dos ambíguos** — `_ANCHOR-REVIEW-QUEUE.md`, ~50-80 itens, Wagner decide em batches de 20 (~30min cada; se >90s/item, melhorar evidência do prompt).

**Testes antigos (o backfill É a onda):** medir primeiro (nightly MySQL diagnóstica), triage Haiku por classe A-F via stacktrace JUnit, depois por arquivo: FIX barato / CONVERTER (RefreshDatabase→DatabaseTransactions, Business::first()→trait WithSeededTenant) / QUARENTENAR `@group legacy-quarantine` com razão / INVESTIGAR (suspeita de bug real). Quarentena em massa até nightly quase-verde, depois burn-down paralelo por módulo.

**Conhecimento antigo (3 classes, catraca-primeiro):** catraca anti-ghost landa ANTES de qualquer correção (ghost novo já nasce barrado). Classe A ~metade = find-replace com tabela curada de 8 renames reais (Copiloto→Jana etc); Classe B = 9 módulos nunca-construídos ganham lápide "(planejado — não existe)", não substituição inventada; Classe C = 8 namespaces legacy exigem reescrita com revisão. 13 colisões ADR → referência canônica por slug + alias map (sem violar append-only). 22 BRIEFINGs órfãos → triagem de identidade primeiro (fundir/renomear/matar — decisão Wagner 15min), depois ~15-17 destilados por IA em lotes com skim.

**Regra de ouro do backfill:** todo lote IA passa pelo protocolo adversarial (G5) ANTES do merge — agente refutador em sessão fresca, modelo ≥ gerador, tenta provar que o anchor/claim está ERRADO; ledger versionado; backfill_error_rate <2%.

## 2. Pode ser MEDIDA, TESTADA, GARANTIDA? — Sim, com 3 linhas de defesa

**MEDIDA — scorecard único de 10 métricas** (`governance/sdd-scorecard.json`, agregador node sem deps):
anchor_coverage (4%→100%) · full_suite_pass_rate (1ª medição real→100% não-quarentenado) · n_quarantine (só diminui) · coverage_pct (0 instrumentação hoje→só sobe) · ghost_count (27→0) · front_door_coverage (62%→100%) · recall_eval_violations (→0) · ragas_real_uptime (≥95%) · drift_alarms (advisory perene) · backfill_error_rate (<2%). Baseline de cada métrica capturado na **1ª medição real da fonte, nunca do plano** (anti-stale). Composta v1 (fontes parciais) vs v2 (10/10 vivas) — regimes não comparáveis, declarado em ADR.

**TESTADA — 3 níveis:** (1) gate/catraca por métrica nas ondas; (2) `gate-selftest.mjs` com fixtures boa+ruim por catraca — prova que os gates MORDEM (quem vigia os vigias); (3) verificação adversarial do backfill (G5) prova que o dado novo é verdadeiro.

**GARANTIDA — meta-catracas:** baselines em arquivo versionado (piorar = PR vermelho, exceção só via --force visível em diff); `required-checks-baseline.json` + protection-drift diário (required check só ENTRA; demoção exige PR+ADR — fecha o único buraco real: demoção invisível de 1 clique do admin); watchdog de staleness (fonte parada = vermelho ≤48h — canário que para de rodar é regressão silenciosa). **Leitura sem esforço:** linha SDD no brief-fetch diário (só quando muda ou tem vermelho) + check `verificacao_sdd` no jana:health-check + card no GovernanceV4Dashboard com histórico (`mcp_sdd_scorecard_history`, 1 row/dia).

## 3. Só essas ondas bastam? — NÃO como saíram do design; SIM com as 7 correções (já incorporadas)

Lacunas achadas pelos críticos adversariais e incorporadas:
1. **Red-first hook bloqueador** (P1 da auditoria) não estava em NENHUMA onda → novo passo FV-T0.
2. **Fluxo NOVO durante a migração**: SPEC criado na janela nasceria sem anchor → SA-A1 atualiza template + spec.schema.json (grace-period do playbook memory-schemas) + skill memory-schema-preflight.
3. **LGPD/PII**: repo é PÚBLICO — todo lote IA (BRIEFINGs, filas, triage) ganha scan PII diff-only (CPF/CNPJ + nomes de cliente do CRM) + item no checklist do refutador.
4. **Meilisearch pós-rename**: re-seed do índice após cada lote de renames (KL-E2b), senão recall busca nomes mortos.
5. **Charters sem frontmatter**: backfill mecânico de `us:`/`component:` nos 32 charters incompletos ANTES do batch SA-A5 (melhora a evidência).
6. **Calendário único de promoções**: máx 1 promoção a required/semana, critérios objetivos pré-escritos no ADR do scorecard (anti promotion-fatigue do decisor único; precedente visual-regression).
7. **gates-registry.json**: todo workflow novo registra (senão memory-health já reprova o PR).

Correções de DAG (14 erros): IDs namespaced por onda (SA-/FV-/KL-/GT-); G5 vira dependência DURA dos primeiros lotes IA (SA-A5, KL-E3); **partição por módulo entre ondas** — codemod/rename do módulo SEMPRE antes do anchor-backfill do mesmo módulo (PontoWr2 era tocado por 3 streams); arquivos compartilhados (knowledge-drift.mjs ×3 steps, anchor-lint.mjs ×2, sdd-scorecard.yml ×3, Kernel.php/HealthCheck ×3 ondas) entram em **infra-lane serializada** com manifest de donos; R1 destravado de B1-B4 (promove após Q3 estável — P0 não espera P1; burn-down continua sob catraca); KL-B/C ordenados (alias map antes do golden set); baseline anti-ghost por módulo, não global (6 streams editavam 1 arquivo); regime de eval congelado na ordem E2→C3→C4→D3→recalibração v2→janela D4.

**Fora de escopo consciente (pós-fundação):** spec→tasks compilation, mutation --min real, PBT em escala — P3 da auditoria, entram depois de R1+C2.

## 4. Plano em ondas PARALELAS (DAG corrigido, ~5-7 semanas, ~60-130 PRs ≤300 linhas)

### Semana 0 — fundações (5 frentes paralelas, zero conflito de arquivo)
| Frente | Passos | IA |
|---|---|---|
| SA | ADR formato anchor + sentinela `pendente` + template/schema fluxo-novo → `anchor-lint.mjs` | Sonnet redige; Wagner aprova ADR |
| FV | F1 fix artefato JUnit (upload-artifact; --log-junit JÁ existe) ∥ F2 composite action pest-mysql ∥ Q1 catracas quarentena ∥ **T0 red-first hook** | 1 Sonnet por passo (nuvem) |
| KL | Catraca anti-ghost (baseline POR módulo) ∥ codemod script + tabela 8 renames ∥ ADR slug/alias das 13 colisões | Sonnet; Wagner revisa tabela |
| GT | ADR scorecard (com calendário de promoções) ∥ agregador G2 ∥ **G5 protocolo refutador (bloqueia lotes IA)** | Sonnet |
| Charters | Backfill mecânico `us:`/`component:` nos 32 incompletos | Haiku |

### Semanas 1-2 — medição real + backfill mecânico (partição por módulo)
- FV: F3 nightly MySQL diagnóstica (1º número real; NUNCA required) → Q2 triage Haiku batch das classes A-F (Sonnet revisa amostra 10%, concordância ≥90%) → Q3 quarentena em massa (3-4 agents, 1 módulo cada) → **catraca Q1 vira required**.
- KL: E1 tabela identidade (Wagner 15min) → E2 renames/fusões → E2b re-seed Meilisearch → codemod lotes Classe A/B/C, módulo a módulo.
- SA: A4 backfill mecânico dos 48 placeholders — **sempre DEPOIS do codemod do módulo** (3 PRs por grupo).
- GT: G3 meta-catraca (advisory) + G6 selftest fixtures.

### Semanas 2-4 — backfill IA com refutador + burn-down paralelo
- SA-A5: batch 1 Haiku/módulo (~57, centavos) + Sonnet nos ~50-80 ambíguos; publica taxa de ambiguidade após 5 módulos e recalibra prompt ANTES dos 52 restantes → A6 fila Wagner (batches de 20).
- FV burn-down (até 5 Sonnet paralelos, RIGOROSAMENTE 1 módulo/agent — áreas disjuntas): B1 Financeiro 172 fails (continuação US-FIN-053) · B2 NfeBrasil 79 · B3 matrix SQLite→MySQL como **mini-onda com diag próprio, orçar 2× L** (1.413 skips viram execuções que voltam como fails novos) · B4 tests/ raiz + trait WithSeededTenant (PR isolado antes dos lotes). Escalar Opus só em estado compartilhado/poisoning.
- KL: E3 BRIEFINGs (Sonnet, lotes 4-5, refutador G5 + scan PII, skim Wagner 10min/lote) ∥ trilha C decay: C1 fix config peso_real (flag OFF) → C2 golden set recall (depende do alias map) → **CT 100**: C3 snapshot+re-seed → C4 flag ON com eval before/after (Wagner aprova evidência; flag = kill-switch) → C5 cron diário ∥ trilha D: D1 destrava RAGAS real (secret = Wagner) → D2 baseline real v1 + gate regressão relativa → D3 canário mede retrieval real (CT 100; Sonnet/Opus, ler LICOES_F3 antes) → D4 cron + uptime (janela 30d SÓ depois do regime congelar).
- GT: G4 protection-drift + watchdog ∥ infra-lane serializada: Kernel.php/HealthCheck (M1+C5+G7 em sequência, não paralelo) → G7 snapshot diário + dashboard → G8 linha SDD no brief.

### Semanas 4-6 — promoções a required (máx 1/semana, critérios objetivos)
R1 full-suite MySQL não-quarentenado (7 nightlies verdes + p95 ≤25min; strict:true ou merge queue no flip — anti race de 2 PRs verdes isolados) → C2 catraca coverage (14d advisory, FP<5%) → T1 mapa teste↔arquivo via --coverage-php per-test → T2 TDAD-lite lane impactados-no-PR (sombra 14d, falso-negativo <1%, auto-demoção se estourar) → SA-A10 anchor gate (coverage 100% antes do flip) → GT-G3 required.

## 5. Matriz de IA por tipo de trabalho

| IA | Onde | Por quê / custo |
|---|---|---|
| **Zero-IA (scripts node determinísticos)** | anchor-lint, codemod, scorecard, catracas, selftest — todo runtime de medição | Auditável, <5s, nunca alucina; IA só ESCREVE o script 1× |
| **Haiku** | Triage stacktraces (Q2, ~600-1.500 arquivos), join charter↔US↔path (1/módulo), sanity-check de diffs, formatação de filas | Tarefas mecânicas de correlação, contexto 10-30k/módulo — centavos por batch |
| **Sonnet** | Escrever scripts/workflows/ADRs, burn-down de testes (1 agent/módulo, área isolada), ambíguos do A5, BRIEFINGs destilados, ops CT 100 via SSH | Default do projeto; julgamento de código sem necessidade de raciocínio profundo |
| **Opus** | SÓ escalação: test-poisoning/estado compartilhado, merge de coverage 40 chunks se estourar memória, D3 pipeline retrieval | Caro; injustificável em correlação mecânica |
| **gpt-4o-mini (judge)** | RAGAS canário diário modo real | ≈$0.04/run ≈ **$1.80/mês** |
| **Agente REFUTADOR (≥ modelo gerador, sessão fresca)** | Todo lote de backfill IA antes do merge (G5) | Anti-envenenamento da memória canônica; ledger versionado |
| **Wagner (humano, gargalo intencional)** | Fila ambígua A6 (30min/batch) · tabela identidade E1 (15min) · skim BRIEFINGs (10min/lote) · secrets · promoções required (1/semana) · aprovação de merges | Única IA que decide; tudo mais só propõe com evidência |

## 6. Riscos top-5 (dos críticos) e mitigação

1. **Throughput Wagner (~60-130 PRs)** → escalonar ondas (não disparar tudo na semana 0), lotes determinísticos agrupados, calendário de promoções.
2. **B3 explode** (skips→fails novos, funil circular Q2) → mini-onda com diag próprio, orçamento 2×.
3. **Fila A6 subdimensionada** (se ambiguidade real for 20-25% e não 9%) → medir taxa nos 5 primeiros módulos, recalibrar; se fila >150, parar e melhorar evidência.
4. **PII em repo público** nos artefatos gerados em massa → scan diff-only + checklist refutador.
5. **Flakiness pós-quarentena** (executionOrder random→default + 7 nightlies) → re-quarentena expressa permitida pré-R1; catraca Q1 promove DEPOIS de R1.

## Execução

Nada deste plano foi implementado — é blueprint. Próximo passo sugerido: aprovar este doc, abrir os PRs da Semana 0 (5 frentes paralelas, nenhuma exige CT 100), e re-derivar TODOS os números/linhas citados a partir de origin/main no momento da execução (regra anti-stale dos críticos).
