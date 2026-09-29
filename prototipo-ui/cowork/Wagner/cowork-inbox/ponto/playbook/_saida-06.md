---
sessao: "06"
titulo: "REP-P sem selfie — saída da thread"
autor: "[CL]"
criado: 2026-09-29
base: bfef050db
thread: 06-rep-p.md
veredito: "passos 1, 2 e 4 entregues em 5 PRs empilhados (#8130 → #8131 → #8136 → #8137 → PR do contrato), nenhum mergeado; passo 3 PARADO pelo PARAR SE (c) da própria thread; lane ponto-pest vermelha no 1º run por ambiente (chave OAuth), conserto já empurrado — veredito final pendente."
---

# _saída 06 · REP-P sem selfie

## Destrave

W10 ratificada por [W] em 2026-09-29 (ADR 0419, PR #8129 — **aberto** quando esta saída foi escrita).
Empilhamento sobre a cadeia W9 (#8118 → #8123 → #8125) por pedido explícito de [W] no chat.

## O que saiu

| PR | base | conteúdo |
|---|---|---|
| [#8130](https://github.com/wagnerra23/oimpresso.com/pull/8130) | `main` | 1a · `marcar` · `marcacoes/hoje` · `saldo` saem do 501 → `MobileMarcacaoController` (`ponto.api.*`) · Tier 0: colaborador sempre o do usuário · 10 casos Pest |
| [#8131](https://github.com/wagnerra23/oimpresso.com/pull/8131) | #8130 | 1b · intercorrências GET/POST · escala de hoje · KPIs · bloco 2 sem nenhum 501 · +4 casos |
| [#8136](https://github.com/wagnerra23/oimpresso.com/pull/8136) | #8131 **+ merge da W9** | 2a · tela `/ponto/mobile` (Bater ponto) via `criar-tela.mjs` · charter · casos UC-REPP-00..05 · `RUNBOOK-mobile.md` · aba "REP-P (celular)" 10ª · sai a exceção do `ponto-subnav-abas.test.tsx` · `RepPMobileContratoTest` na lane |
| [#8137](https://github.com/wagnerra23/oimpresso.com/pull/8137) | #8136 | 2b · Meu espelho (builders do Espelho/Show) · Justificar (nasce `PENDENTE`) · UC-REPP-06/07 · GUARD lê a pasta toda |
| contrato | #8137 | 4 · `governance/design/contracts/ponto-rep-p.contract.json` (8 seções, copy dos dois lados) + esta saída |

Ordem de merge: #8130 → #8131 → cadeia W9 → #8136 → #8137 → contrato.

## Placar da thread (a "Prova" do 06-rep-p.md)

| prova | estado |
|---|---|
| `routes.php` sem `abort(501, 'Implementar em MarcacaoApiController::marcar')` e com `MobileMarcacaoController` | ✓ no #8130 (e zero `abort(501` no bloco 2 com o #8131) |
| `${PAGES}/Mobile/Index.tsx` | ✓ no #8136 |
| `ponto-rep-p.contract.json` com `alvo` + `secoes` | ✓ — `contrato-de-tela --contract` limpo |
| controller **sem** `selfie` | ✓ — só a palavra "biometria" no docblock que registra a decisão |
| lane `ponto-pest.yml` verde (GUARD incluído) | ⏳ **pendente** — ver abaixo |

## Lane ponto-pest — o que foi medido

1º run do #8130 (run `36560526640`): **9 failed · 1 skipped · 348 passed**. As 9 são o bloco HTTP
novo do `Wave28MobileMarcacaoTest`, **todas** por `LogicException: Invalid key supplied`
(`oauth2-server/CryptKey`): o guard `api` monta o `ResourceServer` com a chave pública a cada
request e a lane não tinha chave OAuth. Ambiente, não comportamento. Conserto (`passport:keys` na
pré-condição, idioma do `DesktopAuthTest`) empurrado no #8130 e propagado por merge. O veredito
verde **ainda não existe** — os status dos UC-REPP estão ⬜ até lá.

## PARADO — passo 3 (fila do gestor), pelo PARAR SE (c)

O `ValidacaoMobile` do protótipo lista **marcações** mobile com Validar/Recusar (recusar = marcação
de anulação, D3). A tela viva de Aprovações lista **intercorrências**, que não têm coluna de origem.
"Um filtro `origem=mobile` em Aprovações" não mostra essas marcações — exigiria seção nova, dado novo
e a ação de anulação. É o caso *"fila do gestor exigir tela nova → parar"*. Decisão [W]: seção nova
em Aprovações, tela própria, ou fila por outra via. (`Aprovacoes/Index.tsx` também estava sendo
editado por outra sessão no mesmo dia.)

## Achados para [W] — nenhum consertado de passagem

1. **NSR e hash do REP-P** — para `rep_id = null` o `NsrService` devolve `microtime*1000` (não
   sequencial) e o `MarcacaoService` só encadeia o hash quando há REP. A thread e a ADR 0383 prometem
   "NSR + hash encadeado". Ambos os services estão no `nao_toca` desta thread e tocam Portaria 671/2021.
2. **Alcance** — `/ponto/mobile` está no grupo web do Ponto (`ponto.access`). Colaborador de chão sem
   essa permissão não chega na tela; liberar é dado de runtime em `/roles/{id}/edit`.
3. **KPIs da API** (`/ponto/api/dashboard/kpis`) são do colaborador, não do empregador — escolha de
   segurança minha, a revisar.
4. **Não feito:** comparação **medida** tela × protótipo (`comparar-design-prod`) — exige a tela
   renderizada em ambiente; nenhuma afirmação de "igual ao protótipo" foi feita.
5. `--preflight` do contrato-de-tela reprova os PRs empilhados por estarem atrás de `origin/main` —
   efeito do empilhamento; some quando a pilha descer para `main`.

## Tier 0 corrigido no caminho

O controller aceitava `funcionario_id` do body (marcar por outro colaborador, inclusive de outro
empregador) e, na falta, gravava o **id de usuário** como `colaborador_config_id`. Em chamada Passport
o `ScopeByBusiness` não filtra (sem sessão) — todas as queries do controller filtram `business_id`
explicitamente.
