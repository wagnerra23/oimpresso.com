---
date: "2026-07-17"
topic: "Grade dos 2 eixos cegos — RODAR-E-OBSERVAR + SERVIR-O-NEGÓCIO (o ratio 77% governança disparou)"
hour: "20:57 BRT"
related_adrs: [0333-emenda-0330-eixo-rodar-e-observar-submedido, 0334-anti-atrofia-servir-negocio, 0330-mapa-dos-niveis-estado-real-2026-07-constituicao]
outcomes:
  - "5 dimensões / 2 eixos graduados (workflow wf_e7ff132f-a11, 49 agentes)"
  - "Achado-mãe: ratio 77% governança-meta / 3,4× (4 semanas) — alarme DISPAROU hoje"
  - "0 acima-de-categoria · 9 diferenciais-de-sistema · o buraco caro é SERVIR-O-NEGÓCIO (3,5)"
---

# Grade 2026-07-17 — RODAR-E-OBSERVAR + SERVIR-O-NEGÓCIO

## TL;DR

Rodei a grade `reguas-do-sistema` exclusiva pros 2 eixos que a grade design→código (mesmo dia)
deixou sem retrato — os pontos cegos das ADR 0333/0334. **O achado mais importante não é uma nota:
é o ratio.** `negocio-vs-governanca-ratio.mjs` rodado agora contra origin/main: **77% governança-meta
/ 3,4× em 4 semanas (ALARME disparado)** — 237 negócio / 807 governança. O eixo forte
(CONSTRUIR-E-GOVERNAR) está forte **a ponto de virar risco**: o sistema serve a própria régua 3,4×
mais que o negócio. E a grade design→código de hoje foi 100% governança — isso É o sinal.

## Placar

- **Acima-de-categoria: 0/9** — toda técnica isolada tem par publicado 2026.
- **À-frente-por-integração: 9/9** — ninguém monta o TODO (ERP vertical multi-tenant BR em prod +
  recursão auto-aplicada + loop que fecha) no mesmo contexto. Diferencial de instanciação, **não de
  categoria** (proibido re-inflar a peça — §5 2026-07-10).

## Notas por dimensão (só com evidência)

| Eixo | Dimensão | Nota | Núcleo do veredito |
|---|---|---|---|
| CONSTRUIR-E-GOVERNAR | (capacidade) | ~8/10 **saúde VERMELHA** | único eixo com alarme no repo; disparou hoje (ratio 77%) — sobre-investido contra o negócio |
| RODAR-E-OBSERVAR | observabilidade-agente | **7,0** | maioria construída (OTLP export + tail-sampling JÁ existiam, invisíveis); buracos reais: guardrail síncrono de alucinação (3), fallback de provider (3), teto por tenant (5) |
| RODAR-E-OBSERVAR | qualidade-drift-ia-producao | **4,5** | a mais fraca; drift-sentinel tautológico (aposentar, US-COPI-143), recall client-facing 0,38, zero rigor estatístico (N=51), online-eval dark (flag LGPD) |
| RODAR-E-OBSERVAR | seguranca-do-agente | **5,5** | buracos: excessive agency (3,5 — `gh pr merge` sem gate, token MCP em claro), rug-pull MCP (4), scan de secret na saída ausente, sandbox SO não cobre o desktop do [W] |
| RODAR-E-OBSERVAR | custo-eficiencia | **4,5** | obs de custo server-side é 8; mas o agent-cost-per-PR commitado é **sintético/demo** (`modelos_desconhecidos:['<synthetic>']`) — a régua que fecharia "economize crédito" ainda não mede de verdade |
| SERVIR-O-NEGÓCIO | inteligencia-de-negocio | **3,5** | **o buraco caro**: Jana burra sobre o negócio da Larissa (recall 0,38), SaleInsightAgent congelado no #1040 (projeto ~#4000), loop cliente→sinal→cycle_goal só ganhou a 1ª casa (/feedback) hoje |

## Já feito (creditado — muita coisa fechou nas últimas horas)

