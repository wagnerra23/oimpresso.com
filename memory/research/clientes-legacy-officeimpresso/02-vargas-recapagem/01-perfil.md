---
id: research-clientes-legacy-officeimpresso-02-vargas-recapagem-01-perfil
slug: 02-vargas-recapagem
hash_id: Cliente_874398
status: qualificado
date_first_analysis: 2026-05-11
date_last_update: 2026-05-11
controlador: Wagner
vertical_real: oficina-recapagem-caminhao
size: grande
tipo_relacao: cliente-pagante-saudavel
banco_firebird: D:\DadosClientes\Vargas\Dados\BANCO.FDB
banco_alias_firebird: 192.168.0.55:D:\DadosClientes\Vargas\Dados\BANCO.FDB
---

# Perfil — `Cliente_874398` (oficina de recapagem de caçamba de caminhão)

> **Correção 2026-05-11:** Wagner apontou que **NÃO é gráfica + frota** como inferi inicialmente — é **empresa GRANDE de recapagem de caçamba de caminhão**. Os 1.064 veículos cadastrados são **as caçambas/caminhões dos clientes** (consumidores finais que levam caminhão pra recapagem). PLACA2/CHASSI2 confirmam — cavalo+reboque tem 2 placas e 2 chassis.

## 1. Identificação

| Campo | Valor |
|-------|-------|
| Hash ID | `Cliente_874398` |
| Vertical real | **Oficina de recapagem de caçamba de caminhão** |
| Porte | grande (181 vendas/mês — alto pra oficina) |
| Cidade / UF | a confirmar com Wagner |
| Status comercial | cliente-pagante saudável |

## 2. Tipo de negócio real

Empresa **grande** de recapagem de caçamba de caminhão. Cliente típico: transportadora ou frotista que precisa recolocar pneus/bandas em caminhões e carretas. OS típica envolve cavalo + reboque (semi-reboque) → portanto **2 placas (cavalo + reboque)** e às vezes 2 chassis registrados.

**Operação:**
- Cadastro do equipamento (caminhão/caçamba) → tabela `EQUIPAMENTO_VEICULO`
- Cliente leva pra recapagem → abre OS na `VENDA` linkada ao equipamento via `PESSOA_CLIENTE_CODIGO` (cliente é dono dos múltiplos veículos)
- Múltiplas peças/serviços por OS (média 3.08 itens/venda → bandas + tubos + serviço de aplicação)
- Sem PCP estruturado (zero `VENDA_PRODUTO_CENTRO_TRABALHO`) — fluxo operacional simples (manda pra produção → entrega)

## 3. Sinais Firebird

| Dimensão | Valor | Comentário |
|----------|------:|------------|
| Vendas 24m | 3.979 (181/mês) | volume grande pra oficina |
| Total vendas (histórico) | 3.981 | cliente recente no Delphi (~2 anos) |
| Range temporal | 1900–2026 | datas antigas = lixo de migração? |
| DT_EMISSAO | 100% | sempre |
| **DT_COMPETENCIA** | **100%** | atípico — outros clientes só 7-75%. Oficina precisa lançar competência pra fluxo fiscal? |
| DT_ENVIO_FATURAMENTO | 47.4% | uso médio |
| DT_PROMETIDO | (ausente) | **não tem coluna no banco** |
| CODFINANCEIRO_GRUPO | **65.1%** | **uso alto** — agrupamento de OS por cliente/contrato é comum |
| **Itens/venda média** | **3.08** | 47% das OS têm 2-5 itens; 15% têm 6+ |
| VENDA_SITUACAO inline | 1 (vazio) | **não usa status** |
| VENDA_SITUACAO lookup | 0 linhas | — |
| VENDA_ESTAGIO | 0 linhas | sem FSM |
| PCP centro_trabalho | 0 linhas | sem PCP estruturado |
| **EQUIPAMENTO_VEICULO total** | **1.064 caminhões cadastrados** | — |
| **PLACA** | **80.1%** (852 veículos) | — |
| **PLACA2** | **20.3%** (216) | cavalo + reboque → 2ª placa |
| **CHASSI** | **19.0%** (202) | só registrado quando OS requer |
| **CHASSI2** | **8.3%** (88) | reboque com chassi próprio |
| TIPO | 10.0% | tipo do veículo classificado |

