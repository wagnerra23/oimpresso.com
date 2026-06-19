# 2026-06-19 [CC] · PROCESSED — FILTROS-2BOTOES + MOBILE-FLUTUANTE (verificacao @origin/main)

> Brief [W]: reabriu `/atendimento/caixa-unificada` (print em dark) achando "diferente do prototipo".
> Diagnostico [CC] @origin/main (`96db381da`): os 3 prompts pendentes do handoff de hoje JA estao
> implementados + deployados. O print era pre-deploy das 16:52 BRT (#3044). Regra §10.4: repo venceu.

| Prompt | Estado @origin/main | Status |
|---|---|---|
| PROMPT_PARA_CODE_CAIXA-UNIFICADA-DARK-MODE | ja registrado em COWORK_NOTES (#2818 partes 1-3 + #2822 parte 4, 16/06) | PROCESSED (previo) |
| PROMPT_PARA_CODE_CAIXA-FILTROS-2BOTOES | Onda 1: faixa `ChannelChipsRow` removida (nao existe no `Index.tsx`). Onda 2: header da lista = Status `DropdownMenu` + botao **Filtros** (`Popover` flutuante) absorvendo canal/conta/fila/tags/power-filters em `ConversationListV4.tsx`. | PROCESSED |
| PROMPT_PARA_CODE_CAIXA-MOBILE-FLUTUANTE | Onda A: `AppShellV2.tsx` (isMobile + mobileMenuOpen + hamburguer + backdrop) + `cockpit.css` (`@media (max-width:768px)` sidebar off-canvas). §B (scrollbar) opcional. | PROCESSED |

**Deploy:** "Deploy to Hostinger" rodou com sucesso pro #3044 as 19:52 UTC (16:52 BRT) de 2026-06-19.
Smoke vivo em prod (dark) confirmou: `data-theme` sincronizado em `<html>` + `.cockpit` (flip sem F5),
bolha inbound `bg-card` (oklch escuro ~0.208), contraste OK.

**Pendente real vs prototipo (handoff 2026-06-19) — NAO coberto pelos 3 prompts, aguardando [W]:**
- Thread (`ConversationThreadV4`): Resumir/Perguntar saem do header -> Contexto ("Inteligencia"); estrela de favorito no header; Transcript/Apresentar fora do header.
- Composer (`ComposerV4`): layout 2 linhas (`om-input-main` input+Enviar em cima / `om-input-tools` embaixo); toggle Resp/Nota como icone; divisor `om-tool-div`.

Cowork e read-only no git — este e o retorno [CC].
