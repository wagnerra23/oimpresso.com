# Decisões do app nas lojas — estado em 2026-10-01

> Lista única das decisões [W] sobre o app nas lojas. Quando uma decisão substitui outra, a antiga
> fica riscada aqui com a data — não some. Detalhes de execução ficam nos arquivos ao lado.

## Valendo

| # | Assunto | Decisão | Fonte |
|---|---|---|---|
| D1 | Base técnica | **Capacitor** (repo [`wagnerra23/oimpresso-app`](https://github.com/wagnerra23/oimpresso-app), pasta local `D:\oimpresso-app`). O Expo de `mobile/` fica **fora** das lojas | [W] "base é o Capacitor"; confirmado na coordenação |
| D2 | ID do app | `com.oimpresso.app` (Android e iOS; permanente depois do 1º envio) | [W] via sessão Android |
| D3 | Nome | **`oimpresso`** na loja e embaixo do ícone | [W] "oimpresso nos dois" |
| D4 | Ícone | **A** — só o cubo CMYK no roxo `#795BBF`; splash roxo com o cubo | [W] "icone A" |
| D5 | O que o app mostra | **Telas próprias do app** (empacotadas no `oimpresso-app`, visual do protótipo Mobile v4), falando com o ERP por **API Passport por tela**. **Não** o site do ERP e **não** páginas `/m` dentro do ERP. v1: Início, Tarefas, Pedidos, Produção, Pessoas, Ponto, Mais | [W] na sessão BASE MOBILE após ver `/m` no emulador: "não gostei dele dentro do sistema" → "volta pro app com telas próprias, reverte os PRs" (#8472); confirmado na coordenação |
| D6 | Ponto do colaborador | Perfil **Colaborador** abre direto no ponto; quem tem o ERP vê as abas da v1 | [W] "sim, perfil colaborador abre no ponto" |
| D7 | Contas do revisor | Duas: **`revisor.ponto`** (colaborador, só ponto) e **`gestor.demo`** (gestor), no business 235 em produção. Senhas no Vaultwarden (`ponto-demo-revisor`, `ponto-demo-gestor`) | [W] "ok duas contas, colaborador e gestor" |
| D8 | Marcações de exemplo | Histórico de marcações **só no staging**; produção só com o que o revisor fizer | [W] "marcações só no staging" |
| D9 | Textos de loja | Descrevem só as funções da v1. **Sem** "REP-P", "REP" ou "registrador oficial" até INPI e certificado ICP-Brasil | levantamento legal (#8417) |
| D10 | Política de privacidade | `https://oimpresso.com/privacidade` (app inteiro) + `/privacidade/ponto` (detalhe do ponto) | [W] "fica com a política"; no ar desde #8437 |
| D11 | Conteúdo das telas v1 | Pedido = **venda** do ERP (pipeline FSM de vendas); Produção = fila do **Kanban** por etapa, sem carga %; Tarefas = **ToDo + justificativas do Ponto**; meta do dia = mensal da Jana ÷ dias úteis; **sem** seletor de empresa; urgente = atrasado; **sem** papel Transportadora | [W] na coordenação; registrado no MAPA-DE-DADOS-v1 §8.1 |
| D12 | Coordenação | A sessão **BASE MOBILE** coordena as sessões do app. Instruções novas às sessões passam por ela | [W] na coordenação |

## Substituídas (não usar)

- ~~App = Expo de `mobile/`~~ → D1 (Capacitor).
- ~~Nome "oimpresso Ponto"~~ → D3.
- ~~1ª versão publica o ERP web atual~~ ([W] "1, publica com o ERP atual") → D5. As screenshots do ERP web **não servem**.
- ~~Telas do protótipo em `/m` dentro do ERP~~ (2026-10-01, #8463 + #8465, revertidos no #8472) → D5 (telas próprias no app).

## Ainda abertas

| O quê | Com quem |
|---|---|
| Telas v1 no `oimpresso-app` + API Passport por tela no ERP (client OAuth do app, endpoints de Ponto, Pedidos, Produção, Pessoas, Tarefas) | sessão BASE MOBILE (coordena) |
| Mover as senhas das contas demo para o Vaultwarden | [W] |
| Pacote de módulos "Demo lojas" para o `gestor.demo` | [W] |
| Revisão jurídica de `/privacidade` e `/privacidade/ponto`; confirmar `lgpd@oimpresso.com.br` | Eliana [E] + [W] |
| Screenshots (Android 1080×1920, iPhone 6.9" 1320×2868) — só depois das telas do app existirem | sessão ATIVOS DE LOJA, com login do [W] no navegador |
