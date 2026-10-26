---
id: modules-producao-pages-producao-roteiros-index-charter
page: /producao/roteiros
component: Modules/Producao/Resources/js/Pages/Producao/Roteiros/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/producao-cv-os.jsx
owner: wagner
status: draft
last_validated: "2026-10-21"
parent_module: Producao
related_adrs: [93, 358, 431]
alcance:
  rota: /producao/roteiros
  rota_nome: producao.roteiros.index
  permission: producao.access
  menu_hook: Modules/Producao/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: producao_module
tier: B
charter_version: 1
---

# Page Charter — /producao/roteiros (DRAFT · roteiros por família)

> Nasce na thread 03 do playbook de Produção (ADR 0431). Casos: [`Index.casos.md`](Index.casos.md) ·
> âncora de design: alvo `producao--roteiros--index` (thread A1) · protótipo `producao-cv-os.jsx` → `Roteiros()`.
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Cadastrar o roteiro de cada família de produto, que vira as etapas da OS quando o orçamento é aprovado.

## Goals — Features (faz)

- Lista de roteiros agrupada por família; o roteiro escolhido mostra o fluxo (sequência e blocos em paralelo).
- Criar e ajustar roteiro em drawer de 760px: etapas, posto, horas e a opção do orçamento que liga a etapa.
- "Produtos que usam este roteiro": chips com os produtos ligados e "Ligar produto" (busca do DS, só produto ativo da empresa); ligar aqui tira o produto do roteiro anterior (UC-FLX-07 · UC-PCROT-05/06).
- No máximo 2 trilhas em paralelo, que se juntam numa etapa nomeada (UC-PCV-13) — a regra é do servidor.
- PT-BR em todo label e mensagem.

## Non-Goals — Features (NÃO faz)

- ❌ Editor livre de fluxograma (regra do protótipo e do UC-PCV-13).
- ❌ Criar a OS a partir do orçamento (UC-PCV-12/14, thread 08).

## UX Targets

- Cabe em 1280px sem scroll horizontal.

## Refs

- Playbook `prototipo-ui/cowork/Wagner/cowork-inbox/producao/playbook/03-cadastros.md` · ADR 0431 · UI-0013
