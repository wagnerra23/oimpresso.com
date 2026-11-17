---
id: modules-producao-pages-producao-os-show-charter
page: /producao/ordens/{id}
component: Modules/Producao/Resources/js/Pages/Producao/Os/Show.tsx
related_prototype: prototipo-ui/cowork/Wagner/producao-cv-os.jsx
owner: wagner
status: draft
last_validated: "2026-11-09"
parent_module: Producao
related_adrs: [93, 358, 431, 436]
alcance:
  rota: /producao/ordens/{id}
  rota_nome: producao.ordens.show
  permission: producao.access
  menu_hook: Modules/Producao/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: producao_module
tier: B
charter_version: 1
---

# Page Charter — /producao/ordens/{id} (DRAFT · Ficha da OS por item)

> Auditoria do módulo Produção, lote 3 (thread 08 do playbook). Casos: [`Show.casos.md`](Show.casos.md).
> Âncora visual: `FichaOs` de `producao-cv-os.jsx` (classes `pcv-*` do bundle, recopiado na pcv15 no lote 3.1).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Abrir uma OS por item e ver, num lugar só, em que situação ela está e como anda o roteiro: cada etapa com setor,
posto, responsável e o tempo apontado contra o previsto. Daqui o PCP libera, refaz e cancela o item.

## Goals — Features (faz)

- Cabeçalho com número, produto, cliente, venda, entrega e situação da OS (UC-PCV-10).
- Progresso (etapas feitas sobre as não canceladas) e tempo apontado × previsto (UC-PCV-10).
- Etapas na ordem do roteiro, com a trilha quando são em paralelo; apontado acima do previsto em vermelho.
- Liberar com o responsável de cada etapa aberta; Refazer em Retrabalho; Cancelar item em Aguardando liberação
  ou Aguardando material (UC-PCOSF-01). Os portões de papel são conferidos de novo nas rotas JSON do lote 2.2.
- Aba Custo, só leitura e só para `producao.desempenho.ver`: orçado × realizado, margem e de onde vem o realizado
  (máquina, pessoa, material, terceiros). Os números vêm prontos do backend (UC-PCOSF-03).
- Abas Materiais (previsto pela receita × apontado, com o que falta) e Arte e arquivos (versões, a que vale,
  trocar e reenviar pelas rotas de aprovação que já existem) — UC-PCOSF-04 e 05.
- PT-BR em todo label e mensagem.

## Non-Goals — Features (NÃO faz)

- ❌ Editar custo, etapa ou roteiro aqui. Custo é só leitura (lote 3.3); roteiro muda em Roteiros.

## UX Targets

- Forma: página, não o drawer de 760px do protótipo — a Ficha é aberta por endereço a partir do Quadro e da Linha (lote 3.5).
- Cabe em 1280px sem scroll horizontal.
- O cabeçalho e as etapas vêm na primeira resposta; custo, materiais, artes e a lista de responsáveis do Liberar são deferidos.

## Refs

- Lote 2.2 (#10441): rotas Liberar, Refazer e Cancelar item.
- ADR 0436 (OS por item) · ADR 0093 (multi-tenant) · ADR 0358 (tenant 98 nos testes).
