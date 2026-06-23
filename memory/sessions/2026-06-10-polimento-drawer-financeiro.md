# Sessão 2026-06-10 (c) — Polimento do drawer Financeiro (gabarito 9.75)

## Pedido
[W]: "drawer do financeiro, polimento, frescor, densidade alta, hierarquia… tá feio, pode melhorar."

## O que foi feito (financeiro-page.jsx + financeiro.css + fin-boletos.css · verificado ✅ 2 rounds)
1. **Camada 1 (o fato) fixa fora do scroll** — gabarito `Financeiro - Prova Viva (primitivos).html` (drawer 9.75): label de estado uppercase (neg quando atrasado) · valor sistema Num (inteiro 38px mono tabular, prefixo "− R$"/"+ R$" e centavos 14px, `whitespace-nowrap` no prefixo) · chip + vencimento/liquidação à direita · FSM compacto (`.fin-fsm-compact`: círculos 18px) · Conferir/Editar enxutos (small "Eliana valida" → display:none, vira tooltip). Ordem nova: header → hero → tabs → lentes (antes hero rolava junto).
2. **Lentes (camada 2)** — ícone em quadradinho `--accent-soft` + título 12.5px firme (antes uppercase cinza 11px) · ritmo `py-4` · box Conciliação conciliada DISCRETO (bg sunken + check verde pequeno; antes banda --pos-soft gritando).
3. **KV empilhado** (label mudo 10.5px em cima, valor 13px embaixo) em grid 2-col gap `6px 20px` — ~2× densidade vs label-esq/valor-dir.
4. **LenteFiscal** — grid Documento/Regime + linhas mono justify-between "ISS retido · 5%" e "No DAS do mês · ≈6%" (--warn) + ponte textual pra sub-tela Impostos & obrigações.
5. **Footer** — `.fin-trouble-lbl` max-width 180→250px (truncamento feio resolvido).

## Bugs achados pelo verificador e consertados (loop §8.2)
- **G6 dark: `.fin-stat-hero` ilegível** — `background: var(--text)` flipa pra branco no dark (classe do bug PR #2209; o protótipo nunca recebeu o fix). Fix: bloco `[data-theme="dark"]` com tokens (--sunken/--text/--text-mute) em fin-boletos.css.
- **G5 com drawer aberto: 3 `white` cruas** (fsm-step done num · fin-comment-new button · fin-trouble-ic) → tokenizadas: `var(--accent-fg)` ×2 + `var(--surface)` (trouble, bg --warn).
- **G5 dark: par hardcoded `.fin-num-pos/-neg`** (light+dark, 4 cruas) → `var(--pos)`/`var(--neg)` direto (ds-v6 flipa sozinho) e overrides deletados.
- **Baseline ratchet `fin-root`: 10 → 8** (medida pelo verificador, só-desce) em qa-conformance.js.

## Prova
3 rounds de verifier: round 1 needs_work (G6 dark + 3 whites) → round 2 needs_work (par fin-num dark) → round 3 **done** (light+dark, drawer aberto, G5 ≤8, G4/G6/G7 pass).

## Residual
- F2 [W]: aprovar visual (drawer polido + tela Impostos) → handoff do pacote Financeiro pro [CL]. O port F3 do drawer deve levar junto os fixes de token (fin-num, hero dark — conferir se o live já tem via #2209, REGRA 6: ler @main antes).
- Cruas restantes na rota fin = 8 (chrome do shell: scrollbar+avatares+fin-filter-ct white+hero small/hint) — dívida do shell, não da tela.

## Refs
Gabarito: `Financeiro - Prova Viva (primitivos).html` (Drawer/Sec/KV/FSM) · sessões (a) probe-v22-escopo, (b) onda-w2-financeiro.
