---
id: modules-crm-pages-crm-relatorios-index-casos
casos: Crm · Relatórios · /crm/reports
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: os relatórios agregam o trabalho comercial de toda a equipe e nomeiam contatos convertidos. Errar o escopo soma usuário ou contato de outro negócio; errar o portão abre a visão da equipe inteira para quem não é admin.
owner: wagner
last_run: "2026-10-13"
last_run_nota: "2026-10-13 thread Crm/13 F3: trio nasce; os testes moram em Modules/Crm/Tests/Feature/CrmRelatoriosContratoTest.php (lane verticais-pest)."
last_run_ci: "_pendente_ — o veredito por UC entra no manifesto quando a lane verticais-pest rodar; até lá o Status é 🧪."
---

# Casos de Uso & Aceite — Crm · Relatórios (`/crm/reports`)

> **Âncora:** o [charter](Index.charter.md), o `memory/requisitos/Crm/RUNBOOK-relatorios.md` (§2-5) e a
> paridade `relatorios-parity.md` (linhas 1-10). Os UCs não derivam do `.tsx`.

---

## UC-CRMREL-01 · A flag decide entre a Blade e a tela React; só o admin entra · `must`

**Dado** que sou admin do negócio
**Quando** abro `/crm/reports` com a flag `useV2CrmRelatorios` desligada
**Então** recebo a view `crm::reports.index` **E** com a flag ligada recebo Inertia `Crm/Relatorios/Index`
**E** quem não é admin recebe 403.

Status: 🧪

---

## UC-CRMREL-02 · Por usuário conta cada status, os sem status e o total · `must` `[T0]`

**Dado** acompanhamentos meus com status diferentes e um de usuário de outro negócio
**Quando** a tela pede a matriz por usuário
**Então** a minha linha traz a contagem por status, "Outros" e o total **E** o usuário de outro negócio não aparece.

Status: 🧪

---

## UC-CRMREL-03 · O intervalo de datas recorta a matriz por usuário · `should`

**Dado** um acompanhamento em janeiro e outro em março
**Quando** filtro janeiro
**Então** o total da minha linha é 1.

Status: 🧪

---

## UC-CRMREL-04 · Por contato soma só contato do negócio · `must` `[T0]`

**Dado** um contato meu com dois acompanhamentos e um contato de outro negócio
**Quando** a tela pede a matriz por contato
**Então** o meu aparece com os totais certos **E** o de outro negócio não aparece.

Status: 🧪

---

## UC-CRMREL-05 · Conversão e detalhe ficam no negócio · `must` `[T0]`

**Dado** dois contatos convertidos por mim e um convertido em outro negócio
**Quando** vejo a conversão e abro o detalhe do meu usuário e do usuário vizinho
**Então** meu total é 2, o detalhe lista o mais recente primeiro **E** o do outro negócio não aparece.

Status: 🧪

---

## UC-CRMREL-06 · `?classico=1` mantém a tela antiga · `must`

**Dado** a flag ligada
**Quando** abro `/crm/reports?classico=1`
**Então** recebo a view `crm::reports.index`.

Status: 🧪
