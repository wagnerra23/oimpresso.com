---
id: modules-superadmin-pages-superadmin-paginas-index-casos
casos: Superadmin · Páginas do site · /superadmin/frontend-pages?tela=nova
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: Termos de uso e política de privacidade moram aqui; uma edição errada tem efeito legal, e a tela é global (sem tenant), então a trava é só o gate superadmin.
owner: wagner
last_run: "2026-10-09"
---

# Casos de Uso & Aceite — Superadmin · Páginas do site

> **Âncora:** US-SUPER-005 do [SPEC](../../../../../../../memory/requisitos/Superadmin/SPEC.md)
> (editar páginas estáticas do site público) + decisão [W] D-PAG + o charter ao lado. Os UCs
> derivam disso, nunca do `Index.tsx`.

## UC-SAPAG-01 · A tela nova só responde com a chave; sem ela segue a Blade · `must`

**Dado** que sou superadmin **Quando** abro `/superadmin/frontend-pages?tela=nova` **Então** recebo
Inertia com `superadmin/Paginas/Index`; **e** sem `?tela=nova` recebo a view `superadmin::pages.index`.

Status: 🧪

## UC-SAPAG-02 · Usuário comum é barrado ENQUANTO o superadmin passa · `must` `[T0]`

**Dado** um usuário sem a permissão `superadmin` **Quando** abre a tela **Então** é barrado, e o
mesmo teste prova que o superadmin recebe 200.

Status: 🧪

## UC-SAPAG-03 · A lista traz a página com resumo sem marcação · `must`

**Dado** uma página com conteúdo HTML **Quando** a lista carrega **Então** ela aparece com slug,
ordem e visibilidade, e o `resumo` não tem tag.

Status: 🧪

## UC-SAPAG-04 · Slug repetido é recusado no campo e não duplica · `must`

**Dado** uma página com um slug **Quando** crio outra com o mesmo slug pela tela nova **Então**
recebo erro em `slug` e continua existindo uma só página com aquele slug.

Status: 🧪

## UC-SAPAG-05 · Editar pela tela nova grava e volta para a tela nova · `must`

**Dado** uma página **Quando** salvo título e visibilidade novos **Então** o banco guarda os
valores e o redirect volta para `?tela=nova`, não para a Blade.

Status: 🧪

## UC-SAPAG-06 · Excluir pela tela nova remove a página · `must`

**Dado** uma página **Quando** confirmo a exclusão **Então** ela some do banco e o redirect volta
para `?tela=nova`.

Status: 🧪
