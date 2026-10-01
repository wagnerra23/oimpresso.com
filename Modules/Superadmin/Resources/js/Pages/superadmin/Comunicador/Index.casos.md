---
id: modules-superadmin-pages-superadmin-comunicador-index-casos
casos: Superadmin · Comunicador · /superadmin/communicator
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a única tela que escreve no e-mail de TODOS os clientes de uma vez. Corpo enviado como HTML cru vira injeção em massa; lista escopada por tenant faria o aviso sair só para o negócio do próprio superadmin.
owner: wagner
last_run: "2026-10-01"
last_run_ci: "run 36809972026 (verticais-pest, dispatch no branch) — 6 de 6 UC-SACOM passaram. Status segue 🧪 até o manifesto aterrissar (G-7 lê o manifesto, não a prosa)."
---

# Casos de Uso & Aceite — Superadmin · Comunicador (`/superadmin/communicator`)

> **Âncora:** US-SUPER-004 do [SPEC](../../../../../../../memory/requisitos/Superadmin/SPEC.md)
> (enviar aviso a businesses, logar destinatários) + o charter ao lado. Os UCs derivam disso,
> nunca do `Index.tsx`.

## UC-SACOM-01 · A tela responde em Inertia, não em Blade · `must`

**Dado** que sou superadmin **Quando** abro `/superadmin/communicator` **Então** recebo Inertia com
o componente `superadmin/Comunicador/Index`, não a view `superadmin::communicator.index`.

Status: 🧪

## UC-SACOM-02 · Admin de negócio é barrado ENQUANTO o superadmin passa · `must` `[T0]`

**Dado** um usuário comum **Quando** acessa a tela **Então** é barrado, e o mesmo teste prova que
o superadmin recebe 200.

Status: 🧪

## UC-SACOM-03 · A lista de destinatários é cross-tenant · `must` `[T0]`

**Dado** dois negócios distintos **Quando** o superadmin abre a tela **Então** os dois aparecem em
`negocios`: a lista não é escopada pelo negócio do usuário logado.

Status: 🧪

## UC-SACOM-04 · Envio sem destinatário é recusado e não registra nada · `must`

**Dado** um envio sem destinatário **Quando** o superadmin confirma **Então** recebe erro de
validação em `recipients` e **nenhum** registro entra no histórico.

Status: 🧪

## UC-SACOM-05 · O corpo chega escapado, nunca como HTML · `must` `[T0]`

**Dado** uma mensagem com `<script>` e uma quebra de linha **Quando** o envio é feito **Então** o
registro grava o texto escapado (`&lt;script&gt;`) e a quebra vira `<br>`.

Status: 🧪

## UC-SACOM-06 · O envio fica no histórico com o alcance · `must`

**Dado** um envio para dois negócios **Quando** abro a tela de novo **Então** o histórico traz o
assunto, `destinatarios = 2` e um `resumo` sem tag.

Status: 🧪
