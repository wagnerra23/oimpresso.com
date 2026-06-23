# RECONCILIAÇÃO — Cowork export → memória canônica (livro-razão)

> **REGRA-MÃE (Wagner 2026-06-23):** a **única fonte de memória aceita é a canônica do repo** (`memory/**`, sincronizada pro **MCP** via webhook no merge p/ `main`). O Cowork **NÃO mantém memória paralela** — ele **lê a SUA** (via MCP / snapshot read-only). Este arquivo é o **livro-razão mantido** do que já foi analisado e conciliado — pra o processo ser organizado e **idempotente**.

## ⭐ Memória é DESTILADA, não despejada (qualidade > volume)
A memória boa é **1 assunto = 1 doc, ancorado à fonte viva, com histórico de evolução, guardando só o RESUMO destilado** — não a conversa crua. É o padrão que o repo já tem: **ADR** (1 decisão, append-only) · **SPEC** (ancorada por `anchor-lint`/ADR 0297) · **charter** (1 tela, versão+evolução) · **reference** (fato destilado).

**Sessão crua = diário de conversa ≠ memória.** Despejar `memory/sessions/*` do export no canon **duplica e vira ruído** (e reprova o gate `Session log`). Por isso:
- Conhecimento de tela vindo de uma sessão → **destila o resumo no charter/SPEC daquela tela** (atualiza o doc do assunto, não cria outro).
- A **sessão crua fica no arquivo do Cowork** (recuperável), **fora do canon**.
- Anti-duplicação: se o assunto já tem doc, **evolui** o doc; nunca um arquivo novo repetindo contexto.

## Fluxo (repetível a cada handoff)
1. Extrair o zip do export.
2. **Camada de design** (jsx/tsx/css/html/charters/casos) → `prototipo-ui/cowork/` (overwrite + commit = linhagem de diff). Ver [ADR-proposta SSOT](../memory/decisions/proposals/2026-06-23-prototipo-ssot-unico-com-historico.md).
3. **Memória** → **DESTILAR** o que é novo por-tela/por-assunto no **charter/SPEC/ADR ancorado** (resumo + evolução). Sessão/conversa crua **NÃO** entra no canon. Registrar aqui.
4. **Push → PR → merge `main`** → webhook **GitHub→MCP** ingere a memória destilada → time + Cowork leem via MCP.
5. **Transporte** (imagens, dupes, prompts já processados, sessões cruas) → fora do canon.

## Conciliação 2026-06-23 (export "Oimpresso ERP Comunicação Visual")
Export = 1182 entradas (workspace inteiro do Cowork).

### 🔄 Memória — DESTILAR (não despejar)
Primeira passada despejou 75 `memory/` cruas no canon → **revertido** (reprovava `Session log` e duplicava). Disposição correta:
| Grupo do export | Qtd | Disposição |
|---|---|---|
| `sessions/2026-*` (diários por-tela) | 49 | **Destilar** o resumo por-tela no charter/SPEC; **crua → arquivo Cowork**, não-canon |
| `decisions/_PROPOSTA-*` | 11 | Avaliar 1-a-1: viva → ADR `memory/decisions/proposals/` (1 assunto, supersede); senão arquiva |
| `sprints/{s2,s3}` | 10 | Histórico → arquivo (não ativo) |
| método (`CONTEXTO-DE-TELA`, `FRESCOR-DE-TELA`, `APRENDER-COM-ERRO`, `TESTES_ESPINHA`, `HANDOFF`) | 5 | Checar duplicação vs canon existente antes de virar `reference` |
| `memory/*` idênticos ao canon | 21 | Descartado (redundante) |

> **A FAZER (destilação):** por tela tocada nas 49 sessions, extrair o resumo de decisão de build pro charter/SPEC ancorado. Pendente de priorização [W].

### 🗑️ DESCARTE — transporte/lixo (568) — Cowork pode parar de exportar
- 560 `.png` · 2 duplicatas `?v=` · 6 avulsos (`.thumbnail`/`.bak`/`.napkin`/`.proposto`/`.sh`/`.py`)

### 📦 ARQUIVO do design (70) — fora do canon; raw no zip
`_arquivo/**` (66: bridge-prompts processados, DS v1→v5, diagnósticos/benchmarks arquivados) + `uploads/*` (4: casos já cobertos pelos `*.casos.md` no `cowork/`).
> Candidatos a destilar sob demanda: `_arquivo/referencia/Diagnóstico Vendas KB-9.75`, `.../Cadastro de Contacts - Diagnóstico KB-9.75`, `_arquivo/telas/Frescor - Clientes vs Financeiro`.

### 🎨 LANDADO no SSOT `cowork/` (447) — camada de design (não-memória)
Source das telas + `.html` crítica/tribunal/estado-da-arte + `*.charter.md` + `*.casos.md`.

## Índice de handoffs conciliados
| Data | Export | Memória (destilada) | Descartado | Commit |
|---|---|---|---|---|
| 2026-06-23 | Oimpresso ERP Comunicação Visual | 0 cru no canon · destilação por-tela pendente | 568 transporte + 21 redundante + 70 arquivo + 75 sessions cruas (revertidas) | (este PR) |
