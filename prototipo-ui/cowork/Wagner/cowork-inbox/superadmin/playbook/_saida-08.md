---
sessao: "08"
titulo: Páginas (PageController) → Inertia, atrás da chave ?tela=nova
dono: "[CL]"
data: "2026-10-09"
base: "origin/main b7715dda"
---

# _saida-08 — Páginas do site em React (chave desligada)

Decisão [W] D-PAG (2026-10-07): *migrar para React*, seguindo separado do Cms (D1).

## Entregue

- `PageController@index` com `?tela=nova` → `Inertia::render('superadmin/Paginas/Index')`, prop
  `paginas` em `Inertia::defer` (id, título, slug, ordem, visível, resumo sem tag, conteúdo).
  **Sem a chave, a Blade `superadmin::pages.index` continua sendo o padrão.**
- `store`/`update`/`destroy`: em requisição Inertia, slug repetido vira erro no campo `slug` e o
  sucesso volta para `?tela=nova`. A resposta da Blade (redirect com `status`, JSON no `destroy`)
  ficou igual.
- Tela `Paginas/Index.tsx`: lista PT-01 (tabela) + drawer PT-02 (Sheet) para criar, editar e
  excluir com confirmação. A lista mostra resumo sem tag (a Blade imprimia o HTML cru).
- Trio: `.charter.md` + `.casos.md` (UC-SAPAG-01..06) + teste
  `Modules/Superadmin/Tests/Feature/SuperadminPaginasContratoTest.php`, incluído na lane
  `verticais-pest.yml` (sem a linha o teste não roda).
- RUNBOOK F1: `memory/requisitos/Superadmin/RUNBOOK-paginas.md`.

## Prova do json

| prova | estado |
|---|---|
| `PageController.php` contém `Inertia::render(` | ✅ |

## Fica com o [W]

- **Cutover (MWART F5):** tornar `?tela=nova` o padrão e apagar `pages/index|create|edit.blade.php`.
  A página pública `/page/{slug}` (`pages/show.blade.php`, layout do site) não entrou nesta thread.
- Sem âncora de design: não há `sa-paginas` no protótipo; a tela herda PT-01/PT-02.

## Pendente

- `SPEC.md` US-SUPER-005 `**Implementado em:**` ainda cita só a Blade; atualizar no cutover.
- Medida/alvo da tela: **NÃO MEDI** (a ficha não pede).
- Recibo pendente de upload ao Cowork (DesignSync indisponível nesta sessão).
