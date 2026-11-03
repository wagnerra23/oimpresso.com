---
page: /financeiro/exportacao-contabil
component: resources/js/Pages/Financeiro/Contabilidade.tsx
owner: wagner
status: draft
parent_module: Financeiro
related_prototype: prototipo-ui/cowork/Wagner/financeiro-contabil-page.jsx
alcance:
  rota: /financeiro/exportacao-contabil
  rota_nome: financeiro.exportacao-contabil.index   # name() da rota — é o que o guard procura
  permission: financeiro.relatorios.view   # a mesma do download (ExportacaoContabilController)
  menu_hook: Modules/Financeiro/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: financeiro_module              # superadmin_package
tier: B
charter_version: 1
---

# Page Charter — Financeiro/Contabilidade (DRAFT · carimbado do PT-02)

> Nascida do Padrão de Tela **PT-02 Form/Drawer** via `criar-tela.mjs` (UI-0013 — herança
> de padrão, NÃO bespoke). Golden do arquétipo: [PT-02](../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-02-Form-Drawer.md).
> Preencha os TODO antes de subir de `draft` → `live` (exige screenshot aprovado por Wagner).

## Mission

Levar os lançamentos do mês para o sistema do contador: escolher competência e sistema, preencher o
código de cada conta no plano do contador e baixar o arquivo. Fonte de forma: aba "Exportar para o
contador" do protótipo `financeiro-contabil-page.jsx`. Runbook: [RUNBOOK-contabilidade](../../../../memory/requisitos/Financeiro/RUNBOOK-contabilidade.md).

## Goals — Features (faz)

- Competência (mês) e sistema do contador (Domínio, Alterdata; Questor "em breve")
- De-para "Código no contador" por conta bancária, plano de contas que aceita lançamento, juros/multa e descontos; selo "N sem código" / "Completo"; salvar (código vazio apaga)
- "Gerar arquivo do <sistema>": baixa o arquivo do servidor; com 422 lista as pendências que o servidor devolveu
- Multi-tenant: o de-para só lista e só grava origens da empresa da sessão (UC-EXPC-06/08)
- PT-BR em todo label/placeholder/mensagem

## Divergências deliberadas do protótipo

- O protótipo desabilita "Gerar" com qualquer código vazio; o servidor só cobra as contas usadas no mês. A tela segue o servidor.
- Sem o resumo "Baixas no mês / Lançamentos / Movimento no banco" nem a amostra das primeiras linhas: são valores e exigem prova dupla (regra mestre) — PR à parte.
- Sem a aba "Importar títulos": não há backend (US-FIN-034) e gravar título é VALOR — decisão [W].

## Non-Goals — Features (NÃO faz)

- _pendente [W]_ — Non-Goals e anti-hooks são preenchidos só pelo [W] (`charter-write`).

## UX Targets

- Cabe em 1280px sem scroll horizontal (a tabela do de-para rola abaixo disso)

## Refs

- Padrão de Tela: PT-02 Form/Drawer (useForm + FormSection + FormGrid)
- Constituição UI v2: UI-0013
- Casos: [`Contabilidade.casos.md`](Contabilidade.casos.md)
