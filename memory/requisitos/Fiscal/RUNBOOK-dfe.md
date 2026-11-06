---
title: "RUNBOOK — Fiscal/Dfe (sub-página 4)"
owner: W
status: ativo
last_validated: "2026-11-06"
---

# RUNBOOK — Fiscal/Dfe (sub-página 4)

> **Tela:** `/fiscal/dfe` · **Permissão:** `fiscal.dfe.manage` · **PR origem:** PR #3 Wave final

## 1. Objetivo

Lista de NF-e emitidas CONTRA o CNPJ Oimpresso (manifesto destinatário). Prazo legal 90d para manifestar (confirmar/desconhecer/não-realizada/ciência).

## 2. Estrutura

```
FxShell route="dfe"
└── Body
    ├── Filtros (search + 5 chips status)
    └── Tabela paginada (Deferred)
        colunas: Emitente · Chave · Status pill · Prazo pílula · Valor · Emissão
```

## 3. Dados

- Model: `Modules\NfeBrasil\Models\NfeDfeRecebido` (HasBusinessScope)
- 5 STATUS constants (pendente/ciencia/confirmada/desconhecida/nao_realizada)
- counts eager + rows deferred (paginate 50)
- prazoDias = `now()->diffInDays(prazo_confirmacao_em, false)` (negativo = vencido)

## 4. Permissão

`fiscal.dfe.manage` — adicionada em PR #1. Vale para a tela, para a manifestação por linha (`POST /fiscal/acoes/dfe/{id}/{acao}`) e para a **manifestação em lote** (`POST /fiscal/acoes/dfe/lote`: a mesma ação para as N selecionadas, um request, uma ida à SEFAZ por nota). Desde 2026-11-05 (#10404) a rota também exige o pacote `fiscal_module` (`FiscalLiberado`). A busca ⌘K só mostra DF-e a quem tem esta permissão (#10439).

O ambiente (`tpAmb`) da manifestação e da distribuição vem da configuração da empresa (`business.ambiente`, #10405, 2026-11-05).

## 5. Riscos

- **R1**: Notas DFe podem ter `nome_emitente` PII de terceiros — exibidos legalmente (compliance) mas sem agregação cruzada
- **R2**: Prazo 90d é SEFAZ-driven (`prazo_confirmacao_em` no Model) — não calcular UI-side

## 6. Smoke biz=1

```bash
curl -sv "https://oimpresso.com/fiscal/dfe" -H "Cookie: ..." | grep '^< HTTP'
# Esperado: < HTTP/2 200 (ou 403 sem fiscal.dfe.manage)
```
