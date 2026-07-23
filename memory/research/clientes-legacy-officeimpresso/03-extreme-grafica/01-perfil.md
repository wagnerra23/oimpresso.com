---
id: research-clientes-legacy-officeimpresso-03-extreme-grafica-01-perfil
slug: 03-extreme-grafica
hash_id: Cliente_6928E8
status: qualificado
date_first_analysis: 2026-05-11
date_last_update: 2026-05-11
controlador: Wagner
vertical_real: grafica-industrial-pcp
size: muito-grande
tipo_relacao: cliente-pagante-saudavel
banco_firebird: D:\DadosClientes\Extreme\Dados\BANCO.FDB
banco_alias_firebird: 192.168.0.55:D:\DadosClientes\Extreme\Dados\BANCO.FDB
---

# Perfil — `Cliente_6928E8` (gráfica industrial com PCP por centro de trabalho)

## 1. Identificação

| Campo | Valor |
|-------|-------|
| Hash ID | `Cliente_6928E8` |
| Vertical real | Gráfica industrial PCP |
| Porte | **muito grande** — 705 vendas/mês × 85k total |
| Cidade / UF | a confirmar |
| Status comercial | cliente-pagante saudável |

## 2. Tipo de negócio real

Gráfica de **escala industrial** com **PCP estruturado por centro de trabalho** (máquinas: Roland / Mimaki / HP Latex / outras). 705 vendas/mês × 11 anos de histórico = operação madura.

**Diferencial vs Vargas (recapagem) e Gold (comvis):**
- Zero veículos (zero EQUIPAMENTO_VEICULO) → não atende automotivo
- Zero status estruturado (VENDA.SITUACAO vazio) — controla fluxo via PCP, não via status
- **52.473 linhas em `VENDA_PRODUTO_CENTRO_TRABALHO`** → cada item de venda é rastreado por máquina/centro

## 3. Sinais Firebird

| Dimensão | Valor | Comentário |
|----------|------:|------------|
| Vendas 24m | 16.910 (705/mês) | escala industrial |
| Vendas total | 85.575 | 11+ anos histórico |
| Range temporal | 2015–2026 | operação contínua |
| DT_EMISSAO | 100% | sempre |
| **DT_FATURAMENTO** | **92.9%** | uso muito alto |
| DT_COMPETENCIA | 75.3% | regular |
| **FATURAMENTO_DT_ENVIO** ("Dt Env. Faturamento") | **89.1%** | ⚠️ v4: probe inicial subestimou — Extreme usa **massivamente** |
| **PROJETO_DT_FIM** ("Dt. Prometido" do UI Delphi) | **91.4%** | ⚠️ **CORREÇÃO CRÍTICA v4** — é a coluna que o dropdown UI Delphi chama de "Dt. Prometido". Source-first via `Controller.Venda.Venda.pas` confirmou. **Extreme é o cliente paradigmático de controle de prazo formal** |
| CODFINANCEIRO_GRUPO | 43.3% | médio |
| Itens/venda média | 1.47 | maioria 1 item |
| Status (VENDA.SITUACAO inline) | 1 vazio | **não usa status** |
| VENDA_SITUACAO lookup | 0 linhas | — |
| VENDA_ESTAGIO | 0 linhas | sem FSM |
| **PCP centro_trabalho** | **52.473 linhas** | **rastreio por máquina** |
| EQUIPAMENTO_VEICULO | 0 | gráfica pura |
| Tabelas extras | `EQUIPAMENTO_API_LOG`, `EQUIPAMENTO_PRODUTO` | integração com sistemas externos / impressoras? |

## 4. Módulos OfficeImpresso usados

| Módulo | Uso real | Migração necessária? |
|--------|----------|----------------------|
| Vendas (`VENDA`) | 85k linhas | **sim — crítico** |
| Itens (`VENDA_PRODUTO`) | volumoso | **sim** |
| **PCP centro_trabalho** | **52k linhas** | **sim — DIFERENCIAL Modules/ComVis precisa cobrir** |
| Financeiro | sim | sim |
| Agrupamento | 43% | sim |
| Status produção | não usa | dispensável |
| Veículos | não usa | dispensável |

## 5. Saúde financeira

Pendente. Volume vendas alto (~700/mês) sugere operação madura e provavelmente saudável.

## 6. Sinal qualificado pra migração

- [x] **Volume alto** — operação relevante
- [ ] Reclamou? — desconhecido
- [x] **Múltiplos sistemas integrados** (EQUIPAMENTO_API_LOG sugere integração com impressoras/RIP) — pode ser sinal de modernização
- [ ] Pediu demo? — desconhecido

**Status pra ADR 0105:** sinal **médio-alto** — cliente grande e operação complexa. Mas custo de migração alto também (PCP customizado). Não migrar até `Modules/ComunicacaoVisual` cobrir PCP por centro de trabalho.

## 7. Plano de migração preliminar

- **Pré-requisito**: `Modules/ComunicacaoVisual` ter sub-feature **"PCP por centro de trabalho"** funcional (Modules/Manufacturing ou nova `Modules/Pcp` — decidir em ADR futuro)
- **Custom necessário**:
  - Cada item de venda rastreável por centro/máquina (não só por produto)
  - Histórico operacional 11 anos pra preservar
- **Risco principal**: PCP customizado é feature pesada. Cliente atende 700 vendas/mês — downtime causa prejuízo significativo

## 8. Decisões ADR locais

| ADR | Decisão | Status |
|-----|---------|--------|
| (futuro) | PCP por centro de trabalho — viver em `Modules/ComunicacaoVisual` ou criar `Modules/Pcp` separado | pendente |

## 9. Histórico

### 2026-05-11 — Primeira análise (v2)
- Heatmap UI v2 coletado ([02-heatmap-ui-2026-05-11.md](02-heatmap-ui-2026-05-11.md))
- Descoberta inicial: 52k linhas em `VENDA_PRODUTO_CENTRO_TRABALHO` único entre os 4 amostrados
- Confirma necessidade de feature PCP em `Modules/ComunicacaoVisual`

## 10. Refs

- [Heatmap Extreme anonimizado](../../2026-05-sells-grade-heatmap/03-extreme-grade-usage-anonimizada.md)
- [_ANALISE-CROSS-CLIENTE §graficas-industriais](../_ANALISE-CROSS-CLIENTE.md)
- [ADR 0121](../../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)
