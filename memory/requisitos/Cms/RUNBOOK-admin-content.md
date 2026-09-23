---
id: requisitos-cms-runbook-admin-content
title: "RUNBOOK — /cms/cms-page (Conteúdo do site · Blade → Inertia)"
module: Cms
tela: Admin/Content/Index
owner: W
status: rascunho
last_validated: "2026-09-23"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
spec_ref: memory/requisitos/Cms/SPEC.md
---

# RUNBOOK — `/cms/cms-page` (conteúdo do site, Inertia/React)

F1 do MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) para a
thread **Cms/01** do playbook `prototipo-ui/cowork/Wagner/cowork-inbox/cms/playbook/`. Cobre a
US-CMS-004 do [SPEC](SPEC.md).

- **Fonte de design:** [`CMS-F1-2026-08-19.md`](../../../prototipo-ui/cowork/Wagner/cowork-inbox/cms/CMS-F1-2026-08-19.md)
  §1 (peças → DS), §2 (13 regras) e §3 (18 UC). O `cms-page.jsx` citado lá **não está no
  espelho** — a âncora medida é o documento, não um render.
- **Pacote pronto do [CC]** (charter completo, 18 UC, contrato de tela, teste-âncora):
  mesma pasta, `PEDIDO-CL-cms-trio.md`. Entra por fases — abaixo.

## Fases (1 PR por fase, ≤300 linhas)

| Fase | Entrega | Blade que morre | Status |
|---|---|---|---|
| **1** | Lista `Admin/Content/Index.tsx` + `index()` → `Inertia::render` + contrato UC-CMS-01/02/03/20/21 | `page/index.blade.php` (fica órfã; delete na F5) | este PR |
| 2 | Editor em drawer PT-02 (`create/edit`), derivação de `meta_description` no servidor (R7) | `page/create`, `page/edit` | pendente |
| 3 | `destroy` recusa `layout` preenchido no servidor (UC-CMS-09) + fix do whitelist `type` (pedido §3.b) | — | pendente · decisão [W] no whitelist |
| 4 | Detalhes do site (`SettingsController`) | `settings/index` + 8 partials | pendente |
| 5 | Cutover: apagar Blades órfãs + charter `live` com screenshot [W2] | todas acima | pendente |

## Fase 1 — o que muda e o que NÃO muda

- `index()` deixa de devolver `cms::page.index` e passa a `Inertia::render('Admin/Content/Index')`
  com `tipo`, `contagens` (eager, uma consulta agrupada) e `paginas` (`Inertia::defer`).
- **Ordem:** `priority IS NULL, priority ASC` — vazio no fim (R6). O Blade usava `orderBy('priority')`
  cru, que no MySQL põe NULL **primeiro**. Mudança deliberada, travada pelo UC-CMS-01.
- **Tipo fora do domínio** (`?type=banner`) cai em `page`: a aba nunca mostra enum cru (A1).
- **Excluir** chama o `destroy` existente por `fetch` com `X-Requested-With` — ele só aceita ajax
  e devolve JSON, então `router.delete` do Inertia quebraria. O botão some em página de sistema;
  a recusa **no servidor** é a fase 3.
- **Criar/Editar** continuam Blade (links diretos). Nada de TinyMCE novo.
- **Tier 0:** `cms_pages` não tem `business_id` (single-tenant por natureza, SCOPE.md). A rota
  segue `superadmin` + `throttle:60,1`; nenhuma prop sai sem passar por ela.

## Verificação

- Contrato: `Modules/Cms/Tests/Feature/CmsConteudoIndexContratoTest.php`, rodando na lane MySQL
  `verticais-pest.yml` (o módulo entrou no trigger e no `paths-filter` junto, senão a lane pula).
- Smoke pós-deploy (R1): `https://oimpresso.com/cms/cms-page?type=page` logado como superadmin →
  a lista carrega (não fica no esqueleto) e as três abas trocam.
