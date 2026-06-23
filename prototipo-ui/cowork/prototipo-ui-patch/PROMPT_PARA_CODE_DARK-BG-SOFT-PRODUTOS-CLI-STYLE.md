# Claude Code — 2 mudanças de design (Cowork F1 · sessão 2026-06-22)

Origem: protótipo Cowork (`oimpresso.com.html`). [W] aprovou as duas. Reconcilie contra `main` antes de aplicar (REGRA 6 — os valores abaixo são do espelho Cowork `ds-v6`, não afirmo o estado atual do repo). Cada mudança para no gate visual F2 ([W] screenshot). NÃO quebrar o tema claro.

Referência visual (curl opcional — CSS Cowork completo, ~1h de validade):
- tokens dark: `curl -s "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/ds-v6/tokens.css?t=130140e0d659d0a05c1e56cd45acc4df6e2f81175e5f3bf4123f2d078e158068.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1782157860.fp&direct=1"`
- produtos: `curl -s "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prod-mec.css?t=73c55cb47f1c3319cbe4f934834e0caa47b9119c4202db2cfe3b79e8a4658b05.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1782157861.fp&direct=1"`

---

## PR-1 · Fundo escuro menos agressivo  ⚠️ Tier 0 (token de fundação — PR revisado)

**[W] textual:** "preto menos agressivo no fundo… mais claro não muito."

Causa: o canvas do tema dark é quase-preto. **Intenção:** subir a pilha de neutros escuros ~**+0.04 L** (de quase-preto para carvão suave), preservando a separação canvas↔card↔elevado e os contrastes de texto. Sem mudar hue (282) nem chroma.

Alvo: o bloco `[data-theme="dark"]` dos **neutros do shell** (`resources/css/cockpit.css` — onde `--bg/--sunken/--surface/--raised/--hairline/--border` são definidos para o dark). Localize os valores reais no `main` e aplique o mesmo salto proporcional. Valores-alvo do espelho Cowork (`ds-v6/tokens.css`), antes → depois:

| token | antes | depois |
|---|---|---|
| `--bg`       | `oklch(0.165 0.008 282)` | `oklch(0.205 0.008 282)` |
| `--sunken`   | `oklch(0.142 0.008 282)` | `oklch(0.182 0.008 282)` |
| `--surface`  | `oklch(0.198 0.009 282)` | `oklch(0.238 0.009 282)` |
| `--raised`   | `oklch(0.228 0.010 282)` | `oklch(0.268 0.010 282)` |
| `--hairline` | `oklch(0.27 0.010 282)`  | `oklch(0.31 0.010 282)`  |
| `--border`   | `oklch(0.30 0.012 282)`  | `oklch(0.335 0.012 282)` |
| `--border-2` | `oklch(0.24 0.010 282)`  | `oklch(0.28 0.010 282)`  |

Se o repo usa nomes/escala diferentes para os neutros dark, traduza pela **intenção** (+0.04 L no canvas e cascata acima junto). Texto fica como está (contraste sobra). Gate: `conformance-gate` (hue/chroma intactos, só L). F2 = screenshot dark de qualquer tela.

---

## PR-2 · Lista de Produtos na linguagem da lista de Clientes (tipografia + cor)

**[W]:** "aplicar o estilo do clientes no produtos a tipografia e cores."

Alvo: **`resources/js/Pages/Produto/Index.tsx`** (lista). Referência canônica = **`resources/js/Pages/Cliente/Index.tsx`** (mesma escala/tom). Aplique nas classes Tailwind/CSS reais do Index de Produtos:

**Tipografia (alinhar à escala calma do Clientes):**
- Cabeçalho de coluna (thead): peso `700`→`600`, letter-spacing `.08em`→`.06em`, tamanho `--fs-1` (10.5px). (= thead do Cliente/Index.)
- Nome do produto: `12.5px`→**`13px`** peso `600` (= `cli-name-text` 13px/600).
- Eyebrow de categoria (a linha "COMUNICAÇÃO VISUAL · marca" acima do nome): sair do **mono-uppercase shouty** para **sans** uppercase leve (peso 500, `.05em`), **linha única com ellipsis** (`white-space:nowrap; overflow:hidden; text-overflow:ellipsis`). A identidade fica no dot colorido + marca, não no peso.
- Marca: peso `700`→`600`.

**Cor (tratamento suave como os avatares do Clientes):**
- Thumbnail da linha (e do card no modo Balcão): **remover o gradiente escuro "industrial"** (`…rgba(0,0,0,.18→.22)`). Trocar por brilho chapado e sutil no topo: `linear-gradient(150deg, rgba(255,255,255,.10–.12) 0%, transparent 55–60%)`. As hues de categoria já foram harmonizadas com a paleta de avatar do Clientes (oklch L~0.62 C~0.13) — manter.

Não mudar a **forma** (thumbnail segue quadrado — produto tem imagem) nem a densidade da lista. Gate: `ui:lint` R1 (sem cor crua nova — usar tokens) + `conformance-gate`. F2 = screenshot da lista de Produtos (dark + claro) ao lado da de Clientes.

---

### Ordem / commits
PR-1 (Tier 0, isolado) → PR-2. Cada um seu PR, para no F2. Atualizar `SYNC_LOG.md`. Não é "está commitado" até o merge.
