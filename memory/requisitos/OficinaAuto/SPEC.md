---
module: OficinaAuto
status: backlog_feature_wish (aguarda sinal qualificado)
piloto: Martinho Caçambas (a confirmar — pode ser pesado/frota fora do ICP SMB; revalidar)
piloto_previsao: indeterminada — depende de cliente real pagante
---

# SPEC — Modules/OficinaAuto

> **Status governança:** ADR 0105 (cliente como sinal qualificado) — backlog feature-wish até 1+ piloto pagante. NÃO ativar US sem cliente real reportando dor.

## Posicionamento

ERP vertical especializado em **mecânica geral + centro automotivo + especialista** SMB (CNAE 4520-0/01) — segmento com **133-150k estabelecimentos formais BR**, 5-10x maior que gráficas em volume.

ICP-faixa: 5-50 funcionários · 1-3 elevadores · R$ [redacted Tier 0]k-500k/m faturamento.

## Universo mapeado

Pesquisa de mercado completa em [memory/research/2026-05-prospeccao-auto/](../../research/2026-05-prospeccao-auto/):
- 01 — mercado oficinas auto BR (desk research, dimensionamento)
- 02 — concorrentes ERP auto BR (Mecânico/Auto Manager/Lokoz)
- 03 — pricing ERPs auto BR
- 04+ — prospecção UFs (mapeamento Tier 1+2+3 por estado)

Índice consolidado quando todas UFs voltarem: [00-INDEX-UFS.md](../../research/2026-05-prospeccao-auto/00-INDEX-UFS.md).

## User Stories

> **Vazia até sinal qualificado.** Quando piloto reportar dor concreta (ex: "preciso integrar Audatex pra fluxo seguradora", "quero rastreabilidade de OS multi-dia em campo"), apender US-AUTO-001+ aqui.

## Refs

- [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) — modular especializado por vertical
- [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal qualificado
- [ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md) — Jira-style tasks
