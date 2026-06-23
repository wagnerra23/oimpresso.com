# Sessão 2026-06-10 (d) — Tipografia ancorada: type ramp --fs-1..9 + G8

## Pedido
[W]: "a tipografia e frescor? parece amador… era para ter um padrão no ERP? está ancorado onde? acho que generalizado pelo sistema." → diagnóstico + proposta → **[W]: "vai"** (Tier 0 autorizado).

## Diagnóstico (medido, não inferido)
- Âncora existente ANTES: só família (IBM Plex em `ds-v6/tokens.css` --font-sans/--font-mono) + body 13.5px no styles.css. **Nenhuma escala de tamanho/peso/linha.**
- Censo mecânico do shell (20 CSS carregados): **2.015 declarações `font-size` hardcoded · 37 tamanhos distintos (6→96px)**. [W] estava certo: generalizado.
- No git, a âncora de tipo é o primitivo `Text` do ADR 0253 (✓lido 06-07, não relido hoje) — o protótipo nunca espelhou.
- O "fora de foco/amador" = degraus próximos demais (13/13.5/14 coexistindo) → sem ritmo hierárquico. Font-smoothing já estava ok no body.

## O que foi feito ([W] "vai")
1. **Ramp ancorado em `ds-v6/tokens.css`** (Tier 0 autorizado [W] 2026-06-10): `--fs-1..9` = 10.5 · 11.5 · 12.5 · 13.5 · 15 · 18 · 22 · 28 · 38. Regras de acabamento documentadas no token: pesos 400/500/600/700 · lh 1 números / 1.2 títulos / 1.45 corpo · caption +0.08em · números sempre mono tabular.
2. **G8 no qa-conformance.js** — font-size COMPUTADO fora do ramp na tela ativa = contagem de tamanhos distintos, ratchet por tela (baseline medida, só-desce; ⬜ sem baseline). Controle-negativo **N8** (injeta 17.3px → 🔴 → remove; baseline temporária = atual). Exclusões: #qa-panel, svg, overlays `__om-*` (editor, não é app). Bugfix no caminho: edit consumiu `var all` do negative() — restaurado.
3. **Financeiro snapado (1ª prova): 304 declarações** — financeiro.css 131 · fin-boletos.css 20 · financeiro-page.jsx 69 · financeiro-telas-extras.jsx 84 (text-[Npx] → `text-[length:var(--fs-K)]`, classes nomeadas text-xs/sm/base/xl/2xl idem, shorthand `font:` incluso). Mapeamento: ≤10.75→1 · ≤11.75→2 · ≤12.75→3 · ≤14.6→4 · ≤16.5→5 · ≤20→6 · ≤25→7 · ≤33→8 · resto→9; <9px deixado (decorativo).
4. **Shell os-page-h** snapado (h1 24→fs-7 22 · p 13→fs-4 13.5) — vale pra todas as telas os-page.
5. **Baseline G8 fin-root = 0** (medido: os 2 fora do ramp eram .os-page-h p — snapado — e __om-t — excluído).

## Prova
Verifier round 1: visual íntegro nas rotas fin + vendas/oficina intactas · G8 medido (2 fora) · N8 discrimina ✅ · round 2 (fechamento) em andamento ao registrar.

## Decisões ([W])
- **Ramp --fs-1..9 = âncora única de tamanho tipográfico do ERP** (Tier 0, "vai" 2026-06-10). Tamanho novo fora do ramp = G8 🔴.

## Residual
- Adoção nas demais telas pelas ondas W (cada onda snapa a tela + calibra baseline G8; alvo final = 0 em todas). Shell os-*/sb-* completo ainda não snapado (só os-page-h) — entra na onda do shell.
- Espelhar no git: ramp = proposta pro ds do repo (tokens + alinhar com primitivo Text ADR 0253) — entra no handoff do pacote Financeiro pós-F2.
- G4 negative: skip em drawer fin (seletor .prod-drawer-body hardcoded) — generalizar o host do N4 numa manutenção futura do probe.

## Refs
`ds-v6/tokens.css` (§Type RAMP) · `qa-conformance.js` (G8/N8) · censo no chat (script run_script) · sessões (a)(b)(c) de 2026-06-10.
