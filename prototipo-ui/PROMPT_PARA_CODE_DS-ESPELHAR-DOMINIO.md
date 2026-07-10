# PONTE → CODE · Espelhar domínio no DS do Cowork (pra aposentar o ds-v6)

**Origem:** [CC] Cowork · 2026-07-10 · decisão [W] "canon vence" nas origins.

## O que eu observei (verificado LOCAL, não afirmo estado do git — L-42)
- O protótipo Cowork foi religado ao DS VIVO: `<html class="cockpit">` + `<link _ds/office-impresso-design-system-019dd02f…/colors_and_type.css>`. Neutros/radius/type/status/accent agora vêm ao vivo do DS. ✅
- `grep` no `_ds/…/colors_and_type.css` (o espelho bound aqui no Cowork) → **não expõe** `--origin-*`, `--stage-*`, `--sla-paid`, `--kind-*-soft`, `--kpi-feature-*`. (O arquivo diz que o set completo vive em `_generated-cockpit-light.css`.)
- Você ([CL]) confirmou: esses conceitos **já existem no SSOT** `resources/css/tokens/*.tokens.json` (ADR 0310/0311).
- Por isso o `ds-v6/tokens.css` do Cowork ainda mantém ~56 domínios como LITERAIS — é a única coisa que impede deletá-lo.

## Decisão do [W] (já tomada)
- **Origins: canon vence.** NÃO retunar pros valores mais saturados do Cowork (chroma 0.13 / CRM hue 245). Usar o canon do DS (ADR 0310).
- `sla-paid`: não é conflito (resolve no mesmo valor via alias `--text-3`=`--text-mute`, `--sunken`=`--bg-2`).

## O pedido (você decide o COMO, validando contra o main)
Pra o Cowork consumir os domínios AO VIVO do DS (e o `ds-v6/tokens.css` colapsar em shim / ser deletado), esses grupos precisam estar **acessíveis no CSS do DS que o Cowork carrega** (`colors_and_type.css` do design-system bound), com os valores CANON:
`--origin-{OS,CRM,FIN,PNT,MFG}-{bg,fg}` · `--stage-{slate,indigo,rose,emerald,green}` · `--sla-{fresh,aging,late,expired}` (+`-soft`/`-dot`/`-line`) · `--canal-{email,ig,fb,ml}-{tint,bg,fg}` · `--kind-*-soft` · `--kpi-feature-*` · `--vip`/`--vip-soft` — light + dark.

**Dois caminhos, você escolhe:**
1. **Se a ausência no `colors_and_type.css` é atraso do espelho** → rode o `/design-sync` push (git→design) pra re-espelhar incluindo esses grupos. Aí o Cowork consome live e a gente apaga os literais do `ds-v6`.
2. **Se é curadoria intencional** (o `colors_and_type.css` só mostra um subset legível; o resto fica em `_generated-*.css` não carregado pelo Cowork) → diga, que aí o `ds-v6` **fica** como adaptador desses domínios (não dá pra deletar de vez) e a gente documenta isso como esperado, sem fingir que é bug.

## Regras
- Não afirmar estado do git de cabeça (L-42): valide contra `semantic.tokens.json` no main.
- Tier 0 (toca DS/tokens) → aguardar [W] pro merge.
- Retorno em `CODE_NOTES.md`: qual caminho (1 ou 2) + o que ficou.
