# Decisões do app nas lojas — estado em 2026-10-01

> Lista única das decisões [W] sobre o app nas lojas. Contato que gerencia as sessões do app: sessão "Coordenar app das lojas (oimpresso-app)" ([W] 2026-10-01; antes "Construir a base do app Mobile"). Quando uma decisão substitui outra, a antiga
> fica riscada aqui com a data — não some. Detalhes de execução ficam nos arquivos ao lado.

## Valendo

| # | Assunto | Decisão | Fonte |
|---|---|---|---|
| D1 | Base técnica | **Capacitor** (repo [`wagnerra23/oimpresso-app`](https://github.com/wagnerra23/oimpresso-app), pasta local `D:\oimpresso-app`). O Expo de `mobile/` fica **fora** das lojas | [W] "base é o Capacitor"; confirmado na coordenação |
| D2 | ID do app | `com.oimpresso.app` (Android e iOS; permanente depois do 1º envio) | [W] via sessão Android |
| D3 | Nome | **`oimpresso`** na loja e embaixo do ícone | [W] "oimpresso nos dois" |
| D4 | Ícone | **A** — só o cubo CMYK no roxo `#795BBF`; splash roxo com o cubo | [W] "icone A" |
| D5 | O que o app mostra | **Telas próprias no `oimpresso-app`** (React empacotado + API Passport do ERP), visual do protótipo Mobile. Ordem do dia 2026-10-01: (1) ERP web atual → (2) `/m` no ERP → (3) **telas próprias**; o `/m` foi reprovado e revertido (#8472) | [W] via sessão que gerencia o app |
| D6 | Ponto do colaborador | Perfil **Colaborador** abre direto no ponto; quem tem o ERP vê as abas da v1 | [W] "sim, perfil colaborador abre no ponto" |
| D7 | Contas do revisor | Duas: **`revisor.ponto`** (colaborador, só ponto) e **`gestor.demo`** (gestor), no business 235 em produção. Senhas no Vaultwarden (`ponto-demo-revisor`, `ponto-demo-gestor`) | [W] "ok duas contas, colaborador e gestor" |
| D8 | Marcações de exemplo | Histórico de marcações **só no staging**; produção só com o que o revisor fizer | [W] "marcações só no staging" |
| D9 | Textos de loja | **1ª submissão descreve só o que o app tem hoje:** Login, Início (escala + resumo do ponto), Ponto (Bater · Meu espelho · Justificar), Conta e lembrete por push (`textos/listagem-pt-BR.md`). A versão com Tarefas/Pedidos/Produção/Pessoas fica em `textos/listagem-pt-BR-completa.md`. **Sem** "REP-P", "REP" ou "registrador oficial" até INPI e certificado ICP-Brasil | sessão que gerencia o app (função ausente = recusa); levantamento legal (#8417) |
| D10 | Política de privacidade | `https://oimpresso.com/privacidade` (app inteiro) + `/privacidade/ponto` (detalhe do ponto) | [W] "fica com a política"; no ar desde #8437 |
| D11 | Conteúdo das telas v1 | Pedido = **venda** do ERP (pipeline FSM de vendas); Produção = fila do **Kanban** por etapa, sem carga %; Tarefas = **ToDo + justificativas do Ponto**; meta do dia = mensal da Jana ÷ dias úteis; **sem** seletor de empresa; urgente = atrasado; **sem** papel Transportadora **1ª submissão às lojas = só o que o app tem hoje (Login, Início, Ponto, Conta, push) — ver D9; Tarefas/Pedidos/Produção/Pessoas entram em submissões seguintes.** | [W] na coordenação; registrado no MAPA-DE-DADOS-v1 §8.1 |
| D12 | Coordenação | A sessão **"Coordenar app das lojas (oimpresso-app)"** (ex-BASE MOBILE) coordena as sessões do app. Instruções novas às sessões passam por ela | [W] na coordenação |

## Substituídas (não usar)

- ~~App = Expo de `mobile/`~~ → D1 (Capacitor).
- ~~Nome "oimpresso Ponto"~~ → D3.
- ~~1ª versão publica o ERP web atual~~ ([W] "1, publica com o ERP atual") → depois `/m` → hoje D5.
- ~~Telas `/m` dentro do ERP~~ (textos #8461) → D5 (telas próprias no app). Screenshots do ERP web ou de `/m` **não servem**.

## Ainda abertas

| O quê | Com quem |
|---|---|
| Telas próprias do `oimpresso-app` prontas + API Passport por tela no ERP (client OAuth do app, endpoints de Ponto, Pedidos, Produção, Pessoas, Tarefas) + como rodar o build demo para as screenshots | sessão "Coordenar app das lojas (oimpresso-app)" |
| Mover as senhas das contas demo para o Vaultwarden | [W] |
| Pacote de módulos "Demo lojas" para o `gestor.demo` | [W] |
| Revisão jurídica de `/privacidade` e `/privacidade/ponto`; confirmar `lgpd@oimpresso.com.br` | Eliana [E] + [W] |
| Screenshots (Android 1080×1920, iPhone 6.9" 1320×2868) — do build demo do `oimpresso-app` no emulador, **depois** que as 4 telas passarem para o design-v4 (não fotografar as v3) | sessão ATIVOS DE LOJA, após a sessão APP CAPACITOR |
