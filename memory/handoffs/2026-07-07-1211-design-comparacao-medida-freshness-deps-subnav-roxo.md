# Handoff 2026-07-07 12:11 — Comparação design×prod MEDIDA + freshness de deps + PageHeader roxo

## O que foi feito
Sessão que virou **auditoria do processo de comparar design×prod** (Wagner pegou 3 furos meus seguidos: comparei no olho, não vi center×left/dark, o manifest não baixava as deps). Terminou com **3 lições de código mecanizadas** + o loop do PageHeader roxo fechado do design vivo à prod. **7 PRs MERGED.**

Detalhe completo: [session log 2026-07-07-design-protocol-medido-freshness-deps-subnav-roxo.md](../sessions/2026-07-07-design-protocol-medido-freshness-deps-subnav-roxo.md).

## PRs MERGED (base main, todos deployados)
- **#3915** — branco infinito: `.cockpit .main-body { position: relative }` (sr-only do CommandDialog ancorava no body). Prova prod: `scrollTo(99999)→0`.
- **#3917** — dark-mode Unificado: ~99 classes `stone-*`/`bg-white` fixas → tokens shadcn dark-aware. Tabela legível no dark. Casos G-6 bump.
- **#3918** — `design-diff.mjs` (o `/design-diff` da ADR 0299) + **LC-06** (`visual-compare-eyeball`) + skill `comparar-design-prod` + hook `design-compare-protocol.mjs`. **Comparação design×prod é MEDIDA, nunca no olho** (strike 2 → defesa mecânica).
- **#3919** — freshness manifest **v3**: `parseShellDeps` enumera as ~100 deps de render (não só 3 âncoras) + **LC-07** (`freshness-manifest-partial-coverage`). Pega o drift de `app.jsx`.
- **#3912** — protocolo Fase −1-PULL (import via DesignSync direto) + **ADR 0325**.
- **#3920** — re-export `app.jsx` vivo → espelho `prototipo-ui/cowork/` (mata STALE) + ledger frescor.
- **#3921** — subnav tab ativo `text-primary` roxo (era hue-verde 145). **GLOBAL** (`PageHeaderTabs`, todo módulo). Fiel ao design + ADR 0190.

## Próximos passos (Wagner)
1. **"roxinho" no dark = emenda ADR 0190** — primary fica 0.55 no dark, design brilha pra 0.72. Brightening app-wide do primary é DECISÃO tua (app inteiro vs só Financeiro). NÃO feito.
2. **Drawers/sheets ainda `stone`** (`FinOcrBoletoSheet` 21 · `TituloEditSheet` 14 · `FinAnexosPanel` 9) — dark-mode follow-up.
3. **design-diff rodada COMPLETA** do Financeiro (faltam D1 rede + D3 ícones + D5 footer; rodei só D2/D4/D6/D8).
4. **Smoke visual do #3921 nos outros módulos** (Cadastro/Comercial etc. viraram roxo no subnav — global de propósito, mas vale o olho).

## Como retomar / pegadinhas
- **Fonte de design = Cowork `019dcfd3` via `DesignSync.get_file`** (não há git do design; ADR 0315 leitura livre). `localhost:8765` = python server em `~/Downloads/_cowork-handoff-staging/oimpresso-erp-conunica-o-visual/project/` (staging fixo, bundle 02/07 mas `app.jsx` já atualizado; **efêmero, morre no reboot** — `python -m http.server 8765` na pasta pra reabrir).
- **Comparar design×prod:** frase-gatilho ("compare o design com a tela X") dispara a skill `comparar-design-prod` + hook. Fluxo: `cowork-mirror-freshness --manifest` (v3, âncoras+deps) → pull DesignSync → `design-diff.mjs --probe`/`--compare`. **Nunca no olho.**
- **Render local mente design velho** se o disk-cache do XHR não for invalidado (`fetch(x,{cache:'reload'})` antes do `location.reload()`).

## Estado MCP no momento do fechamento
MCP **indisponível** nesta sessão (brief do cache do hook SessionStart — Brief #317, off-cycle, sem cycle ativo). Sem `cycles-active`/`my-work` ao vivo. Git: 7 PRs MERGED · `origin/main` andou durante a sessão · ledger de frescor 5 entradas (última 2026-07-07: 5 SYNC/0 STALE). Registrar as US no MCP fica pra quando reconectar.
