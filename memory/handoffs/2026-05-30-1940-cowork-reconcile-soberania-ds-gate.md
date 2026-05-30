---
date: '2026-05-30'
time: '19:40 BRT'
slug: cowork-reconcile-soberania-ds-gate
tldr: "Handoff Claude Design (Cowork export) + Wagner 'analise se o filesystem/memória do Design ficou como planejou, use a grade'. Auditei o export (grade 55/100: esqueleto ok, recheio furado), gerei prompt 'lê o git e se arruma' pro Cowork → ele reconciliou (verifiquei: 55→90, 6/6). Daí 2 ADRs canon: 0238 soberania-[W] (#2007) + 0239 governança do DS (git SSOT + Cowork→Code + regressão-IA + 1 vigente na raiz, #2008). Achei o furo real: DesignIndexSingleSourceTest existia mas NÃO rodava em CI nenhum → liguei (design-index-gate.yml + invariante c · R5). + tidy git-side R4 (v3→_arquivo, #2009). 3 PRs, 2 ADRs, tudo em main."
topic: "Auditoria filesystem/memória Claude Design (grade) + loop reconcile Cowork verificado + ADR 0238 soberania + ADR 0239 governança DS + gate do índice wirado"
duration: ~2h
prs: [2007, 2008, 2009]
adrs: [0238, 0239]
decided_by: [W]
authors: [W, C]
---

# Handoff — Cowork reconcile + soberania-[W] (0238) + governança do DS (0239) + gate do índice

## Estado MCP no momento
Cycle **CYCLE-07** (Fundações pós-4.8) · 12d restantes. Sessão = **governança de design transversal** (adjacente, não goal do cycle). `decisions-search`: ADR 0238 + 0239 recém-mergeados (webhook GitHub→MCP propaga ~2min). MCP conectado a sessão toda (brief-fetch ok). Origem: Wagner colou um **handoff do Claude Design** (claude.ai/design, bundle `TC3GBu3WXBledUagxJn8nw`) + pedido "analise se ficou como planejou, gere instruções, use a grade".

## O que aconteceu
1. **Auditei o export do Cowork** (911 arq.) vs canon do git via a **grade** (método 15-dim, gate ≥80) → **55/100**. Esqueleto ✅ (subordinação git, faxina raiz append-only) / recheio ❌ (cunhou nº de ADR `0200`/`0201` que já são do git=Contacts/SEFAZ; dedup raso — `uploads`/`backups` intocados; sem bloco `new_design_memories`; `CLAUDE.md.proposto` navy stale vs roxo 235).
2. **NÃO reinventei** — preflight achou o framework já maduro: `DesignDocsFreshnessChecker` (ADR 0236 já implementado), `jana:reconcile` (ADR 0237), `_INDEX-LIFECYCLE` registra as 11 colisões, **renumber de aceito é bloqueado pelo gate append-only** (PR #1995, by design).
3. **Gerei pro Cowork** o prompt "lê o git e se arruma" (grade estado→meta→ação) — Wagner colou no Design.
4. **Cowork reconciliou** → verifiquei o novo export (`SteLXUhuuAKCUmyvGUtfGg`): **55→90**, 6/6 itens (0200/0201 despromovidos a `_PROPOSTA`, navy→STALE, dedup→`_arquivo/legado/`, hierarquia índice, `new_design_memories` populado, SYNC_LOG). **O loop fechou** — valida o GitOps-de-design.
5. **ADR 0238** (soberania de [W] sobre a constituição) — numerei a proposta órfã do Cowork → **#2007 merged**.
6. **ADR 0239** (governança do DS — R1 git SSOT · R2 Cowork→Code · R3 regressão-IA · R4 1 vigente na raiz · R5 toda regra no índice) → **#2008 merged**.
7. **Furo real achado** (pergunta do Wagner "tem teste pra isso?"): `DesignIndexSingleSourceTest` existia mas **não era rodado por workflow nenhum** (só local) → adicionei **`design-index-gate.yml`** + invariante (c) "toda regra de design no índice". Agora é gate real (verde em #2008 e #2009).
8. **Tidy git-side R4** — `prototipo-ui/Design System v3.html` → `_arquivo/ds/`; raiz fica só com v4 → **#2009 merged**.

## Artefatos gerados
- **ADR 0238** `memory/decisions/0238-soberania-constituicao-wagner.md` (tier-0/canon).
- **ADR 0239** `memory/decisions/0239-governanca-design-system-git-ssot-regressao-ia.md` (R1–R5).
- **`.github/workflows/design-index-gate.yml`** — roda `DesignIndexSingleSourceTest` por path (`_DesignSystem/**`, `decisions/**`, `prototipo-ui/**`).
- **`tests/Feature/Design/DesignIndexSingleSourceTest.php`** — +invariante (c) (ADR de regra-de-design no índice).
- **`INDEX-DESIGN-MEMORIAS.md`** — bloco "Regras canônicas de governança do DS" (0235/0236/0238/0239).
- **`prototipo-ui/_arquivo/ds/`** — v3 arquivado + README.
- Bridge pro Cowork (em #2007): `prototipo-ui/CODE_NOTES.amendment-faxina-followup-2026-05-30.md` + `CODE_NOTES.prompt-cowork-ler-git-2026-05-30.md`.

## Persistência
Git: tudo em `main` (PRs #2007/2008/2009 merged via `--admin` sob aprovação textual de [W] — "merge para o design olhar" / "aprovo" / "faça"). MCP: webhook propaga ADRs 0238/0239 ~2min. Cowork: lê o git on-demand (não-MCP) — vai ver 0238/0239 na próxima releitura.

## Próximos passos pra retomar
- **R3 enforcement** (`ds-regression-gate.yml` orquestrando `visual-regression` + `screen-grade` + `critique-score` ao tocar `design-system.css`/`tokens.css`) — **deferido**: é design maior (AI-judge como step de CI bloqueante não é wire simples). A ADR 0239 R3 já decide; falta ligar.
- **Cowork executa R4 no projeto dele**: trazer `Design System v4.html` pra raiz (estava em `_arquivo/ds/`) + lista de links dos antigos no fim do `_arquivo/INDEX.md` (instrução já dada no chat).
- (cosmético) menções a "Design System v3.html" em comentário de `design-system.css`/`ds-behavior.js` ficaram stale pós-move.

## Lições catalogadas
- **Framework maduro → preflight evita reinventar.** Ia rebuildar o `DesignDocsFreshnessChecker` que já existia. Sempre verificar o que o time já entregou antes de "fechar gap".
- **"Tem teste" ≠ "roda em CI".** O `DesignIndexSingleSourceTest` existia mas era órfão de workflow — afirmei "falha o CI" e estava errado; verifiquei e wirei. Bate o Wagner-2026-05-28: *"se existe mas não funciona ta errado"*.
- **Renumber de ADR aceito é proibido by design** (gate append-only) — colisão vira tracked debt no `_INDEX-LIFECYCLE`, não force-fix.
- **O reconcile loop de design funcionou de verdade** (prompt → Cowork self-fix → verificação 55→90). O GitOps-de-design não é teoria — está rodando.

## Pointers detalhados (on-demand)
- ADR 0239 (R1–R5) · ADR 0236 (governança-doc, máquinas) · ADR 0237 (jana:reconcile) · `_INDEX-LIFECYCLE.md` (11 colisões) · `INDEX-DESIGN-MEMORIAS.md` (regra de ouro + ordem de leitura).
