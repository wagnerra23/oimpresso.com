---
id: modules-fisio-pages-fisio-pacientes-index-charter
page: /fisio/pacientes
component: Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Index.tsx
related_prototype: "n/a (herda PT-01 Lista; segue o Padrão de Tela — o canvas Prescreve Pro não tem vista de lista de pacientes)"
owner: wagner
status: draft
last_validated: "2026-10-18"
parent_module: Fisio
related_adrs: [93, 358, 437]
related_us: [US-FISIO-005]
runbook: memory/requisitos/Fisio/RUNBOOK-pacientes.md
alcance:
  rota: /fisio/pacientes
  rota_nome: fisio.pacientes.index
  permission: fisio.access
  menu_hook: Modules/Fisio/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: fisio_module
tier: B
charter_version: 1
---

# Page Charter — /fisio/pacientes (DRAFT · lista de pacientes da clínica)

> Nasce com a US-FISIO-005 (ADR 0437 §3 e §4, HANDOFF-F1 T-05). Casos: [`Index.casos.md`](Index.casos.md).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Listar os pacientes da clínica e cadastrar um novo a partir de um contato da empresa (existente ou
novo), sem duplicar nome nem telefone — o paciente É um `contact`.

## Goals — Features (faz)

- Lista PT-01: busca pelo nome/telefone do contato (servidor, `?q=`), filtro Ativos/Bloqueados com contagem — UC-FPI-01.
- Novo paciente em drawer 760px: escolhe um contato existente (que ainda não é paciente) ou cria um contato novo; queixa, objetivos, sessões por semana (1–7) e marcadores de condição da lista fechada — UC-FPI-01, UC-FPI-03.
- Atalhos J/K (percorre), Enter (abre a ficha), N (novo), / (busca).
- Só a clínica da sessão (UC-FPI-02); sem pacote ou sem `fisio.access`, 403 (UC-FPI-04). Cadastrar exige `fisio.editar`.

## Non-Goals — Features (NÃO faz)

- ❌ Editar nome/telefone do paciente — mora no contato.
- ❌ Apagar paciente — "excluir" é bloquear, na ficha (UC-FPS-07).
- ❌ Importar CSV, ações em lote, saved views — fora da US-FISIO-005.

## Refs

- ADR 0437 §3/§4 · HANDOFF-F1 · PT-01 Lista · UI-0013 · [RUNBOOK](../../../../../../../memory/requisitos/Fisio/RUNBOOK-pacientes.md)
