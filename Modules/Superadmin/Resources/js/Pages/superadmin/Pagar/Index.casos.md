---
id: modules-superadmin-pages-superadmin-pagar-index-casos
casos: Superadmin · Pagar assinatura · /subscription/{id}/pay
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é onde o cliente paga. Preço diferente do mostrado em Minha assinatura confunde a cobrança, e um gateway que saiu da tela não pode continuar cobrando por POST direto.
owner: wagner
last_run: "2026-10-27"
---

# Casos de Uso & Aceite — Superadmin · Pagar assinatura

> **Âncora:** US-SUPER-003 do [SPEC](../../../../../../../memory/requisitos/Superadmin/SPEC.md)
> (assinaturas) + decisão [W] 2026-10-27 (só Pix automático e offline) + o charter ao lado. Os UCs
> derivam disso, nunca do `Index.tsx`.

## UC-SAPGR-01 · Pagar e register-pay abrem a tela nova · `must`

**Dado** um usuário com `superadmin.access_package_subscriptions` e um pacote pago **Quando** abre
`/subscription/{id}/pay` ou `/subscription/{id}/register-pay` **Então** recebe Inertia com
`superadmin/Pagar/Index`.

Status: 🧪

## UC-SAPGR-02 · Sem a permissão é barrado ENQUANTO quem tem passa · `must` `[T0]`

**Dado** um usuário sem a permissão **Quando** abre a tela **Então** recebe 403, e o mesmo teste
prova que quem tem a permissão recebe 200.

Status: 🧪

## UC-SAPGR-03 · Só Pix automático e offline, com o preço no texto da Blade · `must`

**Dado** o offline ligado **Quando** a tela carrega **Então** as formas são só as de
`GATEWAYS_DA_TELA`, o offline traz os dados cadastrados, e o preço é o mesmo texto que a Blade
mostrava (moeda do sistema, precisão e posição do negócio).

Status: 🧪

## UC-SAPGR-04 · Gateway que saiu não cobra nem cria assinatura · `must` `[T0]`

**Dado** um POST em `confirm()` com `gateway=stripe` **Então** volta para `/subscription` com erro e
nenhuma assinatura é criada.

Status: 🧪

## UC-SAPGR-05 · Offline cria a assinatura aguardando, com o preço do pacote · `must`

**Dado** o offline ligado **Quando** confirmo offline **Então** existe uma assinatura `waiting` do meu
negócio para o pacote, com `package_price` igual ao preço do pacote.

Status: 🧪
