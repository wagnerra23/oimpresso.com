---
page: /privacidade/ponto
component: resources/js/Pages/Ponto/Publico/Privacidade.tsx
owner: wagner
status: draft
parent_module: Ponto
related_us: [US-PONTO-001]
related_prototype: n/a (página pública de texto legal — bespoke; não segue um dos 5 Padrões de Tela)
runbook: memory/requisitos/Ponto/RUNBOOK-publico.md
alcance:
  rota: /privacidade/ponto
  rota_nome: ponto.publico.privacidade
  permission: n/a (pública, sem login — exigência das lojas de aplicativo)
tier: B
charter_version: 1
---

# Page Charter — Ponto/Publico/Privacidade (política de privacidade do app de ponto)

> Casos em [`Privacidade.casos.md`](Privacidade.casos.md); plano em
> [RUNBOOK-publico](../../../../../memory/requisitos/Ponto/RUNBOOK-publico.md).
> **O texto jurídico é rascunho até a revisão da Eliana [E].**

## Mission

Dar ao colaborador, e às lojas de aplicativo, uma URL pública e estável que diga o que o app de
ponto coleta, por quê, por quanto tempo e quais são os direitos LGPD — fiel ao que o código coleta.

## Goals — Features (faz)

- Abre sem login, em layout limpo legível no celular
- Nomeia controlador (empregador) e operador (oimpresso)
- Lista os dados coletados pelo REP-P: horário, tipo, GPS no momento da marcação, identificador do aparelho, cadastro vindo do empregador, justificativas
- Declara que **não** há biometria nem imagem (ADR 0383)
- Explica a retenção legal: marcação é append-only (Portaria MTP 671/2021)
- Traz canal de contato do encarregado

## Non-Goals — Features (NÃO faz)

- ❌ NÃO lê banco, sessão ou dado de tenant — é só texto
- ❌ NÃO promete apagar marcação de ponto (proibido por lei)
- ❌ NÃO lista dado que o código não coleta (nem omite o que coleta)
- ❌ NÃO usa o shell administrativo (AppShellV2/Sidebar)

## Automation Anti-hooks (NÃO faz)

- ❌ NÃO grava nada em GET
