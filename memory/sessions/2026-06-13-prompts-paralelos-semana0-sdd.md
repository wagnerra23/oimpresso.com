---
date: "2026-06-13"
topic: "5 prompts prontos para disparar a Semana 0 do plano SDD em sessões paralelas (outra conta) — frentes SA/FV/KL/GT/Charters com arquivos permitidos, ADRs pré-atribuídos e modelo por frente"
authors: [W, C]
related_adrs: ["0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento", "0271-revisao-gates-ci-estado-real-required-e-subtracao-segura"]
prs: []
---

# 5 prompts paralelos — Semana 0 do plano SDD

> ⛔ **STALE / NÃO DISPARAR (conferido 2026-06-13).** A Semana 0 inteira JÁ FOI EXECUTADA e mergeada em `origin/main` (PRs #2593-#2611, máquina Felipe). Os ADRs 0273 (anchor), 0274 (alias-map), 0275 (scorecard) já existem; `scripts/governance/anchor-lint.mjs`, `ghost-fix.mjs`, `sdd-scorecard.mjs`, `.claude/hooks/warn-red-first.ps1` e o `anchor_format` no `spec.schema.json` já estão no main. Disparar estes prompts colidiria com trabalho mergeado. **Arquivo mantido só como histórico.** O frontier real agora é o burn-down de testes (Fase 2b) — ver [conferência de estado](2026-06-13-conferencia-estado-ondas-sdd.md) quando gerada.

> Origem: [plano de reestruturação SDD](2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md) §4 (Semana 0 — 5 frentes, zero conflito de arquivo). Cada bloco abaixo é auto-contido pra colar numa sessão fresca de OUTRA conta. Cada sessão começa com `brief-fetch` automático (skill `brief-first`) e carrega os hooks/skills do projeto.

## Regras compartilhadas (já embutidas em cada prompt)

- **ADR pré-atribuído** (anti-colisão de numeração rodando em paralelo): SA→**0273**, KL→**0274**, GT→**0275**. Charters e FV não criam ADR.
- **Anti-stale (regra dos críticos):** re-derivar TODO número/lista a partir de `origin/main` no momento da execução — não confiar nos números do plano.
- **Arquivos proibidos pra TODAS as frentes (infra-lane serializada — dono único):** `scripts/governance/knowledge-drift.mjs`, `app/Console/Kernel.php`, `governance/required-checks-baseline.json`, qualquer `.github/workflows/*.yml` que não esteja explicitamente listado na sua frente.
- **Commit discipline:** 1 PR = 1 intent, ≤300 linhas, conventional commit, `Refs: SDD Semana-0 <FRENTE>`. PT-BR. ADR é append-only e nasce como DRAFT (status `proposed`) — Wagner aceita.
- **Não tocar CT 100, não tocar prod, não rodar teste local** (CI MySQL only).

---

## 1. Frente SA — Spec Anchor (modelo: Sonnet)

```
Você está numa sessão paralela da Semana 0 do plano de reestruturação SDD do oimpresso.
Contexto completo: memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md (frente SA).

OBJETIVO desta sessão (frente SA — Spec Anchor):
1. Redigir ADR DRAFT número 0273 em memory/decisions/0273-formato-anchor-spec-sentinela-pendente.md
   definindo: (a) formato canônico do campo de anchor que liga SPEC→tela/código (path + sha7 de
   provenance), (b) a sentinela `_pendente_` como ESTADO DE 1ª CLASSE (tela nunca construída != anchor
   faltando), (c) regra do fluxo NOVO: SPEC criado durante a migração já nasce com campo de anchor.
   Status: proposed. NÃO aceitar sozinho — Wagner aprova.
2. Atualizar o template de SPEC + scripts/memory-schemas/spec.schema.json pra incluir o campo de anchor
   com grace-period (não quebra os 57 SPECs legados de imediato). Conferir a skill memory-schema-preflight.
3. Criar scripts/anchor-lint.mjs — node determinístico SEM dependências, <5s, que valida o formato do
   campo de anchor e a sentinela. NÃO promover a required ainda (advisory na Semana 0).

ARQUIVOS QUE VOCÊ PODE TOCAR (e só esses):
- memory/decisions/0273-formato-anchor-spec-sentinela-pendente.md  (novo)
- scripts/anchor-lint.mjs  (novo)
- scripts/memory-schemas/spec.schema.json  (editar)
- o template de SPEC (localize via skill memory-schema-preflight / scripts/memory-schemas)
- .claude/skills/memory-schema-preflight/SKILL.md  (se precisar registrar a regra nova)

PROIBIDO: scripts/governance/knowledge-drift.mjs, governance/sdd-scorecard.json, qualquer
.github/workflows/*.yml, app/Console/Kernel.php, e qualquer arquivo das frentes FV/KL/GT/Charters.

Re-derive os números do repo (origin/main) antes de afirmar qualquer quadro. Commit conventional,
≤300 linhas, Refs: SDD Semana-0 SA. Abra PR ao final. Não toque CT 100 nem prod.
```

---

## 2. Frente FV — Full-suite / Verificação (modelo: Sonnet — pode subdividir em até 4 sub-sessões)

```
Você está numa sessão paralela da Semana 0 do plano de reestruturação SDD do oimpresso.
Contexto completo: memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md (frente FV).

OBJETIVO desta sessão (frente FV — base de verificação). São 4 passos em ARQUIVOS DISJUNTOS; faça
todos aqui OU divida em 4 sub-sessões (1 por passo):
- F1: corrigir o upload do artefato JUnit no CI. O flag --log-junit JÁ existe na config do Pest; falta
  o passo upload-artifact no .github/workflows/ci.yml pra o run full-repo deixar de salvar 0 bytes.
  Edite CIRURGICAMENTE só o passo de artifact — não reescreva o ci.yml.
- F2: criar composite action reutilizável de pest+mysql em .github/actions/pest-mysql/action.yml.
- Q1: catracas de quarentena — scripts/quarantine-ratchet.mjs (node, sem deps) + governance/quarantine-baseline.json
  (n_quarantine só pode DIMINUIR) + workflow .github/workflows/quarantine-ratchet.yml (advisory na Semana 0).
- T0 (P1 da auditoria): hook BLOQUEADOR red-first. Já existe .claude/hooks/nudge-test-contract-anchor.ps1
  como advisory; crie .claude/hooks/block-test-without-red.ps1 (+ .test.ps1 no padrão dos outros block-*)
  que BARRA quando um teste novo é adicionado sem ter falhado vermelho antes. Registre em .claude/settings.json.

ARQUIVOS QUE VOCÊ PODE TOCAR (e só esses):
- .github/workflows/ci.yml  (CIRÚRGICO — só o passo de upload do JUnit; ci.yml é EXCLUSIVO da frente FV)
- .github/actions/pest-mysql/action.yml  (novo)
- scripts/quarantine-ratchet.mjs  (novo)
- governance/quarantine-baseline.json  (novo)
- .github/workflows/quarantine-ratchet.yml  (novo)
- .claude/hooks/block-test-without-red.ps1 + .claude/hooks/block-test-without-red.test.ps1  (novos)
- .claude/settings.json  (registrar o hook)

PROIBIDO: mutation-gate.yml, no-mock-gate.yml, qualquer outro workflow, scripts/governance/knowledge-drift.mjs,
app/Console/Kernel.php, e arquivos das frentes SA/KL/GT/Charters. NÃO crie ADR.

Re-derive do repo (origin/main). Commit conventional, ≤300 linhas POR PR (1 PR por passo F1/F2/Q1/T0),
Refs: SDD Semana-0 FV. Não toque CT 100 nem prod.
```

---

## 3. Frente KL — Knowledge Lifecycle (modelo: Sonnet — Wagner revisa a tabela de renames)

```
Você está numa sessão paralela da Semana 0 do plano de reestruturação SDD do oimpresso.
Contexto completo: memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md (frente KL).

OBJETIVO desta sessão (frente KL — conhecimento). 3 entregas:
1. Catraca anti-ghost com baseline POR MÓDULO (não global — 6 streams editavam 1 arquivo antes).
   IMPORTANTE: scripts/governance/knowledge-drift.mjs JÁ EXISTE e é infra-lane serializada — NÃO EDITE.
   Crie um WRAPPER scripts/governance/ghost-ratchet.mjs que CONSOME a saída do knowledge-drift e compara
   contra governance/ghost-baseline.json (baseline por módulo). Ghost novo nasce barrado; ghost_count só desce.
2. Script de codemod de identidade scripts/codemod-identity.mjs + tabela curada governance/renames-table.json
   com os 8 renames reais (ex: Copiloto→Jana). NÃO APLICAR os renames agora (isso é Semana 1-2) — só o
   script + a tabela pra Wagner revisar.
3. ADR DRAFT número 0274 em memory/decisions/0274-alias-map-colisoes-adr.md resolvendo as 13 colisões de
   ADR por referência canônica por slug + alias map, SEM violar append-only (não reescreve ADR antigo).
   Status: proposed. Wagner aprova.

ARQUIVOS QUE VOCÊ PODE TOCAR (e só esses):
- scripts/governance/ghost-ratchet.mjs  (novo — wrapper, NÃO editar knowledge-drift.mjs)
- governance/ghost-baseline.json  (novo, por módulo)
- scripts/codemod-identity.mjs  (novo — não aplicar)
- governance/renames-table.json  (novo)
- memory/decisions/0274-alias-map-colisoes-adr.md  (novo ADR draft)

PROIBIDO: scripts/governance/knowledge-drift.mjs (infra-lane — só leitura), app/Console/Kernel.php,
qualquer .github/workflows/*.yml, e arquivos das frentes SA/FV/GT/Charters. Não aplique renames nem
re-seed de Meilisearch (isso é E2/E2b da Semana 1-2).

Re-derive do repo (origin/main): conte os ghosts reais e as colisões reais antes de afirmar. Commit
conventional, ≤300 linhas, Refs: SDD Semana-0 KL. Não toque CT 100 nem prod.
```

---

## 4. Frente GT — Governança / Scorecard + Refutador (modelo: Sonnet)

```
Você está numa sessão paralela da Semana 0 do plano de reestruturação SDD do oimpresso.
Contexto completo: memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md (frente GT).

OBJETIVO desta sessão (frente GT — medida + garantia). 3 entregas:
1. ADR DRAFT número 0275 em memory/decisions/0275-scorecard-sdd-calendario-promocoes.md definindo o
   scorecard único de 10 métricas (anchor_coverage, full_suite_pass_rate, n_quarantine, coverage_pct,
   ghost_count, front_door_coverage, recall_eval_violations, ragas_real_uptime, drift_alarms,
   backfill_error_rate) + o CALENDÁRIO DE PROMOÇÕES a required (máx 1/semana, critérios objetivos
   pré-escritos — anti promotion-fatigue do decisor único). Status: proposed. Wagner aprova.
2. Agregador G2: scripts/governance/sdd-scorecard.mjs (node determinístico SEM deps) que lê as fontes
   parciais disponíveis hoje e escreve governance/sdd-scorecard.json. Baseline de cada métrica capturado
   na 1ª medição real da FONTE, nunca do plano (anti-stale). Marque como composta v1 (fontes parciais).
3. G5: protocolo do agente REFUTADOR que bloqueia os lotes de backfill IA das ondas seguintes. Escreva
   memory/requisitos/_Governanca/PROTOCOLO-REFUTADOR-G5.md — sessão fresca, modelo >= gerador, tenta
   PROVAR que o anchor/claim está errado; ledger versionado; checklist com item de scan PII (repo público).

ARQUIVOS QUE VOCÊ PODE TOCAR (e só esses):
- memory/decisions/0275-scorecard-sdd-calendario-promocoes.md  (novo ADR draft)
- scripts/governance/sdd-scorecard.mjs  (novo)
- governance/sdd-scorecard.json  (novo)
- memory/requisitos/_Governanca/PROTOCOLO-REFUTADOR-G5.md  (novo; crie a pasta se faltar)

PROIBIDO: scripts/governance/knowledge-drift.mjs, scripts/anchor-lint.mjs (dono é a frente SA),
app/Console/Kernel.php, governance/required-checks-baseline.json (onda posterior), qualquer
.github/workflows/*.yml, e arquivos das frentes SA/FV/KL/Charters.

Re-derive do repo (origin/main) quais fontes de métrica já existem hoje vs quais nascem _pendente_.
Commit conventional, ≤300 linhas, Refs: SDD Semana-0 GT. Não toque CT 100 nem prod.
```

---

## 5. Frente Charters — Backfill mecânico de frontmatter (modelo: Haiku)

```
Você está numa sessão paralela da Semana 0 do plano de reestruturação SDD do oimpresso.
Contexto completo: memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md (frente Charters).

OBJETIVO desta sessão (frente Charters — só esta tarefa mecânica):
Backfill mecânico do frontmatter dos charters INCOMPLETOS em resources/js/Pages/**/*.charter.md.
Hoje o quadro é ~component: 104/136, page: 121/136, us: 3/136 (RE-CONTE no repo antes de começar).
- Preencher `page:` e `component:` SÓ quando inferível de forma determinística do path do arquivo /
  do .tsx irmão ao lado. Conformar ao schema scripts/memory-schemas/charter.schema.json.
- `us:` — NÃO inventar. Preencher apenas quando houver match único e óbvio; na dúvida, DEIXAR EM BRANCO
  pra a fila humana (o join US→tela é heurístico e tem fila própria nas ondas seguintes).

ARQUIVOS QUE VOCÊ PODE TOCAR (e SÓ esses):
- resources/js/Pages/**/*.charter.md  (apenas o frontmatter dos incompletos)

PROIBIDO: qualquer .tsx, qualquer Controller, qualquer script, qualquer ADR, e arquivos das frentes
SA/FV/KL/GT. Não invente valores de `us:`. Não mude o corpo do charter — só frontmatter.

Commit conventional, ≤300 linhas por PR (agrupe por módulo se passar disso), Refs: SDD Semana-0 Charters.
```

---

## Ordem de disparo sugerida

Pode disparar as 5 ao mesmo tempo — são disjuntas. Se quiser escalonar o gargalo Wagner (revisão de ADR):
1. Charters (Haiku, zero revisão) + FV (4 PRs técnicos) primeiro.
2. SA / KL / GT (cada uma gera 1 ADR draft) — Wagner revisa os 3 ADRs (0273/0274/0275) num batch.

Depois da Semana 0 fechar, o turbo grande é o burn-down de testes (Semanas 2-4): até 5 Sonnet, 1 módulo
cada (B1 Financeiro · B2 NfeBrasil · B3 matrix SQLite→MySQL · B4 tests/ raiz) — mas SÓ depois da nightly
diagnóstica F3 dar o 1º número real.
<!-- schema-allowlist: salvo de feat/governance-ds-rollout-ledger (branch shallow-orfanada 2026-06-20); output de subagente/legacy, schema estrito de secao nao se aplica -->
