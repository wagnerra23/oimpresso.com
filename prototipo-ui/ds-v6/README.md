# prototipo-ui / ds-v6 — kit DS v6 (proposta)

> **Origem:** handoff bundle do Claude Design (claude.ai/design) — projeto *"Oimpresso ERP Conunicação Visual."*, sessão de design **2026-06-03 [CC]**. Landado verbatim como referência canônica do DS v6 (COWORK_NOTES → Pendentes #1: *"DS v6 (aprovado [W]) — landar showcase/receita"*).
>
> **Natureza:** protótipos estáticos HTML/CSS (self-contained, sem build). São **referência visual e de tokens** — não código de produção. A porta pra Inertia/React segue o MWART (ADR 0104) + gate visual (ADR 0107/0114) **quando [W] liberar a tela**.

## Arquivos

| Arquivo | É | Pra quê |
|---|---|---|
| [`showcase.html`](showcase.html) | **O kit** — *"o kit que faz toda tela nascer coerente"* | Tokens DS v6 (soma do v5 + tempero da sessão) + 11 componentes canônicos: buttons · status pill · stage dots · KPI stat · segmented tabs · plate · asset card · identity header · timeline · context-rail block · next-best-action. Fundamentos (swatches) + claro/escuro. |
| [`receita.html`](receita.html) | **A receita** — *"como nascer uma tela"* | O método em 6 passos: comece pelo shell → monte com o kit (não escreva CSS de cor) → obedeça os tokens (claro/escuro de graça) → pense em fluxo, não em tela → faltou peça é buraco do DS (proponha, não invente) → feche com o gate. |
| [`gabarito-vendas.html`](gabarito-vendas.html) | **O gabarito** da tela Vendas/`/sells` (Index/lista) | Aplicação do kit numa tela real, grounded em `Sells/Index.charter` v6: PageHeader v3 (FOCO/Caixa/Faturamento/Comissão) · 4 KPIs (Faturado hoje · Ticket · A receber+ageing · Notas) · tabela 10 colunas (FSM dots · fiscal badge · pagamento+SLA) · status canon (Paga/Pendente/Faturada/Cancelada) · bulk emit NF-e · drawer SaleSheet 480px. **Zero `oklch` cru** — 100% kit. Tema claro/escuro no botão.

## Tokens DS v6 (resumo)

- **Cor:** sistema `oklch` com par claro/escuro completo (`--bg/--sunken/--surface/--raised`, `--text..--text-4`, `--accent` roxo 295 canon, `--pos/--neg/--warn`, origens `--origin-{OS,CRM,FIN}`, etapas `--stage-{slate,indigo,rose,emerald,green}`).
- **Forma:** raios `--r-2..--r-4` + `--r-pill`; sombras `--sh-1/--sh-3`.
- **Tipo:** IBM Plex Sans (UI) + IBM Plex Mono (números/ids/datas).
- **Tema:** `data-theme="dark"` no `<html>`, persistido em `localStorage` (`dsv6.theme`).

> Regra de ouro (da `receita.html`): **não escreva cor crua — use o token.** Faltou componente, é buraco do DS: proponha ao kit, não improvise na tela.

## Mapa pra produção (quando portar)

- `gabarito-vendas.html` → `resources/js/Pages/Sells/Index.tsx` (hoje vivo, KB-9.75 aprovado [W]). A porta DS v6 é **Tier 0 / MWART** — exige gate visual + [W] aprovar screenshot antes de qualquer Edit.
- `showcase.html` → fonte dos tokens/componentes pra `prototipo-ui/tokens.css` + `design-system.css` (REGISTRY_DS_COMPONENTES).
