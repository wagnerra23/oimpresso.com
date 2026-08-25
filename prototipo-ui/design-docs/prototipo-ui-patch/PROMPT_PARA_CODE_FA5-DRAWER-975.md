# PROMPT PARA CLAUDE CODE — ONDA FA-5 · DRAWER FINANCEIRO 9.75 (F2-APROVADO 2026-06-11)

> **[CC] → [CL]** · F2 dado por [W] ("ok f2") sobre o protótipo Cowork verificado (3 rounds de verifier ✅, inclusive round de polish pós-screenshot do próprio [W]).
> **Regra §10.4:** valide contra `origin/main` FRESCO antes; não refaça o que já landou; este pacote é F3 — traduzir o protótipo aprovado pra Inertia/React real.
> **Ordem nas ondas:** roda como **FA-5**, DEPOIS da FA-1 (precisa dos tokens `--sh-2`/`--ease`/`--t-1` na fundação). Independente de FA-2/FA-3/FA-4 (pode paralelizar).
> **Alvo:** drawer do `resources/js/Pages/Financeiro/Unificado/Index.tsx` + css do módulo. URLs dos gabaritos valem ~1h.

## O que o pacote contém (tudo provado no protótipo — fonte: `financeiro-page.jsx` do gabarito)

### 1. Método 9.75 — P2/N3: teclado visível + navegação em lote
- **J/K** navega prev/next título da lista FILTRADA sem fechar o drawer · **R** liquida (Recebi/Paguei) · guards: ignora quando foco em INPUT/TEXTAREA/SELECT/contentEditable ou com meta/ctrl/alt.
- Header do drawer ganha cluster **↑ n/N ↓** (posição na lista filtrada; disabled nas pontas; título some da lista → "–/N", nav desabilitada).
- Teclas MOSTRADAS: kbds `J` `K` no footer (`.fin-dw-hint`) + kbd `R` dentro do botão primário (`.fin-kbd-acc`).

### 2. Método 9.75 — P5/S3: saída no mundo real
- Botão **Recibo** no footer → `printRecibo(eff)`: iframe oculto com recibo brand OIMPRESSO (Georgia 12pt, valor 24pt mono, tabela KV, "documento sem valor fiscal"), `print()` e remove. No live: considerar rota server-side de recibo se já existir padrão de impressão; senão, espelhar o client-side do gabarito.

### 3. R1 — copy em tudo ao hover (Stripe)
- Componente `CopyVal` (⧉ no hover · 1 clique copia · ✓ 1.4s · `stopPropagation`). Aplicado em: **ID do título** (header), **Contraparte**, **Conta** (prop `copy` no KV). CSS `.fin-copyval*`.

### 4. R2 — KV editável inline (Attio/Linear)
- `KVEdit`: select VESTIDO de valor (`width:auto` — chevron data-URI colado no texto, `height:22px` baseline casada, hover borda+sunken, focus accent). Mostra **"era X"** quando alterado + ✎ no label.
- Aplicado em **Categoria** e **Canal**; opções da MESMA fonte do painel ✎ (no gabarito: `window.FIN_EDIT_OPTIONS`; no live: usar as listas existentes do edit panel — fonte única, não duplicar arrays).

### 5. R3 — hero calmo + harmonização (Mercury/Stripe) — **estado dito 1×**
- **StatusBadge REMOVIDO do hero** (label uppercase colorido já diz o estado); tempo vira **chip de frescor** `.fin-rel-chip` (neg = atrasado · warn ≤3d · mut calmo); linha da data perde o texto relativo duplicado.
- **Profundidade:** drawer `box-shadow: var(--sh-2)` · hero com gradiente `color-mix(in oklab, var(--accent) 4%, var(--surface)) → var(--surface)`.
- **Lentes com cor por domínio** `.fin-lens-ic-{accent|pos|warn|neg|muted}` + anel inset `color-mix(currentColor 20%)`: Vínculos accent · Conciliação pos/muted (settled?) · Fiscal warn · Cobrança dinâmica (pos/neg/accent por tone).
- **Cross-links wayfinding** `.fin-xchip-{venda|os|compra|boleto|nf}`: ícone tintado — venda accent · OS warn (âmbar=Oficina) · compra pos · boleto/nf neutro; hover herda.
- **Histórico** header harmonizado: fs-3, sem uppercase, cor text.

### 6. Polish de formatação (round F2 do [W] — não pular!)
- KV grid SEM coluna órfã: Contraparte (span-2) → Categoria|Canal → **Competência|Conta** (Conta NÃO é mais span-2). Grid `gap: 10px 20px; align-items:start`, filhos `min-width:0`.
- Footer NUNCA estoura: `.fin-trouble-lbl` max-width **150px** ellipsis · `:has(.fin-trouble-btn) .fin-dw-hint{display:none}` · botões `flex-shrink:0` · footer `overflow:hidden`.

