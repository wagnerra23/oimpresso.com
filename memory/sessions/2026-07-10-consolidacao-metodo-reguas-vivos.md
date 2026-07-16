# 2026-07-10 — Método `reguas-do-sistema` + reanálise da grade (ponto cego RODAR-E-OBSERVAR) + consolidação

> Session log (narrativa). Estado vivo = tools MCP. Handoff-par: `2026-07-10-0821-consolidacao-metodo-reguas.md`.
> Continuidade direta da sessão 2026-07-09 (grade das réguas + doutrina 0329 + mapa 0330).

## Arco da sessão

Wagner pediu, em sequência: (1) **reaplicar a grade das réguas como chips** noutra sessão; (2) **"qual o outro que falta"** → o critic; (3) **"crie o método de como pesquisar e documentar as réguas, pra deixar o sistema sempre arrumado"**; (4) **"reanalise a grade, faltou o quê?"**; (5) **"salvar tudo"**.

Fio condutor: transformar o ritual ad-hoc (medir o IA OS contra o mercado) em **método canônico executável** — e descobrir que a própria grade tinha um **ponto cego estrutural**.

## O que virou canon / código

### 1. Método `reguas-do-sistema` (PR #4050 — MERGED)
Skill Tier B + `.claude/workflows/reguas-do-sistema.js` (padrão `sdd-avaliar`). **Irmão do `capterra-senior` apontado pro PROCESSO/IA OS**. 5 fases: Dossiê (do mapa-dos-níveis VIVO, nunca de memória) → Pesquisar (1 web-researcher/dimensão) → Refutar (cético contexto-zero) → **Verificar (fraqueza caçada no REPO antes da nota — a lição 7/9 virou regra)** → Grade (nota só com evidência + chips + rejeitados→§5). 6 regras duras + anti-padrões (Goodhart/0159).

Ciclo do "sempre arrumado": MEDIR (reguas) -> VERIFICAR no repo -> CORRIGIR (chips) -> TRAVAR (sentinela) -> OPERAR -> índices APONTAM -> re-MEDIR. Descoberta: TRAVAR e APONTAR já são máquina contínua; faltava canonizar só o MEDIR.

### 2. Reanálise da grade — o ponto cego
As 9 linhas (v1→v3) mediram só o loop CONSTRUIR-E-GOVERNAR. A grade nunca pôs numa régua o loop RODAR-E-OBSERVAR a IA que o sistema produz (Jana em produção). 4 dimensões ausentes: **observabilidade do agente** (Langfuse/OTel — #4 P0 pendente), **qualidade+drift da IA** (RAGAS/canary — #3 P0 "próximo pendente"; a grade mediu DORA do agente-dev, não a Jana), **segurança do agente** (OWASP LLM Top 10), **custo/eficiência de token**. Leitura: a grade avaliou a fábrica, não o produto.

## Chips despachados (rodam em sessões próprias)

Grade v3 (9 fraquezas): 8 chipadas + o critic. Reanálise: +2 (ponto cego, segurança). Aterrissaram no main durante a sessão:

| Item | PR |
|---|---|
| DORA + flag no Daily Brief | #4053 |
| Goal 109 assinaturas -> rb:backfill-gateway (dry-run) | #4045 |
| map.json Fase 4 consome + sha por conteúdo | #4048 |
| Estado "servido" (hits + lints consomem ledger) | #4043 + #4051 |
| RUNBOOKs Jana frescor V2 | #4047 |
| Spec-feature Kiro-style (feature-trio) | #4044 |
| Método reguas-do-sistema | #4050 |
| Ratificações (0314, 0299, 0319, +16 lei-viva, 0332) | #4039/#4040/#4054/#4055/#4059 |
| Hooks .ps1->.mjs (memory-drift, pii-redactor, block-destructive) | #4046/#4049/#4052 |
| Critic lentes-diversas + medidor de precisão | #4058 (aberto) |

Ainda em voo ao fechar: pr-critic upgrade, +4 dimensões no método, auditoria de segurança do agente, proto-baselines (as 3 finalizaram na cauda da sessão — status real via `gh pr list`/`gh pr view` no fechamento seguinte).

## Lições

- **A grade precisa de completeness-critic.** Um instrumento de medição pode ter ponto cego de dimensão inteira (a régua da fábrica não vê o produto).
- **"Sempre arrumado" = ciclo, não faxina.** Documentar réguas é a fase MEDIR; sozinha não arruma.
- **Índice vivo > doc estático.** Consolidação apontou pros índices GERADOS existentes (adr-index, skills-index, doc-freshness, adr-proposto-parado) em vez de criar mais um .md que apodrece.
- **Outage do classificador de segurança (Opus) bloqueou Bash/Write por ~15min** durante o fechamento — sessão trocou pra Sonnet 5 e o outage se resolveu (correlação, não causa provada). Nenhum dado perdido: o trabalho de código já estava salvo pelos PRs dos chips; só a narrativa de fechamento atrasou.
