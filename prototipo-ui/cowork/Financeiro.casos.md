---
casos: Financeiro · window.FinanceiroPage (financeiro-app.jsx)
irmaos: Financeiro.charter.md · financeiro.css
tecnica: Caso de uso = narrativa do cliente + aceite verificável (Dado/Quando/Então)
nota_tela: 8.0
owner: wagner · last_run: 2026-06-02
---

# Casos de Uso & Aceite — Financeiro

> Derivados do código real (`financeiro-app.jsx`). Persona: Eliana [E]. Caixa unificado (entrada ↑ / saída ↓ intercaladas por data).

## UC-F01 · As 3 lentes do caixa
- **Persona:** Eliana. **Como usa:** alterna **Caixa / A receber / A pagar** no header pra focar a lente.
- **Aceite:** Quando escolhe lente · Então o filtro `states` muda (receber=rec+received; pagar=pay+paid; caixa=tudo).
- **Check:** static `FIN_LENSES` + `setLens`. · **Status: ✅ static · live ⬜**

## UC-F02 · Saldo num relance (KPI strip)
- **Persona:** Eliana/Wagner. **Como usa:** lê Saldo previsto (+realizado +sparkline 30d), A receber (+em atraso), A pagar (+próx. vencimento).
- **Aceite:** Então KPI strip com `saldoPrevisto/saldoAtual`, `aReceber/atrasadoRec`, `aPagar`.
- **Check:** static `KPIStrip` + esses campos. · **Status: ✅ static**

## UC-F03 · Sub-rotas do módulo
- **Persona:** Eliana. **Como usa:** navega Visão unificada / Fluxo de caixa / Conciliação / DRE-Relatórios.
- **Aceite:** Quando escolhe sub-rota · Então o título e o conteúdo mudam.
- **Check:** static `FIN_SUB` (unified/fluxo/concil/dre). · **Status: ✅ static · live ⬜**

## UC-F04 · Filtrar o caixa
- **Persona:** Eliana. **Como usa:** filtra por ciclo (A receber/Recebidas/A pagar/Pagas), marca "atrasados", busca texto.
- **Aceite:** Quando aplica filtro/late/busca · Então a tabela unificada reflete + contadores.
- **Check:** static `FilterBar` + `late` + `query` + counts. · **Status: ✅ static**

## UC-F05 · Ageing de "A receber"
- **Persona:** Eliana. **Como usa:** vê a barra empilhada por janela de vencimento pra saber o que estica o caixa.
- **Aceite:** Então `FinAgeing` mostra total + janelas.
- **Check:** static `FinAgeing`. · **Status: ✅ static**

## UC-F06 · Abrir lançamento (detalhe + FSM + IA)
- **Persona:** Eliana. **Como usa:** clica a linha → drawer com Detalhes e ✦ IA; vê o FSM (emitido→conferido→conciliado→liquidado) e vencimento/atraso.
- **Aceite:** Quando abre · Então drawer com abas Detalhes/IA + `FsmStepper` financeiro.
- **Check:** static `fin-drawer-tab` + `FsmStepper domain=financeiro`. · **Status: ✅ static · live ⬜**

## UC-F07 · Conciliar / novo lançamento
- **Persona:** Eliana. **Como usa:** botão "Conciliar" (banco × sistema) e "Novo lançamento".
- **Aceite:** Quando clica · Então abre conciliação / criação.
- **Check:** static botão "Conciliar" + novo lançamento. · **Status: ✅ static · live ⬜**

## Evolução
- 2026-06-02 · [CC] criou a suíte (7 UCs) grounded em `financeiro-app.jsx`. ⚠️ **A charter canônica da tela é `resources/js/Pages/Financeiro/Unificado/Index.charter.md` v10** (no git) — o `Financeiro.charter.md` local v1 foi SUPERSEDED (CODE_NOTES 2026-05-31, PR #2053). Estes casos devem alinhar ao Unificado/Index.
