---
page: /atendimento/macros
component: resources/js/Pages/Atendimento/Macros/Index.tsx
owner: wagner
status: live
last_validated: "2026-05-16"
parent_module: Whatsapp
parent_adr: memory/decisions/0135-omnichannel-inbox-arquitetura.md
related_adrs: [93, 94, 110]
tier: B
charter_version: 1
---

# Page Charter — `/atendimento/macros`

> Define invariantes da tela de gestão de Macros (quick replies estruturados
> com placeholders + A/B variants). Mudanças que violem este charter exigem
> PR + bump charter.

## Mission

CRUD das macros do business — respostas pré-formatadas que atendente dispara
via slash `/<atalho>` ou botão no composer da Inbox. Substitui copy-paste
manual de mensagens recorrentes (cobrança, orçamento, agradecimento, etc.).

## Goals

- Listar macros do business (filtro por categoria + busca por título/atalho)
- Criar/editar macro com placeholders (`{{cliente.nome}}`, `{{venda.total}}`)
- Suportar A/B variants (link pra `/atendimento/macros/{id}/variants`)
- Métricas por macro: enviadas/dia + taxa de resposta (último 30d)
- Toggle ativo/inativo sem hard delete (preserva histórico de envios)

## Non-Goals

- ❌ NÃO executa macro daqui — execução é DENTRO da Inbox via slash command
- ❌ NÃO renderiza preview real do placeholder hidratado (apenas template literal)
- ❌ NÃO substitui templates HSM Meta Cloud (esses ficam em `/whatsapp/templates`)

## UX targets

- Switch página ≤ 100ms (`Inertia::defer` em listagem + métricas)
- Atalho `N` cria nova macro (consistente com cockpit pattern)
- Validation inline (sem reload) — `react-hook-form` + zod
- Feedback ao salvar via toast (não modal — ADR 0110)

## Automation hooks

- `MacroExecutor` service hidrata placeholders no envio (ContextoNegocio)
- `MacroVariantPicker` escolhe A/B baseado em hash determinístico
- `MacroVariantResponseTracker` agrega métricas via cron daily

## Anti-hooks

- ⛔ Hardcode lista de placeholders — vem do registry `MacroPlaceholders::all()`
- ⛔ Permitir markdown bruto sem sanitização (XSS risk no composer)
- ⛔ Cross-tenant: macro de biz=X visível em biz=Y (global scope obrigatório)
