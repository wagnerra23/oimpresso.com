---
slug: configuracoes-runbook-taxonomias
title: "Configurações — Runbook da tela de listas (Fontes, Estágios, Categorias de acompanhamento, Departamentos, Cargos)"
type: runbook
module: Configuracoes
tela: Configuracoes/Taxonomias/Index
owner: F
status: ativo
last_validated: "2026-11-27"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Listas de cadastro dos módulos (`/taxonomies?type=…`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md))
> **Origem:** triagem do Grupo 2, decisão [F] 2026-11-27: *"Tem que ligar a tela nova em react, não em blade."*
> As gavetas "Fontes e estágios" (CRM) e "Departamentos" e "Cargos" (RH) do protótipo são, em produção, o
> mesmo `TaxonomyController` com `type` diferente. Uma tela React atende as cinco listas.
> **Fonte de design:** `prototipo-ui/cowork/Wagner/crm-blade-telas.jsx` → `Taxonomias()` (as três listas do
> CRM com "Em uso") e `prototipo-ui/cowork/Wagner/hrm-page.jsx` → `Taxonomia()` (adicionar, renomear, excluir
> com contagem de uso).
> **Valor/estoque:** nada. São nomes de cadastro.

## Estado final esperado

`GET /taxonomies?type=X`, para X em `source` · `life_stage` · `followup_category` (CRM) · `hrm_department` ·
`hrm_designation` (RH), responde Inertia `Configuracoes/Taxonomias/Index` quando a flag `useV2Taxonomias` está
ligada (default ligado, decisão [F]). `type=product` e qualquer outro tipo seguem na Blade (categorias de produto
têm tela própria em `/units`). Com `?classico=1`, a Blade.

A tela mostra as listas do mesmo módulo lado a lado em abas (CRM: Fontes · Estágios de vida · Categorias de
acompanhamento; RH: Departamentos · Cargos), cada item com código, descrição e quantos registros o usam. Adiciona,
renomeia e exclui pelos endpoints que a Blade já usa (`store`, `update`, `destroy`, JSON).

## 1. Objetivo

Trocar a Blade (DataTable + modais) pela tela do protótipo, sem mudar quem vê o quê.

## 2. Pré-condições

- O tipo só vale se o módulo dono o declara (`ModuleUtil::getTaxonomyData`): o CRM só declara Fontes com
  `crm.access_sources`, Estágios com `crm.access_life_stage` e Categorias com a permissão de acompanhamento; o RH
  declara Departamentos e Cargos com o módulo ativo. Sem a declaração, a rota segue na Blade (paridade).
- Tabela `categories` (`business_id`, `category_type`, `name`, `short_code`, `description`).
- Uso: `contacts.crm_source` · `contacts.crm_life_stage` · `crm_schedules.followup_category_id` ·
  `users.essentials_department_id` · `users.essentials_designation_id`, sempre no `business_id` da sessão.

## 3. Passo-a-passo

1. **Onda 1 (este PR):** RUNBOOK + ramo Inertia + Page + charter + casos + teste de contrato (98 × 99) + trava
   de exclusão de item em uso para os cinco tipos.
2. Depois do merge: abas "Fontes e estágios" (CRM) e "Departamentos" e "Cargos" (RH) no topo de cada módulo.

## 9. DoD checklist

- [x] RUNBOOK + paridade (abaixo)
- [x] Ramo Inertia + Page + charter + casos + teste (UC-TAX-01..06)
- [ ] Abas nos módulos (CRM e RH)
- [ ] Smoke biz=1 em produção

## 10. Paridade com a Blade

| # | Blade hoje | React (Onda 1) |
|---|---|---|
| 1 | Lista do tipo, só do negócio da sessão | idem |
| 2 | Título e subtítulo vêm do módulo (`heading`, `sub_heading`) | idem, mais as abas irmãs do mesmo módulo |
| 3 | Código só aparece se o módulo habilita (`enable_taxonomy_code`) | idem |
| 4 | Adicionar/editar por modal, JSON `{success, msg}` | inline, mesmos endpoints e mesma resposta |
| 5 | Excluir apaga mesmo com o item em uso | **muda:** item em uso não sai (mensagem com a contagem) — regra do protótipo do RH, estendida aos cinco tipos para não deixar lead ou colaborador apontando para item apagado |
| 6 | Subcategoria (`parent_id`) | os cinco tipos não usam subcategoria (`enable_sub_taxonomy` falso); a tela não oferece |

## 11. Pegadinhas

- `getTaxonomyData` devolve vazio quando o usuário não tem a permissão do módulo: aí a rota cai na Blade, como
  antes — a tela React não vira atalho para quem o módulo esconde.
- `destroy` e `update` só respondem com `X-Requested-With` (`request()->ajax()`); a tela manda o cabeçalho.
- Inertia v3 manda `X-Requested-With`: o ramo DataTable do `index()` exige `! request()->inertia()`.

## 12. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
