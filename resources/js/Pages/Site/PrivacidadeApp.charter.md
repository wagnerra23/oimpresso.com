---
page: /privacidade
component: resources/js/Pages/Site/PrivacidadeApp.tsx
owner: wagner
status: draft
parent_module: Site
related_us: []
related_prototype: n/a (página pública de texto legal — bespoke; não segue um dos 5 Padrões de Tela)
runbook: memory/requisitos/Site/RUNBOOK-privacidade.md
alcance:
  rota: /privacidade
  rota_nome: publico.privacidade
  permission: n/a (pública, sem login — exigência das lojas de aplicativo)
tier: B
charter_version: 1
---

# Page Charter — Site/PrivacidadeApp (política de privacidade do app oimpresso)

> Casos em [`PrivacidadeApp.casos.md`](PrivacidadeApp.casos.md); plano em
> [RUNBOOK-privacidade](../../../../memory/requisitos/Site/RUNBOOK-privacidade.md).
> **O texto jurídico é rascunho até a revisão da Eliana [E].**

## Mission

Dar às lojas de aplicativo e a quem usa o app uma URL pública e estável que diga o que o app
oimpresso (ERP no celular + registro de ponto) coleta, por quê, com quem compartilha, por quanto
tempo e quais são os direitos LGPD — fiel ao que o código coleta.

## Goals — Features (faz)

- Abre sem login, no mesmo layout das páginas públicas do ponto (`DocumentoPublico`)
- Nomeia controladora (empresa cliente) e operador (oimpresso)
- Lista os dados do ERP e do ponto, e aponta para `/privacidade/ponto` em vez de repetir o ponto
- Declara que não usa localização em segundo plano e que o ponto não coleta biometria nem imagem (ADR 0383)
- Explica exclusão de conta pelo caminho da empresa e traz o contato do encarregado

## Non-Goals — Features (NÃO faz)

- ❌ NÃO lê banco, sessão ou dado de tenant — é só texto
- ❌ NÃO promete apagar marcação de ponto (proibido por lei)
- ❌ NÃO lista dado que o app não coleta (nem omite o que coleta)
- ❌ NÃO usa o shell administrativo (AppShellV2/Sidebar)

## Automation Anti-hooks (NÃO faz)

- ❌ NÃO grava nada em GET
