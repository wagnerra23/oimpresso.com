---
id: modules-superadmin-pages-superadmin-pagar-index-casos
casos: Superadmin · Pagar assinatura · /subscription/{id}/pay
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é onde o cliente escolhe como paga. Forma de pagamento errada ou preço diferente do pacote confunde a cobrança; a Blade tem que seguir servindo quem não está no canary.
owner: wagner
last_run: "2026-11-03"
---

# Casos de Uso & Aceite — Superadmin · Pagar assinatura (`/subscription/{id}/pay`)

> **Âncora:** US-SUPER-003 do [SPEC](../../../../../../../memory/requisitos/Superadmin/SPEC.md), a
> decisão [W] de 2026-10-27 (cabeçalho de `superadmin-pagar-page.jsx`) e o charter ao lado.

## UC-SAPG-01 · Com a flag ligada, a tela responde em Inertia · `must`

**Dado** a flag `useV2SuperadminPagar` ligada para o negócio **Quando** o usuário com
`superadmin.access_package_subscriptions` abre o pagamento de um pacote pago **Então** recebe Inertia
com o componente `superadmin/Pagar/Index`.

Status: 🧪

## UC-SAPG-02 · Com a flag desligada, segue a Blade · `must`

**Dado** a flag desligada **Quando** abre a mesma rota **Então** não recebe Inertia (a Blade de
sempre); e com a flag ligada, `?classico=1` também devolve a Blade.

Status: 🧪

## UC-SAPG-03 · Só Pix automático e offline são oferecidos · `must`

**Dado** o pagamento offline habilitado **Quando** a tela abre **Então** as formas oferecidas são um
subconjunto de `paymentgateway_pix_automatico` e `offline`, e `offline` está entre elas.

Status: 🧪

## UC-SAPG-04 · O preço é o texto de `moedaComoBlade` do preço do pacote · `must` `[T0]`

**Dado** um pacote com preço **Quando** a tela abre **Então** `pacote.preco` é exatamente
`moedaComoBlade(price)` e o POST vai para o `confirm()` do mesmo pacote.

Status: 🧪

## UC-SAPG-05 · Sem a permissão é barrado ENQUANTO quem tem passa · `must` `[T0]`

**Dado** um usuário sem a permissão **Quando** acessa **Então** recebe 403, e o mesmo teste prova que
o usuário com a permissão recebe 200.

Status: 🧪
