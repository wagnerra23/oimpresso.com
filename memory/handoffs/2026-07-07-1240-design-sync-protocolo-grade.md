---
slug: 2026-07-07-1240-design-sync-protocolo-grade
date: "2026-07-07 12:40 UTC"
autor: "[F+C] (Felipe pareado Claude Code — sessão remota claude.ai/code)"
tema: "Protocolo Design↔Code: §10.6 DesignSync merged + grade estado-da-arte + direção baixar-primeiro"
cycle: off-cycle
---

# Handoff — DesignSync no protocolo Design↔Code + grade com nota

## O que foi feito (2 PRs + 1 commit retido)

1. **PR #3913 MERGED** — `PROTOCOL.md` **§10.6**: tool `DesignSync` + skill `/design-sync` documentadas como acesso direto (leitura/escrita) a projetos design-system claude.ai/design. Governança inalterada (proposta≠autoridade §10.4 · reporte §10.2 · git=SSOT ADR 0239). Sondagem empírica: **desktop OK** (login claude.ai); **remoto bloqueado** (`/design-login` exige terminal interativo — erro literal capturado).
2. **PR #3914 ABERTO, `clean` (todos required verdes), aguardando clique humano** — doc de arte [`2026-07-07-arte-handoff-design-code-grade.md`](../sessions/2026-07-07-arte-handoff-design-code-grade.md) (grade ponderada P0=4/P1=2/P2=1: **oimpresso 82/100** · DTCG 62 · Claude Design+DesignSync 52 · Figma DevMode MCP 52 · Builder.io 48 · Locofy 41 · v0 40) + append `SYNC_LOG.md`. **Auto-merge foi DESATIVADO manualmente** (ator humano) após eu armar — respeitado, não re-armei.
3. **Commit local RETIDO `4af38386`** (não pushed de propósito — push agora entraria no #3914 aberto e resetaria o CI verde): `PROTOCOL-F3-COWORK-CODE.md` F3.0 ganha fonte **DesignSync como caminho PREFERIDO de descida** (list_projects → get_project → list_files diff → get_file só-do-que-mudou → commit imediato) + linha F3.1 + tabela tools.

## Decisões desta sessão

- **[F] 2026-07-07: "não quero subir quero baixar"** — uploader canon→DesignSync (gap #1 da grade) **ADIADO sem sinal**; prioridade = descida (baixar sempre o mais fresco). Registrado no PROTOCOL-F3 (commit retido) e aqui.
- Grade honesta: lideramos por governança (P0 = gate/SSOT/anti-regressão/auditoria); em fluidez de ida (formato + tradução estilo→código) Claude Design vence 9×6 — gap estrutural segue sendo o CSS→Tailwind manual (F-C do §10.5, reativo ao bundle GA).

## Próximos passos (ordem)

1. **Humano mergear #3914** (verde, docs-only). Auto-merge foi desativado manualmente — decisão de quem desativou.
2. **Pós-merge #3914**: reiniciar branch `claude/design-code-protocol-v9bfh0` de `origin/main`, cherry-pick `4af38386` (emenda F3.0 descida) + este handoff, push, PR novo.
3. **Teste real no desktop [F]**: sessão desktop com login claude.ai → "pega o mais fresco do design" → valida fluxo §10.6/F3.0 (list_projects já mapeados no INDEX-DESIGN-MEMORIAS §0.2: `019dcfd3` fonte-telas · `019dd02f` só-DS).
4. (se sinal voltar) uploader canon→DesignSync — hoje descartado por [F].

## Estado MCP no momento do fechamento

- **MCP oimpresso NÃO conectado nesta sessão** (remota claude.ai/code — nenhuma tool `mcp__Oimpresso_*` exposta; brief-fetch indisponível, consistente com handoff 2026-07-06 23:55 "MCP indisponível"). Fallback usado: git/filesystem (`Glob memory/handoffs/2026-07-*` rodado — sem duplicata; `origin/main` fetch fresco 0/0 no início, gate §10.4 Passo 0 cumprido).
- GitHub: #3913 merged 11:22 UTC · #3914 open/clean 12:38 UTC · check-in `send_later` re-armado.
