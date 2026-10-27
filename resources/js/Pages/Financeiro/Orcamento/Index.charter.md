---
page: /financeiro/orcamento
component: resources/js/Pages/Financeiro/Orcamento/Index.tsx
owner: wagner
status: draft
parent_module: Financeiro
related_prototype: prototipo-ui/cowork/Wagner/financeiro-centro-custo-orcado.html
related_us: [US-FIN-076]
alcance:
  rota: /financeiro/orcamento
  rota_nome: financeiro.orcamento.index
  permission: financeiro.relatorios.view
  menu_hook: Modules/Financeiro/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: financeiro_module
tier: B
charter_version: 1
---

# Page Charter — Financeiro/Orcamento/Index (DRAFT · PT-01 Lista)

> Fonte de design: aba "Orçado × realizado" do protótipo `financeiro-centro-custo-orcado.html`,
> aprovado por [W] em 2026-10-27. Backend: `OrcamentoController` + `OrcamentoService`.

## Mission

Mostrar, no mês, quanto foi orçado por conta do plano de contas, quanto aconteceu e o desvio,
no total da empresa ou por centro de custo (US-FIN-076).

## Goals — Features (faz)

- Lista todas as contas de receita, despesa e custo que aceitam lançamento, agrupadas em Receitas e Despesas, com orçado, realizado, desvio, desvio % e barra de consumo.
- Filtra por mês, por centro de custo (todos · geral sem centro · um centro) e por regime (competência = títulos; caixa = baixas).
- Edita o orçado na célula (Enter ou sair do campo grava; vazio ou zero apaga).
- Linha de resultado do mês (receitas − despesas) no orçado, no realizado e no desvio.
- PT-BR em todo label/placeholder/mensagem.

## Non-Goals — Features (NÃO faz)

- ❌ Não edita o orçado em "Todos os centros": ali ele é a soma de geral + centros; edita-se em Geral ou num centro.
- ❌ Não lança título nem baixa: o realizado só lê o que já existe.
- ❌ Não mostra dado de outra empresa (`business_id` da sessão em toda query).

## UX Targets

- Cabe em 1280px; a tabela rola na horizontal dentro do cartão em tela estreita.

## Contrato visual

`governance/design/contracts/financeiro-orcamento.contract.json` — seções `cabecalho`, `filtros`, `lista`.

## Refs

- Casos: [Index.casos.md](Index.casos.md) · teste `Modules/Financeiro/Tests/Feature/OrcamentoTelaContratoTest.php` (tela) e `OrcamentoContratoTest.php` (serviço)
- Padrão de Tela: PT-01 Lista · Constituição UI v2: UI-0013
