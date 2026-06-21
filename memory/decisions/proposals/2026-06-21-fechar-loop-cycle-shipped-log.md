---
title: "Fechar o loop: porta de saída do cycle — shipped-log gerado + changelog derivado (estende ADR 0294)"
status: proposed
date: "2026-06-21"
decisores: [Wagner (aprova), Claude Code (autor)]
related_adrs:
  - 0294-metodo-dual-track-shapeup-catraca
  - 0070-jira-style-task-management-current-md-removed
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0226-brief-v2-1m-aware-rico
origem: "Wagner: 'qual a lista do roadmap?' → 'tem coisa feita em PR antigo que não caiu no changelog?' → descoberta: changelog do DS 10d parado, ~80 PRs de DS sumidos, cycle drift 0/24, backlog por módulo desligado do cycle → 'e se roadmap/changelog/backlog dentro de algum ciclo?' → 'A' (detalhar proposta de fechar o loop)."
prs: []
---

# Fechar o loop: porta de saída do cycle (shipped-log gerado + changelog derivado)

> **⚠️ Status 2026-06-21 — v1 REPROVADA por red-team (3 céticos adversariais).** O gerador `shipped-log-generate.mjs` (no disco, não-commitado) **não deve landar como está**: já perde **48 PRs reais** (teto de 1000 da Search API), é cego a **push-direto-na-main** (18+ commits, incl. produto), confunde *merge* com *entrega* (revert/deploy-fail/flag), e põe a parede num locus que não morde (tool MCP vs CI-sobre-git). Detalhe em [Modos de falha conhecidos](#modos-de-falha-conhecidos-red-team-2026-06-21). O sintoma imediato (changelog DS) já foi curado à mão ([#3167](https://github.com/wagnerra23/oimpresso.com/pull/3167)). Esta proposta segue **viva como pitch**, mas só vira aposta após as [Condições de validade](#condições-de-validade--o-que-tem-que-ser-verdade-antes-de-virar-aposta). **Esforço real revisto: ~1-2 dias IA-pair, não 45min.**

## Contexto

[ADR 0294](../0294-metodo-dual-track-shapeup-catraca.md) montou a **porta de entrada** do cycle: descoberta diverge em `_Ideias`/proposals, a betting table aposta 1 caminho, e só o apostado vira task MCP `parent_plan` dentro do cycle (WIP cap 3 + sentinela `plan-health`). Pitch → aposta → tasks → cycle está coberto e travado por catraca.

A **porta de saída não existe.** Quando o cycle fecha, nada registra *o que foi entregue*. Três sintomas medidos nesta sessão (2026-06-21):

1. **Roadmap fragmentado:** `memory/07-roadmap.md` órfão (abr/2026, só PontoWR2); o roadmap vivo é o goal do cycle; ROADMAPs por módulo avulsos. Nenhum se referencia.
2. **Changelog drifado:** o **único** changelog do projeto fora de `vendor/` é o do DS ([_DesignSystem/CHANGELOG.md](../../requisitos/_DesignSystem/CHANGELOG.md)) — congelado em **[0.6.14] · 11/jun** (10 dias). Cruzando contra `gh`: **~80 PRs de DS/UI** mergeados desde então (Onda M1 oklch/motion, dark mode ADR 0281, redesign Cowork Caixa/Forja/Financeiro, pipeline zero-paste, catracas Contrato de Tela) **não estão no changelog**. Sumiram do registro — não do código.
3. **Backlog desligado:** `_BACKLOG-GENERATED.md` indexa **696 US abertas em 44 módulos** organizadas **por módulo**, não por cycle. O cycle não puxa do backlog — as 5 metas foram escritas à mão. E o brief grita *cycle drift*: **0/24 commits (7d) linkam US do CYCLE-08**.

Diagnóstico: **roadmap, changelog e backlog usam três eixos (cycle / versão / módulo) que não se conversam.** 0294 resolveu a entrada; a saída aberta é o que faz o trabalho entregue evaporar do registro. Se `cycles-close` tivesse gerado um registro de entrega, **nenhum dos 80 PRs teria sumido.**

> **O changelog não drifou por desleixo — drifou porque era manual e nada o forçava.** Mesma lição de 0294/0256: método que depende de força de vontade morre; método na catraca vive.

## Decisão (proposta)

Adicionar a **porta de saída do cycle**: ao fechar o cycle, um **shipped-log é gerado mecanicamente** a partir dos PRs realmente mergeados na janela do cycle. O changelog do DS (e qualquer changelog por domínio) passa a ser **derivado** desse shipped-log, não mantido à mão. O loop fecha: `_Ideias → aposta → tasks parent_plan → cycle → shipped-log → retro alimenta a próxima betting table`.

### A mecânica (a catraca — por que sobrevive)

- **Gerador `shipped-log` por cycle** (`scripts/governance/shipped-log-generate.mjs`, mesmo padrão de `tasks-index-generate.mjs`/`plans-index`): recebe o cycle, lê `start/end` do MCP, consulta `gh pr list --search "merged:>=START merged:<=END"`, agrupa por **scope do conventional-commit** (`feat(financeiro)` → área Financeiro) e emite `memory/governance/shipped/CYCLE-NN.md`. **É o changelog do projeto inteiro que nunca existiu** — por cycle, gerado, à prova de drift.
- **NÃO depende de `Refs: US-XXX`.** Aprendizado empírico desta sessão: os commits **não** estão linkando (drift 0/24). A janela de merge (`mergedAt`) + scope é a fonte robusta; `Refs`/`parent_plan` enriquecem quando presentes, mas não são pré-requisito. O gerador funciona apesar do drift de commit, não por causa da sua ausência.
- **Changelog do DS vira derivado:** o gerador filtra os PRs que tocam paths de DS (`Components/`, `resources/css`, `prototipo-ui/`, `_DesignSystem/`, `*.casos.md`, workflows `ui-lint`/`visual-regression`/`design-index`) e emite um bloco `## [versão] - <cycle>` candidato no `_DesignSystem/CHANGELOG.md`. Curadoria humana fica por cima (narrativa "Não regrediu"), mas a **lista de PRs nunca mais some**.
- **A parede é o fechamento:** `cycles-close` (MCP) só finaliza com o shipped-log gerado e commitado. Sem ele = cycle não fecha (ou fecha com flag vermelha). Espelha a parede de 0294 ("plano só vira `em-execução` quando há task `parent_plan`"); aqui: **"cycle só fecha quando o shipped-log foi gerado."**
- **Sentinela `shipped-log-health`** (estende `memory-health.mjs`, [ADR 0256](../0256-knowledge-survival-meia-vida-catraca-sentinela.md)): flaga cycle fechado sem shipped-log + changelog de domínio > N dias atrás do último cycle fechado. Sai no Daily Brief ([ADR 0226](../0226-brief-v2-1m-aware-rico.md)).

### Riders de limpeza (resolvem o "roadmap fragmentado")

- **`07-roadmap.md` → arquivado** com ponteiro: o roadmap de entrega é o cycle (0294); o de descoberta é `_Ideias`/proposals. Não há terceiro "roadmap".
- **ROADMAPs por módulo** (`requisitos/<Mod>/ROADMAP.md`) → ou viram fatia gerada do backlog por módulo, ou ganham `status:` + ponteiro pro cycle. Decidir na Onda 2.
- **Backlog → cycle:** o corte do cycle deixa de ser escrito à mão — `cycles-create` propõe um corte priorizado do `_BACKLOG-GENERATED.md` (por sinal/ROID, [ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md)) como candidatos a goal. (Onda 3, depende da Onda 1.)

## Por quê

1. **Fecha o loop que 0294 deixou aberto** — sem custo de filosofia nova; é a porta de saída da mesma membrana.
2. **Mata o "PR some do registro" na raiz** — o registro é gerado da verdade (PRs mergeados), não da memória de quem lembrou de escrever o changelog.
3. **Dá ao projeto o changelog que ele nunca teve** — hoje só o DS tem; governance/jana/financeiro/whatsapp não têm nenhum. O shipped-log por cycle cobre tudo de uma vez.
4. **Determinístico, sem LLM no caminho crítico** — igual `plans-index`/`tasks-index`. Barato e à prova de alucinação.
5. **Transforma o `cycles-close --rollover` num momento de verdade** — fechar cycle deixa de ser só mover incompletas; passa a produzir o artefato de retro que alimenta a próxima aposta.

## Consequências

**Positivas:** changelog para de drifar (gerado); projeto inteiro ganha registro de entrega por cycle; retro fim-a-fim (aposta → o que entregou); o gap de DS que achamos não se repete.

**Custo/negativas:** precisa do gerador + 1 sentinela + hook no `cycles-close` (custo determinístico). Risco de **dupla manutenção** se o DS changelog continuar manual em paralelo — mitigação: o DS changelog passa a ser **derivado** (bloco gerado + curadoria por cima), não fonte paralela. Risco de **shipped-log ruidoso** (PRs de docs/chore poluindo) — mitigação: agrupar por scope e colapsar `chore/docs/test` numa linha-resumo.

## Alternativas consideradas

- **Manter changelog manual, só lembrar de atualizar** — ❌ é exatamente o que falhou (10 dias, 80 PRs). Força de vontade.
- **Changelog por PR-merge automático (cada merge apenda)** — ⚠️ vira ruído sem agrupamento; o valor é a síntese por cycle, não o log cru. Rejeitado a favor do gerador por janela.
- **GitHub Releases / release-please** — ⚠️ assume semver + 1 changelog raiz; o projeto é multi-módulo sem versão única e o registro precisa ser por cycle (unidade de planejamento real), não por tag.
- **Não fazer nada** — ❌ o registro segue evaporando; "qual a lista do roadmap?" continua sem resposta confiável.

## Implementação (ondas)

- **Onda 0 (esta proposta):** documentar a porta de saída; Wagner aposta.
- **Onda 1 (núcleo):** `shipped-log-generate.mjs` + `memory/governance/shipped/` + retrofit **manual** do gap atual (gerar o shipped-log de CYCLE-08 11→21/jun, que já preenche o buraco do DS achado hoje). Condição de validade — sem isto, é só papel (mesma regra de 0294 Onda 1).
- **Onda 2:** DS changelog vira derivado (bloco gerado) + sentinela `shipped-log-health` no Daily Brief + arquivar `07-roadmap.md` + decidir ROADMAPs por módulo.
- **Onda 3:** hook `cycles-close` exige shipped-log (a parede) + `cycles-create` propõe corte priorizado do backlog (liga backlog→cycle).

## Modos de falha conhecidos (red-team 2026-06-21)

Três céticos adversariais atacaram a v1 contra a janela real do CYCLE-08. Achados que se sustentam, com dado medido:

### A. Já incompleto — falha no que existe pra impedir
- **Teto de 1000 da Search API:** `gh pr list --search` trava em 1000 resultados (teto duro; `--limit` não ajuda). A janela tem **1048 PRs reais** (REST paginado, `base=main`) → **48 PRs somem agora**, os mais antigos. O guard `=== LIMIT` era cego (passar `--limit=2000` pulava o aviso); já endurecido pra `>= teto` + `exit 1`, mas o fix real é trocar a fonte.
- **Push direto na main sem PR:** **18+ commits** entraram sem objeto-PR (incl. `feat`/`fix` de produto) → invisíveis a qualquer query de PR. Classe inteira de entregas que o gerador nunca vê.
- **Borda BRT vs UTC:** `merged:..DATA` é UTC; a última noite BRT do cycle (21h–23:59 BRT) cai fora → terra-de-ninguém entre dois logs.
- **Mid-cycle / rate-limit:** janela aberta = alvo móvel sem marca "parcial"; 5xx/rate-limit parcial pode truncar com exit 0.

### B. Mentira de rótulo — "entrega" prova só *merge*
- **merged ≠ entregue:** `#2104` (feature) mergeado, `#2107` revertido 11h depois (quebrou cliente) — entrega líquida zero, mas o log conta "+1 feature". 4 reverts na janela, nenhum reconciliado.
- **merged ≠ deployado ≠ funciona:** incidentes reais (classmap-stale prod 500; ext-sodium) e feature-flags = código mergeado/"entregue" mas fora do ar/invisível. O nome promete realidade de produção; a evidência é só merge no GitHub.
- **Ruído de agrupamento:** scope cru racha a mesma área (`caixa-unificada`/`caixa-unif`/`caixa`; acento `governance`/`governanca`); ~9 títulos não-convencionais (`PR Onda B:`) caem em "outros"; `chore`/`docs` colapsam trabalho real; heurística "DS" pega `cliente` (CRM, não design).

### C. Não morde onde o plano põe a parede
- **Locus errado:** a parede proposta vive no `cycles-close` (tool MCP de runtime), que não lê estado git → vira "aviso e fecha mesmo assim". A catraca que FUNCIONA aqui é **CI `--check` sobre arquivo versionado** (adr-index, memory-health).
- **Teatro:** hoje é 100% papel — não-commitado, nunca rodou `--write`, **nada lê** o output, **nada arma** o `--write` (os geradores-irmãos também dependem de `--write` manual + CI `--check`), **sem `.test.mjs`**.
- **Gaming:** janela vazia/errada gera-e-passa; o script não puxa `start/end` do cycle do MCP (datas vêm 100% dos args do operador).

## Condições de validade — o que tem que ser verdade antes de virar aposta

1. **Fonte completa:** trocar Search API por **REST paginado** (`base=main`, sem teto) + **2ª fonte `git log origin/main`** pra commits sem PR + **cross-check de contagem com `exit 1`** ao divergir (mata truncação silenciosa e rate-limit parcial de uma vez).
2. **Rótulo honesto:** renomear de "entregue/shipped" para **"mergeado na janela"**; **reconciliar reverts** (riscar par PR↔revert); marcar o fosso *merge→deploy→funciona* explicitamente fora de escopo (ou cruzar com SHA de deploy real).
3. **Janela confiável:** `--since/--until` derivados do **cycle via MCP** pelo `--cycle` (não args livres); TZ explícito `-03:00`; bloquear janela aberta sem `--allow-open` (banner "PARCIAL" no doc).
4. **Parede que morde:** mover pra **gate CI `--check`** sobre o arquivo versionado (cycle fechado sem shipped-log fresco/commitado → PR vermelho), espelhando `adr-index-generate --check`. **Não** no `cycles-close`.
5. **Armar e ler:** `--write` por **cron + auto-PR** (`peter-evans/create-pull-request`, padrão já no repo); **≥1 leitor mecânico** (sentinela `shipped-log-health` no Daily Brief) antes de declarar "loop fechado".
6. **Auto-verificação:** `.test.mjs` com fixtures-armadilha (título sem prefixo, scope desconhecido, janela vazia, revert) no `governance-gate-umbrella` (controle-negativo), como os geradores-irmãos.
7. **Agrupamento:** tabela de aliases de scope + `normalize('NFD')` pro acento; derivar "DS" dos **paths tocados** (`Components/`, `resources/css`, `_DesignSystem/`), não do título.

> Linhagem: 3 red-teams adversariais 2026-06-21 (enforcement/locus · semântica merged≠entregue · completude de dados) + estado-da-arte [`memory/sessions/2026-06-21-arte-engineering-work-management-ia.md`](../../sessions/2026-06-21-arte-engineering-work-management-ia.md). A máquina de catraca (ADR 0294/0256) é o ativo; este elo é o passivo a fechar **com honestidade de rótulo**, não com teatro.

## Refs

- ADRs: [0294](../0294-metodo-dual-track-shapeup-catraca.md) (entrada do loop — esta é a saída) · [0070](../0070-jira-style-task-management-current-md-removed.md) · [0256](../0256-knowledge-survival-meia-vida-catraca-sentinela.md) · [0226](../0226-brief-v2-1m-aware-rico.md) · [0105](../0105-cliente-como-sinal-guiar-sem-mandar.md)
- Artefatos tocados: [_DesignSystem/CHANGELOG.md](../../requisitos/_DesignSystem/CHANGELOG.md) · [_BACKLOG-GENERATED.md](../../requisitos/_BACKLOG-GENERATED.md) · [PLANS-INDEX](../../requisitos/_processo/PLANS-INDEX.md) · `memory/07-roadmap.md`
- Evidência do gap: 589 PRs mergeados desde 11/jun; ~80 de DS fora do changelog (#2552→#3114).
