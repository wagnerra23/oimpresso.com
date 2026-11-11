---
last_validated: "2026-11-11"
slug: runbook-comunicacao-visual-index
title: "RUNBOOK — /comunicacao-visual (Orçamento por m²)"
type: runbook
module: ComunicacaoVisual
page: /comunicacao-visual
component: resources/js/Pages/ComunicacaoVisual/Index.tsx
status: rascunho
updated_at: 2026-11-11
version: 0.1
owner: W
---

# RUNBOOK — /comunicacao-visual (Orçamento por m²)

> **F1 PLAN do MWART** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) para a tela que
> **já está em React**. Criado na thread 01 do playbook Módulos faltantes (contratos de tela), que só põe atributo
> `data-contract` na Page: o hook `block-mwart-violation` exige RUNBOOK da tela antes de qualquer edição, e esta não
> tinha. Charter: [`Index.charter.md`](../../../resources/js/Pages/ComunicacaoVisual/Index.charter.md) · casos:
> [`Index.casos.md`](../../../resources/js/Pages/ComunicacaoVisual/Index.casos.md) · SDD:
> [`SDD-tela-orcamento-m2-v1.0.md`](SDD-tela-orcamento-m2-v1.0.md).

## 1. Estrutura

- Rota em `Modules/ComunicacaoVisual/Routes/web.php` (`Inertia::render('ComunicacaoVisual/Index')`), com as props
  `bizName`, `materiais`, `podeCriar`, `podeVerCusto`, `orcamento` (`?orcamento={id}`) e `recentes` (deferida).
- Calculadora de m² no cliente; "Conferir no servidor" recalcula no backend (a conta do servidor é a que vale) e,
  para quem gerencia materiais, mostra custo e margem por peça.
- Depois de conferir: "Salvar orçamento" (ou "Salvar alterações" no reaberto), "Anexar arquivo", "Enviar no WhatsApp"
  (gera o link de aceite e oferece "Copiar link" e "Abrir no WhatsApp", no WhatsApp do próprio vendedor) e
  "Aprovar e criar OS".
- Cartões "Orçamentos salvos" (reabre qualquer um) e "Roteiro da produção" (roteiro padrão com que a OS entra no
  quadro do PCP, para quem cuida do catálogo).
- APIs que a tela chama: `/comunicacao-visual/api/calcular`, `orcamentos` (POST/PUT), `orcamentos/{id}/aprovar`,
  `orcamentos/{id}/link`, `orcamentos/{id}/arquivos` e `roteiro-padrao`.

## 2. Âncoras de contrato

| âncora | elemento |
|---|---|
| `cabecalho` | `<div>` sem classe em volta do `PageHeader` canônico (que não repassa atributos ao DOM) |
| `calculadora` | `<Card>` da calculadora (repassa o atributo ao `<div>`) |
| `ajustes` | grade de acabamento/instalação/entrega e desconto |
| `total` | total estimado + conferir no servidor |
| `em-breve` | `<Card>` "Em breve nesta tela" |

Contrato: `governance/design/contracts/comunicacao-visual.contract.json`. Rodar:
`node scripts/contrato-de-tela.mjs --contract <arquivo>`. As âncoras são só atributos: não mudam dado, handler, copy,
classe nem cálculo.

## 3. Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

Catálogo de materiais e preço vêm escopados por `business_id` da rota; a Page só mostra o que recebe. Qualquer mudança
no cálculo segue a regra mestre de valor (`memory/proibicoes.md`): dupla confirmação e antes→depois.

## 4. O que esta tela NÃO faz

- Não tem o bloco "Na fábrica agora" (PCP) do protótipo: a produção é vista em `/producao/quadro`, no Modules/Producao.
- Não gera PDF do orçamento. "Enviar no WhatsApp" existe desde 2026-10-27 (#9860), mas manda o link de aceite, não
  um PDF, e quem envia é o WhatsApp do vendedor, não o sistema. "Salvar orçamento" existe desde 2026-10-21.
- Não fatura a OS: nenhum código liga a OS a venda, NF-e ou NFS-e.
- Construir ou podar o que falta é decisão de produto ([W]).

> Registro de 2026-10-13: esta seção dizia que a tela não tinha "Salvar orçamento" nem "Enviar PDF no WhatsApp".
