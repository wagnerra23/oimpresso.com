---
title: "RUNBOOK — Fiscal/Das (apuração do DAS)"
owner: W
status: ativo
last_validated: "2026-10-27"
---

# RUNBOOK — Fiscal/Das (apuração do DAS)

> **Tela:** `/fiscal/das` · **Permissão:** `fiscal.access` · **Flag:** `fiscal.das_apuracao_enabled`
> (`FISCAL_DAS_APURACAO`, default `false` → 404) · **US:** US-FISCAL-030 · **Protótipo:**
> `prototipo-ui/cowork/Wagner/prototipos/fiscal-das.html`

## 1. Objetivo

Prévia do DAS do mês pelo anexo que o contador usa: RBT12, faixa, alíquota efetiva e valor, com a
conta da LC 123/2006 art. 18 §1º-A escrita por extenso. Só leitura: não grava, não lança título e não
transmite o PGDAS-D.

## 2. Estrutura

```
AppShellV2
└── FxShell route="das"
    ├── Filtros: competência (YYYY-MM) · anexo (I–V, informado pelo contador) → query string
    ├── Sem anexo: vazio "Escolha o anexo que o contador usa"
    ├── Avisos: acima do sublimite · sem RBT12
    ├── KpiGrid: receita do mês · RBT12 · alíquota efetiva · DAS do mês
    ├── Card "A conta" (fórmula com os números)
    ├── Card faixas do anexo (faixa atual destacada)
    └── Card "Confirme com o contador" (4 perguntas)
```

## 3. Backend

`Modules/Fiscal/Http/Controllers/DasController::index` chama
`Modules/NfeBrasil/Services/Tributacao/ApuracaoDasService::apurar(businessId, competencia, anexo)`.
O business vem da sessão. Anexo fora de I–V vira `null` (tela pede escolha). Competência inválida
vira o mês anterior.

## 4. Multi-tenant (ADR 0093)

O service filtra `transactions` por `business_id`. Teste: `ApuracaoDasTest` UC-DAS-05 e
`DasControllerTest`.

## 5. O que fica fora (Non-Goals)

- Lançar a pagar (o Financeiro segue com a estimativa ≈6% em `ImpostosController`).
- Comparar com a estimativa ≈6% (o protótipo mostra; a tela espera um dono único da receita recebida).
- Entrar na sub-nav do Fiscal: só quando [W] ligar a flag.

## 6. Ligar

1. O contador confere a conta numa empresa real (UC-DAS-01).
2. [W] decide e liga `FISCAL_DAS_APURACAO=true`.
3. Entrar na sub-nav (`_lib/paginas-fiscais.tsx`) no mesmo PR que liga.

## 7. Histórico

- 2026-10-27 · [C] fatia 2: tela atrás de flag, protótipo publicado como Artefato antes do `.tsx`.
