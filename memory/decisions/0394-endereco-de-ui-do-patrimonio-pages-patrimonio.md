---
slug: 0394-endereco-de-ui-do-patrimonio-pages-patrimonio
number: 394
title: "Endereço de UI do Patrimônio é `Pages/Patrimonio/**` — módulo próprio, não seção do Estoque"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-08"
module: null
tags: [patrimonio, assetmanagement, ui, endereco, inertia, mwart, sidebar, ghost]
supersedes: []
superseded_by: []
supersedes_partially: []
related:
  - 0180-sidebar-v3-5-grupos-ghosts-header
  - 0182-pageheadertabs-canon-pattern-telas
  - 0104-processo-mwart-canonico-unico-caminho
---

# ADR 0394 — Endereço de UI do Patrimônio é `Pages/Patrimonio/**`

## Contexto

O `Modules/AssetManagement` (Patrimônio) é **100% Blade**: 6 `Route::resource` sob o prefixo
`asset`, **zero `Inertia::render`** nos 7 controllers, e **zero** arquivos casando
`(?i)(patrimonio|asset)` em `resources/js/Pages/` (medido em `c7bd83944f42`). Nenhuma tela
React existe.

A migração estava travada por **uma** pergunta de endereço, e travava 46 arquivos — 7 Pages +
7 charters + 7 casos + 2 `_shared` + 6 `_components`, 7 `.contract.json`, 6 controllers →
Inertia + `Routes/web.php`, 3 testes de tela, mais a ADR e o `SCOPE.md`. Errar o endereço
custa refazer ~12 deles, porque muda import, rota, breadcrumb, sidebar e o caminho dos charters.

**Duas ADRs existentes foram lidas como se divergissem:**

| ADR | o que diz | linha |
|---|---|---|
| 0180 | `AssetManagement` → grupo `operar`, **"ghost de Estoque"** | `0180:256` |
| 0182 | tabela de SubNav lista **"Estoque (AssetManagement+)"** apontando `Pages/Estoque/_shared/EstoqueSubNav.tsx` | `0182:247` |

E o `SCOPE.md` do módulo não decidia: `migracao_ui: "bloqueado-escopo — aguarda decisao [W]"`.

**A divergência era aparente, não real** — e é o que esta ADR reconcilia: as duas falam de
**agrupamento na sidebar**, que é uma decisão de *navegação*; nenhuma delas decide **onde o
arquivo mora**. Ser ghost de Estoque no menu não implica morar em `Pages/Estoque/`.

## Decisão

**O endereço de UI do Patrimônio é `resources/js/Pages/Patrimonio/**`** — módulo próprio.

Decidido por [W] em **2026-09-04** e reafirmado em 2026-09-08. O registro de 04/09 já estava
no `main`, no cabeçalho do `.github/workflows/modules-pest.yml:36` (commit `d6457184ea`,
[PR #6784](https://github.com/wagnerra23/oimpresso.com/pull/6784)), e a lane de CI já apontava
para `resources/js/Pages/Patrimonio/**` (`:48`, `:71`). O que faltava era o **dono canônico**
— o `SCOPE.md` — refletir isso; ele seguia dizendo `bloqueado-escopo`, e os dois se
contradiziam no `main`.

**O que NÃO muda:** o agrupamento de sidebar continua como a 0180 define — Patrimônio segue
**ghost de Estoque** no grupo `operar`, com o item apontando para `AssetController::dashboard`
(`DataController.php:109-138`). Endereço de pasta e agrupamento de menu são eixos
independentes, e esta ADR só decide o primeiro.

## Consequências

**Destrava** a frente de UI do módulo, que se reescreve como **5–7 threads, uma por tela** —
sete telas não cabem numa thread só. As 7 telas estão confirmadas na fonte visual
(`prototipo-ui/cowork/Wagner/patrimonio-page.jsx:835`): **Painel · Bens · Alocações · Manutenções ·
Garantias · Auditoria · Configurações**.

**Obriga**, no mesmo PR desta ADR: `SCOPE.md:4` sai de `bloqueado-escopo` para o endereço
decidido, e o playbook (`00-INDICE.md` fonte JSON + `06-ui-bloqueada.md`) deixa de declarar a
decisão como pendente — senão o `placar-indice.mjs` segue derivando "bloqueada" de um estado
que não existe mais.

**Não autoriza pular o MWART.** Cada tela segue o processo canônico da
[ADR 0104](0104-processo-mwart-canonico-unico-caminho.md) — RUNBOOK antes do `.tsx`, charter +
casos ao lado, e o merge do `.tsx` continua humano. Ter endereço não é ter tela; ter protótipo
não é ter autorização de escopo.

**Resíduo declarado:** as demais decisões abertas do módulo (custo de manutenção; Garantias
como tela ou filtro; Auditoria aqui ou no `Modules/Auditoria`; depreciação linear ou SAC;
baixa por `status` ou tabela; transferência entre locais; QR/scan mobile; placa veicular
compartilhada com a Oficina) **seguem abertas** e são de [W]. Nenhuma delas bloqueia o
endereço, e o endereço não as resolve.
