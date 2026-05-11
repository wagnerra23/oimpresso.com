# Heatmap UI Vendas — `01-wr2` (anonimizado)

> Coletado: 2026-05-11T09:47:53.286274
> Banco: `192.168.0.55:Banco` · Schema fingerprint: OK
> Refs: [ADR 0136](../../decisions/0136-sells-grade-avancada-modo-toggle.md) · [Sells/SPEC.md](../../requisitos/Sells/SPEC.md) US-SELL-015..026

## Q1 · Volume de vendas 24 meses

- Total: **180** vendas em **20** meses
- Media: **9.0** vendas/mes

| Ano | Mes | Vendas |
|----:|----:|-------:|
| 2025 | 02 | 1 |
| 2025 | 03 | 4 |
| 2025 | 04 | 3 |
| 2025 | 05 | 1 |
| 2025 | 06 | 2 |
| 2025 | 08 | 1 |
| 2025 | 10 | 1 |
| 2025 | 11 | 1 |
| 2026 | 01 | 27 |
| 2026 | 02 | 1 |
| 2026 | 03 | 2 |
| 2026 | 04 | 3 |

**Implicacao:** se total_24m > 5000 → paginacao Inertia 25/pag eh suficiente; se > 50k → precisa virtual scroll (libs tipo @tanstack/react-virtual).

## Q2 · Campos de data preenchidos (US-SELL-018 + US-SELL-021)

Total vendas: 1,866

| Campo | Preenchidas | % |
|-------|-----------:|--:|
| `DT_EMISSAO` | 1,864 | **99.9%** |
| `DT_FATURAMENTO` | 983 | **52.7%** |
| `DT_COMPETENCIA` | 348 | **18.6%** |
| `DT_ENVIO_FATURAMENTO` | 59 | **3.2%** |
| `DT_ALTERACAO` | 1,722 | **92.3%** |

**Regra de qualificacao:**
- Campo >70% preenchido → cliente USA → mantem US-SELL-018/021 P1
- Campo 30-70% → considerar como opcional na UI
- Campo <30% → rebaixar pra P3 ou esconder por default na Grade

## Q3 · SITUACAO/Status distincts (US-SELL-020 + US-SELL-023)

- Campo encontrado: `SITUACAO`
- Valores distintos: **5**

| Situacao | Vendas |
|----------|-------:|
| _situacao_redacted_8deb_ | 802 |
| _situacao_redacted_da39_ | 121 |
| _situacao_redacted_6d70_ | 2 |
| _situacao_redacted_0dd3_ | 2 |
| _situacao_redacted_bcd6_ | 1 |

**Regra de qualificacao:**
- distinct > 5 → ha sub-status estruturados → US-SELL-020 (3 badges separados) faz sentido
- distinct ≤ 3 → simples → 1 badge unico, US-SELL-020 vira P3

## Q4 · Agrupamento implicito (US-SELL-019 + US-SELL-024)

- `VENDA_FINANCEIRO`: campo de agrupamento NAO encontrado
### FINANCEIRO
- Campo agrupador: `CODFINANCEIRO_GRUPO`
- Total linhas: 59,186
- Com agrupamento: 20,392 (34.5%)
- Grupos distintos: 8,779
- Tamanho medio do grupo: **2.32** linhas

**Regra de qualificacao:**
- pct > 30% E avg_size > 2 → cliente USA agrupamento → US-SELL-019 P1 + US-SELL-024 P2
- pct < 10% → praticamente nao usa → US-SELL-019 vira P3, US-SELL-024 cancela

## Q5 · Itens por venda (US-SELL-022 sub-linha produtos)

- Tabela: `VENDA_PRODUTO` FK `CODVENDA`
- Total itens: 2,171
- Vendas com itens: 1,664
- **Media: 1.3 itens/venda**

| Faixa | Vendas |
|-------|-------:|
| 1 item | 1,535 |
| 2 5 | 114 |
| 6 10 | 10 |
| 11 plus | 5 |

**Regra de qualificacao:**
- media > 3 itens/venda OU pct(11+) > 15% → sub-linha vale → US-SELL-022 P2
- media = 1 → toda venda tem 1 item so → sub-linha eh ruido → US-SELL-022 cancela

## Q6 · Range temporal (US-SELL-018 presets Dia/Semana/Mes/Ano)

- Campo: `DT_EMISSAO`
- Periodo: 2007-11-20 00:00:00 → 2026-04-27 09:53:18

| Janela | Vendas |
|--------|-------:|
| ultimos 7d | 0 |
| ultimos 30d | 3 |
| ultimos 90d | 5 |
| ultimos 365d | 39 |

**Regra de qualificacao:**
- Se ultimos_30d > ultimos_7d * 3 → cliente trabalha em ciclo mensal → preset Mes prioritario
- Se ultimos_365d > ultimos_30d * 11 → cliente olha historicos longos → preset Ano importante

## Q7 · Campos automotivos (decisao Grade hide-by-default)

| Campo | Preenchidos | % |
|-------|------------:|--:|
| `PLACA` | 0 | 0.0% |
| `MARCAMODELO` | 0 | 0.0% |
| `ANO` | 0 | 0.0% |

**Regra:** se TODOS os campos automotivos < 5% → grafica → Grade Avancada esconde colunas Placa/Chassi por default (mostra so se `vertical='oficina'`).

---

## Resumo decisional preliminar (sera consolidado em HEATMAP-CONSOLIDADO.md apos 3 clientes)

| US | Status inicial | Sinal deste cliente |
|----|----------------|---------------------|
| US-SELL-018 (filtros multi-data) | a definir | Q2 mostra 3/5 campos de data com uso >30% |
| US-SELL-019 (agrupamento) | a definir | Q4 pct agrupamento: VF=-% / FIN=34.5% |
| US-SELL-020 (status badges) | a definir | Q3 distinct: 5 → unico |
| US-SELL-021 (qual data) | a definir | Q2 mesmo sinal de 018 |
| US-SELL-022 (sub-linha produtos) | a definir | Q5 media itens/venda: 1.3 |
| US-SELL-024 (is_grouped explicito) | a definir | Q4 mesmo sinal de 019 |

> Final decisional depende de cruzar com mais 2 clientes (Vargas/Extreme/Gold) — single sample = ruido.