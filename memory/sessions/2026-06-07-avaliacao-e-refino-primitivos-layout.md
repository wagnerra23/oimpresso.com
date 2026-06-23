# Sessão 2026-06-07 — Avaliação + refino dos primitivos de layout (ADR 0253)

**Pedido [W]:** "lei OS COMPONENTES PRIMITIVOS" → "LEU DO GIT? pode melhorar? falta alguma coisa, avalie tos com notas e gere relatório" → "refine antes de enviar e complete… envie o relatório e o porquê pra manter na memória."

## O que foi feito
1. **Li do git de verdade** (`@main 1f53d49`, não a memória local). Descobri que "os componentes primitivos" do canon vivo = camada React `Components/layout/` (Box/Stack/Inline/Grid/Container/Text), criada na **ADR 0253** (aceita [W] 06-06), governada pelo **MANUAL-CSS-JS §2.1/§5**. Li os 7 arquivos + ADR 0253 + MANUAL + DESIGN.md (06-06) + inertia.css.
2. **Avaliei com nota** → relatório `Avaliacao - Primitivos de Layout (ADR 0253).html`. Global **7.6/10 (B)**: arquitetura/token-only excelentes (9.5+), cobertura-vs-ERP e responsividade fracas (5–6). Por primitivo: Stack/Inline 9.0 · Container 7.5 · Box 7.0 · Grid 6.5 · Text 6.0.
3. **Refinei/completei os 6 primitivos (v2)** — código `.tsx` pronto em `prototipo-ui-patch/resources/js/Components/layout/`, fiado nos tokens **reais** do `@theme` (success/warning/destructive/card/muted/radius-*), não nos nomes da régua CSS (`--pos/--neg`). Aditivo, sem breaking change.
4. **Porquê na memória** → proposta de emenda `memory/decisions/_PROPOSTA-amend-0253-primitivos-layout-completos.md` (tabela "por que dói × custo de não-fazer").
5. **Ponte zero-toque** pro [CL] (`prototipo-ui-patch/PROMPT_PARA_CODE_PRIMITIVOS-LAYOUT-V2.md` + URLs públicas).

## Decisões / propostas
- **Refino v2 dos primitivos** = PROPOSTA (emenda 0253), não lei. [W] decide; [CL] numera/versiona.
- **`--font-mono` no @theme** isolado como **Tier 0** (só [W]) — `family="mono"` funciona sem ele, mas Plex Mono real exige o token.

## Erros / correção (vira lição)
- **L-NN (memória local stale):** afirmei a régua `ds-v6/` local como "os primitivos" antes de ler o git. O git (DESIGN.md 06-06, ADR 0253/0254, MANUAL-IDENTIDADE) estava **2 dias à frente** e a espinha local (STATUS/MEMORY_INDEX 06-04) não mencionava nada disso. **Regra reforçada (REGRA 6):** "componente/tela/canon existe e é X" = `⚠ inferido` até `github_read_file @main` nesta sessão. Corrigi lendo o git e marquei o drift no relatório.

## Residual / aberto
- Risco do Container (`max-w-screen-*` no TW v4) é **inferido, não testei** — o refino v2 já troca pra `max-w-*` v4-safe, mas a confirmação de build fica no critério-de-pronto do [CL].
- "Critério de pronto" da ADR 0253 (tela piloto/doc/REGISTRY) **não confirmado** no main.
- Atualizar STATUS/MEMORY_INDEX locais pra apontar ADR 0253/0254 + MANUAL-IDENTIDADE + DESIGN.md 06-06 (drift de 06-04).

## Refs
- ADR 0253 · MANUAL-CSS-JS §2.1/§5 · DESIGN.md §16.3 · inertia.css @theme
- `Avaliacao - Primitivos de Layout (ADR 0253).html` · `prototipo-ui-patch/resources/js/Components/layout/*` · `_PROPOSTA-amend-0253-*`

## Próximo passo
[W] cola o PROMPT no [CL] → [CL] aplica refino v2 + numera a emenda + roda os gates + tela piloto. Cowork = git (D-06).
