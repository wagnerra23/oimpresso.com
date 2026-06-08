# Snapshot Cowork export — 2026-05-15

> **Origem:** `claude.ai/design` → "Oimpresso ERP Conunicação Visual." (handoff de `Oimpresso ERP - Chat.html` aberto).
> **Como veio:** tarball `tar.gz` baixado via WebFetch da URL `api.anthropic.com/v1/design/h/a4CrzbDGb9Qy6GB6OqMAcw`, extraído em `/tmp` e copiado pra cá.
> **Tipo de sync:** snapshot-only — não promove nada pra `prototipos/<tela>/` ou `resources/js/`. Wagner/Claude Code decide depois quais peças sobem.

---

## O que está aqui

161 arquivos · ~3.3 MB · estrutura flat do Cowork (sem reorganização por tela).

```
_cowork-export-2026-05-15/
├── Oimpresso ERP - Chat.html  ← entry point do shell (imports todos os módulos)
├── 27 *.html                  ← mocks de tela individual (Compras, Financeiro Unificado, etc.)
├── 50 *.jsx                   ← componentes per-tela + shell global (app.jsx, sidebar.jsx)
├── 12 *.css                   ← styles per-tela + global (styles.css)
├── 14 *.md                    ← docs (CLAUDE.md, AUDITORIA_MODULOS.md, etc.)
├── prototipos/sells/          ← critique-score.json + CRITIQUE.md (entrada nova)
├── prototipo-ui-patch/        ← ⚠️ patch Laravel completo — VER AVISO abaixo
├── memory/                    ← snapshot de memory/ (decisions, sessions, sprints)
├── memory-para-github/        ← session log 2026-04-28 (chat ERP)
└── _SNAPSHOT.md               ← este arquivo
```

**Excluídos do sync** (presentes no tarball, NÃO copiados):

- `uploads/` (9.3 MB · 331 arquivos) — Design System antigo + PNGs + nested handoff. Mesmo trap de 2026-05-11 (SYNC_LOG: *"Design System do ZIP é fotografia ANTIGA... NÃO copiado pra resources/js/ — sobrescrever destruiria features mergeadas em prod"*).
- `backups/` (2 MB · 112 arquivos) — backups internos do Cowork (`2026-05-14-pre-handoff/`, `2026-05-14-vendas-pre-aplus/`).
- `scraps/` — sketch napkin file.

Se alguém precisar acessar esses arquivos, re-extrair do `webfetch-*.bin` em `~/.claude/projects/.../tool-results/`.

---

## ⚠️ AVISOS — não copiar cego pra produção

### 1. `prototipo-ui-patch/` tem código de produção (Modules/, Pages/, resources/, routes/, app/)

Mesma trap de 2026-05-09 (PR #352 bloqueado · LICOES_F3_FINANCEIRO_REJEITADO.md) e 2026-05-11 (zip canon visual).
**Tier 0 IRREVOGÁVEL ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))** — controllers do Cowork são NO-OP sem `business_id` scope. Aplicar direto destrói multi-tenant + regride fixes mergeados em prod.

Tratamento canônico:

- Visual/JSX/CSS → pode promover pra `prototipos/<tela>/` se Wagner aprovar via F2.
- Modules/Pages/resources/routes → **rescrever do zero** em PR separado seguindo MWART ([ADR 0104](../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)).

### 2. `memory/` aninhado é foto do Cowork — não é canon do repo

O repo já tem `memory/` autoritativo na raiz. Este `memory/` interno é só referência da última sessão Cowork; **não sincronizar** com `memory/` raiz sem revisão arquivo-por-arquivo.

### 3. Sidebar / AppShell / StatusBadge no patch são versões antigas

A produção (`resources/js/Layouts/AppShellV2.tsx`, `resources/js/Components/cockpit/Sidebar.tsx`) está à frente do snapshot. **Não sobrescrever.** Wagner já decidiu (SYNC_LOG 2026-05-11 21:35): "sidebar em prod é canon, Design System do ZIP descartado".

---

## ⭐ Highlight pro próximo passo — JANA Chat V2 (provavelmente!)

A `HANDOFF.md` de 2026-05-14 estava bloqueando F3 da Jana Chat aguardando `[CC]` entregar V2 atendendo amendments (avatar quadrado, tabs Todas/Minhas/Compartilhadas/Arquivadas, 4 components block renderer, mock-stream SSE, etc.).

**Este snapshot traz `chat-jana.jsx` (491 linhas, 20KB) + `chat-jana.css` (645 linhas, 17KB) — candidato direto a V2.**

Conteúdo aparente (cabeçalho do arquivo): *"Cockpit do Analista IA (substitui o chat tradicional). Conceito: a IA é uma analista (Jana) que entrega um brief diário, monitora KPIs, detecta anomalias, sugere ações com HITL e responde via chat."* Inclui mock de Martinho Caçambas (biz=164 legacy), 4 KPIs, 4 analyses (inadimplência/faturamento/concentração/churn ouro), chips de ação HITL.

**Próximo passo recomendado** (se Wagner confirmar):

1. Comparar `chat-jana.jsx` deste snapshot com `COWORK_NOTES.amendment-jana-chat-block-renderer.md` (19 divergências catalogadas) — checa se V2 atende todos os P0.
2. Rodar `design:design-critique` no snapshot pra gerar `prototipos/chat/critique-score.json` (gate F1.5 ≥80).
3. Se score ok → screenshot pra [W2] aprovação → F3 implementação em `resources/js/Pages/Jana/Chat.tsx`.

Não execute esses passos automaticamente — esperar Wagner mandar.

---

## Outros telas potencialmente novas

Comparar diff com `prototipos/` atual revelará. Candidatos óbvios:

- `crm-page.jsx` + `crm-page.css` — não há `prototipos/crm/` no repo.
- `kb-page.jsx` + `kb-*.jsx` (5 arquivos) — não há `prototipos/kb/` no repo.
- `inbox-page.jsx` + `inbox-v2-*.jsx` (4 arquivos) — comparar com `prototipos/caixa-unificada/`.
- `equipe-page.jsx` + `equipe-page.css` — comparar com `prototipos/equipe/`.
- `vendas-aplus.jsx` + `vendas-extras.jsx` + `vendas-create-completo.jsx` — comparar com `prototipos/sells-create/` + `vendas-cockpit/`.

---

## Histórico relacionado

- 2026-05-09: PRs #321 → #327 (loop inbox), PR #352 bloqueado (controllers Cowork NO-OP).
- 2026-05-11: `_zip-novo-2026-05-11/` precedente (mesmo padrão de snapshot isolado, removido depois de consolidar).
- 2026-05-14: amendment-block-renderer P0 → bloqueia F3 da Jana Chat aguardando V2.

Documentos canônicos pra ler antes de promover qualquer coisa daqui:

- `prototipo-ui/PROTOCOL.md` (5+2 fases F0..F4 do loop Cowork↔Claude Code)
- `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md` (6 meta-anti-padrões + 15 técnicos)
- `prototipo-ui/COWORK_NOTES.amendment-jana-chat-block-renderer.md` (19 divergências P0)
- [ADR 0114](../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md) (loop formalizado)
