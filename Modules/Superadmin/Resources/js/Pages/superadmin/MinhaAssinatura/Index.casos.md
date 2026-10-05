---
id: modules-superadmin-pages-superadmin-minhaassinatura-index-casos
casos: Superadmin · Minha assinatura · /subscription
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a tela em que o cliente vê quanto paga. Valor diferente do que a Blade mostrava confunde a cobrança; histórico sem filtro de negócio mostra a assinatura de outro cliente.
owner: wagner
last_run: "2026-10-05"
---

# Casos de Uso & Aceite — Superadmin · Minha assinatura (`/subscription`)

> **Âncora:** US-SUPER-003 do [SPEC](../../../../../../../memory/requisitos/Superadmin/SPEC.md)
> (assinaturas) + a ficha da thread 07 + o charter ao lado. Os UCs derivam disso, nunca do `Index.tsx`.

## UC-SAMA-01 · A tela responde em Inertia, não em Blade · `must`

**Dado** um usuário com `superadmin.access_package_subscriptions` **Quando** abre `/subscription`
**Então** recebe Inertia com o componente `superadmin/MinhaAssinatura/Index`.

Status: 🧪

## UC-SAMA-02 · Sem a permissão é barrado ENQUANTO quem tem passa · `must` `[T0]`

**Dado** um usuário sem a permissão **Quando** acessa **Então** recebe 403, e o mesmo teste prova
que o usuário com a permissão recebe 200.

Status: 🧪

## UC-SAMA-03 · O histórico é só do próprio negócio · `must` `[T0]`

**Dado** uma assinatura do negócio 98 e outra de um segundo negócio **Quando** o usuário do 98
abre a tela **Então** o histórico traz a dele e **não** traz a do outro negócio.

Status: 🧪

## UC-SAMA-04 · O valor sai no mesmo texto que a Blade mostrava · `must` `[T0]`

**Dado** a moeda do sistema e a precisão/posição do negócio **Quando** o valor é formatado
**Então** o texto é o do accounting.js da Blade: `1.005` com 2 casas vira `1,00` (não `1,01`),
`1234567.895` vira `1.234.567,90`, e com 0 casas e símbolo depois `99.5` vira `100 <símbolo>`.

Status: 🧪

## UC-SAMA-05 · Pacote privado não aparece para quem não é superadmin · `must`

**Dado** um pacote ativo marcado como privado **Quando** um admin de negócio abre a tela **Então**
o pacote não está na lista.

Status: 🧪
