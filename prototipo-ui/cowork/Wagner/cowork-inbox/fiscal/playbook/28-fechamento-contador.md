---
sessao: "28"
titulo: Fechamento do mês para o contador (10 conferências)
dono: "[CL]"
base: wagnerra23/oimpresso.com@main (2026-10-06)
prefixo: Modules/Fiscal/Services/<FechamentoContadorService novo> · página do link de revisão (thread 15) · Tests
depende: 15 (link) · 24 (Fator R) · leitura do Financeiro e do Manifesto DF-e
decisao: _DECISOES-W-2026-10-06.md (respondidas: D-NATUREZA · D-DIFAL-UF · D-FECHAMENTO · D-REJEITADA)
---
# 28 · Fechamento do mês para o contador (10 conferências)

Aba "Contador" do protótipo: 10 conferências calculadas do mês, mostradas no mesmo link da revisão (sem venda, cliente ou valor de nota individual). Cada conferência diz o que está pendente e o que fazer. **Só informa**: não gera DAS, não transmite PGDAS.

CT01 notas fecham (autorizadas + canceladas + denegadas + rejeitadas = emitidas) · CT02 rejeitadas pendentes · CT03 sublimite (aviso a 90%) · CT04 segregação de receitas (ST já retida separada) · CT05 Fator R · CT06 DIFAL de compra pra uso/consumo/ativo · CT07 entradas sem manifestação · CT08 devolução sem nota de origem · CT09 retenção da NFS-e × financeiro · CT10 regras usadas sem aceite.

## Fontes (lidas pelo [CC] em 2026-10-06; o Code cita a lei literal no docblock)
- Rotina do contador no Simples: conciliar receita com notas emitidas e conferir o sublimite (instacont, 2026).
- DIFAL: Simples paga em compra interestadual para uso, consumo ou ativo (rolmyjun, 2026).

## Casos de uso
**Números provisórios:** confirmar o próximo livre no turno.

### R-FISC-040 · Fechamento do mês soma igual às notas · `must` `[fiscal]`
- **Destino:** `Index.casos.md` (NfeBrasil/Tributacao) ou SPEC do módulo
- **Aceite:** Dado o mês com 398 autorizadas, 9 canceladas, 1 denegada e 4 rejeitadas · Então "notas fecham" = 412 e "rejeitadas pendentes" = 4 com ação. Controle positivo: mês sem rejeitada mostra ok.
- **Teste:** `FechamentoContadorTest` — `R-FISC-040`
- **Contrato:** fonte única: `NotasUnifiedService` (CU-FISC-16)
- **Regressão que defende:** contador fechando com nota rejeitada esquecida.

### R-FISC-041 · Sublimite avisa a 90% e segregação separa a ST · `must` `[fiscal]`
- **Destino:** `Index.casos.md` (NfeBrasil/Tributacao) ou SPEC do módulo
- **Aceite:** Dado receita 12m de R$ 3,48 mi · Então "atenção" (96,7% de R$ 3,6 mi). Dado receita com CSOSN 500 · Então ela sai numa linha própria e a soma das linhas = receita do mês. Controle positivo: receita abaixo de 90% mostra ok.
- **Teste:** `FechamentoContadorTest` — `R-FISC-041`
- **Contrato:** LC 123/2006 arts. 13-A e 18 §4º
- **Regressão que defende:** ICMS pago duas vezes no DAS; estouro de sublimite descoberto tarde.

### R-FISC-042 · Compra interestadual de uso/consumo aponta DIFAL · `must` `[fiscal]`
- **Destino:** `Index.casos.md` (NfeBrasil/Tributacao) ou SPEC do módulo
- **Aceite:** Dado NF de entrada de MG para ativo · Então aparece em "DIFAL de compra" mesmo no Simples. Controle positivo: entrada interestadual para revenda não aparece.
- **Teste:** `FechamentoContadorTest` — `R-FISC-042`
- **Contrato:** LC 87/1996 · LC 190/2022
- **Regressão que defende:** DIFAL de entrada não pago.

## Prova
Testes verdes com controle positivo · biz de teste conforme ADR 0358.

Terminou: `_saida-28.md`. Pare.
