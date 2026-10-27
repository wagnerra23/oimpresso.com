---
casos: Fiscal/Das — apuração do DAS pelo Anexo (prévia, atrás de flag)
irmaos: Das.charter.md (lei) · ../../../../memory/requisitos/Fiscal/_telas/apuracao-das.casos.md (a conta)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-10-27"
related_us: [US-FISCAL-030]
---

# Casos de Uso & Aceite — Fiscal/Das

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> A conta em si (faixa, efetiva, valor) é contrato do `apuracao-das.casos.md` (UC-DAS-01..05).
> Aqui fica o que a TELA garante. Teste: `Modules/Fiscal/Tests/Feature/DasControllerTest.php`
> (lane Pest NfeBrasil, MySQL; tenant 98, vizinho 99).

---

## UC-FDAS-01 · Flag desligada esconde a tela
- **Persona:** dono da empresa, antes de o contador conferir a conta.
- **Aceite:** Dado `fiscal.das_apuracao_enabled = false` · Quando abre `/fiscal/das` · Então 404.
- **Regressão que defende:** prévia de imposto aparecendo antes da conferência do contador.
- **Status: ⬜** — vira 🧪 quando a lane rodar.

## UC-FDAS-02 · Sem permissão não entra
- **Aceite:** Dado flag ligada e usuário sem `fiscal.access` · Quando abre `/fiscal/das` · Então 403.
- **Status: ⬜**

## UC-FDAS-03 · Sem anexo, a tela pede o anexo e não calcula
- **Persona:** dono da empresa abrindo a tela pela primeira vez.
- **Aceite:** Dado flag ligada e usuário com `fiscal.access` · Quando abre sem `anexo` (ou com
  `anexo=VI`) · Então a página `Fiscal/Das` vem com `anexo = null` e sem faixas.
- **Regressão que defende:** o sistema escolher o anexo no lugar do contador.
- **Status: ⬜**

## UC-FDAS-04 · Com anexo, a tela recebe a apuração da empresa da sessão
- **Aceite:** Dado anexo `I` e competência `2026-10` · Quando abre · Então a página traz o anexo, as
  6 faixas do Anexo I e a apuração da competência pedida para o business da sessão (tenant 98);
  competência inválida cai no mês anterior.
- **Status: ⬜**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Chego na tela pela sub-nav do Fiscal — só quando [W] ligar a flag (RUNBOOK §6).
- **[BACKLOG]** Comparação com a estimativa ≈6% do Financeiro, com dono único da receita recebida.

## Trilha do tempo
- 2026-10-27 · [C] carimbado por criar-tela.mjs e preenchido; protótipo publicado como Artefato antes.
