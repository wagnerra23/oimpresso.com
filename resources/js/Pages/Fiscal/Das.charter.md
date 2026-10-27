---
page: /fiscal/das
component: resources/js/Pages/Fiscal/Das.tsx
owner: wagner
status: draft
parent_module: Fiscal
related_prototype: prototipo-ui/cowork/Wagner/prototipos/fiscal-das.html
related_us: [US-FISCAL-030]
alcance:
  rota: /fiscal/das
  rota_nome: fiscal.das        # name() da rota — é o que o guard procura
  permission: fiscal.access      # declarada em DataController::user_permissions
  menu_hook: Modules/Fiscal/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: fiscal_module              # superadmin_package
tier: B
charter_version: 1
---

# Page Charter — Fiscal/Das (DRAFT · PT-04 · atrás de flag)

> Nascida do PT-04 Dashboard via `criar-tela.mjs`. Forma do protótipo
> [`fiscal-das.html`](../../../../prototipo-ui/cowork/Wagner/prototipos/fiscal-das.html), publicado como
> Artefato antes do `.tsx` (ADR 0438). Contrato: [Das.casos.md](Das.casos.md). Conta:
> [`apuracao-das.casos.md`](../../../../memory/requisitos/Fiscal/_telas/apuracao-das.casos.md).

## Mission

Mostrar ao dono e ao contador a prévia do DAS do mês pelo anexo que o contador usa, com a conta da
LC 123/2006 art. 18 §1º-A escrita por extenso.

## Goals — Features (faz)

- Competência e anexo na query string; o business vem da sessão.
- Quatro números: receita do mês, RBT12, alíquota efetiva e DAS do mês, com o vencimento.
- A conta com os números da empresa e a tabela de faixas do anexo, com a faixa atual marcada.
- Avisos de sublimite e de falta de RBT12; as quatro perguntas ao contador.
- PT-BR em todo label/placeholder/mensagem.

## Non-Goals — Features (NÃO faz)

- ❌ Escolher o anexo pelo usuário sem o contador: sem anexo a tela pede para escolher.
- ❌ Gravar, lançar a pagar ou transmitir o PGDAS-D.
- ❌ Recalcular valor no navegador: a conta vem pronta do `ApuracaoDasService`.
- ❌ Comparar com a estimativa ≈6% do Financeiro nesta fatia: o protótipo mostra, mas a receita
  recebida tem dono no `ImpostosController` e não se duplica.
- ❌ Entrar na sub-nav do Fiscal enquanto `fiscal.das_apuracao_enabled` estiver desligada.

## UX Targets

- Cabe em 1280px sem scroll horizontal.
- Sem anexo, um estado vazio com instrução, nunca zeros.

## Refs

- Padrão de Tela: PT-04 Dashboard (KpiGrid + KpiCard) dentro do FxShell do Fiscal.
- RUNBOOK: `memory/requisitos/Fiscal/RUNBOOK-das.md`.
