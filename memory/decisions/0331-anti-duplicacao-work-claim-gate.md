---
slug: 0331-anti-duplicacao-work-claim-gate
number: 331
title: "Trava anti-duplicação de trabalho entre sessões paralelas (claim + dup-detector gate)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-22"
module: governance
related:
  - 0070-jira-style-task-management-current-md-removed
  - 0040-policy-publicacao-claude-supervisiona
  - 0053-mcp-server-governanca-como-produto
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
# legado da proposta (preservado — additionalProperties)
proposal_id: anti-duplicacao-work-claim-gate
created: 2026-06-22
proposed_by: claude-code
parent_adr: "0094 (Constituição v2)"
origem: memory/decisions/proposals/onda-0-rede-seguranca-enforcement.md
---

> Movido de proposals/ em 2026-07-09 (lei decidida invisível ao MCP — sentinela adr-proposto-parado check A). Conteúdo original intacto; ajustes SÓ mecânicos: frontmatter adequado ao schema canônico (`status: accepted→aceito`, `type→adr`, `decided_by→[W]`, `related` com slugs completos) + links relativos re-baseados de proposals/ pro top-level.

# Proposta · Trava anti-duplicação de trabalho entre sessões paralelas (claim + dup-detector gate)

> **Status:** ✅ **ACEITA 2026-06-22** — Wagner aprovou a **L3 como MVP** (recomendação desta proposta).
> **L3 IMPLEMENTADA** em 2026-06-22 (PR #3200): `scripts/governance/dup-detector.mjs` + `governance/dup-hot-paths.json`
> + `dup-detector-gate.yml` (advisory) + 14 fixtures no `governance-script-tests` + registro no `gates-registry.json`.
> **L1 (claim) e L2 (pré-flight) adiados** — dependem do MCP server (tasks/claim, cc-watcher), repo separado (CT 100).
> Origem: Wagner — *"como garantir SEMPRE uma única fonte de verdade? pode vir outro e estragar isso de novo. é bom botar trava ou um SDD."*

## Contexto (o problema, com evidência)

Sessões Claude paralelas **duplicam trabalho** e às vezes **aterrissam PRs concorrentes**. Não é hipótese:
- Handoff [#3092](../handoffs/2026-06-20-2115-sessao-duplicada-armamento-sdd.md): uma sessão repetiu o backlog de armamento SDD de outra (PR #3087 = duplicata do #3084). Recomendação dele: **disciplina** ("checar git log + sessões antes de codar").
- **A disciplina falhou de novo**: na Onda 0 (2026-06-22) eu dupliquei o Brick B (#3182 vs #3150) e o Brick D (plano + agenda vs #3181/#3143) — **mesmo com o #3092 já avisando**.

Fato estrutural: **o paralelismo é intencional** (Wagner roda o mesmo prompt em 2-3 sessões; "a 1ª que landa vence"). Então o objetivo **não é impedir paralelo** — é garantir que **a 1ª vença, as outras abortem cedo, e nenhuma duplicata aterrisse**. Qualquer solução que dependa de alguém *lembrar* vai reincidir.

## Princípio (o que "fonte única" exige aqui)

O projeto já tem a fórmula certa: **uma fonte é única só se houver (a) um arquivo/registro canônico + (b) uma catraca que bloqueia criar concorrente.** Vivo em `gates-registry.json` (⟵ memory-health Check G), `required-checks-baseline.json` (⟵ protection-drift), âncora spec↔código (ADR 0273). **Falta** aplicar isso a uma fonte: **"o que está sendo trabalhado agora" (work-intent)** — hoje espalhado em branches/PRs/sessões, sem dono nem catraca.

## Decisão proposta — 3 camadas (mecânica, não disciplina)

| Camada | O que é | Onde pega |
|---|---|---|
| **L1 · Registro de claim** | work-item = task no MCP ([ADR 0070](./0070-jira-style-task-management.md), já existe), **reivindicada** (assignee+status doing) antes de codar. A task vira a fonte única do intent. | início |
| **L2 · Pré-flight "estou atrasado?"** (hook SessionStart/pre-edit) | cruza tópico+arquivos-alvo com: PRs abertos, merges dos últimos N dias, sessões vivas (o **cc-watcher** já ingere sessões no MCP), claims abertas. Overlap → **PARA com aviso + ponteiro** | antes de gastar trabalho |
| **L3 · Gate `dup-detector` no CI** ⭐ | no PR: compara arquivos tocados + tópico vs outros PRs abertos + merges recentes em **paths "quentes"** (gates, scorecard, baselines, registries, ADRs). Overlap alto → **bloqueia** até ack explícito "não é dup" | no merge (última linha) |

**A keystone é a L3.** É mecânica, roda em todo PR, **não depende de sessão nenhuma lembrar**, e morde no ponto de serialização (o merge — onde as paralelas convergem). Teria barrado meu #3182 (tocava `sdd-scorecard.yml`/`gates-registry.json`, sobrepondo #3150/#3181). L1+L2 reduzem trabalho desperdiçado; **L3 garante que nenhuma duplicata *aterrisse*.**

Esboço da L3: `scripts/governance/dup-detector.mjs` + workflow advisory; lê hot-paths de um `governance/dup-hot-paths.json` (fonte única configurável), consulta `gh pr list --state open` + `git log --since` por overlap de arquivos, e exige um marcador `Dedup-ack: <PR/justificativa>` no corpo do PR quando há colisão. Counterfactual versionado (good/bad fixture) no `gate-selftest.mjs`, igual às outras catracas.

## Apoiado no que JÁ existe (não reinventar)
- **ZELADOR** (`scripts/governance/ZELADOR.md`): reconciliador diário — **reativo** (limpa drift), não previne. L1-L3 são a metade preventiva que falta; o ZELADOR continua sendo a rede reativa.
- **cc-watcher → MCP**: já põe todas as sessões CC no servidor → **fonte de dados** pronta pra L2/L3 saberem "quem faz o quê".
- **Tasks MCP** (ADR 0070): já é o lugar canônico — falta o **claim obrigatório** + a catraca.
- **`session-anti-dup` / jscpd**: cobre dup de **código** (copy-paste), problema diferente — não há sobreposição com work-dup.

## Cláusula de evolução (template ZELADOR — obrigatória)
Nasce com: (a) métrica sobre si — `nº de PRs-duplicados que aterrissam/mês` (tem que CAIR) + `nº de falso-positivos do gate` (tem que ficar baixo); (b) auto-aplicação periódica (a cada 7 PRs barrados, revisar se o hot-paths está calibrado); (c) emenda pelo mesmo gate (PR + Wagner); (d) **núcleo imutável**: a L3 nunca pode ser afrouxada no mesmo PR que ela barraria (anti-grandfather, como o `baseline-tamper-guard`).

## Caminho de promoção
L3 entra **advisory** e vira `required` após **3 dias verdes + 0 falso-positivo** (decisão Wagner 2026-06-22 — ritmo IA-pair torna 14d excessivo; **sobe a 7 dias depois**, quando o sistema amadurecer). Soak menor que o default de 14d da [ADR 0275](./0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) §5 **só para gates de tempo-puro** como este; métricas SDD compostas (C2/T2/A10/G3) mantêm os critérios técnicos próprios.

## Kill-criteria
1. Falso-positivo frequente travando PRs legítimos (overlap de arquivo ≠ overlap de intenção) → recalibrar hot-paths / só advisory.
2. Custo de manter o hot-paths > valor → subtrair (vira elefante, ADR 0270).
3. Se L1 (claim) provar suficiente sozinha (raro aterrissar dup) → não promover L3 a required.

## Reversibilidade
Alta. L2/L3 são aditivos (hook + workflow advisory). L1 é convenção sobre o sistema de tasks que já existe. Nada cria schema irreversível nem toca Tier 0.

## Decisão a tomar pelo Wagner
- [ ] Aprovar o escopo (L1+L2+L3) e **nomear UM dono/sessão** pra construir — explicitamente pra **não** virar N sessões construindo o anti-duplicador (a ironia que originou esta proposta), OU
- [x] **APROVADO (2026-06-22): só a L3 (keystone) como MVP, L1/L2 adiados.** ✅ implementada (#3200).
- [ ] Ajustar / rejeitar.

> Recomendação: aprovar **L3 como MVP** (maior alavanca, mecânica, independe de adoção humana) com **um dono único**, advisory→required pelo calendário dos 14 dias. L1/L2 entram depois se a L3 sozinha não bastar.
