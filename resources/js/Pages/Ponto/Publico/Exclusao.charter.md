---
page: /privacidade/ponto/exclusao
component: resources/js/Pages/Ponto/Publico/Exclusao.tsx
owner: wagner
status: draft
parent_module: Ponto
related_prototype: n/a (página pública de texto legal — bespoke; não segue um dos 5 Padrões de Tela)
runbook: memory/requisitos/Ponto/RUNBOOK-publico.md
alcance:
  rota: /privacidade/ponto/exclusao
  rota_nome: ponto.publico.exclusao
  permission: n/a (pública, sem login — exigência do Google Play)
tier: B
charter_version: 1
---

# Page Charter — Ponto/Publico/Exclusao (pedido de exclusão de conta e dados)

> Casos em [`Exclusao.casos.md`](Exclusao.casos.md); plano em
> [RUNBOOK-publico](../../../../../memory/requisitos/Ponto/RUNBOOK-publico.md).
> **O texto jurídico é rascunho até a revisão da Eliana [E].**

## Mission

Atender a exigência do Google Play de uma URL pública onde o usuário pede a exclusão da conta e dos
dados — dizendo a verdade: a conta é do empregador e a marcação de ponto não pode ser apagada.

## Goals — Features (faz)

- Abre sem login, legível no celular
- Explica que a conta é gerida pelo empregador e como pedir o encerramento
- Oferece canal ao oimpresso quando o empregador não responde
- Separa o que se encerra (acesso) do que a lei manda guardar (marcações e justificativas)
- Linka a política de privacidade

## Non-Goals — Features (NÃO faz)

- ❌ NÃO promete apagar marcação de ponto (Portaria MTP 671/2021 — append-only)
- ❌ NÃO promete descarte automático em prazo (não existe expurgo de marcação)
- ❌ NÃO tem formulário que grava pedido — o canal é e-mail
- ❌ NÃO lê banco, sessão ou dado de tenant

## Automation Anti-hooks (NÃO faz)

- ❌ NÃO grava nada em GET
