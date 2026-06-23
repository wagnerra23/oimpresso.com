# Sessão 2026-06-11 (b) — Drawer Financeiro: método 9.75 aplicado + bench + refinos R1–R3

## Pedido ([W])
(1) "quero aplicar o método 9.75 específico no drawer" → (2) "pode criar aqui primeiro" (FX no protótipo antes do live) → (3) "o drawer está ancorado nos melhores layouts? compare e avalie" → (4) "pode fazer, design profundidade tipografia cor, frescor crosslink, muito mais harmonizado densidade e cores complementares".

## O que foi feito (protótipo `oimpresso.com.html` · financeiro-page.jsx + financeiro.css + financeiro-curation.jsx · verifier ✅ 2 rounds)1. **Método 9.75 no drawer — 3 furos da régua fechados:**
   - **P2/N3**: J/K navega títulos sem fechar + cluster ↑ n/N ↓ no header + R liquida + kbds VISÍVEIS (footer + botão primário).
   - **P5/S3**: `printRecibo()` — recibo imprimível com brand OIMPRESSO via iframe oculto (footer "Recibo").
2. **FX-2/3/4 provados no protótipo primeiro** (achados do print do live): "maio" hardcoded morto (período = fonte única); "próx." só p/ obrigação FUTURA (vencida = "vencida há Nd" em neg); `amtSign()` — zero nunca leva sinal (tabela, ⌘K, hero, recibo). FX-1/FX-5 = só live (protótipo já certo).
3. **Bench honesto** → `Bench Drawer Financeiro.html` (asset): 12 dimensões vs Linear/Stripe/Mercury/Attio/Front. **8,4** (era 7,8). Gaps: inline edit 6,0 · copy 6,0 · status 3× · timeline enterrada.
4. **Refinos R1–R3 + harmonização ([W] "pode fazer"):**
   - **R1 copy-em-tudo** (`CopyVal`): ID header, Contraparte, Conta — ⧉ hover/✓.
   - **R2 KV inline** (`KVEdit`): Categoria/Canal = select vestido de valor + "era X"; opções expostas em `window.FIN_EDIT_OPTIONS` (curation).
   - **R3 hero calmo**: StatusBadge REMOVIDO do hero (estado 1×); tempo = `.fin-rel-chip` (neg/warn/mut); header do Histórico harmonizado (fs-3, sem uppercase).
   - **Harmonização**: drawer `--sh-2` + hero gradiente roxo 4%; lentes com `fin-lens-ic-{hue}` (Vínculos roxo · Conciliação pos · Fiscal warn · Cobrança dinâmica) + anel inset; cross-links wayfinding (`fin-xchip-*`: venda roxo · OS âmbar=oficina · compra verde).

## Decisões
- Drawer pós-refinos ≈ 9,5 na régua do bench. Tudo F1 — **aguarda F2 [W]** pra virar handoff (junto com os refinos entram na FA-4 ou onda própria).

## Erros + correção
- Nenhum novo. Timeline do audit já tinha dots/cores (bench inicial subestimou — corrigido pra "promover header", não reconstruir).
- **Round de polish pós-print [W] ("Detalhes mal formatado"):** grid com coluna órfã (Conta span-2 deixava Competência só) → pareado Competência|Conta; select KVEdit width:100% jogava o chevron na borda da coluna → width:auto (chevron colado no valor) + height 22px baseline casada; footer estourava com troubleshooter+hint+botões → trouble-lbl 150px ellipsis + `:has()` esconde hint + botões flex-shrink:0. Verifier ✅.
- **Round "cores cruas, sem vida" [W]:** tokens -soft puros em branco = anêmicos → passe VIDA via color-mix dos mesmos tokens (hero radial 15% + linear 7% · lens-ic 15-18% + anel 30% · StatusChip → classes `.fin-chip-*` com chroma+anel · conciliado verde vivo com check sólido · xchips tintados por módulo · rel-chip com anel). **L-40 nova:** tint em CSS próprio perdia pro utility `bg-[var(--surface)]` no cascade do CDN → utility da mesma propriedade SAI do className. Verifier ✅ (needs_work → fix → done).

