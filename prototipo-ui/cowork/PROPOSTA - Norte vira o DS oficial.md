# PROPOSTA — Norte vira o sistema oficial do Oimpresso

> **Autor:** [CC] · **Decisão:** [W] 2026-06-05 "norte vira o sistema oficial" · **Tier 0**
> **Status:** PROPOSTA (não-commitada — [CC] é read-only no git). Vira lei só quando [CL] aplicar no `main` sob OK de [W].
> **Não renumerar/versionar** sem [W]. Este arquivo descreve o **delta**; os valores canônicos vivem em `ds-v6/tokens.css` após aplicação.

---

## 1. O que está sendo decidido

O **Norte** deixa de ser exploração paralela e **passa a ser o design system oficial** — ou seja, o `ds-v6` **incorpora** o Norte. Não há dois sistemas: o Norte É o ds-v6 daqui pra frente. Isto resolve de vez o conflito recorrente de "DS duplo".

## 2. O que NÃO muda (continuidade — sem regressão)

- **Roxo canon** `oklch(0.55 0.15 295)` (ADR 0235) — intacto. O Norte já usa exatamente este roxo.
- **IBM Plex** (Sans + Mono) — intacto.
- **Cor única** — D-02 (roxo único) **confirmado e reforçado**. O Norte agora é 100% roxo; cor-por-módulo foi **descartada** por [W] 2026-06-05. ✅ passa no `conformance-gate` (accent sempre roxo 250–330).
- **Padrão Cockpit** (sidebar + header + corpo + footer/rail) — o shell Norte é a evolução direta dele.

## 3. O DELTA — o que o Norte ADICIONA ao ds-v6 (✓ aferido contra `inertia.css`@main 2026-06-05)

> **Boa notícia da leitura do `main`:** o delta é MENOR do que parecia. Roxo idêntico, creme idêntico, e o tema escuro **já existe no código** (`.dark{}` em `inertia.css`) — só não é ativado. Então quase nada aqui é token novo Tier-0.

| # | Adição | Realidade no `main` (✓ lido) | Esforço real |
|---|---|---|---|
| D1 | **Tema escuro** | **JÁ EXISTE** — bloco `.dark{}` completo em `inertia.css` (primary roxo `oklch(0.62 0.15 295)`, card/border/etc). Comentário confirma: "data-theme dark não é ativado em nenhuma tela". | **ATIVAR + auditar contraste das telas**, NÃO criar token. Tier 1, não 0. |
| D2 | **Denso = padrão** | Telas Cowork (Sells/Financeiro) já são densas via `.sells-cowork`/`.fin-cowork`/`.cockpit`. | Tornar o denso o default no shell. Tier 1. |
| D3 | **A Costura** | Não existe no `main`. É a única adição **genuinamente nova**. | Componente novo (`.costura`/`.spine`). Tier 1. |
| D4 | **Shell unificado + busca global no header** | `cockpit.css` (82KB) já é o shell canon. Falta a **busca global à direita** (hoje cada tela tem a sua). | Evoluir o cockpit. Tier 1. |
| D5 | **Kit denso** | Já existe vocabulário `vd-*` (Sells), `fin-*`, `cl-*`/`cw-*` (campos). | **Conformar, não criar** — reusar o que há. Tier 1. |

**Dois dialetos de token a respeitar (✓ lido):** o repo usa (a) `@theme --color-*` (shadcn/slate → gera utilities `bg-primary`/`bg-success`…) nas Pages Inertia, e (b) `--bg/--surface/--accent/--text/--border` (Cowork, escopados em `.cockpit`/`.sells-cowork`/`.fin-cowork`) nas telas densas. O Norte oficial **fala os dois** — não inventa um terceiro namespace.

## 4. Impacto nos GATES (✓ aferido)

- **`conformance-gate`** (accent roxo 250–330): **PASSA** — Norte é 100% roxo, e o `--color-primary` do repo já é esse roxo.
- **`foundation-guard`** (token novo só em `foundations.css`/`cockpit.css`): **o tema escuro NÃO precisa de token novo** (já existe em `inertia.css`). Se algo novo for preciso (ex. token da costura), entra em `cockpit.css` (que está na allowlist de definição). **Risco Tier-0 quase zerado.**
- **`ui:lint` R1**: Norte consome via `var()`/utility semântica → ok.

> **Conclusão da auditoria:** "Norte oficial" é, na prática, **(1) ativar o dark que já existe, (2) tornar denso o default, (3) adicionar a costura, (4) unificar busca no header** — tudo Tier 1, conformando ao que o `main` já tem. O único Tier-0 genuíno seria um token de fundação realmente novo, que até agora **não é necessário**.

## 5. Caminho de migração (proposto — [CL] executa)

1. **Fundação primeiro** (multiplicador): tokens (D1/D2) + kit (D5) no `ds-v6`. Re-skina o chrome de todas as telas de uma vez.
2. **Costura (D3)** como componente compartilhado.
3. **Telas reais por prioridade do balcão:** OS · Vendas · Clientes · Caixa (P0) → Financeiro · Produtos · Compras (P1) → resto.
4. **Acessibilidade** (contraste AA nos 2 temas) + remoção incremental do CSS morto (1 família/PR, nunca nuke — regra do projeto).

## 6. Artefato-fonte (resolve a nº 3)

- **OFICIAL / vivo:** `Oimpresso — Norte.html` (shell unificado) + `norte-identidade.css` (tokens) + `norte-shell.css` + `norte-shell.jsx` + `norte-screens.jsx`.
- **Histórico de exploração** (podem ir pro `_arquivo/` depois de confirmar): `Clientes - Identidade Norte.html`, `OS - Identidade Norte.html`, `Norte — Board.html`, `Norte — Identidade Oimpresso.html`, e os `*-norte.css/jsx` standalone.

## 7. Ponte pro Code (como isto vira real)

- [CC] **não commita** (read-only no git). 
- Quando [W] mandar publicar, [CC] gera: (a) este ADR de proposta, (b) os tokens/CSS finais espelhados em `prototipo-ui-patch/`, (c) **URLs públicas** dos arquivos, (d) **1 prompt** pronto pro Claude Code (com comandos git + PR). [W] cola 1x.
- Até lá: **"o Code resolve com este pedido"**, nunca "está commitado".

---

**Próximo passo de [CC]:** aguardar [W] dizer **"publica"** → aí gero a ponte zero-toque (patch + URLs + prompt). Ou continuar refinando o shell oficial aqui antes de publicar.
