---
casos: Financeiro/Orcamento/Index · /financeiro/orcamento
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-10-27"
---

# Casos de Uso & Aceite — Financeiro/Orcamento/Index

> Derivado do aceite da US-FIN-076 (SPEC do Financeiro) e do protótipo aprovado em 2026-10-27.
> **Status:** ✅ passa · 🧪 teste cita o UC e ainda sem veredito · ⬜ sem prova · ❌ quebrou.
> Teste: `Modules/Financeiro/Tests/Feature/OrcamentoTelaContratoTest.php`.

## UC-ORC-01 · Vejo orçado, realizado e desvio por conta no mês `[V0]`
- **Persona:** Wagner (escritório) — confere se o mês está dentro do orçamento.
- **Aceite:** Dado orçado e títulos no mês · Quando abro `/financeiro/orcamento?mes=…` · Então toda conta de resultado aparece (com zero quando não tem nada) e a conta com movimento mostra orçado, realizado e desvio; despesa acima do orçado é desfavorável.
- **Status: 🧪**

## UC-ORC-02 · Edito o orçado da conta no mês `[V0]`
- **Aceite:** Dado o filtro em Geral ou num centro · Quando digito o orçado e saio do campo · Então o valor é gravado para aquele mês, conta e centro; zero apaga a linha.
- **Status: 🧪**

## UC-ORC-03 · Filtro por centro e regime
- **Aceite:** Dado um título rateado entre centros · Quando escolho o centro A · Então o realizado é só a fatia do A; em Caixa, o realizado vem das baixas do mês.
- **Status: 🧪**

## UC-ORC-04 · Tier 0 — conta de outra empresa
- **Aceite:** Dado uma conta do plano da empresa 99 · Quando a 98 tenta gravar orçado nela · Então é recusado e nada é gravado; a lista da 98 não traz contas da 99.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Coluna de orçado dentro da DRE e do fluxo (hoje o comparativo vive nesta tela).
- **[BACKLOG]** Copiar o orçado de um mês para o seguinte.

## Trilha do tempo
- 2026-10-27 · [CC] trio nascido junto com a tela (charter + casos + teste). Refs: US-FIN-076 · UI-0013 · ADR 0264.
