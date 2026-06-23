# RECONCILIAÇÃO — Cowork export → memória canônica (livro-razão)

> **REGRA-MÃE (Wagner 2026-06-23):** a **única fonte de memória aceita é a canônica do repo** (`memory/**`, sincronizada pro **MCP** via webhook GitHub→push). Todo conteúdo **novo** de um handoff Cowork é **absorvido** ("sugado") pra cá. O Cowork **NÃO mantém memória paralela** — ele **lê a SUA memória** (via MCP / snapshot read-only). Este arquivo é o **livro-razão mantido**: o que já foi analisado, conciliado e absorvido — pra o processo ser organizado e **idempotente** (não re-absorver, não perder o novo).

## Fluxo (repetível a cada handoff)
1. Extrair o zip do export.
2. **Camada de design** (jsx/tsx/css/html/charters/casos) → `prototipo-ui/cowork/` (overwrite + commit = linhagem de diff). Ver [ADR-proposta SSOT](../memory/decisions/proposals/2026-06-23-prototipo-ssot-unico-com-historico.md).
3. **Memória** (`memory/**` do export) → cruza com o canon; **só o que é NOVO** é copiado pro `memory/**` canônico (mesmo path). Registra aqui.
4. **Push → PR → merge `main`** → webhook **GitHub→MCP** ingere → time + Cowork passam a ler via MCP. _É assim que a memória "entra no fluxo do MCP"._
5. **Transporte** (imagens, dupes, bridge-prompts já processados) → **descartado** (fica só no zip original).

## Conciliação 2026-06-23 (export "Oimpresso ERP Comunicação Visual")
Export = 1182 entradas (workspace inteiro do Cowork).

### ✅ ABSORVIDO pro `memory/` canônico (75 novos — vão pro fluxo MCP no merge)
| Grupo | Qtd | Path canônico |
|---|---|---|
| Sessions por-tela (análises que decidem o build) | 49 | `memory/sessions/2026-05-30..06-12-*.md` |
| Propostas de decisão (Cowork) | 11 | `memory/decisions/_PROPOSTA-*.md` |
| Sprint docs (s2-os-listagem, s3-handoff-mcp) | 10 | `memory/sprints/**` |
| Método de design | 5 | `memory/{CONTEXTO-DE-TELA,FRESCOR-DE-TELA,APRENDER-COM-ERRO,TESTES_ESPINHA,HANDOFF}.md` |

> ⚠️ Estes 75 entraram crus do Cowork — podem precisar normalizar frontmatter pros gates (`memory-schema`, `anchor-lint`) antes do merge. O merge é o que dispara o MCP.

### ⏭️ JÁ NO CANON (21) — não reabsorver
21 `memory/*` do export idênticos ao repo (cópia redundante do snapshot). Descartados do transporte.

### 🗑️ DESCARTE — transporte/lixo (568) — Cowork pode parar de exportar
- 560 `.png` (screenshots + capturas de auditoria — derivados regeneráveis)
- 2 duplicatas cache-bust (`app.jsx?v=eb2`, `clientes-page.jsx?v=ph3`)
- 6 avulsos (`.thumbnail`, `vendas.css.bak`, `.napkin`, `.proposto`, 1 `.sh`, 1 `.py`)

### 📦 ARQUIVO do design (70) — NÃO absorvido (arquivado pelo próprio Cowork); raw no zip
- `_arquivo/bridge-processados/*` (13) — PROMPTs/GAPS já processados
- `_arquivo/ds-historico/*` (7) + `_arquivo/ds/*` (4) — Design System v1.1→v5 (superados por DS v6)
- `_arquivo/{exploracoes,referencia,relatorios,telas,sessao-2026-05-30,venda-estado-da-arte,...}` (~46) — diagnósticos/benchmarks arquivados
- `uploads/*` (4) — Casos de Uso (cobertos pelos `*.casos.md` no `cowork/`) + Mobile DS
> **Candidatos a absorver sob demanda** (se Wagner marcar fundamental): `_arquivo/referencia/Diagnóstico Vendas KB-9.75`, `.../Cadastro de Contacts - Diagnóstico KB-9.75`, `_arquivo/telas/Frescor - Clientes vs Financeiro`.

### 🎨 LANDADO no SSOT `cowork/` (447) — camada de design (não-memória)
Source das telas + `.html` de crítica/tribunal/estado-da-arte + `*.charter.md` + `*.casos.md`. Ver [ADR-proposta SSOT](../memory/decisions/proposals/2026-06-23-prototipo-ssot-unico-com-historico.md).

## Índice de handoffs conciliados
| Data | Export | Absorvidos | Descartados | Commit |
|---|---|---|---|---|
| 2026-06-23 | Oimpresso ERP Comunicação Visual | 75 | 568 transporte + 21 redundante + 70 arquivo | (este PR) |
