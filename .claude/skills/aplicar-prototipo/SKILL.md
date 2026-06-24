---
name: aplicar-prototipo
description: ATIVAR quando Wagner pedir "pega o que mudou no protótipo e aplica", "aplicar protótipo nas telas", "atualizar as telas com o protótipo", "o que mudou no protótipo desde o último git", "aplicar todos os protótipos", "rodar o fluxo de aplicação de tela", OU qualquer pedido de aplicar/sincronizar protótipo Cowork em MAIS DE UMA tela (orquestração). Carrega o fluxo canônico de 6 fases (detectar → mapear paralelo read-only → consolidar/decidir → registrar task+changelog+SPEC → aplicar em SESSÃO LIMPA por tela em paralelo → fechar loop) — RUNBOOK detalhado em [prototipo-ui/RUNBOOK-aplicar-prototipo-orquestracao.md](../../prototipo-ui/RUNBOOK-aplicar-prototipo-orquestracao.md). É a camada ACIMA da skill `cowork-prototype-replication` (que é a mecânica de UMA tela). Regra de ouro: análise barata 1x (read-only) separada da aplicação cara (sessão limpa por tela = economia de token + isolamento). Origem: Wagner 2026-06-22.
trigger_intensity: B
tier: B
---

# Skill `aplicar-prototipo` — orquestração multi-tela (Tier B)

> Tira o peso do Wagner: ele só escolhe a tela e aprova o screenshot. O resto é carregado por este fluxo. Detalhe completo + template do GAP-SPEC em [`prototipo-ui/RUNBOOK-aplicar-prototipo-orquestracao.md`](../../prototipo-ui/RUNBOOK-aplicar-prototipo-orquestracao.md).

## Regra de ouro (por que este método)
Separar os dois custos opostos:
- **Análise** = barata, read-only, **1x**, precisa ver o todo → faz em **paralelo** (1 agente read-only por tela).
- **Aplicação** = cara, escreve código, **por tela**, precisa ver **só 1 tela** → faz em **sessão LIMPA por tela** (não arrasta a análise das outras = economia **O(1) vs O(N) telas**) + **worktree isolada** (isolamento **PARCIAL** — DS compartilhado + `config/*baseline*.json` saem do paralelo, viram PR de fundação sequencial antes; ver Fase 4).

Nunca aplicar às cegas: **mapa read-only primeiro**.

## As 6 fases (resumo — detalhe no RUNBOOK)
0. **Detectar** — **0.0 pré-voo de sanidade (ANTES de qualquer Glob/`git log`):** o cwd é checkout COMPLETO? (`git rev-parse --is-inside-work-tree` = true **e** `resources/`+`Modules/`+`prototipo-ui/` existem). Worktree órfã/husk dá **falso negativo silencioso** ("arquivo não existe"/"diff vazio") → PARE e mude de worktree. Referência por tela = `git log -1 --format=%H -- prototipo-ui/prototipos/<dir>/` (o SYNC_LOG NÃO guarda sha por tela). Mapa nome↔Page NÃO é 1:1 (crm→Cliente, vendas só charter). Sem mudança/vazio, o protótipo É o alvo. Intake canônico = Issue `cowork-intake` (ADR 0282), mas adoção ainda zero → hoje é handoff/bundle; trate ambos.
1. **Mapear** — 1 agente `general-purpose` READ-ONLY por tela, em paralelo. Divide a tela em PARTES (header/KPIs/filtros/lista/drawer/footer), por parte: o quê mudou/falta + **POR QUÊ** + esforço(P/M/G) + risco. Grava `memory/requisitos/<Mod>/<tela>-gap.md`.
2. **Consolidar + decidir [W]** — tabela mestre + **flags de governança que PARAM**: módulo silenciado (BRIEFING), Tier 0 (ADR 0093), tela "ouro"/`contrato-de-tela`, ADR-mãe não aprovada, cliente-sinal (ADR 0105). Wagner aprova ordem das ondas.
3. **Registrar** — `tasks-create` (MCP) com o GAP-SPEC embutido + CHANGELOG da tela (o quê+porquê por parte) + SPEC (US + `**Implementado em:**`).
4. **Aplicar** — **1 sessão limpa por tela** (task MCP retomada / `Agent(isolation:"worktree")` / `coordenador-paralelo`), carregando SÓ o `<tela>-gap.md` + skills auto (`mwart-process`, `cowork-prototype-replication`, `charter-first`, `multi-tenant-patterns`, `preflight-modulo`). Paralelo entre telas independentes. **PORTÃO:** screenshot 1280/1440 light+dark → Wagner aprova o SCREENSHOT → merge (pr-ui-judge + visual-regression + contrato-de-tela no CI).
5. **Fechar** — `SYNC_LOG` append + charter status/version + `anchor-lint --check` (fidelidade spec↔código, ADR 0297) + `brief-update`.

## Anti-padrões (PARA)
- Aplicar sem mapa read-only. · Carregar a análise das N telas dentro da sessão de aplicação de 1 tela (queima token). · Aplicar em tela silenciada/Tier-0/contract-locked sem OK [W]. · Inventar path/feature: gap incerto = `_pendente_`/pergunta (LICOES_F3). · Aprovar por tabela em vez de screenshot. · **Paralelizar telas que tocam o MESMO DS component ou rebaselinam o MESMO `config/*baseline*.json`** (conflito determinístico — serialize a fundação antes, incidente #2495). · Confiar no SYNC_LOG pra sha de diff (não tem). · Regredir tela que já está à frente do protótipo. · **Rodar de worktree órfã/husk** (sem código): Glob/`git log` devolvem falso-negativo silencioso → conclui "não existe" e duplica artefato real (pré-voo 0.0 primeiro).

## Pareada com
- `cowork-prototype-replication` (mecânica F0–F7 de UMA tela) · `mwart-process` (5 fases backend→cutover) · `coordenador-paralelo` (spawna as sessões limpas) · `anchor-lint` (fecha a fidelidade da SPEC).
