# Decisões do app nas lojas — estado em 2026-10-01

> Lista única das decisões [W] sobre o app nas lojas. Contato que gerencia as sessões do app: sessão "Construir a base do app Mobile" ([W] 2026-10-01). Quando uma decisão substitui outra, a antiga
> fica riscada aqui com a data — não some. Detalhes de execução ficam nos arquivos ao lado.

## Valendo

| # | Assunto | Decisão | Fonte |
|---|---|---|---|
| D1 | Base técnica | **Capacitor** (repo [`wagnerra23/oimpresso-app`](https://github.com/wagnerra23/oimpresso-app), pasta local `D:\oimpresso-app`). O Expo de `mobile/` fica **fora** das lojas | [W] "base é o Capacitor"; confirmado na coordenação |
| D2 | ID do app | `com.oimpresso.app` (Android e iOS; permanente depois do 1º envio) | [W] via sessão Android |
| D3 | Nome | **`oimpresso`** na loja e embaixo do ícone | [W] "oimpresso nos dois" |
| D4 | Ícone | **A** — só o cubo CMYK no roxo `#795BBF`; splash roxo com o cubo | [W] "icone A" |
| D5 | O que o app mostra | **Telas próprias dentro do `oimpresso-app`**, no visual do protótipo Mobile (v1: Início, Tarefas, Pedidos, Produção, Pessoas, Ponto, Mais). **Não** o site do ERP e **não** `/m` no ERP | [W] via sessão "Construir a base do app Mobile" (gestora das sessões do app), 2026-10-01; revert do `/m` no #8472 |
| D6 | Ponto do colaborador | Perfil **Colaborador** abre direto no ponto; quem tem o ERP vê as abas da v1 | [W] "sim, perfil colaborador abre no ponto" |
| D7 | Contas do revisor | Duas: **`revisor.ponto`** (colaborador, só ponto) e **`gestor.demo`** (gestor), no business 235 em produção. Senhas no Vaultwarden (`ponto-demo-revisor`, `ponto-demo-gestor`) | [W] "ok duas contas, colaborador e gestor" |
| D8 | Marcações de exemplo | Histórico de marcações **só no staging**; produção só com o que o revisor fizer | [W] "marcações só no staging" |
| D9 | Textos de loja | Descrevem só as funções da v1. **Sem** "REP-P", "REP" ou "registrador oficial" até INPI e certificado ICP-Brasil | levantamento legal (#8417) |
| D10 | Política de privacidade | `https://oimpresso.com/privacidade` (app inteiro) + `/privacidade/ponto` (detalhe do ponto) | [W] "fica com a política"; no ar desde #8437 |

## Substituídas (não usar)

- ~~App = Expo de `mobile/`~~ → D1 (Capacitor).
- ~~Nome "oimpresso Ponto"~~ → D3.
- ~~1ª versão publica o ERP web atual~~ ([W] "1, publica com o ERP atual") → depois `/m` → hoje D5.
- ~~Telas `/m` dentro do ERP~~ (textos #8461) → D5 (telas próprias no app). Screenshots do ERP web ou de `/m` **não servem**.

## Ainda abertas

| O quê | Com quem |
|---|---|
| Telas próprias do `oimpresso-app` prontas + como rodar o build demo para as screenshots | sessão gestora do app ("Construir a base do app Mobile") |
| Decisões D4–D6 do mapa de Tarefas, Pedidos e Produção (o mapa é da sessão CONTA DEMO) | [W] |
| Mover as senhas das contas demo para o Vaultwarden | [W] |
| Pacote de módulos "Demo lojas" para o `gestor.demo` | [W] |
| Revisão jurídica de `/privacidade` e `/privacidade/ponto`; confirmar `lgpd@oimpresso.com.br` | Eliana [E] + [W] |
| Screenshots (Android 1080×1920, iPhone 6.9" 1320×2868) — do build demo do `oimpresso-app` | sessão ATIVOS DE LOJA |
