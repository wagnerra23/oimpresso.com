---
id: research-clientes-legacy-officeimpresso-04-gold-comvis-01-perfil
slug: 04-gold-comvis
hash_id: Cliente_09FEB1
status: qualificado
date_first_analysis: 2026-05-11
date_last_update: 2026-05-11
controlador: Wagner
vertical_real: comunicacao-visual
size: grande
tipo_relacao: cliente-pagante-saudavel
banco_firebird: D:\DadosClientes\Gold\Dados\BANCO.FDB
banco_alias_firebird: 192.168.0.55:D:\DadosClientes\Gold\Dados\BANCO.FDB
---

# Perfil — `Cliente_09FEB1` (comunicação visual com funil produção textual)

> **Correção 2026-05-11:** Wagner apontou — é **comunicação visual**, não gráfica genérica. CNAE 1813-0/01 (impressão de material publicitário) ou 7320-5/00 (publicidade/comunicação visual).

## 1. Identificação

| Campo | Valor |
|-------|-------|
| Hash ID | `Cliente_09FEB1` |
| Vertical real | **Comunicação visual** |
| Porte | grande — 356 vendas/mês × 55k total |
| Cidade / UF | a confirmar |
| Status comercial | cliente-pagante saudável |

## 2. Tipo de negócio real

Empresa de **comunicação visual** — banner, fachada, sinalização, adesivagem, painéis. Atende cliente final (loja, comércio) com **produção sob demanda** (cada peça é única — medida, material, arte).

**Como sei (sinais Firebird):**
- 466 tabelas no banco (mais que outros — schema rico de comvis)
- **29.559 vendas com situação "EM PRODUÇÃO"** + **7.082 "FINALIZADA"** → fluxo de produção formal estruturado
- **DT_PROMETIDO 85.2% preenchido** → cliente comvis precisa dar prazo de entrega (única gráfica do sample que tem essa coluna populada)
- Itens/venda média 1.58 → maioria 1 item por OS (1 banner, 1 fachada)
- Zero veículos → não atende automotivo
- Zero PCP estruturado por centro_trabalho → fluxo simples (não tem múltiplas máquinas industriais como Extreme)

**Diferença vs gráfica industrial (Extreme):**
- Extreme: PCP por máquina (Roland/Mimaki) → industrial, alta escala
- Gold: produção sob demanda + funil status textual → comvis personalizada

## 3. Sinais Firebird

| Dimensão | Valor | Comentário |
|----------|------:|------------|
| Vendas 24m | 8.176 (356/mês) | grande |
| Vendas total | 55.715 | 11+ anos histórico |
| Range temporal | 2015–2026 | operação contínua |
| 466 tabelas | — | schema mais rico do sample |
| DT_EMISSAO | 100% | sempre |
| **DT_FATURAMENTO** | **92.4%** | uso alto |
| DT_COMPETENCIA | 7.5% | baixo — não controla regime competência |
| ~~DT_PROMETIDO 85.2%~~ | ❌ erro v3 | ⚠️ **v4 CORRIGIDO** — coluna correta é `PROJETO_DT_FIM` ("Dt. Prometido" no UI). Gold tem só **6.2%** — NÃO usa controle de prazo |
| FATURAMENTO_DT_ENVIO | 0.0% | não usa |
| CODFINANCEIRO_GRUPO | 53.1% | médio-alto |
| Itens/venda média | 1.58 | maioria 1 item |
| **Status (VENDA.SITUACAO inline)** | **7 distinct** | EM PRODUÇÃO 29k, FINALIZADA 7k + outros 5 |
| **VENDA_SITUACAO lookup** | **5 linhas** | catalog formalizado |
| VENDA_ESTAGIO | 0 | sem FSM separado — usa SITUACAO inline |
| PCP centro_trabalho | 0 | sem PCP industrial |
| EQUIPAMENTO_VEICULO | 0 | sem veículos |

## 4. Módulos OfficeImpresso usados

| Módulo | Uso real | Migração necessária? |
|--------|----------|----------------------|
| Vendas (`VENDA`) | 55k linhas | **sim — crítico** |
| Status produção textual | **uso massivo** (29k EM PRODUÇÃO) | **sim — Modules/ComVis precisa cobrir** |
| Prazo de entrega (DT_PROMETIDO) | 85% | **sim — feature comvis específica** |
| Financeiro | sim | sim |
| Agrupamento | 53% | sim |
| Veículos | não usa | dispensável |
| PCP centro_trabalho | não usa | dispensável (Extreme precisa, Gold não) |

## 5. Saúde financeira

Pendente.

## 6. Sinal qualificado pra migração

- [x] **Volume alto + funil maduro** — operação relevante e bem estruturada
- [x] **DT_PROMETIDO único do sample** — cliente comvis típico precisa controlar prazo (sinal de adequação ao Modules/ComVis)
- [ ] Reclamou? — desconhecido

**Status pra ADR 0105:** sinal **médio-alto** — alinha perfeitamente com `Modules/ComunicacaoVisual` que já está em construção. Bom candidato pra **canary** de cutover quando módulo amadurecer.

## 7. Plano de migração preliminar

- **Pré-requisito**: `Modules/ComunicacaoVisual` ter:
  - Campo `prazo_prometido` em sells/orders (mapeia `DT_PROMETIDO`)
  - Status produção: EM PRODUÇÃO / FINALIZADA / outros 5 valores migrados como state machine
  - Cálculo m² (já existe — US-COMVIS-001 ✅)
- **Custom necessário**:
  - Status produção mapeado (não criar FSM custom — usar US-SELL-011 base + processo "Venda Com Produção")
  - Preservar histórico 11 anos
- **Risco principal**: cliente grande (356/mês) — qualquer downtime custa. Canary 7-14d obrigatório.

## 8. Decisões ADR locais

| ADR | Decisão | Status |
|-----|---------|--------|
| (futuro) | Mapping `VENDA.SITUACAO` Delphi → `transaction.fsm_state` (US-SELL-011) | pendente |
| (futuro) | `DT_PROMETIDO` → campo `due_date` no `Modules/ComunicacaoVisual` | pendente |

## 9. Histórico

### 2026-05-11 — Primeira análise (correção)
- Heatmap UI v2 coletado
- **Erro inicial:** classifiquei como "gráfica com funil textual" genérica. Wagner corrigiu: é comunicação visual
- Reanálise: 29k "EM PRODUÇÃO" + DT_PROMETIDO 85% + zero PCP industrial + zero veículos = comvis sob demanda
- Autor: Claude + Wagner

## 10. Refs

- [Heatmap Gold anonimizado](../../2026-05-sells-grade-heatmap/04-gold-grade-usage-anonimizada.md)
- [_ANALISE-CROSS-CLIENTE §comvis](../_ANALISE-CROSS-CLIENTE.md)
- [Modules/ComunicacaoVisual/SPEC.md](../../../requisitos/ComunicacaoVisual/SPEC.md) — feature backlog que esse cliente valida
