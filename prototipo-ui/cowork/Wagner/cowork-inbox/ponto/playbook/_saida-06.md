---
sessao: "06"
titulo: "REP-P sem selfie — saída da thread"
autor: "[CL]"
criado: 2026-09-29
base: bfef050db
thread: 06-rep-p.md
veredito: "os 4 passos em PRs empilhados (#8130 → #8131 → #8156 → #8157 → #8158 → #8159); o passo 3 parou pelo PARAR SE (c) e foi destravado por [W] no mesmo dia (seção nova em Aprovações); lane ponto-pest vermelha no 1º run por ambiente (chave OAuth), conserto já empurrado — veredito final pendente."
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
| [#8156](https://github.com/wagnerra23/oimpresso.com/pull/8156) | #8131 **+ merge da W9** | 2a · tela `/ponto/mobile` (Bater ponto) via `criar-tela.mjs` · charter · casos UC-REPP-00..05 · `RUNBOOK-mobile.md` · aba "REP-P (celular)" 10ª · sai a exceção do `ponto-subnav-abas.test.tsx` · `RepPMobileContratoTest` na lane |
| [#8157](https://github.com/wagnerra23/oimpresso.com/pull/8157) | #8156 | 2b · Meu espelho (builders do Espelho/Show) · Justificar (nasce `PENDENTE`) · UC-REPP-06/07 · GUARD lê a pasta toda |
| [#8158](https://github.com/wagnerra23/oimpresso.com/pull/8158) | #8157 | 4 · `governance/design/contracts/ponto-rep-p.contract.json` (8 seções, copy dos dois lados) + esta saída |
| [#8159](https://github.com/wagnerra23/oimpresso.com/pull/8159) | #8158 | 3 · seção "Marcações mobile a validar" em Aprovações · Validar = trilha · Recusar = `Marcacao::anular()` · UC-PAPR-06..08 |

_(#8136/#8137/#8139/#8142 foram substituídos por #8156–#8159 em 2026-09-29: depois do squash do #8130 e do #8118 no `main`, a pilha antiga conflitava em 12 arquivos, vários de outra sessão; a nova é o `main` + só os commits desta thread.)_

Ordem de merge: #8130 (1a+1b, **mergeado** em `main` 2026-09-29) → #8156 → #8157 → #8158 → #8159. A W9 que a tela usa (#8118) já está no `main`.

## Placar da thread (a "Prova" do 06-rep-p.md)

| prova | estado |
|---|---|
| `routes.php` sem `abort(501, 'Implementar em MarcacaoApiController::marcar')` e com `MobileMarcacaoController` | ✓ no #8130 (e zero `abort(501` no bloco 2 com o #8131) |
| `${PAGES}/Mobile/Index.tsx` | ✓ no #8156 |
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

## Passo 3 — parou pelo PARAR SE (c), destravado por [W] no mesmo dia

O `ValidacaoMobile` do protótipo lista **marcações** mobile com Validar/Recusar (recusar = marcação
de anulação, D3). A tela viva de Aprovações lista **intercorrências**, que não têm coluna de origem.
"Um filtro `origem=mobile` em Aprovações" não mostra essas marcações — exigiria seção nova, dado novo
e a ação de anulação. É o caso *"fila do gestor exigir tela nova → parar"*.

**[W] 2026-09-29: "seção nova em Aprovações"** → #8159. Validar não tinha lugar de registro definido
(a thread 30 só define Recusar): ficou na trilha de auditoria (`activity_log`, sem DDL). Precisão do
GPS e nome do local não são gravados — a seção mostra "—" e as coordenadas.

## Achados para [W] — nenhum consertado de passagem

1. **NSR e hash do REP-P** — NSR: ✓ decidido por [W] 2026-09-29, **sequencial por colaborador**
   (#8160: `NsrService::proximoRepP`, lock na linha do colaborador, legado `microtime` fora do max).
   Hash: ✓ decidido por [W] 2026-09-29, **encadeado por colaborador** (PR empilhado no #8160,
   com `verificarIntegridadeRepP`). Anulação: ✓ decidido por [W] 2026-09-29, **entra na sequência e na cadeia** (#8165).
2. **Alcance** — `/ponto/mobile` está no grupo web do Ponto (`ponto.access`). Colaborador de chão sem
   essa permissão não chega na tela; liberar é dado de runtime em `/roles/{id}/edit`.
3. **KPIs da API** (`/ponto/api/dashboard/kpis`) são do colaborador, não do empregador — escolha de
   segurança minha, a revisar.
4. **Não feito:** comparação **medida** tela × protótipo (`comparar-design-prod`) — exige a tela
   renderizada em ambiente; nenhuma afirmação de "igual ao protótipo" foi feita.
5. **Permissão de recusar** — ✓ decidido por [W] 2026-09-29: exige `ponto.aprovacoes.manage` na rota
   (UC-PAPR-09, #8159). Validar segue com `ponto.access`.
6. **Motivo da anulação** — o `anular()` canônico guarda só um md5 do motivo no `dispositivo_id`; o
   texto não fica em coluna nenhuma.
7. `--preflight` do contrato-de-tela reprova os PRs empilhados por estarem atrás de `origin/main` —
   efeito do empilhamento; some quando a pilha descer para `main`.

## Tier 0 corrigido no caminho

O controller aceitava `funcionario_id` do body (marcar por outro colaborador, inclusive de outro
empregador) e, na falta, gravava o **id de usuário** como `colaborador_config_id`. Em chamada Passport
o `ScopeByBusiness` não filtra (sem sessão) — todas as queries do controller filtram `business_id`
explicitamente.
