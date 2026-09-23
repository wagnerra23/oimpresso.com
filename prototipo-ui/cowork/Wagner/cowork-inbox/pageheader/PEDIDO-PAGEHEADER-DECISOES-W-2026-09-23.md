# PEDIDO — PageHeader: 7 decisões [W] (2026-09-23)

> **Dono:** [CL]. **Origem:** formulário respondido por [W] em 2026-09-23. Tudo lido no `main` @`8795571ef11e` no mesmo turno.
> **Fontes das perguntas:** `prototipo-ui/cowork/Felipe/divergencia-pageheader-tabbar-pt01.md` §4–§5 (31/08) · `memory/requisitos/Whatsapp/CaixaUnificadaV4-visual-comparison.md` (medição 08/09) · `memory/decisions/0395-pageheader-ratchet-required-emenda-0314.md` · `memory/requisitos/_DesignSystem/pageheader-matriz-diferencas.md`.
> **Protótipo já atualizado** (`styles.css?v=ph26d`, `.os-page-h-l`): h1 600 / −0.015em · subtítulo 12px. Aba já é ~36px (`.cli-moduletopnav-tab` 11+13+9+2). Título da Caixa já é 22px.

## Decisões

| # | Item | Decisão [W] | Onde aplica |
|---|---|---|---|
| a | peso do h1 | **600 (DS)** | `resources/js/Components/PageHeader/PageHeader.tsx` — doc de 31/08 diz `font-bold` (700) + `tracking-tight` → `font-semibold` + `-0.015em`. **Linha do h1 NÃO conferida hoje** |
| b | subtítulo | **12px (produção)** | repo já está em `text-xs` (`PageHeader.tsx:184`, conferido 2026-09-23 @`cd78c7a10f66`) — **nada no repo**. DS (`PageHeader` do espelho, `13px`) → 12px |
| c | altura da aba | **36px (DS)** | `resources/js/Components/shared/PageHeaderTabs.tsx:135` — `default: { base: 'px-3 py-1.5 text-sm' }` (~30px, conferido) → altura 36px, padding `0 14px`, 13px |
| d | `TabBar` do DS sem `role="tablist"` + setas | **corrigir no DS** | fonte do DS (`prototipo-ui/design-system/components/TabBar/`) — espelhar a mecânica ←/→/Home/End do `PageHeaderTabs.tsx` |
| e | `PageHeader` do DS sem `role="banner"` | **corrigir no DS** | `prototipo-ui/design-system/components/PageHeader/PageHeader.jsx` — o repo já emite (`PageHeader.tsx:140`, conferido) |
| 2 | título da Caixa Unificada (14px) | **22px (protótipo)** | `Modules/Whatsapp/Resources/js/Pages/Atendimento/CaixaUnificada/Index.tsx` — `<h1 className="font-semibold text-[14px] …">`: tirar o valor arbitrário, adotar o `PageHeader` canon ou `var(--fs-7)` |
| 3 | ADR 0395 (ratchet required) | **aprovar** | merge do PR da ADR → depois o flip da branch protection **na ordem da própria ADR** (merge → flip UTF-8 sem BOM → `protection-drift.mjs` → `update-branch`) |
| 4 | `pageheader-matriz-diferencas.md` | **arquivar** | marcar `lifecycle: arquivado` + `superseded_by` apontando pro canon v3.8/ADR 0189. F10 (16px) e F13 (`ui-sans-serif` forçado) contradizem o v3.8 — não reusar como checklist |

## O que isto NÃO resolve

- **(c) muda altura em ~104+ telas** que usam `PageHeaderTabs` — rodar `pageHeaderTabsDensity.spec.tsx` e `pageHeaderTabsFidelity.spec.tsx` e atualizar os baselines no mesmo PR.
- **(a)** também é a primeira linha visível de toda tela — PR próprio de fundação, **sequencial, nunca em paralelo com telas** (FRESCOR ⚪, incidente #2495).
- **Não verifiquei** se `pageheader-gate.yml` ou `config/pageheader-shared-baseline.json` fixam peso/altura em algum valor que agora quebre.
- Item **f** (pílula inativa em hue 240 cravado no `PageHeaderTabs.tsx`) ficou fora do formulário — continua aberto.

## Ordem sugerida (1 PR cada)

1. ADR 0395 (governança, independente).
2. Arquivar a matriz (só `.md`).
3. (d)+(e) no DS.
4. (a) peso do h1 — fundação.
5. (c) altura da aba — fundação, depois do 4.
6. (2) título da Caixa — tela, depois do 4.
