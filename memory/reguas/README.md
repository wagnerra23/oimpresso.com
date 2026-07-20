# memory/reguas/ — ledger persistente da máquina de réguas em looping

> Estado versionado do ciclo MEDIR (skill `reguas-do-sistema`). Nasceu em 2026-07-19 por ordem [W]
> ("mecanismo mais eficiente, organizado e em looping"); arquitetura na
> [ADR proposta reguas-loop](../decisions/proposals/reguas-loop-maquina-evolucao.md).
> Fecha a pendência da regra 12 da skill (*"notas precisam de artefato versionado no repo"*).

| Arquivo | O que guarda | Quem escreve |
|---|---|---|
| `config.json` | TTLs + `paths_por_dimensao` (o mapa dimensão→código do modo delta) | humano/PR |
| `retratos.json` | série temporal de notas por dimensão, com proveniência e regra de composição DECLARADAS por retrato | fase Persistir do workflow (via PR) |
| `claims.json` | claims de superioridade com **ID persistente**, veredito, peer, TTL, correção obrigatória | fase Persistir (via PR) |
| `fraquezas.json` | fraquezas com nota/evidência/degrau + flag `existia_invisivel` + `onde_indexar` | fase Persistir (via PR) |
| `cross-model/` | verdicts de um 2º modelo (não-Opus) + relatório do controle-negativo (ver §Cross-model) | passe cross-model (sob demanda) |

## Cross-model — oráculo institucional contra agreement-bias (fraqueza "same-model" 5,0)

O refutador da grade (`.claude/workflows/reguas-do-sistema.js` fase Refutar), o adversário e o
ultrareview rodam **Opus×Opus by-design** — um modelo tende a **concordar consigo mesmo**. A régua
de mercado é o **Amp Oracle** (2º modelo, cross-vendor, ataca o que o 1º deixaria passar). Antes,
o cross-model só acontecia ad-hoc (Codex #4009). O oráculo institucional é
[`scripts/governance/reguas-cross-model.mjs`](../../scripts/governance/reguas-cross-model.mjs):
re-ataca **BLIND** (contexto-zero, sem ver o veredito/peer do Opus) as claims que o Opus **manteve**
(ACIMA/EMPATADO) e **diffa contra este ledger** — o **controle negativo** (mesmo lote Opus-only vs
+2º-modelo). Três fontes do 2º modelo, MESMO classificador: (A) HTTP cross-vendor (`OPENAI_API_KEY`);
(B) `--verdicts <f.json>` (Codex/GPT/um Claude não-Opus via Agent); (C) `--dry`.

- **É técnica de PROCESSO, NÃO gate de CI** — nunca avermelha PR (só a lógica pura tem selftest advisory).
- `DIVERGE_DERRUBA` = o 2º modelo derrubou o que o Opus manteve → **vai pro humano, nunca auto-aplica**.
- 1ª rodada (2026-07-19, `claude-sonnet-5` web-blind): **5/8 claims mantidas foram derrubadas** (as 2
  `ACIMA_CONFIRMADO` incluídas) — evidência em `cross-model/relatorio.md`. NÃO reverte o ledger sozinho;
  cada `DIVERGE_DERRUBA` é insumo pra [W] decidir re-refutar/rebaixar na próxima rodada.

**Regras duras:**
- Nota histórica NUNCA é editada — retrato novo entra no TOPO de `retratos.json` (append-only na prática).
- Claim `ACIMA_CONFIRMADO` tem TTL curto (30d) — expira e re-refuta; EMPATADO/REFUTADO 90d. Vencida ≠ apagada: re-veredito atualiza `data_veredito`.
- `correcao_obrigatoria` viaja COM a claim — quem citar a claim cita a correção (lápide claims-com-data-e-fonte, §5 2026-07-09).
- Fila de indexação: `node scripts/governance/reguas-indexar.mjs` (report-only) lista os `existia_invisivel` pendentes — o chip mais barato de cada rodada.
- ⛔ Anti-Goodhart (errata 0159): estes números apontam ONDE trabalhar; **nota nunca é alvo**. As metas "nível 9,75" da ADR são da MÁQUINA (custo, fidelidade, lead-time), não das dimensões.
