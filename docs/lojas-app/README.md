# Ativos de loja — app oimpresso

App único nas lojas: o ERP no celular **junto com o ponto** ([W] 2026-10-01). Nome proposto: `oimpresso`.
Pasta neutra de propósito: não depende do framework do app. Base decidida por [W] em 2026-10-01:
Capacitor com o ERP web inteiro (`com.oimpresso.app`); `docs/lojas-app/app/` usa os nomes do `@capacitor/assets`
(`npx @capacitor/assets generate --assetPath docs/lojas-app/app --iconBackgroundColor '#795BBF' --splashBackgroundColor '#795BBF'`).

Gerados por `node docs/lojas-app/gerar-ativos.mjs` (da raiz do repo) a partir do cubo CMYK do Design
System e do roxo `oklch(0.55 0.15 295)` = `#795BBF`. Ícone escolhido por [W] em 2026-10-01: **variante A** (só o cubo).
Para mudar a arte, mude o script e rode de novo — não edite os PNGs à mão.

| Arquivo | Uso | Exigência (conferida 2026-10-01) |
|---|---|---|
| `app-store-icon-1024.png` | App Store Connect | 1024×1024, PNG **sem alfa** |
| `play-icon-512.png` | Play Console | 512×512, PNG, ≤1 MB |
| `play-feature-graphic-1024x500.jpg` | Play Console (obrigatório) | 1024×500, JPEG/PNG sem alfa |
| `app/icon-only.png` | ícone do app (Capacitor) | 1024×1024, sem alfa |
| `app/icon-{foreground,background}.png` | ícone adaptativo Android | 1024×1024; cubo dentro da zona segura |
| `app/splash.png`, `app/splash-dark.png` | splash | 2732×2732, fundo `#795BBF` |
| `textos/listagem-pt-BR.md` | nome, descrições, palavras-chave, classificação etária | rascunho para [W] |
| `textos/privacidade-lojas.md` | Data Safety (Google) e App Privacy (Apple) | rascunho para [W] |

**Screenshots:** pendentes da conta demo (sessão CONTA DEMO). Por decisão de [W], as marcações de exemplo
existem só no staging; produção fica limpa para o revisor. Tamanhos: iPhone 6.9" 1320×2868 · Android 1080×1920
(4+ para elegibilidade a destaque). iPad dispensado se `supportsTablet: false`.

Nada aqui contém dado pessoal. Credenciais da conta demo vão direto no console das lojas, nunca neste repo.
