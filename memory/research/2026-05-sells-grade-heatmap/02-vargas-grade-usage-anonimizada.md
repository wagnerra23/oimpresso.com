---
id: research-2026-05-sells-grade-heatmap-02-vargas-grade-usage-anonimizada
---

# Heatmap UI Vendas — `02-vargas` (anonimizado)

> Coletado: 2026-05-11T10:14:32.398926
> Banco: `192.168.0.55:D:\DadosClientes\Vargas\Dados\BANCO.FDB` · Schema fingerprint: OK
> Refs: [ADR 0136](../../decisions/0136-sells-grade-avancada-modo-toggle.md) · [Sells/SPEC.md](../../requisitos/Sells/SPEC.md) US-SELL-015..026

## Q1 · Volume de vendas 24 meses

- Total: **3,979** vendas em **22** meses
- Media: **180.9** vendas/mes

| Ano | Mes | Vendas |
|----:|----:|-------:|
| 2025 | 04 | 287 |
| 2025 | 05 | 282 |
| 2025 | 06 | 201 |
| 2025 | 07 | 204 |
| 2025 | 08 | 236 |
| 2025 | 09 | 271 |
| 2025 | 10 | 325 |
| 2025 | 11 | 252 |
| 2025 | 12 | 152 |
| 2026 | 01 | 292 |
| 2026 | 02 | 218 |
| 2026 | 03 | 115 |

**Implicacao:** se total_24m > 5000 → paginacao Inertia 25/pag eh suficiente; se > 50k → precisa virtual scroll (libs tipo @tanstack/react-virtual).

## Q2 · Campos de data preenchidos (US-SELL-018 + US-SELL-021)

Total vendas: 3,981

| Campo | Preenchidas | % |
|-------|-----------:|--:|
| `DT_EMISSAO` | 3,981 | **100.0%** |
| `DT_FATURAMENTO` | 1,392 | **35.0%** |
| `DT_COMPETENCIA` | 3,981 | **100.0%** |
| `DT_ENVIO_FATURAMENTO` | 1,888 | **47.4%** |
| `DT_ALTERACAO` | 3,976 | **99.9%** |

**Regra de qualificacao:**
- Campo >70% preenchido → cliente USA → mantem US-SELL-018/021 P1
- Campo 30-70% → considerar como opcional na UI
- Campo <30% → rebaixar pra P3 ou esconder por default na Grade

## Q3 · Status estruturado (US-SELL-020 + US-SELL-023)

### Inline VENDA.SITUACAO
- Valores distintos: **1**

| Situacao | Vendas |
|----------|-------:|
| _redacted_da39_ | 1,929 |

### Tabela VENDA_SITUACAO (lookup)
- Linhas: **0** · cols: `CODIGO, DESCRICAO, DT_ALTERACAO, ATIVO, TEM_FATURA`

### Tabela VENDA_ESTAGIO (FSM funil)
- Linhas: **0** · cols: `CODIGO, DESCRICAO, ICONE, ATIVO, DT_ALTERACAO`

**Regra de qualificacao revisada (Wagner 2026-05-11):**
- VENDA_ESTAGIO populado (>0 linhas) → cliente USA FSM/funil de venda → US-SELL-023 P1
- VENDA_SITUACAO lookup populado → US-SELL-020 P1 (3 badges separados)
- Nenhum dos dois → status inexistente → US-SELL-020/023 P3

## Q4 · Agrupamento implicito (US-SELL-019 + US-SELL-024)

- `VENDA_FINANCEIRO`: campo de agrupamento NAO encontrado
### FINANCEIRO
- Campo agrupador: `CODFINANCEIRO_GRUPO`
- Total linhas: 14,783
- Com agrupamento: 9,625 (65.1%)
- Grupos distintos: 7,131
- Tamanho medio do grupo: **1.35** linhas

**Regra de qualificacao:**
- pct > 30% E avg_size > 2 → cliente USA agrupamento → US-SELL-019 P1 + US-SELL-024 P2
- pct < 10% → praticamente nao usa → US-SELL-019 vira P3, US-SELL-024 cancela

## Q5 · Itens por venda (US-SELL-022 sub-linha produtos)

- Tabela: `VENDA_PRODUTO` FK `CODVENDA`
- Total itens: 11,790
- Vendas com itens: 3,827
- **Media: 3.08 itens/venda**

| Faixa | Vendas |
|-------|-------:|
| 1 item | 1,430 |
| 2 5 | 1,819 |
| 6 10 | 482 |
| 11 plus | 96 |

