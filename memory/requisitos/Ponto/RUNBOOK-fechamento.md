---
owner: W
last_validated: "2026-09-25"
slug: ponto-runbook-fechamento
title: "Ponto — Runbook do fechamento da competência (/ponto/fechamento · Fechamento/Index)"
type: runbook
module: Ponto
tela: Ponto/Fechamento/Index
status: ativo
date: 2026-09-25
related_adrs:
  - 0413-ponto-fechamento-competencia-conformidade-relatorios-legais
  - 0104-processo-mwart-canonico-unico-caminho
---

# RUNBOOK — Fechamento da competência (`Ponto/Fechamento/Index`)

> **F1 PLAN da tela nova** (thread 04 do playbook Ponto). Não é migração de Blade: o Blade
> nunca teve fechamento. A lei é a [ADR 0413](../../decisions/0413-ponto-fechamento-competencia-conformidade-relatorios-legais.md);
> onde este RUNBOOK e a ADR divergirem, **a ADR manda**.

## 1. O que a tela faz

O RH escolhe a **competência** (mês), vê a **pré-checagem** e **fecha**. Fechar grava uma linha
em `ponto_competencias` (quem, quando, bloqueios aceitos) e nada mais.

| Rota | Método | Quem |
|---|---|---|
| `GET /ponto/fechamento?competencia=AAAA-MM` | `FechamentoController@index` | `ponto.access` (ver) |
| `POST /ponto/fechamento` | `FechamentoController@store` | **`ponto.fechar`** (ADR 0413 D1) |

## 2. Domínio (já em `main` pelos PRs 1–3 da thread)

| Peça | Regra |
|---|---|
| `ponto_competencias` + `Entities/Competencia` | gravada **uma vez**; triggers MySQL + model recusam UPDATE/DELETE — não existe reabrir (D1) |
| `FechamentoService::preChecagem()` | **só lê**. Graves: dia em `DIVERGENCIA`, intercorrência `RASCUNHO`/`PENDENTE`, violação já apurada de Art. 66 / Art. 71 CLT. Conferir: sem PIS, importação em andamento |
| `FechamentoService::fechar()` | grave aberto só fecha com aceite; os bloqueios abertos ficam na linha (D2 + W3) |

## 3. O que a tela NÃO faz (vem da ADR, não de gosto)

- **Não reabre** competência (D1). Correção depois de fechada: anulação da marcação com trilha
  (`Marcacao::anular()`, Portaria MTP 671/2021).
- **Não gera AFD/AEJ** e não condiciona a geração ao fechamento (D4 + W3) — o botão final leva a Relatórios.
- **Não assina digitalmente** o ato (D2).
- **Não recalcula** apuração, horas nem banco de horas. Reapurar é `ReapurarDiaJob`.

## 4. Diferenças conscientes contra o protótipo (`ponto-fechamento.jsx`)

| Protótipo | Tela | Por quê |
|---|---|---|
| 3 estados (aberta → consolidada → fechada) em `localStorage` | 2 estados (aberta → fechada) no banco | a ADR 0413 define **um** ato persistido (W1); "consolidar" e "fechar" são o mesmo registro (D2) |
| card "Totais da competência" (horas) | fora da v1 | soma de horas é cálculo de hora — `PARAR SE` da thread sem dupla prova |
| bloqueio "NSR fora de sequência" | fora | não há coluna apurada; calcular aqui duplicaria a Conformidade (thread 05) |
| tabela "Divergências por colaborador" | fora da v1 | a ação da pré-checagem já leva ao Espelho |

## 5. Como validar

- Pest: `CompetenciaAppendOnlyTest` + `FechamentoContratoTest` (lane `ponto-pest`, CT 100 para rodar à mão).
- Contrato de tela: `governance/design/contracts/ponto-fechamento.contract.json` (entra com o `.tsx`).
- Smoke pós-deploy em **biz=1**: abrir `/ponto/fechamento`, conferir a pré-checagem; **não** fechar competência real no smoke — fechar é irreversível por lei.
