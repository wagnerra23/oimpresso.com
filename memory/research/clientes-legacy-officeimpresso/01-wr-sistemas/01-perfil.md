---
id: research-clientes-legacy-officeimpresso-01-wr-sistemas-01-perfil
slug: 01-wr-sistemas
hash_id: Cliente_498223
status: empresa-mae
date_first_analysis: 2026-05-11
date_last_update: 2026-05-11
controlador: Wagner
vertical_real: erp-fornecedor (auto-uso)
size: micro
tipo_relacao: empresa-mae
banco_firebird: C:\WR Sistema\Dados\BANCO.FDB
banco_alias_firebird: 192.168.0.55:Banco
---

# Perfil — `Cliente_498223` (WR Sistemas — empresa-mãe)

> **Caso especial:** não é cliente, é a empresa que fornece o OfficeImpresso pros outros 37. Wagner [W] é dono. Análise serve pra calibrar queries antes de rodar em cliente real (sem risco LGPD).

## 1. Identificação

| Campo | Valor |
|-------|-------|
| Hash ID | `Cliente_498223` |
| Vertical real | ERP fornecedor (auto-uso operacional) |
| Cidade / UF | Tubarão / SC (provavelmente — não confirmado) |
| Porte | 9 vendas/mês (toy) |
| Tempo de operação | 26+ anos |
| Status | empresa-mãe |

## 2. Tipo de negócio real

Empresa **fornecedora** do OfficeImpresso pra outros 37 clientes legacy. Wagner usa o próprio sistema pra controle financeiro/contratos da WR. Por isso volume baixo de "vendas" no Firebird — não é loja, é uso administrativo.

## 3. Sinais Firebird

| Dimensão | Valor | Comentário |
|----------|------:|------------|
| Vendas 24m | 180 (9/mês) | toy — uso interno |
| DT_EMISSAO | 99.9% | sempre preenchido |
| DT_FATURAMENTO | 52.7% | uso médio |
| DT_COMPETENCIA | 18.6% | raramente |
| CODFINANCEIRO_GRUPO | 34.5% | médio uso de agrupamento |
| Itens/venda média | 1.30 | quase tudo 1 item |
| VENDA_SITUACAO | 16 catalogados | catalog rico mas zero uso |
| VENDA_ESTAGIO | 10 catalogados | catalog rico mas zero uso |
| EQUIPAMENTO_VEICULO | 102 (sujos, 0% PLACA) | provável dado de teste/migração antiga |
| Range temporal | 2007–2026 | 19 anos histórico |

## 4. Conclusão pra Grade Avançada

- Útil pra **validar schema do Delphi** mas **não pra qualificar US** (volume insuficiente)
- Confirma que `VENDA_SITUACAO`/`VENDA_ESTAGIO` lookups existem como meta-tabelas mesmo quando não usadas
- Calibra anonimização e fluxo de queries antes de rodar em cliente real

## 5. Histórico

### 2026-05-11 — Primeira análise
- Coletado heatmap UI ([02-heatmap-ui-2026-05-11.md](02-heatmap-ui-2026-05-11.md))
- Foi o "Round 1" pra desbravar queries antes de rodar em Vargas/Extreme/Gold/Martinho
- Autor: Claude (IA-pair) + Wagner aprovando

## 6. Refs

- [Heatmap WR2 anonimizado](../../2026-05-sells-grade-heatmap/01-wr2-grade-usage-anonimizada.md)
- [HEATMAP-CONSOLIDADO.md](../../2026-05-sells-grade-heatmap/HEATMAP-CONSOLIDADO.md)
- [_ANALISE-CROSS-CLIENTE.md](../_ANALISE-CROSS-CLIENTE.md)
