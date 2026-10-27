---
page: /financeiro/centros-custo
component: resources/js/Pages/Financeiro/CentrosCusto/Index.tsx
owner: wagner
status: draft
parent_module: Financeiro
related_prototype: prototipo-ui/cowork/Wagner/financeiro-centro-custo-orcado.html
related_us: [US-FIN-075]
alcance:
  rota: /financeiro/centros-custo
  rota_nome: financeiro.centros-custo.index
  permission: financeiro.dashboard.view
  menu_hook: Modules/Financeiro/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: financeiro_module
tier: B
charter_version: 1
---

# Page Charter — Financeiro/CentrosCusto/Index (DRAFT · PT-01 Lista)

> Fonte de design: aba "Centros de custo" do protótipo `financeiro-centro-custo-orcado.html`,
> aprovado por [W] em 2026-10-27. Backend: `CentroCustoController` (rotas `financeiro.centros-custo.*`).

## Mission

Cadastrar os centros de custo da empresa, para ratear títulos entre eles e filtrar DRE, fluxo e
orçamento por centro (US-FIN-075).

## Goals — Features (faz)

- Lista os centros da empresa: código, nome, situação, quantos títulos e quanto foi rateado na competência do mês.
- Busca por código ou nome, na própria lista.
- Cria e edita centro (código opcional, nome) num drawer; código e nome não se repetem na empresa.
- Inativa e reativa o centro.
- PT-BR em todo label/placeholder/mensagem.

## Non-Goals — Features (NÃO faz)

- ❌ Não exclui centro com título rateado: o model recusa e a tela não oferece exclusão (só inativar).
- ❌ Não rateia título aqui: o rateio é feito no drawer do título.
- ❌ Não mostra centro de outra empresa (`business_id` da sessão em toda query).

## UX Targets

- Cabe em 1280px sem scroll horizontal (monitor da Larissa/ROTA LIVRE).

## Contrato visual

`governance/design/contracts/financeiro-centros-custo.contract.json` — seções `cabecalho`, `filtros`, `lista`.

## Refs

- Casos: [Index.casos.md](Index.casos.md) · teste `Modules/Financeiro/Tests/Feature/CentroCustoCadastroContratoTest.php`
- Padrão de Tela: PT-01 Lista · Constituição UI v2: UI-0013
