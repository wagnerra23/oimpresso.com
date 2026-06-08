# Heatmap UI Vendas — `04-gold` (anonimizado)

> Coletado: 2026-05-11T10:14:54.330187
> Banco: `192.168.0.55:D:\DadosClientes\Gold\Dados\BANCO.FDB` · Schema fingerprint: OK
> Refs: [ADR 0136](../../decisions/0136-sells-grade-avancada-modo-toggle.md) · [Sells/SPEC.md](../../requisitos/Sells/SPEC.md) US-SELL-015..026

## Q1 · Volume de vendas 24 meses

- Total: **8,176** vendas em **23** meses
- Media: **355.5** vendas/mes

| Ano | Mes | Vendas |
|----:|----:|-------:|
| 2025 | 04 | 291 |
| 2025 | 05 | 324 |
| 2025 | 06 | 328 |
| 2025 | 07 | 384 |
| 2025 | 08 | 439 |
| 2025 | 09 | 479 |
| 2025 | 10 | 481 |
| 2025 | 11 | 478 |
| 2025 | 12 | 372 |
| 2026 | 01 | 494 |
| 2026 | 02 | 435 |
| 2026 | 03 | 218 |

**Implicacao:** se total_24m > 5000 → paginacao Inertia 25/pag eh suficiente; se > 50k → precisa virtual scroll (libs tipo @tanstack/react-virtual).

## Q2 · Campos de data preenchidos (US-SELL-018 + US-SELL-021)

Total vendas: 55,715

| Campo | Preenchidas | % |
|-------|-----------:|--:|
| `DT_EMISSAO` | 55,715 | **100.0%** |
| `DT_FATURAMENTO` | 51,494 | **92.4%** |
| `DT_COMPETENCIA` | 4,198 | **7.5%** |
| `DT_PROMETIDO` | 47,455 | **85.2%** |
| `DT_ENVIO_FATURAMENTO` | 3,614 | **6.5%** |
| `DT_ALTERACAO` | 55,714 | **100.0%** |

**Regra de qualificacao:**
- Campo >70% preenchido → cliente USA → mantem US-SELL-018/021 P1
- Campo 30-70% → considerar como opcional na UI
- Campo <30% → rebaixar pra P3 ou esconder por default na Grade

## Q3 · Status estruturado (US-SELL-020 + US-SELL-023)

### Inline VENDA.SITUACAO
- Valores distintos: **7**

| Situacao | Vendas |
|----------|-------:|
| _redacted_32d7_ | 29,559 |
| _redacted_68aa_ | 7,082 |
| _redacted_da39_ | 89 |
| _redacted_98b9_ | 72 |
| _redacted_4682_ | 72 |
| _redacted_31d8_ | 16 |
| _redacted_f028_ | 4 |

### Tabela VENDA_SITUACAO (lookup)
- Linhas: **5** · cols: `CODIGO, DESCRICAO, DT_ALTERACAO, ATIVO, TEM_FATURA`

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
- Total linhas: 136,754
- Com agrupamento: 72,671 (53.1%)
- Grupos distintos: 57,142
- Tamanho medio do grupo: **1.27** linhas

**Regra de qualificacao:**
- pct > 30% E avg_size > 2 → cliente USA agrupamento → US-SELL-019 P1 + US-SELL-024 P2
- pct < 10% → praticamente nao usa → US-SELL-019 vira P3, US-SELL-024 cancela

## Q5 · Itens por venda (US-SELL-022 sub-linha produtos)

- Tabela: `VENDA_PRODUTO` FK `CODVENDA`
- Total itens: 83,477
- Vendas com itens: 52,789
- **Media: 1.58 itens/venda**

| Faixa | Vendas |
|-------|-------:|
| 1 item | 37,619 |
| 2 5 | 14,041 |
| 6 10 | 897 |
| 11 plus | 232 |

**Regra de qualificacao:**
- media > 3 itens/venda OU pct(11+) > 15% → sub-linha vale → US-SELL-022 P2
- media = 1 → toda venda tem 1 item so → sub-linha eh ruido → US-SELL-022 cancela

## Q6 · Range temporal (US-SELL-018 presets Dia/Semana/Mes/Ano)

- Campo: `DT_EMISSAO`
- Periodo: 2015-04-06 14:51:14 → 2026-03-16 12:10:22