## 4. Módulos OfficeImpresso usados

| Módulo | Uso real | Migração necessária? |
|--------|----------|----------------------|
| Vendas/OS (`VENDA`) | sim — 181/mês | **sim — crítico** |
| Itens da OS (`VENDA_PRODUTO`) | sim — média 3 itens | **sim** |
| Financeiro (`FINANCEIRO`) | sim — 9.1k linhas | **sim** |
| Agrupamento OS (`CODFINANCEIRO_GRUPO`) | 65% | **sim — UI Grade Avançada precisa** |
| Cadastro veículos (`EQUIPAMENTO_VEICULO`) | sim — 1.064 caminhões | **sim — feature oficina** |
| Status produção | não usa | dispensável |
| PCP estruturado | não usa | dispensável |

## 5. Saúde financeira

Pendente — rodar skill `officeimpresso-financial-snapshot --slug 02-vargas` em sessão futura.

## 6. Sinal qualificado pra migração

- [ ] Reclamou explicitamente? — desconhecido (Wagner sabe)
- [ ] Pediu feature nova? — desconhecido
- [x] **Volume alto** (181/mês × 3 itens = 540 lançamentos de produto/mês) — operação relevante
- [x] **Cliente saudável** — sem flag de inadimplência detectada
- [ ] Pediu demo oimpresso? — desconhecido

**Status pra ADR 0105:** sinal **médio** — operação grande, mas sem reclamação/pedido explícito. Não migrar agressivo; aguardar sinal forte.

## 7. Decisor + janela

Anonimizado aqui — detalhes em [01-perfil-COM-NOMES.md](01-perfil-COM-NOMES.md) (gitignored).

## 8. Plano de migração preliminar

- **Quando**: pós-Modules/OficinaAuto estar pronto (atualmente em backlog ADR feature-wish)
- **Custom necessário**:
  - Cadastro caminhão com **2 placas + 2 chassis** (cavalo+reboque) — não é caso padrão de oficina auto
  - OS multi-item (média 3 peças/serviços por entrada)
  - Sem necessidade de PCP / status — fluxo simples
- **Risco principal**: schema multi-placa não é padrão UltimatePOS; precisa custom em `Modules/OficinaAuto/Models/Veiculo`

## 9. Decisões ADR locais

| ADR | Decisão | Status |
|-----|---------|--------|
| (futuro) | Veículo multi-placa (cavalo+reboque) — schema com `placa_secundaria` opcional | pendente |
| (futuro) | OS de recapagem = template específico (banda + serviço aplicação) | pendente |

## 10. Histórico

### 2026-05-11 — Primeira análise (correção)
- Heatmap UI v2 coletado ([02-heatmap-ui-2026-05-11.md](02-heatmap-ui-2026-05-11.md))
- **Erro inicial:** classifiquei como gráfica + frota (multi-vertical). Wagner corrigiu: é oficina de recapagem pura
- Reanálise dos sinais: 3.08 itens/venda + 1.064 veículos com 2 placas = oficina especializada em recapagem de cavalo+reboque
- Autor: Claude + Wagner

## 11. Refs

- [Heatmap Vargas anonimizado](../../2026-05-sells-grade-heatmap/02-vargas-grade-usage-anonimizada.md)
- [_ANALISE-CROSS-CLIENTE §oficinas](../_ANALISE-CROSS-CLIENTE.md)
- [ADR 0121 — modular especializado por vertical](../../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) — Modules/OficinaAuto cabe esse cliente
