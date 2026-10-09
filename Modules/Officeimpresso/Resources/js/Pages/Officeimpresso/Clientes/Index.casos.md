---
id: modules-officeimpresso-pages-clientes-index-casos
casos: Clientes OAuth · /officeimpresso/client
irmaos: Index.charter.md (lei) · Index.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela emite a credencial com que cada Delphi autentica; secret reexibido ou credencial de outro negócio é incidente Tier 0.
owner: wagner
last_run: "2026-10-09"
---

# Casos de Uso & Aceite — Clientes OAuth (`/officeimpresso/client`)

> Derivados da ficha 10 do playbook Officeimpresso, da thread 05 (`_saida-05.md`: secret só uma vez)
> e da decisão D1 (`_DECISOES-W-2026-10-01b.md`: delegação só na operadora) — **não do `Index.tsx`**.
> Teste: `Modules/Officeimpresso/Tests/Feature/ClientesIndexContratoTest.php` (lane `officeimpresso-pest`, MySQL, tenant 98).
> Status: 🧪 escrito e na allowlist, veredito ainda não emitido.

## UC-OICLI-01 · Flag desligada serve a Blade · `must`

**Dado** a flag `useV2OfficeimpressoClientes` desligada (o default) **Quando** quem libera abre a lista
**Então** recebe a Blade `officeimpresso::clients.index` — a rota de fuga até o cutover.

Status: 🧪

## UC-OICLI-02 · Flag ligada serve a tela React · `must`

**Dado** a flag ligada **Quando** a lista abre **Então** a resposta é `Officeimpresso/Clientes/Index`,
com `permissions` no payload inicial e `clientes` adiada.

Status: 🧪

## UC-OICLI-03 · Só credenciais do negócio da sessão · `must` `[T0]`

**Dado** uma credencial de usuário do 98 e outra do 99 **Quando** o 98 pede a lista **Então** vê a sua
e não a do 99.

Status: 🧪

## UC-OICLI-04 · O secret nunca vem na lista · `must` `[T0]`

**Dado** uma credencial existente **Quando** a lista é pedida **Então** nem a chave `secret` nem o
valor do secret aparecem no payload.

Status: 🧪

## UC-OICLI-05 · O secret da criação aparece uma vez · `must`

**Dado** quem acabou de criar uma credencial **Quando** abre a lista **Então** `credencial` traz o
nome e o secret; **Quando** abre de novo **Então** `credencial` vem vazia.

Status: 🧪

## UC-OICLI-06 · Sem permissão, 403; excluir e regenerar só superadmin · `must` `[T0]`

**Dado** a flag ligada e um usuário sem `superadmin` nem `officeimpresso.clientes.liberar` **Quando**
abre a lista **Então** recebe 403; **e** um delegado da operadora recebe `pode_excluir` e
`pode_regenerar` falsos.

Status: 🧪
