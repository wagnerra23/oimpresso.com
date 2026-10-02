---
sessao: "01"
titulo: App das lojas v1 — Mobile com o Ponto (escopo da coordenação)
dono: "[Design]"
pedido_por: "[W] 2026-10-01 (\"junta a tela de ponto\" · \"solicita para o design fazer isso\")"
base_mobile: projeto Claude Design "Mobile app structure review" (b29cacda-da7c-4c02-b91e-4064b35ba951) — cópia de referência em wagnerra23/oimpresso.com mobile/ref/design-v4/ (antes: mobile/ref/design-v3/, ver errata)
base_ponto: ponto-mobile.jsx · ponto-data.jsx · ponto-ui.jsx · ponto-page.css (neste projeto)
nao_toca: resources/js/Pages/** · ponto-page.jsx · ponto-telas.jsx
---
# 01 · Ponto dentro do app Mobile

> **Errata [CL] 2026-10-02 — decisões [W] de 2026-10-01 que mudaram este pedido:**
> - **Arquitetura:** o `/m` dentro do ERP foi reprovado (*"não gostei dele dentro do sistema"*) e revertido no `oimpresso.com` (PR #8472). O app das lojas é o repo **`wagnerra23/oimpresso-app`** (Capacitor + React/Vite, telas próprias, dados pela API Passport do ERP).
> - **Onde o desenho vive:** no projeto **"Mobile app structure review"** (`b29cacda…`), que o [W] apontou como o correto. As telas 36 Bater ponto · 37 Meu espelho · 38 Justificar já estão lá. Este playbook passa a ser só o registro do pedido; o trabalho de desenho continua naquele projeto.
> - **Tema (D14):** o app segue o tema claro/escuro do celular.
> - **Dashboard (tela 35):** a decidir pelo [W].
> - Decisões completas: `docs/lojas-app/DECISOES.md` (D5, D13, D14) no `oimpresso.com`.

## Contexto
O app que vai para Google Play e App Store e **o protótipo Mobile** (`Oimpresso Mobile.html`: Início · Tarefas · Pedidos · Produção · Mais), nao o site dentro de uma casca. Decisão [W] 2026-10-01, depois de ver o site rodando no emulador: *"não gostei, foi pego o site e emulado. eu quero o Mobile mesmo"*.

O ponto do colaborador (REP-P) hoje existe só como protótipo separado, `ponto-mobile.jsx`, desenhado dentro de moldura e ao lado da fila do gestor. Ele precisa virar parte do app Mobile.

## Escopo da 1ª versão (decisão [W] 2026-10-01 — substitui o "só o Ponto" anterior)
Entram: **Início · Tarefas · Pedidos · Produção · Pessoas · Ponto · Mais**. Ficam para a v2: **Produtos · Venda rápida · Finanças**.

Arquitetura: ~~as telas viram páginas Inertia dentro do ERP, sob `/m`; o app das lojas abre `/m`~~ (reprovado, ver errata). As telas são **próprias do app `oimpresso-app`**, falando com o ERP pela API Passport. O Design desenha estas telas; o código é feito no `oimpresso-app` (sessão do app Capacitor, coordenada pela sessão "Coordenar app das lojas (oimpresso-app)").

## O que pedir ao Design
1. **Navegação da v1.** Barra de abas com no máximo 5 itens (Material). Sugestão: Início · Tarefas · Ponto · Pedidos · Mais, com Produção e Pessoas dentro de Mais — o Design decide. **Pessoas** é tela nova (não existe no protótipo Mobile): lista da equipe com quem está trabalhando agora pelo ponto do dia. Login com a marca oimpresso.
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
- `Oimpresso Mobile.html` atualizado (ou `screens-ponto.jsx` + `screens-pessoas.jsx` carregados por ele) com a navegação da v1 e as telas novas, navegável no Android e no iOS. Produtos, Venda rápida e Finanças podem ficar no arquivo, mas fora da navegação da v1. **Atualização 2026-10-02:** o desenho vive no projeto "Mobile app structure review" (`Oimpresso Mobile.dc.html`), não neste projeto.
- `_saida-01.md` em `cowork-inbox/app-lojas/playbook/` dizendo onde o Ponto ficou e o que mudou.
