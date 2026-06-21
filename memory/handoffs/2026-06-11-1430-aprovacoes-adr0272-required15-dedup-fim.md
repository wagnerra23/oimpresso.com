---
date: "2026-06-11"
time: "14:30"
slug: "aprovacoes-adr0272-required15-dedup-fim"
tldr: "Segunda metade da sessão componentes: dedup executado (shared/ flat #2547 · renames #2549 · shim MercosulPlate out #2555, catraca reuse 25→21), ADR 0272 ACEITA ('eu aprovo'), visual-regression PROMOVIDO a 15º required (always-run+skip-as-pass #2553, validado 47s em PR docs-only), gate visual de Wagner exercido ao vivo no #2544, proposal 'recusado com motivo' publicada (#2556, aguarda Wagner)."
decided_by: [W]
cycle: null
prs: ["2547", "2549", "2552", "2553", "2555", "2556"]
next_steps: ["Wagner: aprovar/recusar proposal recusado-com-motivo (memory/decisions/proposals/2026-06-11-recusado-com-motivo-status-primeira-classe.md)", "MemCofre Pages/* — deleção verificada (passada de deprecação dedicada)", "atoms.tsx/cn Cobrança — F5 tela-a-tela", "chip /mwart-override: sessão spawnada vai achar opção (b) já resolvida no #2553; opção (a) handler real é decisão dela/Wagner"]
duration: "~3h (continuação do handoff 12:05)"
authors: [Wagner (aprovador), Claude Code]
---

# Handoff — aprovações: ADR 0272 aceita, visual-regression required, dedup fechado

> Continuação de [2026-06-11-1205-arvore-componentes-canonica-3-prs.md](2026-06-11-1205-arvore-componentes-canonica-3-prs.md).

## O que aconteceu

1. **Pergunta "tem arquivos duplicados ainda?"** → medição (reuse:duplicates 23 + basename scan): classificou governados (F4/F5) vs zumbis (MemCofre) vs genuínos. Wagner: "faça o necessário".
   - **#2547** shared/ vira FLAT (CHECK 3 no guard, furo achado horas após o guard nascer) + MOVE `shared/ponto` → `Pages/Ponto/_components` (38 R1 + 6 eslint pré-existentes absorvidos ao entrar no escopo Pages/**).
   - **#2549** renames de colisão (`FiscalModuleTopNav`, `KbCommandPalette` — diff provou implementações próprias, não cópias) + catraca reuse **25→21**.
   - **#2555** shim MercosulPlate deletado (ADR 0251 cumprida; liberado pós-#2544).
2. **Gate visual exercido ao vivo**: #2544 (workspace OS unificado, sessão irmã) — diagnóstico cross-sessão (R1 + anchor 'Ordens' quebrado na main pelo #2533), screenshot levado a Wagner, aprovação F1.5 registrada, #2550 (fix anchor) mergeado. Colaboração sem colisão (list_sessions antes de tocar).
3. **"eu aprovo"** → **ADR 0272 aceita** (#2552, árvore canônica de componentes, índice regenerado 277 ADRs) + **visual-regression promovido a 15º required** na ordem anti-deadlock: #2553 (always-run + dorny/paths-filter skip-as-pass, padrão financeiro-pest) mergeado ANTES do `gh api POST contexts`. Validado: PR docs-only #2556 passou em 47s. Promessa morta `/mwart-override` removida do bot no mesmo PR.
4. **"vai"** → proposal **recusado-com-motivo** publicada (#2556): `status: recusado` no enum fonte-única + `rejected_at/via/reason` (com critério de reabertura) + seção Recusadas no índice. **Aguarda aceite Wagner.**

## Estado required (pós-promoção)

15 contexts (snapshot rollback no body do #2553). `enforce_admins=true`. Mudança visual sem screenshot aprovado por Wagner agora é MURO, não processo.

## Lições

- Baseline ui-lint usa `\/` escapado E escopo de regras difere por pasta — mover arquivo pra Pages/** SEMPRE absorve hand-roll pré-existente (3ª ocorrência hoje; padrão documentado na rule components.md).
- `gh pr checks --watch` + pipe `head` mata o watch cedo (SIGPIPE) — redirecionar pra arquivo e grepar depois.
- Required novo SÓ depois do always-run mergeado (ADR 0261) — funcionou limpo.

## Pointers

- ADR 0272 · proposal recusado-com-motivo · `.claude/rules/components.md` · CHANGELOG DS 0.6.12–0.6.14 · PRs #2547/#2549/#2552/#2553/#2555/#2556
