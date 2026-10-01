---
page: /m/mais
component: resources/js/Pages/Mobile/Mais.tsx
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

# Page Charter — Mobile/Mais (`/m/mais`)

> Base do app das lojas (decisão [W] 2026-10-01: o app abre o protótipo Mobile, não o site).
> Contrato do shell: [RUNBOOK-shell-mobile.md](../../../../memory/requisitos/AppMobile/RUNBOOK-shell-mobile.md).
> Casos: [Mais.casos.md](Mais.casos.md).

## Mission

Hub da aba Mais com o mínimo que já funciona: quem está logado, sair e voltar para o ERP completo.

## Goals — Features (faz)

- Cabeçalho `Mais` com eyebrow `Módulos · ferramentas · conta`.
- Seção Conta: usuário e empresa ativos; **Abrir versão completa** (`/home`); **Sair** (`/logout`).
- Lockup da marca (logo + Oimpresso + subtítulo) no rodapé.

## Non-Goals — Features (NÃO faz)

- ❌ **Não lista módulo sem tela** — Produtos, Pessoas, Finanças, Ponto entram aqui quando a tela /m deles existir (cada sessão de tela acrescenta o próprio item). Link para tela inexistente é beco sem saída.
- ❌ **Não tem Venda rápida** — é v2 ([W] 2026-10-01).
