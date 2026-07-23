---
id: research-2026-05-sells-grade-heatmap-01-wr2-grade-usage-anonimizada
---

# Heatmap UI Vendas — `01-wr2` (anonimizado)

> Coletado: 2026-05-11T10:14:30.643520
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

## Q3 · Status estruturado (US-SELL-020 + US-SELL-023)

### Inline VENDA.SITUACAO
- Valores distintos: **5**

| Situacao | Vendas |
|----------|-------:|
| _redacted_8deb_ | 802 |
| _redacted_da39_ | 121 |
| _redacted_6d70_ | 2 |
| _redacted_0dd3_ | 2 |
| _redacted_bcd6_ | 1 |

### Tabela VENDA_SITUACAO (lookup)
- Linhas: **16** · cols: `CODIGO, DESCRICAO, DT_ALTERACAO, ATIVO, CODUSUARIO_ALTERACAO, CODUSUARIO_CADASTRO...`

### Tabela VENDA_ESTAGIO (FSM funil)
- Linhas: **10** · cols: `CODIGO, DESCRICAO, DT_ALTERACAO, ATIVO, CODUSUARIO_ALTERACAO, CODUSUARIO_CADASTRO...`

**Regra de qualificacao revisada (Wagner 2026-05-11):**
- VENDA_ESTAGIO populado (>0 linhas) → cliente USA FSM/funil de venda → US-SELL-023 P1
- VENDA_SITUACAO lookup populado → US-SELL-020 P1 (3 badges separados)
- Nenhum dos dois → status inexistente → US-SELL-020/023 P3

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

## Schema dump · VENDA — colunas relacionadas a UI Grade

Total colunas em VENDA: **381**. Relevantes pra UI Grade (47):

| Coluna | Tipo |
|--------|------|
| `DT_EMISSAO` | TIMESTAMP |
| `DT_FATURAMENTO` | TIMESTAMP |
| `PROJETO_DT_INICIO` | TIMESTAMP |
| `PROJETO_DT_FIM` | TIMESTAMP |
| `STATUS` | VARYING |
| `NF_DT_EMISSAO` | TIMESTAMP |
| `NF_DT_SAIDAENTRADA` | TIMESTAMP |
| `NF_STATUS` | VARYING |
| `EQUIPAMENTO_DT_COMPRA` | TIMESTAMP |
| `DT_ALTERACAO` | TIMESTAMP |
| `SITUACAO` | VARYING |
| `DT_COLETA` | TIMESTAMP |
| `CODCIDADE_ENTREGA` | LONG |
| `BAIRRO_ENTREGA` | VARYING |
| `ENDERECO_ENTREGA` | VARYING |
| `NUMERO_ENTREGA` | VARYING |
| `COMPLEMENTO_ENTREGA` | VARYING |
| `UF_ENTREGA` | VARYING |
| `CEP_ENTREGA` | VARYING |
| `DT_PRE_VENDA` | TIMESTAMP |
| `DT_PODE_FATURAR` | TIMESTAMP |
| `DT_APROVADO_PRODUCAO` | TIMESTAMP |
| `ROMANEIO_STATUS` | VARYING |
| `STATUS_VENDA` | BLOB |
| `SITUACAOFINANCEIRA` | VARYING |
| `DT_COMPETENCIA` | DATE |
| `NFSE_SITUACAO` | VARYING |
| `DT_ORCAMENTO_FINALIZADO` | TIMESTAMP |
| `ENTREGA_NOME` | VARYING |
| `ENTREGA_CEP` | VARYING |
| `ENTREGA_CODPAIS` | LONG |
| `ENTREGA_FONE` | VARYING |
| `ENTREGA_EMAIL` | VARYING |
| `ENTREGA_IE` | VARYING |
| `DT_CREDITO_DISPONIVEL` | TIMESTAMP |
| `ENTREGA_CIDADE` | VARYING |
| `ENTREGA_CNPJCPF` | VARYING |
| `ENTREGA_PAIS` | VARYING |
| `NFSE_SITUACAO_TRIBUTACAO` | VARYING |
| `NF_NUM_LOTE` | VARYING |

