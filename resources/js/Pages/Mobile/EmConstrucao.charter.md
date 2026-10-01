---
page: /m/{tarefas|pedidos|producao}
component: resources/js/Pages/Mobile/EmConstrucao.tsx
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

# Page Charter — Mobile/EmConstrucao (`/m/{tarefas|pedidos|producao}`)

> Base do app das lojas (decisão [W] 2026-10-01: o app abre o protótipo Mobile, não o site).
> Contrato do shell: [RUNBOOK-shell-mobile.md](../../../../memory/requisitos/AppMobile/RUNBOOK-shell-mobile.md).
> Casos: [EmConstrucao.casos.md](EmConstrucao.casos.md).

## Mission

Marcador honesto das abas cuja tela ainda não existe, para a tab bar nunca levar a 404.

## Goals — Features (faz)

- Título da aba e estado vazio `Em construção` explicando que a tela chega numa próxima versão do app.
- Mantém a aba certa destacada na tab bar.

## Non-Goals — Features (NÃO faz)

- ❌ **Não simula dados** da aba — nenhum card de exemplo.
- ❌ **Não é permanente** — a sessão de tela que entrega a aba remove o slug do marcador no mesmo PR (RUNBOOK §5.1).
