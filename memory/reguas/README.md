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

**Regras duras:**
- Nota histórica NUNCA é editada — retrato novo entra no TOPO de `retratos.json` (append-only na prática).
- Claim `ACIMA_CONFIRMADO` tem TTL curto (30d) — expira e re-refuta; EMPATADO/REFUTADO 90d. Vencida ≠ apagada: re-veredito atualiza `data_veredito`.
- `correcao_obrigatoria` viaja COM a claim — quem citar a claim cita a correção (lápide claims-com-data-e-fonte, §5 2026-07-09).
- Fila de indexação: `node scripts/governance/reguas-indexar.mjs` (report-only) lista os `existia_invisivel` pendentes — o chip mais barato de cada rodada.
- ⛔ Anti-Goodhart (errata 0159): estes números apontam ONDE trabalhar; **nota nunca é alvo**. As metas "nível 9,75" da ADR são da MÁQUINA (custo, fidelidade, lead-time), não das dimensões.