Tabelas candidatas a producao: `AGENDA_PRODUCAO, AGENDA_TITULO_WORKFLOW, BALANCO_PRODUTOS, CLIENTES_PRODUTO, COMISSAO_PRODUTO, CONTRATO_PRODUTO, ECF_ATUALIZACAO_PRODUTOS, ESTOQUE_PRODUTO, NF_ENTRADA_PRODUTOS, NF_ENTRADA_PRODUTOS_AFETADOS, NF_ENTRADA_PRODUTOS_COMPOSICAO, NF_ENTRADA_PRODUTOS_CUSTO_AD, NOTA_FISCAL_PRODUTO, PESSOAS_PRODUTO, PRODUCAO, PRODUCAO_ACAO, PRODUCAO_CENTRO_TRABALHO, PRODUCAO_CUSTO_ADICIONAL, PRODUCAO_ESTAGIO, PRODUCAO_ETAPAS, PRODUCAO_MARCADOR, PRODUCAO_MOTIVO, PRODUCAO_MOVIMENTO, PRODUCAO_NAO_LIDO, PRODUCAO_OS, PRODUCAO_PRODUTO, PRODUCAO_ROTEIRO, PRODUCAO_ROTEIRO_ORGANOGRAMA, PRODUCAO_ROTEIRO_PERGUNTA, PRODUCAO_SITUACAO`

## Q7 · Veiculos cadastrados (EQUIPAMENTO_VEICULO — corrigido)

Tabela: `EQUIPAMENTO_VEICULO` · Total veiculos cadastrados: **102**

| Campo | Preenchidos | % |
|-------|------------:|--:|
| `PLACA` | 0 | **0.0%** |
| `PLACA2` | 0 | **0.0%** |
| `CHASSI` | 0 | **0.0%** |
| `CHASSI2` | 0 | **0.0%** |
| `ANO_MODELO` | 0 | **0.0%** |
| `ANO_FABRICACAO` | 0 | **0.0%** |
| `RENAVAN` | 0 | **0.0%** |
| `TIPO` | 0 | **0.0%** |
| `ESPECIE` | 0 | **0.0%** |

**Regra revisada:**
- total_veiculos = 0 → grafica pura → esconde colunas auto na Grade
- total_veiculos > 0 E PLACA > 30% → cliente USA veiculo → mostra colunas PLACA + dependent (PLACA2/CHASSI conforme uso)
- PLACA2 > 10% → cliente trabalha com cavalo+reboque/multiplas placas (Vargas) → mostra PLACA2 tambem

## Q8 · PCP estruturado (US-SELL-023 sinal real)

- `VENDA_PRODUTO_ETAPA`: **0** linhas · **0** vendas distintas com etapa/centro
- `VENDA_PRODUTO_CENTRO_TRABALHO`: **0** linhas · **0** vendas distintas com etapa/centro

**Regra:** linhas > 100 em qualquer das duas tabelas → cliente USA PCP estruturado → US-SELL-023 P1

## Q9 · VENDA_OBRA (relevante pra Modules/ComunicacaoVisual)

- VENDA_OBRA nao existe ou erro

**Regra:** vendas_com_obra > 0 → cliente tem instalacao fisica (gestao de obra) → relevante pra Modules/ComunicacaoVisual; possivel coluna 'Obra' na Grade Avancada

---

## Resumo decisional preliminar (sera consolidado em HEATMAP-CONSOLIDADO.md apos 3 clientes)

| US | Status inicial | Sinal deste cliente |
|----|----------------|---------------------|
| US-SELL-018 (filtros multi-data) | a definir | Q2 mostra 3/5 campos de data com uso >30% |
| US-SELL-019 (agrupamento) | a definir | Q4 pct agrupamento: VF=-% / FIN=34.5% |
| US-SELL-020 (status badges) | a definir | Q3 distinct: 0 → unico |
| US-SELL-021 (qual data) | a definir | Q2 mesmo sinal de 018 |
| US-SELL-022 (sub-linha produtos) | a definir | Q5 media itens/venda: 1.3 |
| US-SELL-024 (is_grouped explicito) | a definir | Q4 mesmo sinal de 019 |

> Final decisional depende de cruzar com mais 2 clientes (Vargas/Extreme/Gold) — single sample = ruido.