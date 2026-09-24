---
casos: Essentials/Painel — carimbado do Padrão de Tela
irmaos: Painel.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-09-24"
---

# Casos de Uso & Aceite — Essentials/Painel

> Derivados do playbook hrm thread 06 (§B/§C) e do charter — não do `.tsx`.
> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Prova: `Modules/Essentials/Tests/Feature/HrmPainelTest.php` (lane essentials-pest, MySQL real).

---

## UC-PAINEL-00 · Chego no painel pelo menu do HRM
- **Persona:** administrador — entra no HRM pelo sidebar.
- **Aceite:** Dado o pacote do Essentials · Quando abre `/hrm/dashboard` pela rota `hrmDashboard`
  (entrada do grupo HRM no `DataController::modifyAdminMenu`) · Então responde 200.
- **Regressão que defende:** a Page existir e a rota nomeada do menu deixar de apontar para ela.
- **Status: ⬜** — o teste cita o UC; vira 🧪 quando a lane rodar verde.

---

## UC-PAINEL-01 · O painel abre como Page Inertia, com o conteúdo adiado
- **Aceite:** Dado um usuário do tenant · Quando visita `/hrm/dashboard` como o navegador faz
  (com `X-Requested-With`) · Então o componente é `Essentials/Painel`, `is_admin` vem no first
  render e `painel` só chega no partial reload.
- **Regressão que defende:** a tela voltar a renderizar a Blade, ou o conteúdo ficar no skeleton.
- **Status: ⬜**

---

## UC-PAINEL-02 · O painel só mostra dado do próprio tenant (ADR 0093)
- **Aceite:** Dado um feriado e um colaborador do tenant adversário · Quando o administrador do
  tenant 98 abre o painel · Então o feriado alheio não aparece e a contagem de colaboradores é a
  do tenant 98.
- **Regressão que defende:** vazamento cross-tenant em feriados ou em "Colaboradores".
- **Status: ⬜**

---

## UC-PAINEL-03 · Presença e realizado de vendas não entram no painel
- **Aceite:** Quando o painel carrega · Então o payload tem exatamente as chaves
  `colaboradores · setores · minhas_licencas · feriados · faixas_meta` — nada de marcação
  (a jornada é do Ponto, D1) nem de venda realizada (caminho de valor).
- **Regressão que defende:** o painel voltar a contar `essentials_attendances` ou a apurar vendas.
- **Status: ⬜**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** KPI "Licenças pendentes" com número — exige agregado que o controller não calcula hoje.
- **[BACKLOG]** Clique nos KPIs navega para a tela dona — camada de render (e2e com fixture autenticado).

## Trilha do tempo
- 2026-09-24 · [C] trio carimbado por criar-tela.mjs e preenchido na thread 06 do playbook hrm.
