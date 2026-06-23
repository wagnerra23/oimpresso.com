# Sessão 2026-05-31 — Análise do projeto + P1 faxina de tokens

## Pedido
[W]: "transforme-se em gerente/projetista e analise todo meu projeto" → depois "sim faça completo" → depois "faça".
Interpretação: (1) diagnóstico completo do projeto; (2) executar o P1 que apontei (faxina da cascata de tokens).

## O que foi feito
1. **Leitura da espinha** — STATUS, MEMORY_INDEX, LICOES_CC (L-01→L-18), Financeiro.charter, AUDITORIA_MODULOS, app.jsx, head do Chat.html.
2. **Diagnóstico visual** — `Diagnóstico de Projeto - CC.html` (working doc, não-canon): placar de saúde (composta 6.7), mapa 7 telas reais vs 29 stubs, P1 cascata CSS · P2 largura×profundidade · P3 identidade, o que preservar, roadmap 3 sessões.
3. **Auditoria de cascata (evidência estática)** — `--accent` definido em 3 fontes vivas (`styles.css :root`, `tokens.css :root`, `mockup-pages.css .mockup-page`), todas roxo 295, + 1 bloco DUPLICADO literal em `mockup-pages.css` (linhas 6 e 192). `financeiro.css` e demais page-css NÃO redefinem `--accent`.
4. **P1 executado (cirúrgico, sem mudar valor):**
   - `mockup-pages.css`: removida a 2ª cópia literal do bloco de tokens (código morto); removido o leak global `* {}`+`html,body{}` (forçava body do shell pra 12.5/13px sobre o 13.5px do `styles.css`); removido o `--accent` hardcoded de `.mockup-page` (agora herda canon + responde ao tweak `accentHue`).
   - `styles.css :root` e `tokens.css :root`: comentários canônicos documentando a cascata (runtime = `app.jsx` inline; `:root` = fallback; tokens.css = espelho).

## Decisões
- Nenhuma decisão de lei (tudo working/Cowork). P2 (consolidar vs espalhar) e P3 (roxo universal vs hue-por-módulo) ficam pendentes — **Tier 0, só [W]**.

## Erros + correção
- Sonda ao vivo (`getComputedStyle`/screenshot/`eval_js`) deu timeout no app pesado → **não adivinhei** qual `:root` vence (armadilha L-10); só dedupei o provável pela fonte (idênticos/valor-igual/leak). Registrado em **L-19**.

## Residual
- Única mudança de render: body do shell 12.5/13px → 13.5px (intencional do styles.css; leak removido). Verificar com [W] se a leve diferença de tamanho incomoda (revert = 1 linha em styles.css).
- Enforcement durável (Stylelint `.css` + regra "não redeclarar `--accent` fora do canônico") já está enfileirado em `COWORK_NOTES` ("Guard de lint anti-drift") — falta espelhar no repo.
- Espelhar a faxina dos `.css` pro repo (ainda só no Cowork).

## Refs
- `Diagnóstico de Projeto - CC.html` · `mockup-pages.css` · `styles.css` · `tokens.css` · `app.jsx` · L-10/L-19 · ADR 0235 · `COWORK_NOTES` (Guard de lint).

## Próximo passo
[W] decide P2 e P3 (Tier 0). [CC] pode: montar os 6 charters faltantes (Vendas/Inbox/Oficina/Compras/CRM/Clientes), ou espelhar a faxina pro repo via ponte.
