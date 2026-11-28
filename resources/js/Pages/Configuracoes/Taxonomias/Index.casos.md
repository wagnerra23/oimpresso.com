---
id: resources-js-pages-configuracoes-taxonomias-index-casos
casos: Configurações · Listas dos módulos · /taxonomies
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: as listas classificam leads, acompanhamentos e colaboradores. Errar o escopo mostra a lista de outra empresa; excluir item em uso deixa registros apontando para nada; abrir a tela sem a permissão do módulo vira atalho para o que o módulo esconde.
owner: felipe
last_run: "2026-11-27"
last_run_ci: "_pendente_ — o trio nasce na Onda 1 do RUNBOOK-taxonomias. O veredito por UC entra quando a lane rodar; até lá o Status é 🧪."
---

# Casos de Uso & Aceite — Configurações · Listas dos módulos (`/taxonomies`)

> **Âncora:** o [charter](Index.charter.md) e a tabela de paridade do RUNBOOK-taxonomias (§10). Os UCs não derivam
> do `.tsx`.

---

## UC-TAX-01 · Tipo declarado pelo módulo abre a tela React · `must`

**Dado** a flag `useV2Taxonomias` ligada e um tipo que o módulo dono declara para mim (ex.: `hrm_department`)
**Quando** abro `/taxonomies?type=hrm_department`
**Então** recebo Inertia `Configuracoes/Taxonomias/Index`; com `?classico=1`, a view `taxonomy.index`.

Status: 🧪

---

## UC-TAX-02 · Categoria de produto segue na Blade · `must`

**Dado** a flag ligada
**Quando** abro `/taxonomies?type=product`
**Então** recebo a view `taxonomy.index`, não a tela React.

Status: 🧪

---

## UC-TAX-03 · A lista é só do meu negócio, com a contagem de uso · `must`

**Dado** um departamento na minha empresa usado por um colaborador e outro departamento na empresa vizinha
**Quando** peço a prop `itens`
**Então** vejo o meu, com `em_uso` igual a 1, e nunca o da vizinha.

Status: 🧪

---

## UC-TAX-04 · Item em uso não se exclui · `must`

**Dado** um departamento usado por um colaborador
**Quando** peço a exclusão (DELETE ajax)
**Então** a resposta é `success: false` com a contagem, e o item continua no banco.

Status: 🧪

---

## UC-TAX-05 · Item sem uso se exclui · `must`

**Dado** um departamento que ninguém usa
**Quando** peço a exclusão (DELETE ajax)
**Então** a resposta é `success: true` e o item sai da lista.

Status: 🧪

---

## UC-TAX-06 · Tipo que o módulo não declara para mim segue na Blade · `must`

**Dado** a flag ligada e o CRM sem a permissão `crm.access_sources` para mim
**Quando** abro `/taxonomies?type=source`
**Então** recebo a view `taxonomy.index`, como antes.

Status: 🧪