Heartbeat US-COPI-138 (#4425/#4444) · business_id-tag US-COPI-132 (#4145) · online-eval codado
US-COPI-137 (#4460, dark) · piso recall US-COPI-136 (#4412) · invocador schedule US-COPI-140
(#4426) · drift-sentinel honesto + guard US-COPI-143 (#4457) · C7 egress (#4420) + C11 corpus
injection (#4409) · ADR 0334 alarme LIGADO (#4410) · ADR 0333 eixo (#4064) · /feedback (#4413,
hoje) · OTLP export + tail-sampling (ADR 0132/0162, já existiam).

## O que roubar (top-8, impacto÷esforço)

1. **Teto de gasto POR TENANT** (business_id no QuotaEnforcer) — alto÷baixo (motor 80% pronto).
2. **Fechar cliente→cycle_goal + cravar 1 cycle de NEGÓCIO** — alto÷baixo (loop morto há 14 meses).
3. **Unir custo × outcome** (custo-por-PR-ACEITO) — alto÷baixo (2 scripts existem).
4. Ancorar Jana-BI nos fatos do tenant (descongelar SaleInsightAgent) — alto÷médio-alto.
5. Loop produção→golden-set — depende de ligar online-eval.
6. Telemetria OTel nativa do Claude Code (troca parse manual) — fecha custo G3+G5.
7. Drift de condição CONJUNTA (PSI/KL + recall) — **substitui** o sentinel tautológico.
8. Guardrail síncrono de faithfulness **OPT-IN** por rota de alto risco.

## Chips sugeridos (16, ressalva embutida)

OBS: C-OBS-1 teto/tenant · C-OBS-2 span tree · C-OBS-3 fallback provider (US-COPI-135).
QUAL: C-QUAL-1 ligar online-eval (LGPD [W]) · C-QUAL-2 loop prod→golden · C-QUAL-3 drift PSI/KL
(SUBSTITUI sentinel) · C-QUAL-4 guardrail síncrono OPT-IN · C-QUAL-5 rigor estatístico (gold-set →200).
SEG: C-SEG-1 rug-pull MCP · C-SEG-2 excessive agency ([W] permissão) · C-SEG-3 sandbox SO desktop ·
C-SEG-4 scan secret na saída · C-SEG-5 rodar camada C do corpus.
CUSTO: C-CUSTO-1 OTel nativo Claude Code · C-CUSTO-2 custo×outcome.
NEGÓCIO: C-BI-1 ancorar Jana-BI no tenant · C-BI-2 wiring cliente→cycle_goal (SÓ o wiring —
taxonomia-IA já rejeitada) · C-BI-3 digest proativo pro dono.

## Rejeitados → §5 (não re-propor)

Re-inflar peça REFUTADA como "acima" (falácia de composição inversa) · regravar baseline do
drift-sentinel (§5 2026-07-17) · taxonomia-IA de feedback + tabela nova (Non-Goal [W]) · guardrail
síncrono GLOBAL (só opt-in por rota) · 2º detector de drift paralelo ao ragas-real (duplica régua) ·
presence-gate sobre `online_eval.enabled` (flag-off é decisão LGPD, não drift).

## Leitura fria

1. **O eixo forte virou risco** — 77% do fluxo das últimas 4 semanas foi governança-meta (3,4×,
   alarme hoje); o sistema serve a própria régua mais que o negócio.
2. **Zero peças acima-de-categoria** — todo diferencial isolado tem par 2026; só o TODO integrado
   auto-aplicado é raro (9 diferenciais-de-sistema, frágeis feature-a-feature, reais só enquanto a
   instância única em prod fizer dogfooding do próprio agente).
3. **O buraco caro não é observabilidade** (a maioria já existia, invisível) — **é servir o
   negócio**: a Jana continua burra sobre o negócio da Larissa (recall 0,38, SaleInsightAgent
   congelado no #1040) e o loop cliente→sinal só ganhou a 1ª casa hoje.

## Pointer

Grade completa: workflow `wf_e7ff132f-a11` (49 agentes, 7M tokens, fresh worktree de origin/main).
Fonte do ratio: `scripts/governance/negocio-vs-governanca-ratio.mjs`.
