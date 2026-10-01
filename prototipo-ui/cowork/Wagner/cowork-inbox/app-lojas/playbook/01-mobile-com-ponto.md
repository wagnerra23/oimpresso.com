---
sessao: "01"
titulo: App das lojas v1 — só o Ponto, no visual do protótipo Mobile
dono: "[Design]"
pedido_por: "[W] 2026-10-01 (\"junta a tela de ponto\" · \"solicita para o design fazer isso\")"
base_mobile: mobile/ref/design-v3/oimpresso-mobile/project/ (copia de referencia em cowork-inbox/app-lojas/ref/oimpresso-mobile/)
base_ponto: ponto-mobile.jsx · ponto-data.jsx · ponto-ui.jsx · ponto-page.css (neste projeto)
nao_toca: resources/js/Pages/** · ponto-page.jsx · ponto-telas.jsx
---
# 01 · Ponto dentro do app Mobile

## Contexto
O app que vai para Google Play e App Store e **o protótipo Mobile** (`Oimpresso Mobile.html`: Início · Tarefas · Pedidos · Produção · Mais), nao o site dentro de uma casca. Decisão [W] 2026-10-01, depois de ver o site rodando no emulador: *"não gostei, foi pego o site e emulado. eu quero o Mobile mesmo"*.

O ponto do colaborador (REP-P) hoje existe só como protótipo separado, `ponto-mobile.jsx`, desenhado dentro de moldura e ao lado da fila do gestor. Ele precisa virar parte do app Mobile.

## Escopo da 1ª versão — SÓ o Ponto
Decisão [W] 2026-10-01: *"primeiro só com o ponto"*. A 1ª versão nas lojas **não** leva Tarefas, Pedidos, Produção nem Financeiro (essas telas ainda não têm dados reais do ERP). Ela leva só o que o colaborador usa para o ponto, no **visual** do protótipo Mobile (tokens, cabeçalho, cartões, barra de abas).

## O que pedir ao Design
1. **Navegação do app só com o Ponto.** Telas: **Login** (marca oimpresso, login do ERP) → app com barra de abas própria do Ponto, por exemplo **Ponto · Espelho · Justificar · Conta**. O Design decide a ordem e os nomes; as abas do ERP (Início, Tarefas, Pedidos, Produção, Mais) ficam para a versão seguinte e **não** aparecem nesta.
2. **Três telas no padrão do Mobile** (tokens `oimpresso-tokens.css`, `.oi-app` / `.oi-screen` / `ScreenHeader`), sem moldura e sem o painel "Simular condição de campo":
   - **Bater ponto** — relógio, estado do GPS, os 4 tipos (Entrada · Saída almoço · Retorno almoço · Saída), botão de marcar, recibo com NSR + hash.
   - **Meu espelho** — totais do mês e lista dia a dia.
   - **Justificar** — motivo, dia, das/às ou dia todo, texto.
3. **A fila do gestor (`ValidacaoMobile`) fica FORA do app** nesta versão — é tela de desktop.
4. Margens reais do aparelho: medido no emulador Android 16, o padding fixo de moldura (`.oi-app[data-platform="android"] { padding: 40px 0 24px }`) esconde a barra de abas atrás da barra de gestos. Use `viewport-fit=cover` + `env(safe-area-inset-*)`.

## Regras que nao mudam (nao inventar)
- **Sem biometria/selfie/câmera** — ADR 0383 (LGPD Art. 5º II + Art. 11).
- Anti-fraude do `MobileMarcacaoService`: GPS ≤ 500 m **recusa**; relógio ≤ 30 s **recusa**; fora do geofence **grava e sinaliza** para revisão. Sem "bater mesmo assim".
- Marcação imutável — Portaria MTP 671/2021. Correção só por intercorrência.
- Localização só "durante o uso" (sem segundo plano) — é o que vai declarado nas lojas.
- Tokens do DS, sem cor crua, sem emoji.

## Entrega esperada
- Arquivo novo `ponto-app.html` (+ `screens-ponto.jsx`) reaproveitando `oimpresso-tokens.css` e os átomos do Mobile, com Login + Ponto navegáveis no Android e no iOS. O `Oimpresso Mobile.html` completo fica como está, para a versão seguinte.
- `_saida-01.md` em `cowork-inbox/app-lojas/playbook/` dizendo onde o Ponto ficou e o que mudou.
