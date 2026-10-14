---
id: modules-crm-pages-crm-configuracoes-index-casos
casos: Crm · Configurações · /crm/settings
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a opção "Ativar solicitação de pedido" liga o menu de pedido no portal do contato e o prefixo numera esses pedidos. Gravar no negócio errado, ou deixar quem não é dono mudar, altera o portal de outro cliente.
owner: wagner
last_run: "2026-10-13"
last_run_ci: "_pendente_ — o trio nasce na thread Crm/15 (F3). O veredito por UC entra quando a lane verticais-pest rodar; até lá o Status é 🧪."
---

# Casos de Uso & Aceite — Crm · Configurações (`/crm/settings`)

> **Âncora:** o [charter](Index.charter.md), o mapa de paridade
> `memory/requisitos/Crm/configuracoes-parity.md` (linhas altas 1–3) e o baseline da Blade
> `CrmConfiguracoesBaselineTest` (F2). Os UCs não derivam do `.tsx`.

---

## UC-CRMCFG-01 · Com a flag ligada, a tela abre em Inertia com o que está gravado · `must`

**Dado** a flag `useV2CrmConfiguracoes` ligada e o negócio com pedido ativo e prefixo `PFX-`
**Quando** o `Admin#` abre `/crm/settings`
**Então** recebe Inertia `Crm/Configuracoes/Index` com `ativar_pedido` verdadeiro e `prefixo` `PFX-`.

Status: 🧪

---

## UC-CRMCFG-02 · Quem não é Admin# não abre · `must`

**Dado** a flag ligada e um usuário do negócio sem o papel `Admin#`
**Quando** abre `/crm/settings`
**Então** recebe 403.

Status: 🧪

---

## UC-CRMCFG-03 · A gravação da tela nova vale só para o negócio da sessão · `must` `[T0]`

**Dado** que sou `Admin#` do meu negócio
**Quando** a tela posta em `/crm/update-settings` (com `X-Inertia`) um `business_id` de outro negócio
**Então** grava no meu negócio, o outro fica intacto e a resposta é redirect (o Inertia segue).

Status: 🧪

---

## UC-CRMCFG-04 · Desmarcar desliga · `must`

**Dado** o pedido ativo no meu negócio
**Quando** a tela posta só o prefixo (o checkbox desmarcado não manda o campo)
**Então** o pedido fica desligado.

Status: 🧪

---

## UC-CRMCFG-05 · Flag desligada ou `?classico=1` seguem na Blade · `must`

**Dado** a flag desligada (default) — ou ligada com `?classico=1`
**Quando** o `Admin#` abre `/crm/settings`
**Então** recebe a view `crm::settings.index`.

Status: 🧪
