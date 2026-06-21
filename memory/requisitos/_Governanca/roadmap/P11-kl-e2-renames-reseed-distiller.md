---
roadmap_item: P11
slug: kl-e2-renames-reseed-distiller
onda: 4
status: proposed
depende_de: [P05]
destrava: []
related_adrs: [270, 271, 291, 292, 275, 288]
esforco_estimado: "0.5d codável + IA-pair (margem 2x = 1d) · + relógio humano-limitado: re-seed Meili (CT100, ~30min op) + 1ª destilação ligada (Wagner skim 10min/lote, janela de dias até cron descomentar)"
---

# P11 · KL E2: aplicar renames Classe A + re-seed Meilisearch + cron distiller

## Problema (o que está quebrado, em 2-3 frases)
A frente KL detectou que os docs de `memory/requisitos/**` citam módulos com nomes MORTOS (`Modules/MemCofre`, `Modules/PontoWr2`, `Modules/Copiloto`, `Modules/DocVault`) que não existem no disco — os módulos reais hoje são `Modules/SRS`, `Modules/Ponto`, `Modules/Jana`. O codemod `ghost-fix.mjs` foi construído e a tabela `ghost-rename-map.json` foi curada com evidência dura, mas a fatia Classe A NÃO foi aplicada aos docs fixáveis (só os 4 renames de #2603 rodaram). Além disso o re-seed do índice Meilisearch (a busca canônica do MCP) nunca rodou pós-rename, e o motor de destilação de portas (`jana:distill-module-truth`) está com o cron COMENTADO — o motor existe mas dorme, e `distiller_freshness=not_yet_measured`.

## Causa-raiz (evidência VERIFICADA — file:line reais que confirmei)

**1. ghost_count LIVE = 14 nomes distintos (confirmado).**
`node scripts/governance/knowledge-drift.mjs --json` agregado dá **14 nomes-fantasma distintos** citados por **24 módulos**. Distribuição real medida agora:
`MemCofre:9 · Accounting:5 · Project:4 · PontoWr2:3 · Copiloto:2 · Sells:2 · CrmPipeline:1 · Vendor:1 · LaravelAI:1 · DocVault:1 · Chat:1 · IProduction:1 · Pcp:1 · NfseBrasil:1` (contagem por MÓDULO citante).
O baseline armado bate: `governance/sdd-scorecard-baseline.json` → `ghost_count = {"value":14,"direction":"down","armed":true,...}` (re-armado 2026-06-15 commit 3b281d864; regressão >14 = exit 1).

**2. Os renames Classe A NÃO foram aplicados aos docs fixáveis.**
`governance/ghost-rename-map.json:6-11` lista 4 renames Classe A com evidência: `Copiloto→Jana`, `PontoWr2→Ponto`, `MemCofre→SRS`, `DocVault→SRS`. Dry-run agora (`node scripts/governance/ghost-fix.mjs --json`) → `occurrences_mapped: 25` em `files_with_changes: 12`, mas **TODAS** são `MemCofre→SRS` (perModule: MemCofre 15, ComunicacaoVisual 3, Vestuario 2, Autopecas 1, OficinaAuto 1, SRS 1, TeamMcp 1, _DesignSystem 1). PontoWr2/Copiloto/DocVault = **0 ocorrências fixáveis**.

**3. Disco confirma os renames:** `Modules/SRS`, `Modules/Ponto`, `Modules/Jana` EXISTEM; `Modules/MemCofre`, `Modules/PontoWr2`, `Modules/Copiloto`, `Modules/DocVault` ABSENT (checado com `[ -d ]`).

**4. ghost-fix #2603 rodou só 4 renames, depois foi RESTRINGIDO a pular ADRs.**
`git log`: `2df3410085 fix(requisitos): codemod ghost-fix --write — 4 renames aprovados (KL-A3) (#2603)`, seguido de `d415b4a55e fix(codemod): reverte 11 ADRs historicos + exclusao **/adr/** no ghost-fix (reprovacao auditor Tier 0)` e `219e8dac9d ...ghost-fix.mjs pula **/adr/** (#2729)`. O codemod hoje (`ghost-fix.mjs:60-61`) faz `if (e.name === 'adr') continue;` — pula a subárvore `adr/` inteira, por design (ADR 0094 Art.3: ADR de rename CITA o nome antigo como FATO).

**5. cron do distiller COMENTADO.** `app/Console/Kernel.php:209` → `// $schedule->command('jana:distill-module-truth --all')` (bloco 203-217 todo comentado). Comentário 205-208 explica: exige gate Wagner/CT100 (smoke skim 10min/lote) porque a destilação chama LLM e MUTA memória canônica PÚBLICA.

**6. motor distiller PRONTO, dormente.** `Modules/Jana/Console/Commands/DistillModuleTruthCommand.php` e `Modules/Jana/Services/Memoria/DistillerModuloVerdade.php` existem no main. `distiller_freshness=not_yet_measured` confirmado: `node -e measureDistillerFreshness('memory/requisitos')` → `{"status":"not_yet_measured","value":null}`; **0 BRIEFINGs têm `distilled_at:`** (`grep -rl "distilled_at:" --include=BRIEFING.md` = 0). ADRs 0291/0292 (contrato + errata determinística) existem no main.

## Estado atual no repo (o que achei ao verificar agora — DIVERGÊNCIAS reportadas)

**DIVERGÊNCIA-MÃE (a evidência mente sobre a viabilidade do DoD "ghost_count→0"):**
A evidência diz "aplicar renames Classe A (ghost_count→0)". Isso é **estruturalmente impossível só com o codemod**, por um descasamento entre detector e corretor:
- `knowledge-drift.mjs` (detector) varre `allMd(REQ)` SEM pular `adr/` (linhas 75-87) → ele CONTA ghosts dentro de ADRs.
- `ghost-fix.mjs` (corretor) PULA `adr/` (linhas 60-61) → ele NÃO corrige ghosts dentro de ADRs.

Resultado verificado: das citações dos 4 nomes Classe A, **PontoWr2, Copiloto e DocVault vivem 100% dentro de `adr/`** (todos os arquivos têm `/adr/` no path — confirmado com `grep -rl`). Logo o codemod **nunca os toca**. E mesmo o MemCofre: dos 14 docs citantes, **2 são ADRs** (`memory/requisitos/LaravelAI/adr/arq/0002-...md` e `memory/requisitos/MemCofre/adr/0008-rename-docvault-para-memcofre.md`) — o codemod fixa 12, mas **MemCofre sobrevive como ghost** pelas 2 citações em ADR.

Conclusão: após `ghost-fix --write`, os ghosts dos 4 nomes Classe A NÃO vão a 0. O melhor caso realista do codemod é derrubar o **MemCofre de 9 módulos citantes para ~2 (os 2 ADR docs)** — e os ghosts PontoWr2/Copiloto/DocVault ficam intactos. O número de **nomes distintos** (a métrica `ghost_count=14`) cai no máximo de 14→13 (só se TODAS as citações de DocVault saíssem — mas a única é um ADR, então nem isso). **Portanto ghost_count provavelmente não muda de 14 com o codemod sozinho.**

**Divergências menores:** PontoWr2 = **7 docs** citantes (evidência disse 8). Copiloto = 2 (✓). MemCofre = 14 docs (✓).

**O que isto força:** o DoD precisa ser RECONCILIADO antes de prometer "ghost_count→0". Há 3 saídas honestas (decisão em Passos §0). A mais limpa: **alinhar o detector ao corretor** — fazer `knowledge-drift.mjs` também pular `adr/` na contagem de ghosts (já que ADR é append-only e CITAR nome antigo lá é FATO histórico correto, não drift). Isso re-mede o ghost_count só sobre docs vivos e re-arma o floor pra baixo de forma defensável.

## Objetivo / DoD (critério de pronto OBJETIVO e checável)
**E2a — renames Classe A nos docs vivos:**
1. `node scripts/governance/ghost-fix.mjs --write` aplicado; re-run dry-run = `occurrences_mapped: 0` (idempotente). Os 12 arquivos não-ADR de MemCofre→SRS reescritos.
2. **Reconciliação detector×corretor decidida e aplicada** (Passo §0): `knowledge-drift.mjs` e `ghost-fix.mjs` concordam no escopo (ambos pulam `adr/`, ou ambos não). Sem isso o DoD "→0" é mentira.
3. ghost_count re-medido sobre docs vivos = **0 ghosts Classe A** (MemCofre/PontoWr2/Copiloto/DocVault) fora de `adr/`. Floor re-armado pra baixo em `governance/sdd-scorecard-baseline.json` via ratchet (`sdd-scorecard.mjs:293-311`); regressão acima do novo piso = exit 1.
4. Baseline anti-ghost encolhido: `node scripts/governance/knowledge-drift.mjs --write-baseline` reescreve `governance/knowledge-ghosts-baseline/<Mod>.json` por INTERSEÇÃO (catraca só diminui, linhas 99-113); MemCofre.json deixa de listar os nomes corrigidos.

**E2b — re-seed Meilisearch (CT100):**
5. Após renames mergeados, rodar re-seed do índice de busca canônica no CT100 (`config/scout.php` confirmado). Evidência objetiva: query no MCP por termo do doc renomeado retorna o doc com `Modules/SRS` (não `Modules/MemCofre`); recall não casa nome morto. Registrar em session log com timestamp + comando.

**E3 — cron distiller:**
6. Descomentar bloco `app/Console/Kernel.php:209-217` (`jana:distill-module-truth --all` daily 05:30) SOMENTE depois do processo de skim Wagner estar de pé (o próprio comentário 205-208 condiciona isto).
7. Rodar 1ª destilação manual (`php artisan jana:distill-module-truth --all`) → ≥1 BRIEFING ganha `distilled_at:`; `measureDistillerFreshness()` vira `status: measured` (deixa de ser `not_yet_measured`). Floor de `distiller_freshness` arma no 1º carimbo (padrão ADR 0291 D-D / 0279).

## Passos (ordenados, concretos)

**§0 — RECONCILIAR detector×corretor (decisão de arquitetura, bloqueia tudo).** Levar a Wagner as 3 opções:
   - (A) **alinhar detector ao corretor** (recomendado): `knowledge-drift.mjs` passa a pular `adr/` no `scanGhostsByModule` (linha 75-87) e no scan principal (linha 145-202). Justa: ADR é append-only e citar nome morto lá é FATO. Re-arma ghost_count só sobre docs vivos. Risco: perde visibilidade de ADR que cita módulo de verdade inexistente — mitigar com nota no header.
   - (B) manter detector amplo e ACEITAR floor não-zero (ex.: ghost_count=4 = "os 4 nomes Classe A só sobrevivem em ADR"). DoD vira "0 ghosts FORA de adr/".
   - (C) reescrever os ADRs (REJEITADO — viola ADR 0094 Art.3, foi exatamente a reprovação Tier 0 do `d415b4a55e`).
   Sem §0 fechado, NÃO aplicar §1.

**§1 — aplicar codemod (E2a).** `node scripts/governance/ghost-fix.mjs --write` (após Wagner aprovar o map, que já está aprovado). Conferir diff = só os 12 arquivos não-ADR, só `Modules/MemCofre`→`Modules/SRS`. Atenção ao sinal `mapped_target_subpath_missing: 6` (o nome corrige mas o subpath interno — ex. `Modules/SRS/Tests/Feature/InboxTest` — pode não existir no destino; fila humana, não bloqueia o rename do nome).

**§2 — re-medir e re-armar.** `node scripts/governance/knowledge-drift.mjs --json` → novo ghost_count. `node scripts/governance/sdd-scorecard.mjs --write-baseline` (ou o caminho de ratchet do scorecard) abaixa o piso. `node scripts/governance/knowledge-drift.mjs --write-baseline` encolhe `knowledge-ghosts-baseline/`.

**§3 — re-seed Meilisearch (E2b · CT100 · humano-limitado).** SSH CT100, snapshot do índice atual (rollback), rodar `php artisan scout:import` dos modelos de doc/memória (confirmar nome exato do searchable no `config/scout.php` + ADR do índice). Validar via query MCP. Registrar em session log.

**§4 — ligar cron distiller (E3 · humano-limitado).** Só DEPOIS do processo de skim Wagner formalizado: descomentar `Kernel.php:209-217`. Rodar 1× manual `php artisan jana:distill-module-truth --all --dry-run` → skim Wagner 10min → `--all` real → confirmar `distilled_at:` apareceu + `measureDistillerFreshness` = measured. Arma o floor.

## Arquivos a tocar (lista real)
- `memory/requisitos/**/*.md` (12 arquivos não-ADR de MemCofre→SRS) — escritos pelo codemod, NÃO à mão.
- `scripts/governance/knowledge-drift.mjs` — SE opção §0(A): pular `adr/` no scan (linhas 75-87 + 145-202).
- `governance/sdd-scorecard-baseline.json` — re-arma ghost_count pra baixo (via ratchet, não edição manual crua).
- `governance/knowledge-ghosts-baseline/<Mod>.json` — encolhidos por `--write-baseline` (MemCofre.json etc.).
- `app/Console/Kernel.php:209-217` — descomentar bloco distiller (E3).
- `memory/sessions/2026-06-NN-kl-e2-renames-reseed-distiller.md` — session log com evidência do re-seed CT100 + 1ª destilação (NÃO criar agora; é artefato da execução).
- (CT100, fora do git deste repo) índice Meilisearch — re-seed operacional.

## Gate / counterfactual (COMO provo que o gate MORDE)
**Counterfactual E2a (já existe e MORDE hoje):** o ghost_count está `armed:true value:14 direction:down` no scorecard baseline. Um diff que ADICIONE uma citação `Modules/MemCofre` num doc vivo novo (subindo o nome-fantasma) deve dar `exit 1` no ratchet do `sdd-scorecard.mjs:293-311` (regressão acima do piso). Após P11 abaixar o piso, o MESMO diff que tentasse re-introduzir MemCofre num doc vivo dá exit 1 contra o piso novo. **Prova de que P11 fechou:** rodar `ghost-fix --write` de novo → `occurrences_mapped: 0` (não há mais o que corrigir nos docs vivos).
**Counterfactual E3:** antes, `measureDistillerFreshness()` retorna `not_yet_measured` (provado agora). Depois da 1ª destilação, retorna `measured` com `value` numérico — o teste `scripts/governance/sdd-distiller-freshness.test.mjs` (casos A→E) já congela esse comportamento e dá exit 1 se o read-side parar de distinguir not_yet/measured. **Por que não é teatro:** a freshness só vira measured se um `distilled_at:` REAL existir num BRIEFING — não dá pra forjar sem rodar o motor.
**Dependência de P05 (por que o gate precisa de P05 pra não ser furado):** P11 abaixa o piso de ghost_count e encolhe `knowledge-ghosts-baseline/`. Mas o `baseline-tamper-guard` hoje só guarda 1 dos 4 baselines (`baseline-tamper-guard.mjs:55-57`) e NÃO cobre `knowledge-ghosts-baseline/` — exatamente o vetor #2848 (ghost 14→16 grandfatherado no mesmo PR). Sem P05, um PR futuro pode re-afrouxar o baseline-ghost + meter código no mesmo commit e o rename "re-abre" sem ninguém pegar.

## Dependências (e por que)
- **depende_de: [P05]** — P05 estende o `baseline-tamper-guard` pra cobrir `knowledge-ghosts-baseline/` (DoD §1-2 de P05) e prova via selftest que MORDE. Sem isso, abaixar o piso de ghost em P11 é reversível por grandfather (vetor #2848). Ordem obrigatória: fechar o cofre (P05) antes de baixar o que ele protege (P11).
- **destrava: []** — P11 não é pré-requisito formal de outro item do roadmap; é entrega terminal da frente KL E2/E3.

## Esforço (recalibrado ADR 0106)
- **Codável + IA-pair (10x + margem 2x):** §0 (reconciliação) é decisão + ~10 linhas no `knowledge-drift.mjs`; §1-2 são chamadas de scripts já prontos + revisão do diff de 12 arquivos. **~0.5 dia codável → margem 2x = ~1 dia.**
- **Relógio humano-limitado (NÃO comprime com IA):**
  - §0 espera **decisão do Wagner** sobre a opção (A/B/C) — minutos a horas de janela, não de trabalho.
  - §3 re-seed Meilisearch é **op manual no CT100** (SSH + snapshot + scout:import + validar) — ~30min de relógio real, MAS só pode rodar DEPOIS dos renames mergeados (serializa).
  - §4 cron distiller condiciona ao **processo de skim Wagner estar de pé** (o próprio Kernel.php:205-208 exige) + 1ª destilação chama LLM e exige **skim Wagner 10min/lote**. A janela até descomentar com segurança é de **dias**, governada pela disponibilidade do Wagner, não por código.
- Resumo honesto: o código fecha numa sessão; o ITEM só fecha quando o re-seed CT100 rodou e a 1ª destilação foi skimada por Wagner — relógio do mundo real de poucos dias.

## Kill-criteria / risco (quando parar ou reabrir)
- **Kill se §0 travar:** se Wagner não decidir A/B/C, NÃO aplicar o codemod prometendo "→0" — seria a mesma mentira que o avaliador adversarial caça ("métrica de forma, não de correção"). Entregar só E2a com DoD honesto "0 ghosts fora de adr/" (opção B) e parar.
- **Risco subpath-rot (`mapped_target_subpath_missing: 6`):** o codemod corrige o NOME (`MemCofre→SRS`) mas o subpath interno citado (ex. `Modules/SRS/Http/Controllers/DashboardController.php`, `Modules/SRS/Tests/.../InboxTest`) pode não existir em SRS. Isso é fila humana de path-rot, NÃO bloqueia o rename do nome — mas registrar os 6 casos pra não fingir que ficaram corretos.
- **Risco re-seed CT100:** índice Meilisearch é runtime de produção (busca do MCP). Snapshot ANTES (rollback). Se recall piorar pós-seed, reverter snapshot. Não acoplar ao deploy de código (Hostinger ≠ CT100, ADR 0062).
- **Risco distiller auto-mutando memória pública:** o cron descomentado roda LLM que REESCREVE BRIEFING.md canônico. Manter o gate de skim Wagner; se 1 lote vier ruim, recomentar o cron (kill-switch é o `//`). Não ligar sem o processo de skim formalizado — é a razão do comentário existir.
- **Reabrir se:** um 5º nome Classe A entrar no `ghost-rename-map.json` com evidência dura → re-rodar o codemod (idempotente) + re-armar pisos.