## Refs
- `Bench Drawer Financeiro.html` · `prototipo-ui-patch/PROMPT_PARA_CODE_ONDAS-FINANCEIRO-APLICAR.md` (FX anexados) · sessão-mãe `2026-06-10-polimento-drawer-financeiro.md`

## Continuação (mesmo dia, pós-F2) — VIDA sistêmica + BATERIA v2.4
- **[W] "cor fosca é erro sistêmico"** → raiz achada na FONTE: `ds-v6/tokens.css` semânticos lavados. Chroma subiu light+dark: pos .12→.17 · neg .18→.21 · warn .12→.16 · softs .05-.075→.08-.11 · accent-soft/line .022/.05→.045/.09 · origins +chroma. Roxo canônico INTOCADO. Cache-buster ?v=v6-2.
- **[W] "Vínculos/ficha feios"** → Vínculos = 1 linha (chips no header, status redundante fora) · ficha em `.fin-kv-card` (lavanda 2.5% + borda roxa 10%).
- **[W] "revisão em TODOS os componentes, tem teste pra isso"** → cumprido o loop erro→asserção que eu vinha furando: **qa-conformance v2.4, gates novos G10 chip fosco · G11 select fantasma · G12 grid órfão · G13 texto cortado**, todos com controle-negativo. Bateria rodada em Financeiro (lista+drawer+4 sub-telas), Vendas, Compras.
- **Achados da bateria consertados:** G3 `.fin-kbd-acc` (-fg como superfície → currentColor) · G11 select esticava o track do grid pelo max-content da lista → select ABSOLUTO sobre o espelho (+ buffer 24px no mirror) · Vendas G2 7 checkboxes nativos → accent-color · Vendas G3 `.vd-av-2`/`.vd-plate-top` → `--av-5` · G9 over-broad → flutuante = só fixed/absolute/sticky (painel docado da Compras passa).
- **Placar final: 0 🔴 em todas as rotas medidas** (verifier, 3 rounds needs_work→done).

## Continuação 2 (mesmo dia) — acabamento fino + varredura por ESTADO (0 🔴 final)
- **[W] "lápis de cor pinto melhor… fontes nada encaixadas"** → metade de baixo do drawer: Histórico com formato Único de data `dd/mm · HH:MM` (eram 3 formatos) · headers Histórico/Comentários = tipografia das lentes · composer calmo (hairline, focus-within accent, botão disabled sunken) · cards de comentário lavanda 4% · lente Fiscal no `.fin-kv-card` + NF com copy.
- **Bateria por ESTADO** (drawer aberto, linhas diferentes) pegou e consertei em série: `.vd-link` 11px→fs-2 (G8) · regra stale `.vd-step.active .vd-step-num` deletada (G3) · `.vd-cob-*` movido pg-styles→vendas.css (G7) · `.os-drawer{max-width:92vw}` na base (G4) · G10 ganhou exceção outline-chip SEM cegar (negative ✓) · **sweep único das 9 ocorrências `background:-fg` no vendas.css** (7→av-5; 2 eram PRIMÁRIOS AZUIS em recibo/orçamento→accent roxo canônico).
- **Verifier final: done — 0 🔴 em Vendas (5 estados de drawer) + Financeiro.** Lição operacional: needs_work em série = parar de consertar instância e varrer a CLASSE inteira por grep (foi o que fechou).

## Próximo passo
~~[W] aprova visual (F2)~~ → **F2 DADO ("ok f2" 2026-06-11)**. Ponte: `prototipo-ui-patch/PROMPT_PARA_CODE_FA5-DRAWER-975.md` (onda FA-5, roda após FA-1; URLs ~1h). [W] cola 1× no Code.
