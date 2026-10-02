---
page: /import-sales
component: resources/js/Pages/ImportSales/Index.tsx
owner: wagner
status: draft
last_validated: "2026-10-02"
parent_module: Sells
related_prototype: prototipo-ui/cowork/Wagner/venda-blade.jsx
related_runbook: memory/requisitos/Sells/RUNBOOK-import-sales.md
tier: B
charter_version: 1
---

# Page Charter — Importação de vendas (`/import-sales`)

> Texto revisado em `prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/Importacao.charter.md`
> (frescor medido em 2026-08-22: a tela ainda era Blade), ajustado às decisões D2 e D3 de [W]
> (2026-10-02). Thread 05 do playbook `venda-menu`.
> **Fonte legado:** `import_sales/index + preview` · **Permissão:** `sell.create` (importar) ·
> `sell.delete` (reverter lote). Item de menu já existente no core — esta migração não toca o menu.
> **Protótipo:** `TelaImportar` em `venda-blade.jsx` · alvo medido
> `governance/design/targets/vendas--importacao--index.secoes.json` (header · tabs · enviar · instrucoes · importacoes).

## Mission

Trazer venda de fora por planilha, com prévia e mapeamento de colunas antes de gravar.

## Regras

- R1 Campos importáveis são 14 (+5 de tipos de serviço quando o módulo está ligado), com os
  rótulos do serviço (`ImportSalesService::campos`).
- R2 A prévia chega **pré-mapeada** por semelhança de nome (≥ 50%).
- R3 Obrigatórios: telefone OU e-mail do cliente; produto OU SKU; quantidade; preço unitário.
  Um campo não pode ser usado em duas colunas.
- R4 "Agrupar por" define o que vira uma venda só.
- R5 A venda importada nasce **finalizada**, baixa estoque e cria o cliente se não existir.
- R6 Erro para na linha e diz o número dela (produto, imposto ou unidade não encontrados, data
  inválida). Nada do lote é gravado.
- R7 **D2:** até `limiteSincrono` linhas (padrão 200) importa na hora; acima vai para a fila
  `sales-import` e esta tela mostra o andamento (na fila → importando N de M → concluído / erro).
- R8 **D3 (parada):** [W] decidiu que reverter lote deve **cancelar** em vez de apagar. Não há
  hoje caminho de cancelar venda sem efeito externo (o cancelamento do FSM dispara SEFAZ e
  gateway e não devolve estoque), então o reverter segue o legado — **apaga** — e a tela diz isso
  na confirmação.
- R9 SKU, unidade e local da baixa são sempre do próprio negócio (ADR 0093).

## Goals — Features (faz)

- Enviar planilha (.xlsx, .xls, .csv) e ir para a prévia.
- Baixar o arquivo modelo.
- Instruções com os campos importáveis e o que cada um exige.
- Lista dos lotes importados (mais novo primeiro) com busca por lote, fatura ou usuário.
- Andamento da importação em fila, atualizado sozinho enquanto o job roda.
- "Excluir lote" só para quem tem `sell.delete`, com confirmação.
- PT-BR em todo label/placeholder/mensagem.

## Non-Goals — Features (NÃO faz)

- ❌ Rota nova: as quatro rotas do legado bastam; o andamento vem por recarga parcial da própria `GET /import-sales`.
- ❌ Cancelar lote sem apagar (D3) — fica para depois da decisão sobre o caminho seguro.
- ❌ Editar uma venda importada nesta tela.

## UX Targets

- Cabe em 1280px sem scroll horizontal da página.

## Refs

- Casos: `Index.casos.md` ao lado · tela irmã `Preview.tsx` (prévia + mapeamento).
- Padrão de Tela: PT-01 Lista.
- Constituição UI v2: UI-0013.