**Regra de qualificacao:**
- media > 3 itens/venda OU pct(11+) > 15% → sub-linha vale → US-SELL-022 P2
- media = 1 → toda venda tem 1 item so → sub-linha eh ruido → US-SELL-022 cancela

## Q6 · Range temporal (US-SELL-018 presets Dia/Semana/Mes/Ano)

- Campo: `DT_EMISSAO`
- Periodo: 1900-01-02 13:53:36 → 2026-03-13 10:57:18

| Janela | Vendas |
|--------|-------:|
| ultimos 7d | 0 |
| ultimos 30d | 0 |
| ultimos 90d | 270 |
| ultimos 365d | 2,477 |

**Regra de qualificacao:**
- Se ultimos_30d > ultimos_7d * 3 → cliente trabalha em ciclo mensal → preset Mes prioritario
- Se ultimos_365d > ultimos_30d * 11 → cliente olha historicos longos → preset Ano importante

## Schema dump · VENDA — colunas relacionadas a UI Grade

Total colunas em VENDA: **359**. Relevantes pra UI Grade (41):

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
| `DT_COMPETENCIA` | DATE |
| `SITUACAOFINANCEIRA` | VARYING |
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
| `FATURAMENTO_DT_ENVIO` | TIMESTAMP |
| `PROJETO_DT_EMISSAO` | TIMESTAMP |
| `FATURA_PREVISAO` | VARYING |
| `DT_REQUISICAO` | TIMESTAMP |
| `DT_ENVIO_FATURAMENTO` | TIMESTAMP |

Tabelas candidatas a producao: `AGENDA_TITULO_WORKFLOW, BALANCO_PRODUTO, BALANCO_PRODUTOS, CLIENTES_PRODUTO, COMISSAO_PRODUTO, CONTRATO_PRODUTO, ECF_ATUALIZACAO_PRODUTOS, NF_ENTRADA_PRODUTOS, NF_ENTRADA_PRODUTOS_AFETADOS, NF_ENTRADA_PRODUTOS_COMPOSICAO, NF_ENTRADA_PRODUTOS_CUSTO_AD, NOTA_FISCAL_PRODUTO, PESSOAS_PRODUTO, PROCESSOS_ETAPAS, PROCESSOS_ETAPAS_PERMISSOES, PROCESSOS_ETAPAS_REGRAS, PRODUCAO, PRODUCAO_ACAO, PRODUCAO_CENTRO_TRABALHO, PRODUCAO_CUSTO_ADICIONAL, PRODUCAO_ESTAGIO, PRODUCAO_ETAPAS, PRODUCAO_MARCADOR, PRODUCAO_MOTIVO, PRODUCAO_MOVIMENTO, PRODUCAO_NAO_LIDO, PRODUCAO_OS, PRODUCAO_PRODUTO, PRODUCAO_ROTEIRO, PRODUCAO_ROTEIRO_ORGANOGRAMA`

## Q7 · Veiculos cadastrados (EQUIPAMENTO_VEICULO — corrigido)

Tabela: `EQUIPAMENTO_VEICULO` · Total veiculos cadastrados: **1,064**

| Campo | Preenchidos | % |
|-------|------------:|--:|
| `PLACA` | 852 | **80.1%** |
| `PLACA2` | 216 | **20.3%** |
| `CHASSI` | 202 | **19.0%** |
| `CHASSI2` | 88 | **8.3%** |
| `ANO_MODELO` | 0 | **0.0%** |
| `ANO_FABRICACAO` | 0 | **0.0%** |
| `RENAVAN` | 0 | **0.0%** |
| `TIPO` | 106 | **10.0%** |
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
| US-SELL-018 (filtros multi-data) | a definir | Q2 mostra 5/5 campos de data com uso >30% |
| US-SELL-019 (agrupamento) | a definir | Q4 pct agrupamento: VF=-% / FIN=65.1% |
| US-SELL-020 (status badges) | a definir | Q3 distinct: 0 → unico |
| US-SELL-021 (qual data) | a definir | Q2 mesmo sinal de 018 |
| US-SELL-022 (sub-linha produtos) | a definir | Q5 media itens/venda: 3.08 |
| US-SELL-024 (is_grouped explicito) | a definir | Q4 mesmo sinal de 019 |

> Final decisional depende de cruzar com mais 2 clientes (Vargas/Extreme/Gold) — single sample = ruido.