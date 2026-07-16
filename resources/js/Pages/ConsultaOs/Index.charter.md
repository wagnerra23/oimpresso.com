---
page: /consulta-os
component: resources/js/Pages/ConsultaOs/Index.tsx
related_prototype: n/a (página pública de acompanhamento — bespoke; não segue um dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ConsultaOs
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /consulta-os (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ConsultaOs/Http/Controllers/ConsultaOsController@index` (rota **pública** `consulta-os.index`) + `@buscar` (`consulta-os.buscar`). É uma página pública — NÃO usa AppShellV2/Sidebar do ERP; layout limpo pro cliente final.

---

## Mission

Página pública onde o cliente final digita o número da OS pra acompanhar o status do seu pedido, sem login no ERP. Layout enxuto e centralizado (sem shell administrativo). É a porta de auto-atendimento — reduz ligação/WhatsApp perguntando "e a minha OS?".

---

## Goals — Features (faz)

- Campo de busca por número da OS + ação de consultar (`consulta-os.buscar`)
- Exibe o status atual do pedido quando encontrado
- Estado "OS não encontrada" claro quando o número não bate
- Layout público limpo (sem AppShellV2, sem sidebar do ERP), tokens DS

---

## Non-Goals — Features (NÃO faz)

- ❌ NÃO expõe dados internos/financeiros da OS ao público (só status de acompanhamento)
- ❌ NÃO exige login — é rota pública intencional
- ❌ NÃO lista todas as OS (só a consultada pelo número informado)
- ❌ NÃO permite editar/cancelar a OS (read-only pro cliente)
- ❌ NÃO usa o shell administrativo (AppShellV2/Sidebar)

---

## UX targets

- Carrega rápido em conexão móvel (página pública, cliente final)
- Legível em mobile e 1280px
- Mensagem de "não encontrada" sem jargão técnico

---

## Automation hooks (faz)

- Busca consulta o status via `consulta-os.buscar` sob demanda (ação do usuário)

---

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO indexa/expõe OS por enumeração (proteção de token público — ver testes de segurança do módulo)
- ❌ NÃO dispara notificação ao cliente ao consultar
- ❌ NÃO grava nada em GET

---

## Pendências antes de `status: live`

- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Confirmar o que exatamente é exposto ao público (campos do status) vs guardado
- [ ] Smoke visual mobile + 1280px (screenshot) — encontrada e não-encontrada