| Janela | Vendas |
|--------|-------:|
| ultimos 7d | 0 |
| ultimos 30d | 0 |
| ultimos 90d | 508 |
| ultimos 365d | 4,343 |

**Regra de qualificacao:**
- Se ultimos_30d > ultimos_7d * 3 → cliente trabalha em ciclo mensal → preset Mes prioritario
- Se ultimos_365d > ultimos_30d * 11 → cliente olha historicos longos → preset Ano importante

## Schema dump · VENDA — colunas relacionadas a UI Grade

Total colunas em VENDA: **385**. Relevantes pra UI Grade (45):

| Coluna | Tipo |
|--------|------|
| `DT_EMISSAO` | TIMESTAMP |
| `DT_FATURAMENTO` | TIMESTAMP |
| `DT_ENTRADA` | TIMESTAMP |
| `DT_PROMETIDO` | TIMESTAMP |
| `STATUS` | VARYING |
| `NF_DT_EMISSAO` | TIMESTAMP |
| `NF_DT_SAIDAENTRADA` | TIMESTAMP |
| `NF_STATUS` | VARYING |
| `EQUIPAMENTO_DT_COMPRA` | TIMESTAMP |
| `DT_ALTERACAO` | TIMESTAMP |
| `SITUACAO` | VARYING |
| `DT_SITUACAO` | TIMESTAMP |
| `DT_COLETA` | TIMESTAMP |
| `CODCIDADE_ENTREGA` | LONG |
| `BAIRRO_ENTREGA` | VARYING |
| `ENDERECO_ENTREGA` | VARYING |
| `NUMERO_ENTREGA` | VARYING |
| `COMPLEMENTO_ENTREGA` | VARYING |
| `UF_ENTREGA` | VARYING |
| `CEP_ENTREGA` | VARYING |
| `DATA_EVENTO` | TIMESTAMP |
| `PROJETO_DT_INICIO` | TIMESTAMP |
| `PROJETO_DT_FIM` | TIMESTAMP |
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

Tabelas candidatas a producao: `AGENDA_TITULO_WORKFLOW, BALANCO_PRODUTO, BALANCO_PRODUTOS, CLIENTES_PRODUTO, COMISSAO_PRODUTO, CONTRATO_PRODUTO, ECF_ATUALIZACAO_PRODUTOS, NF_ENTRADA_PRODUTOS, NF_ENTRADA_PRODUTOS_AFETADOS, NF_ENTRADA_PRODUTOS_COMPOSICAO, NF_ENTRADA_PRODUTOS_CUSTO_AD, NOTA_FISCAL_PRODUTO, PESSOAS_PRODUTO, PROCESSOS_ETAPAS, PROCESSOS_ETAPAS_PERMISSOES, PROCESSOS_ETAPAS_REGRAS, PRODUCAO, PRODUCAO_ACAO, PRODUCAO_ANEXO, PRODUCAO_CENTRO_TRABALHO, PRODUCAO_CUSTO_ADICIONAL, PRODUCAO_ESTAGIO, PRODUCAO_ETAPAS, PRODUCAO_FUNCIONARIO, PRODUCAO_MARCADOR, PRODUCAO_MATERIAL, PRODUCAO_MOTIVO, PRODUCAO_MOVIMENTO, PRODUCAO_NAO_LIDO, PRODUCAO_OS`

## Q7 · Veiculos cadastrados (EQUIPAMENTO_VEICULO — corrigido)

Tabela: `EQUIPAMENTO_VEICULO` · Total veiculos cadastrados: **0**

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
| US-SELL-018 (filtros multi-data) | a definir | Q2 mostra 4/6 campos de data com uso >30% |
| US-SELL-019 (agrupamento) | a definir | Q4 pct agrupamento: VF=-% / FIN=53.1% |
| US-SELL-020 (status badges) | a definir | Q3 distinct: 0 → unico |
| US-SELL-021 (qual data) | a definir | Q2 mesmo sinal de 018 |
| US-SELL-022 (sub-linha produtos) | a definir | Q5 media itens/venda: 1.58 |
| US-SELL-024 (is_grouped explicito) | a definir | Q4 mesmo sinal de 019 |

> Final decisional depende de cruzar com mais 2 clientes (Vargas/Extreme/Gold) — single sample = ruido.