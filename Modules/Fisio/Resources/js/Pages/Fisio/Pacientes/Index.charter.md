---
id: modules-fisio-pages-fisio-pacientes-index-charter
page: /fisio/pacientes
component: Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Index.tsx
related_prototype: "n/a (herda PT-01 Lista; o canvas Prescreve Pro não desenha a lista de pacientes)"
owner: wagner
status: draft
last_validated: "2026-10-18"
parent_module: Fisio
related_adrs: [93, 358, 437]
alcance:
  rota: /fisio/pacientes
  rota_nome: fisio.pacientes.index
  permission: fisio.access
  menu_hook: Modules/Fisio/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: fisio_module
tier: B
charter_version: 1
---

# Page Charter — /fisio/pacientes (DRAFT · lista e cadastro de pacientes)

> Nasce na US-FISIO-005 (ADR 0437 §4). Casos: [`Index.casos.md`](Index.casos.md).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Listar os pacientes ativos da clínica e cadastrar um paciente a partir de um contato da empresa — achado pela busca ou criado na hora.

## Goals — Features (faz)

- Lista de pacientes ativos, com busca pelo nome do contato — UC-FPI-01.
- Cadastro a partir de um contato da empresa: queixa, objetivos, dias por semana (1 a 7) e marcadores de condição da lista fechada — UC-FPI-01, UC-FPI-03.
- Lista só da empresa da sessão — UC-FPI-02.
- Contato apagado (soft-delete) não vira paciente — UC-FPI-05.
- Escolha do contato por busca no servidor (nome ou celular, até 20, só da empresa e sem ficha) — UC-FPI-06.
- "Novo contato" no cadastro: nome e celular; o contato nasce como cliente pelo cadastro de contatos do sistema (ContactUtil), sem duplicar celular na empresa — UC-FPI-07.

## Non-Goals — Features (NÃO faz)

- ❌ Guardar nome ou telefone do paciente na ficha: isso mora no contato (ADR 0437 §4). O "Novo contato" cria o contato — a ficha só aponta para ele.
- ❌ Apagar paciente: bloquear é na ficha (UC-FPS-07).

## Refs

- ADR 0437 · `memory/requisitos/Fisio/HANDOFF-F1.md` (T-05) · PT-01 Lista · UI-0013
