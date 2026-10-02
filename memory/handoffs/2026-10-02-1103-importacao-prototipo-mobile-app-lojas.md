---
date: "2026-10-02"
time: "1103"
slug: "importacao-prototipo-mobile-app-lojas"
tldr: "Protótipo do app das lojas (Claude Design b29cacda) importado em mobile/ref/design-v4, âncora do projeto trocada para o oimpresso-app, decisões D13 (v1 = 7 áreas) e D14 (tema do celular) registradas, CI de PR no oimpresso-app e rota de importação --projeto mobile. Só o #8509 segue aberto."
decided_by: [W]
cycle: null
prs: [8467, 8470, 8473, 8485, 8494, 8498, 8509]
us: []
next_steps:
  - "#8509 (painel + receber-handoff --projeto mobile) entrar — a coordenação vigia o CI"
  - "[W] decidir se o Dashboard (tela 35) entra na v1"
  - "Próximo export do Design: node scripts/design-sync/receber-handoff.mjs --zip <zip> --projeto mobile, ler o relatório, depois --apply"
related_adrs: []
---

# Handoff 2026-10-02 11:03 BRT — importação do protótipo mobile e âncora do app das lojas

## TL;DR

O protótipo correto do app das lojas é o projeto do Claude Design **"Mobile app structure review"** (`b29cacda`), confirmado pelo [W] com screenshot. Ele está no repo em `mobile/ref/design-v4/`, a âncora do projeto aponta para o `wagnerra23/oimpresso-app` e a importação dos próximos exports passa a ter rota própria (#8509, aberto). A coordenação das sessões do app é da sessão "Coordenar app das lojas (oimpresso-app)".

## O que foi feito

| PR | O quê | Estado |
|---|---|---|
| #8467 | Protótipo v4 em `mobile/ref/design-v4/` (44 arquivos, cópia fiel; 1 CNPJ do mock com DV válido trocado) | mergeado |
| #8470 | BRL scan isenta `mobile/ref/` (referência de design, como `prototipo-ui/`) | mergeado |
| #8485 | v4 atualizado com o export novo: 50 alturas para 36 px (Sincronizar agora, filtros) | mergeado |
| #8473 | `DECISOES.md`: D11/D12 + **D13 v1 = 7 áreas** + **D14 tema do celular** ([W]) | mergeado |
| #8494 | `github.md` novo na cópia do ERP (alvo = oimpresso-app) | mergeado |
| #8498 | Playbook `cowork-inbox/app-lojas/01` com errata (/m reprovado), publicado no Cowork | mergeado |
| #8509 | `PROJETOS.mobile` no painel + `receber-handoff --projeto mobile` (cópia fiel, invalida DV, nunca apaga) | **aberto** |
| app #15 | `pr-check.yml` no oimpresso-app: typecheck + 13 testes de contrato do Ponto (6 mutantes pegos) | mergeado |

Escritas no Claude Design, com opt-in explícito do [W]: `github.md` do projeto `b29cacda` (alvo trocado de `oimpresso.com/mobile/` para `oimpresso-app`, revisado por adversário antes) e o playbook acima no projeto do site.

## Decisões [W] desta sessão

- O app das lojas é o `oimpresso-app` (telas próprias); `/m` no ERP foi reprovado e revertido (#8472, outra sessão).
- v1 = 7 áreas: Início, Tarefas, Pedidos, Produção, Pessoas, Ponto e Mais. Substitui o "1ª submissão só com o Ponto", que tinha sido decisão de sessão, não do [W].
- O app segue o tema claro/escuro do celular.
- Pendente: **Dashboard (tela 35)** entra na v1? Marcado "a decidir" no `github.md`.

## Lições que valem para a próxima sessão

- O zip do primeiro export estava **atrás** do projeto vivo (o Design editou depois). Antes de importar, comparar com `get_file` do projeto.
- A versão viva de um arquivo de `cowork-inbox/` pode ser **mais nova** que a do git: subir a cópia do git apagaria trabalho do Design. Partir da viva.
- O hook de opt-in do DesignSync trata qualquer "que/como/qual" como pergunta e não libera; peça ao [W] uma linha limpa ("publica X no design-sync").
- Eu afirmei um risco ("zip mobile cai no espelho do site") sem rodar; rodando, a rota já recusava. Corrigido no relatório.

## Estado MCP no momento do fechamento

O servidor MCP `oimpresso` desconectou na 1ª tentativa e voltou minutos depois; consulta refeita em 2026-10-02 ~11:05 BRT:
- `cycles-active`: nenhum cycle ATIVO em COPI.
- `my-work` (@wr23): sem tasks ativas.
- `decisions-search "app lojas mobile Capacitor oimpresso-app"`: nenhuma ADR do tema (os 3 resultados são de outros assuntos — sidebar, NFSe, caixa). As decisões do app vivem em `docs/lojas-app/DECISOES.md` (D1–D14), não em ADR.

Estado medido por `gh` em 2026-10-02 11:03 BRT: #8467, #8470, #8473, #8485, #8494 e #8498 MERGED; #8509 OPEN; `oimpresso-app` #15 MERGED. `whats-active` rodado mais cedo na sessão (2026-10-01) mostrou as sessões do app; a coordenação é da sessão "Coordenar app das lojas (oimpresso-app)".