### 7. FX provados aqui (já listados na FA-4 — implementar UMA vez, citar lá)
- FX-2 período fonte única (zero "maio" hardcoded) · FX-3 "próx." só futuro / "vencida há Nd" neg · FX-4 `amtSign()` — zero sem sinal (tabela, ⌘K, hero, recibo). Gabarito vivo agora existe nestes arquivos.

## Gabaritos (curl -L · ~1h)

| Arquivo | O quê | URL |
|---|---|---|
| `financeiro-page.jsx` | Drawer completo: CopyVal, KVEdit, hero, nav J/K, printRecibo, KV grid | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/financeiro-page.jsx?t=a7cd5a850df80a5e4ec052a41ac7cbaebb0cdad8c9f50c4f825fdc1a393372ec.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781212000.fp&direct=1 |
| `financeiro.css` | Blocos "Método 9.75 no drawer" + "Refinos R1–R3 + harmonização" + polish (fim do arquivo) | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/financeiro.css?t=72f7364775a0423a265629e19eec748851b8d140c061138c7e931fc2fc38c56f.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781212001.fp&direct=1 |
| `financeiro-curation.jsx` | FIN_EDIT_OPTIONS (fonte única de opções) + audit/frescor de referência | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/financeiro-curation.jsx?t=3ff2daa568edfba330341892608cfbdaa03ebe17ba8176671075f782a3c080f7.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781212001.fp&direct=1 |

| `ds-v6/tokens.css` | VIDA 06-11: semânticos com chroma (pos .17 · neg .21 · warn .16 · softs .08-.11 · accent-soft/line .045/.09 · origins) light+dark — aplicar no `@theme` | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/ds-v6/tokens.css?t=361c7a578126ea2e17d56153840cc44d6de9d1c2f9bb275b4f870b83d514d67a.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781212002.fp&direct=1 |
| `qa-conformance.js` | Referência dos gates v2.4 (G10–G13 + exceção outline-chip + G9 flutuante=fixed/abs/sticky) pra espelhar como teste estático | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/qa-conformance.js?t=0aff6eb4b00875d8a59bfb8cc44d15b7765d7be2d49b604df0eb9c83b1853d59.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781212003.fp&direct=1 |

## Execução
```bash
git checkout main && git pull
git checkout -b feat/fin-drawer-975   # FA-5 · roda após FA-1 (tokens --sh-2/--ease)
```
**Adendos pós-F2 (mesmo dia, [W]):**

- **VIDA nos tokens semânticos** — o gabarito `ds-v6/tokens.css` (URL na FA-1) agora serve chroma maior (pos .17 · neg .21 · warn .16 · softs .08-.11 · accent-soft/line .045/.09 · origins): aplicar no `@theme` junto com a FA-1/FA-4 (autorização [W] 06-11 "cor fosca é erro sistêmico").
- **Critérios de aceite extras (qa-conformance v2.4 do Cowork — espelhar como teste estático se couber):** chip/badge semântico nunca acromático (G10) · select custom dimensiona pelo VALOR (G11 — no gabarito: select absoluto sobre span-espelho) · grid 2-col sem linha órfã (G12) · nowrap não corta sem ellipsis (G13) · kbd/acento nunca usa token -fg como superfície (G3).
```bash
# fetch gabaritos → traduzir pro Index.tsx + css do módulo (Tailwind 4 compila shadow-[var(--sh-2)];
#   em css de tela prefira propriedade direta — nota L-37)
# atalhos: cuidado pra J/K/R não colidirem com atalhos globais do shell Inertia; mesmo guard de foco do gabarito
# gates: ui:lint · stylelint · conformance (cor+ramp — kbds e chips usam só tokens) · pest
# screenshots @1280/@1440 light+dark: hero sem badge · KV grid 2-col · footer com troubleshooter longo
# CI verde + não-Tier-0 → merge autônomo. Atualizar SYNC_LOG.md + CODE_NOTES.md.
```

**Adendos do round final (06-11, verifier 0 🔴 em todas as rotas/estados):**
- **Acabamento fino do drawer (no gabarito):** Histórico com formato ÚNICO de data `dd/mm · HH:MM` · headers Histórico/Comentários na mesma tipografia das lentes · composer de comentário calmo (hairline + focus-within accent + disabled sunken) · lente Fiscal dentro do `.fin-kv-card` + NF com CopyVal.
- **Sweep G3 repo-wide (entra nesta onda ou na FA-4):** `grep -rn "background[^;]*-fg" resources/` — no Cowork havia 9 ocorrências (`--origin-CRM-fg` como superfície); 7 viraram `--av-5` e 2 eram BOTÕES PRIMÁRIOS AZUIS (recibo/orçamento) → `--accent` roxo canônico. Repetir a mesma regra no repo: -fg nunca é superfície; primário nunca é azul.
- **`.os-drawer`-equivalente no live:** garantir `max-width: 92vw` na base do drawer/Sheet (G4).

F1.5 (critique) se o fluxo pedir: rubrica do bench em `Bench Drawer Financeiro.html` no Cowork (8,4 → alvo ≥9 pós-pacote).
