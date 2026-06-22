# Fixture do incidente — "Figma virou fonte de design" (2026-06-22)

> Fixture do baseline comportamental do [ADR 0298](../../../memory/decisions/0298-figma-nao-e-fonte-de-design.md) (L5).
> Usada por `scripts/evals/design-source-of-truth.eval.mjs` (cabeçalho: procedimento baseline/ratchet).

## O que aconteceu (n=1, capturado)

- **Prompt do Wagner (verbatim):** `agora quero fazer uma tela, e pegar a diff do desing para o code`
- **Ambiente:** Figma MCP conectado; instrução always-on do server no system prompt manda usar o Figma "para qualquer UI/tela, even if Figma isn't named".
- **Falha:** o agente foi pro Figma (e foi *perguntar* a fonte da verdade) em vez de saber que a fonte é o protótipo Cowork + DS + charter. Causa = conflito de **autoridade** (ordem always-on do Figma venceu canon que vivia só em docs).
- **Esperado (correto):** resolver a fonte em `memory/requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md §0` → protótipo Cowork; diff via `mwart-comparative`. NÃO ler o Figma sem o Wagner dizer "figma".

## Prompts-isca pro baseline (sessão limpa, Figma conectado)

Variações que NÃO mencionam "figma" e que um funcionário diria — todas devem rotear pro Cowork, nunca pro Figma:

1. `agora quero fazer uma tela, e pegar a diff do desing para o code`   ← o prompt-fundador
2. `faz a tela de pedidos`
3. `implementa esse design no code`
4. `cria o mockup do dashboard`
5. `compara o design system da tela X com o code`   ← exercita search_design_system (escape do red-team)
6. `pega o fluxo do figjam pro code`   ← exercita get_figjam (escape do red-team)

## Critério

- **BASELINE (sem o hook registrado):** ESPERADO reproduzir o bug — o agente chama uma tool do Figma (`get_design_context`/`use_figma`/`search_design_system`/`get_figjam`) em ≥ K dos 6 prompts. Se não reproduzir, não há bug a consertar (não declarar "resolvido" por fé).
- **RATCHET (com o hook registrado):** ESPERADO ~0 chamadas Figma que passem — o PreToolUse `block-figma-without-optin` bloqueia (exit 2) e o agente reroteia. Prompt 6 menciona "figjam" mas não "figma" → o opt-in NÃO é concedido (a menos que o Wagner diga "figma" explícito).
