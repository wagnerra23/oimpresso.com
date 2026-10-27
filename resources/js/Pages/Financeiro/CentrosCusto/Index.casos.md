---
casos: Financeiro/CentrosCusto/Index · /financeiro/centros-custo
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-10-27"
---

# Casos de Uso & Aceite — Financeiro/CentrosCusto/Index

> Derivado do aceite da US-FIN-075 (SPEC do Financeiro) e do protótipo aprovado em 2026-10-27.
> **Status:** ✅ passa · 🧪 teste cita o UC e ainda sem veredito · ⬜ sem prova · ❌ quebrou.
> Teste: `Modules/Financeiro/Tests/Feature/CentroCustoCadastroContratoTest.php`.

## UC-CCUSTO-01 · Vejo os centros da empresa com o uso do mês
- **Persona:** Eliana (financeiro) — quer saber onde o dinheiro do mês foi rateado.
- **Aceite:** Dado centros cadastrados e títulos rateados na competência do mês · Quando abro `/financeiro/centros-custo` · Então cada centro mostra situação, quantos títulos e quanto foi rateado no mês; título cancelado não conta.
- **Status: 🧪**

## UC-CCUSTO-02 · Crio um centro de custo
- **Aceite:** Dado o drawer "Novo centro de custo" · Quando salvo código e nome · Então o centro nasce ativo na minha empresa. Nome ou código repetido na empresa é recusado com mensagem no campo.
- **Status: 🧪**

## UC-CCUSTO-03 · Edito e inativo um centro
- **Aceite:** Dado um centro · Quando edito o nome ou clico em inativar · Então a lista reflete a mudança; inativo deixa de aparecer na escolha do título, mas o rateio antigo continua nos relatórios.
- **Status: 🧪**

## UC-CCUSTO-04 · Tier 0 — centro de outra empresa
- **Aceite:** Dado um centro da empresa 99 · Quando a empresa 98 lista, edita ou inativa por id · Então não aparece na lista e a edição responde 404.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Chego na tela pelo menu (atalho "Centros de custo" no grupo Financeiro) sem digitar URL.
- **[BACKLOG]** Excluir centro sem rateio (hoje a tela só inativa; o model já recusa exclusão com rateio).

## Trilha do tempo
- 2026-10-27 · [CC] trio nascido junto com a tela (charter + casos + teste). Refs: US-FIN-075 · UI-0013 · ADR 0264.
