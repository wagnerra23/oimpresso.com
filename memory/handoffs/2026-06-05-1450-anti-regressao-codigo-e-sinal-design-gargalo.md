---
date: "2026-06-05"
hour: "14:50 BRT"
topic: "Anti-regressão do código (Opção B) + sinal forte: DESIGN é o gargalo de receita"
duration: "~longa (pesquisa + 2 PRs + reframe estratégico)"
authors: [W, CC]
---

# Handoff — anti-regressão do código + sinal DESIGN-gargalo

> **TL;DR:** Sessão começou em pesquisa design+spec, virou implementação de anti-regressão pro **código** (Opção B: dar ao código o §5 + gate pontuado que o método de design já tinha), entregou 2 PRs mergeados. No fim, Wagner cortou todo o meta: **"meu gargalo real é o DESIGN, regra absoluta, está tirando toda minha receita."** Próxima sessão ataca ISSO — não teste, não processo.

## Estado MCP no momento
- Sessão exploratória/processo — **não dirigida por task de cycle** específica.
- 2 PRs mergeados na `main` nesta sessão (ambos `--admin`, CI verde): **#2273** + **#2278**.
- ⚠️ Repo primário ficou na branch `docs/handoff-parecer-pr2270` → **PR #2272 aberto e FALHANDO** o schema de Handoff (loose end pra [W] decidir: consertar schema ou fechar).
- `#2271` (PBT piloto) já estava mergeado; `#2270` (pesquisa design-judge) status a confirmar.

## O que aconteceu
1. Pesquisa estado-da-arte design+spec 2026 (SDD/Kiro/Spec-Kit/Tessl · DESIGN.md Stitch · Figma MCP) + leitura crítica dos PRs #2270/#2271.
2. Wagner apontou **regressão de processo**: testes/planos gerados **sem** o pré-flight do método anti-regressão dele (`PROCESSO_MEMORIA_CC`). Insight aceito: teste que deriva do **código** (não do contrato) é tautológico → trava o drift.
3. **Opção B** (Wagner escolheu): dar ao mundo de **código** as 3 peças anti-regressão que o método de **design** já tinha, **sem inchar** o de design.
4. Implementei Peças 1+2 e Stage 1 da mecanização.
5. Reavaliação crítica do processo → diagnóstico **"cerca-não-casa"**: construímos guardrails (camada barata), as técnicas de maior ROI (TDAD, PBT-real, VLM-judge) = 0 aplicadas.
6. Pesquisa frontier 2026: gargalo virou **verificação/evals** + **distribuição** (solo founder); "building got cheap, distribution didn't".
7. **Sinal forte do fundador:** design é o gargalo absoluto de receita. Wagner dispensou meu menu de 4 mecanismos — vai explicar com as palavras dele na próxima.

## Artefatos gerados (mergeados na main)
- **#2273** — `memory/proibicoes.md` §"Ideias avaliadas e DESCARTADAS" (§5 do código, +16 linhas, 2 entradas reais) + `module-completeness-audit` v1.0→**v1.1** (Check 9 "teste ancorado em contrato" DURO + veredito pontuado Bateria §9: 9 checks, 4 duros, corte ≥90).
- **#2278** — `.claude/hooks/nudge-test-contract-anchor.ps1` (advisory PreToolUse, lembra âncora de contrato ao editar `*Test.php`) + registro no `settings.json`. Dispara em **sessões novas**.

## Persistência
- git: 2 PRs na `main` (#2273, #2278) + este handoff (branch `docs/handoff-2026-06-05-design-bottleneck`).
- MCP: propaga via webhook ~2min após push.

## Próximos passos pra retomar
> **PRIORIDADE ABSOLUTA (Wagner declarou):** atacar **DESIGN como gargalo de receita**. Próxima sessão começa ouvindo Wagner descrever, com as palavras dele, COMO o design trava receita (lento? depende dele? não converte? inconsistente?) — diagnóstico antes de solução. **NÃO** retomar teste/TDAD/processo.

Parados (sem pressa, só se [W] pedir):
- Peça 3 (métrica de drift: recidiva + escapes) · Stage 2-3 mecanização (CI gate + audit em CI).
- TDAD-lite **PR-1 por COBERTURA** (não grep) — pesquisa profunda concluiu: seleção de teste tem que ser *derivada* (cobertura real), *medida* (decay) e com *rede de segurança* (full no merge). 3 invariantes do "vivo ao tempo".
- Roadmap evolução design+spec (4 etapas) · #2272 schema handoff · #2270 status.

## Lições catalogadas
- **Cerca-não-casa / pesquisa subaplicada:** construir guardrails (barato) em vez das técnicas de ROI alto (a casa). Vale pro dev E pra empresa (frontier: distribuição/evals > engenharia).
- **Menu demais cansa o [W]** ("fiquei perdido") — propor 1 caminho, não 4 opções.
- **Teste tautológico** (deriva do código) trava drift — pior que não ter (catalogado no §5 #2273).

## Pointers detalhados (on-demand)
- Método anti-regressão: `prototipo-ui/PROCESSO_MEMORIA_CC.md` (NÚCLEO 13 · Bateria §9 · §5 · catraca).
- §5 do código: `memory/proibicoes.md` §"Ideias avaliadas e DESCARTADAS".
- Gate pontuado: `.claude/skills/module-completeness-audit/SKILL.md` (Check 9 + Veredito).
