---
date: "2026-10-01"
time: "15:30 BRT"
slug: app-lojas-coordenacao-passada
tldr: "Coordenação das ~12 sessões do app oimpresso nas lojas. A arquitetura mudou 4 vezes no dia (ERP web → /m → telas próprias); três vezes a causa foi a mesma resposta do [W] chegando diferente em sessões diferentes, e o que resolveu foi pergunta única com as opções lado a lado. Coordenação passada à sessão BASE MOBILE por decisão [W]."
prs: [8473]
decided_by: [W]
related_adrs:
  - 0383-ponto-interno-nao-coleta-biometria
  - 0423-push-lembrete-ponto
next_steps:
  - "Estado vivo e próximos passos: sessão BASE MOBILE (coordena) + docs/lojas-app/DECISOES.md"
  - "Merge do #8473 (DECISOES D5/D11/D12 + MAPA §8.1) é do [W]"
---

# App nas lojas — coordenação passada à BASE MOBILE

## Estado MCP no momento do fechamento

Não consultado: o estado vivo deste projeto mora nas sessões e em `docs/lojas-app/DECISOES.md`,
não em task MCP. Quem sabe o estado agora é a sessão "Construir a base do app Mobile dentro do
ERP" (coordenadora por decisão [W]).

## O que ficou decidido (fonte única: `docs/lojas-app/DECISOES.md`)

- App = **Capacitor**, repo `wagnerra23/oimpresso-app`, id `com.oimpresso.app`, nome `oimpresso`, ícone A.
- Mostra **telas próprias do app** no visual do protótipo Mobile v4, falando com o ERP por API Passport por tela. O `/m` dentro do ERP foi feito e revertido (#8463/#8465 → #8472).
- v1: Início, Tarefas, Pedidos, Produção, Pessoas, Ponto, Mais. Pedido = venda (FSM); Produção = Kanban por etapa; Tarefas = ToDo + justificativas do Ponto.
- Textos de loja sem "REP-P" até INPI + ICP-Brasil (#8417).

## Lição de coordenação (para quem coordenar sessões paralelas)

Três vezes no dia a mesma decisão do [W] chegou em versões diferentes por sessões diferentes
(Expo × Capacitor; /m × telas no app). Repassar "o que outra sessão disse que ele disse" espalhou
a contradição. O que fechou cada uma foi **uma pergunta única ao [W] mostrando as respostas
conflitantes lado a lado**, e repassar a resposta **literal**. Também errei por medição: afirmei
"não existe app nativo no repo" com saída cortada por `head -60` (a pasta `mobile/` ficou de fora).

## Pendências do [W] (repassadas à BASE MOBILE)

Chave de upload do Android no Vaultwarden · conta Apple Developer · senhas demo no Vaultwarden ·
pacote "Demo lojas" · e-mail `lgpd@` + revisão da Eliana · chip dos 2 bugs de Pessoas · não
mergear o PR #5 do oimpresso-app (abriria o `/m`).
