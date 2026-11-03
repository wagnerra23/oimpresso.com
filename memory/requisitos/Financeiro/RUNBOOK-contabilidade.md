---
slug: financeiro-runbook-contabilidade
title: "Financeiro — Runbook da tela Contabilidade (exportar lançamentos para o contador)"
type: runbook
module: Financeiro
tela: Financeiro/Contabilidade
owner: W
status: ativo
last_validated: "2026-11-03"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
  - 0438-artefato-substitui-cowork-como-superficie-do-prototipo
---

# RUNBOOK — Contabilidade (`/financeiro/exportacao-contabil`)

> **Tipo:** F1 PLAN do processo MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).
> **Dona:** [US-FIN-077](SPEC.md) — a fatia 1 (backend, 2026-10-27) deixou "falta a tela (de-para + botões)".
> **Fonte de forma:** `prototipo-ui/cowork/Wagner/financeiro-contabil-page.jsx`, aba "Exportar para o contador".
> **Origem:** lote Financeiro do programa "protótipo em produção" ([W] 2026-11-03).

Não é migração de Blade: a tela não existia. Antes dela, o arquivo só saía pela rota de download
digitando a URL, e o de-para (`fin_contabil_contas`) não tinha onde ser preenchido.

## 1. Objetivo

Uma tela onde o financeiro escolhe a competência e o sistema do contador, preenche o código de cada
conta no plano do contador e baixa o arquivo. Com de-para incompleto, o arquivo não sai e a tela
lista as pendências que o servidor devolveu.

## 2. Pré-condições

- Backend da fatia 1: `ExportacaoContabilService` + rota de download (UC-EXPC-01..05).
- Permissão `financeiro.relatorios.view` (a mesma do download e da classe de custo do plano).
- Fora do escopo: a aba "Importar títulos" (US-FIN-034, sem backend; gravar título é VALOR — decisão [W]),
  o Questor (sem leiaute publicado, decisão [W]) e o resumo de valores do mês (exige prova dupla).

## 3. Passo-a-passo

1. `ExportacaoContabilController@index` renderiza `Financeiro/Contabilidade` com `competencia` (padrão: mês anterior),
   `layouts` e `depara` diferido (contas bancárias + plano de contas que aceita lançamento + juros e descontos).
2. `PUT /financeiro/exportacao-contabil/de-para` grava os códigos; código vazio apaga a linha.
   Toda origem é conferida contra a empresa da sessão (UC-EXPC-08).
3. "Gerar arquivo" chama a rota de download; 422 vira a lista de pendências, 200 vira o arquivo baixado.

## 4. Tokens CSS

Só tokens do `.cockpit` e classes do DS (`bg-card`, `bg-warning-soft`, `text-muted-foreground`). Sem cor crua.

## 5. Estados visuais

Carregando o de-para (skeleton) · de-para completo/incompleto (selo) · pendências (aviso) · arquivo baixado.

## 6. Responsividade

Cabe em 1280px; a tabela do de-para rola na horizontal abaixo disso.

## 7. Atalhos

Nenhum próprio.

## 8. Component contract

`governance/design/contracts/financeiro-contabilidade.contract.json` — seções `formulario` e `acoes`.

## 9. DoD checklist

- [ ] UC-EXPC-06..09 verdes na lane `financeiro-pest`
- [ ] Menu leva à tela (UC-CONTAB-00)
- [ ] Smoke em biz=1 com screenshot e `design-diff` contra o protótipo após o deploy

## 10. Pegadinhas

- O código do contador é texto (`varchar(30)`), não número: zero à esquerda conta.
- O protótipo desabilita "Gerar" com qualquer código vazio; o servidor só cobra as contas usadas no mês.
  A tela segue o servidor: deixa gerar e mostra o que faltou (divergência deliberada, declarada no charter).
- O download é `fetch` + blob, porque o 422 traz JSON e precisa virar aviso na tela.

## 11. ADR de origem

[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md).
