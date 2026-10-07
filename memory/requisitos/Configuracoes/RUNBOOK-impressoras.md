---
slug: configuracoes-runbook-impressoras
title: "Configurações — Runbook da tela Impressoras"
type: runbook
module: Configuracoes
tela: Configuracoes/Impressoras/Index
owner: W
status: ativo
last_validated: "2026-10-07"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Impressoras (`/printers`)

> **Tipo:** runbook reproduzível · MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) · thread `sistema/playbook/04` (1ª de 3 telas: Impressoras → Código de barras → Locais)
> **Fonte de design:** `prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx` → `Impressoras()` (rota `cfg-impressoras`).
> **Decisão D1 ([W] 2026-10-06):** Configurações é uma tela com abas, como o protótipo. Cada aba continua com a
> própria URL e a própria permissão; as Pages moram em `resources/js/Pages/Configuracoes/<Aba>/Index.tsx`.

## Estado final esperado

`GET /printers` (`PrinterController::index`) responde Inertia `Configuracoes/Impressoras/Index` **quando a flag
`useV2ConfiguracoesImpressoras` está ligada** para o negócio; desligada, segue a Blade `printer/index`. Cadastrar e
editar abrem um drawer na própria tela; excluir confirma em diálogo. Os três gravam pelos endpoints de sempre.

## 1. Objetivo

Trocar a Blade (DataTable + páginas `create`/`edit` separadas) pela aba React, sem mudar o que `store`, `update` e
`destroy` gravam nem a permissão.

## 2. Pré-condições

- Permissão: `access_printers` (o controller exige em todas as ações).
- Tabela `printers` (`business_id` NOT NULL + FK; `connection_type` enum `network|windows|linux`).

## 3. Passo-a-passo

1. **F1/F2 (este PR):** RUNBOOK + [`impressoras-parity.md`](./impressoras-parity.md) + Pest baseline do comportamento
   da Blade (`tests/Feature/Configuracoes/ImpressorasBaselineTest.php`, lane `acessos-pest`). Controller intocado.
2. **F3:** `index()` ganha o ramo Inertia atrás da flag (padrão `LicencaLogController::FLAG_V2`); o ramo DataTable fica
   com `ajax() && ! inertia()`. Page + charter + casos no mesmo PR.
3. **F4:** smoke em biz=1 com a flag ligada só para biz=1 (`flag:set useV2ConfiguracoesImpressoras --biz=1 --enabled=true`).
4. **F5 (cutover):** decisão [W] — não faz parte da thread.

## 4. Tokens CSS

Só tokens do DS. Sem cor crua.

## 5. Estados visuais

Lista · vazio ("Sem impressora o cupom sai pelo diálogo do navegador") · busca sem resultado · drawer novo/editar ·
confirmação de exclusão · aviso da última ação.

## 6. Responsividade

Tabela rola na horizontal abaixo de 768px; drawer ocupa a largura em telas estreitas.

## 7. Atalhos

`/` foca a busca · `n` abre "Adicionar impressora".

## 8. Component contract

Seções `data-contract`: `page-header`, `toolbar`, `impressoras-table`, `vazio`, `impressora-form`, `confirm-excluir`.
Contrato de forma: depois do alvo medido.

## 9. DoD checklist

- [x] RUNBOOK + paridade
- [x] Pest baseline do comportamento Blade (tenant 98 × 99)
- [ ] Ramo Inertia atrás da flag + Page + charter + casos (F3)
- [ ] Smoke biz=1 com a flag ligada só para biz=1 (F4)
- [ ] Cutover e remoção das Blades `printer/*` (F5, decisão [W])

## 10. Pegadinhas e divergências do protótipo

- O Inertia v3 manda `X-Requested-With` em toda visita: o ramo `request()->ajax()` do `index()` engoliria a visita
  sem a perna `! $request->inertia()` (medido no `LicencaLogController`, 2026-09-23).
- `destroy()` só responde a `ajax()` — a tela nova chama com `X-Requested-With` e lê `{success, msg}`.
- O `store()`/`update()` limpam `path` quando a conexão é `network`, e `ip_address`/`port` quando é `windows`/`linux`.
  A tela mostra só os campos da conexão escolhida, e o servidor continua limpando.
- A Blade mostra "Editar"/"Excluir" com `@can('printer.update')`/`@can('printer.delete')`, permissões que **não existem**
  no `PermissionCatalog` — o controller só confere `access_printers`. A tela nova segue o controller.
- O protótipo tem "Testar" (imprime cupom de teste): **não existe endpoint** no legado. Fica fora.
- O perfil "Star" do protótipo é `SP2000` ("Star Branded") no enum; os rótulos vêm de `Printer::capability_profiles()`.
- `edit($id)` de impressora de outro negócio usa `find()` (devolve `null`) e a Blade quebra — o drawer novo não passa por ali.

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)
