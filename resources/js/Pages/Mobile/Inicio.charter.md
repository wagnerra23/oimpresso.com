---
page: /m
component: resources/js/Pages/Mobile/Inicio.tsx
owner: wagner
status: draft
last_validated: "2026-10-01"
parent_module: Mobile
related_prototype: "n/a (fonte = handoff design-v3 do app mobile, fora de prototipo-ui; caminho em RUNBOOK-shell-mobile §2; a ancora entra quando descer o _saida-01 do playbook app-lojas)"
related_adrs: [93, 104, 358]
runbook: memory/requisitos/AppMobile/RUNBOOK-shell-mobile.md
tier: B
charter_version: 1
---

# Page Charter — Mobile/Inicio (`/m`)

> Base do app das lojas (decisão [W] 2026-10-01: o app abre o protótipo Mobile, não o site).
> Contrato do shell: [RUNBOOK-shell-mobile.md](../../../../memory/requisitos/AppMobile/RUNBOOK-shell-mobile.md).
> Casos: [Inicio.casos.md](Inicio.casos.md).

## Mission

Provar o shell Mobile na raiz do app: o usuário abre o app e vê a aba Início com a saudação e a empresa dele, dentro da tab bar de 5 abas.

## Goals — Features (faz)

- Cabeçalho grande com eyebrow `Início · Hoje, <data>` e `Bom dia|Boa tarde|Boa noite, <primeiro nome>` + avatar de iniciais.
- Pílula com o nome da empresa (business) do usuário autenticado.
- Aviso honesto de que o painel do dia ainda está em construção.

## Non-Goals — Features (NÃO faz)

- ❌ **Não mostra KPI, faturamento nem tarefa** nesta versão — é a sessão de tela do Início que traz o painel do dia (handoff §1); número inventado aqui pareceria dado real.
- ❌ **Não troca de empresa** — o seletor de tenant é tela própria, posterior.
