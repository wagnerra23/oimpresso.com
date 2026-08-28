---
date: "2026-08-28"
time: "1745 BRT"
slug: "ponto-runbooks-fronteira-dono-modulo-100-inertia"
tldr: "Pedido era criar os RUNBOOKs faltantes do Ponto (8 docs). Terminou com o módulo 100% Inertia, o catálogo de relatórios ligado no gerador que já existia e um gate required consertado — porque [W] perguntou se os itens que eu devolvi como decisão dele não eram conflito de fronteira. Eram: dois dos quatro eram meus. 5 PRs mergeados, 11 gates distintos morderam, zero falso-positivo."
decided_by: [W]
cycle: null
prs: [6403, 6409, 6411, 6412, 6415]
us: []
next_steps:
  - "Nada bloqueado da minha parte — os 5 PRs estão no main e verificados de lá"
  - "Se o eixo casos.md do Ponto voltar à pauta: 6 telas antigas seguem sem casos.md por decisão registrada (atacar antes os UC órfãos)"
related_adrs:
  - "0104-processo-mwart-canonico-unico-caminho"
  - "0093-multi-tenant-isolation-tier-0"
  - "0264-governanca-executavel-trio-dominio-e2e"
---

# Handoff 2026-08-28 17:45 BRT — RUNBOOKs do Ponto, a fronteira do dono, e o módulo 100% Inertia

## TL;DR

O pedido era **criar os RUNBOOKs faltantes do Ponto** para destravar o
`block-mwart-violation`. O enunciado media 8 das 20 telas e estimava ~5 RUNBOOKs.
Medindo todas: **15 barravam**, faltavam **8**.

O que expandiu o escopo não foi ambição minha — foi [W] perguntar
*"antes tem conflitos de fronteiras quem sabe pode ser isso"* sobre os 4 itens que
eu tinha devolvido como decisão dele. **Estava certo, e dois dos quatro eram meus.**

## O erro de fronteira (o item mais importante deste handoff)

Eu confundi **"merece PR separado"** com **"é decisão sua"**. São perguntas
diferentes: a primeira é sobre tamanho de diff, a segunda sobre soberania.
Misturar as duas parece zelo e entrega defeito parado em produção — é a lápide
[LC-28](../LICOES_CODE.md) acontecendo.

| # | O que eu disse | Dono real |
|---|---|---|
| 1 | *"três saídas, decisão de produto"* | **[W]** — mas eu devia ter **recomendado**, não listado um cardápio |
| 2 | *"F1→F5 com escopo próprio"* | **misto** — empacotei duas perguntas como uma |
| 3 | *"outro arquivo, outro intent"* | **MEU** — argumento de diff, não de dono |
| 4 | *"reduzir é sua chamada"* | **MEU** — já decidido; bastava declarar |

No item 2 havia duas perguntas coladas: *a tela de edição deve existir?* (dele —
havia alternativa real de derrubar a rota e usar cancelar+recriar) e *como migrar?*
(minha, MWART canônico). [W] respondeu **"sim pode fazer tudo"** com as minhas
recomendações: espelho **por colaborador**, edição **fica**.

## O que entrou

| PR | |
|---|---|
| [#6403](https://github.com/wagnerra23/oimpresso.com/pull/6403) | 8 RUNBOOKs retroativos · 15 telas destravadas |
| [#6409](https://github.com/wagnerra23/oimpresso.com/pull/6409) | errata nas 2 seções do `RUNBOOK-configuracoes` |
| [#6411](https://github.com/wagnerra23/oimpresso.com/pull/6411) | catálogo de relatórios aponta pro PDF que existe · 3 UC |
| [#6412](https://github.com/wagnerra23/oimpresso.com/pull/6412) | edição de intercorrência sai do Blade · trio completo · 3 UC |
| [#6415](https://github.com/wagnerra23/oimpresso.com/pull/6415) | `ui:lint` reconhece comentário JSX |

**Verificado a partir do `main`, não de memória:**

```
guard MWART ......... 0 de 21 telas barradas
controle positivo ... Ponto/NaoExiste/Index.tsx -> rc=2 (ainda morde)
return view( ........ ZERO nos controllers do Ponto  <- o módulo ficou 100% Inertia
manifesto ........... 8 UCs (RELIDX 01-05 + INTEDT 01-03) verdict=pass
```

O `zero return view(` fecha a dívida que o SDD §5.4 item 1 media: **21 renders =
20 Inertia + 1 Blade**. Aquele 1 era a edição de intercorrência.

## Achados de produto que eu NÃO consertei de carona

- **`Relatorios`**: `gerar()` era `abort(501)` para toda chave, mas o card
  `espelho` — o único `disponivel: true` — tinha botão habilitado. **O único
  "Disponível" levava a um 501.** O PDF sempre existiu, pelo F3; o catálogo é que
  não estava ligado nele. Corrigido no #6411 **depois** da decisão de [W].
- **Prop `colaboradores`** existia no `.tsx` e o controller nunca enviava — o filtro
  jamais renderizava. Saiu de graça no mesmo PR.
- **`Route::resource` declara `escalas.show` e `intercorrencias.destroy`** e os
  métodos não existem. ⚠️ **Rota não exercitada** — afirmo o par
  declarada/ausente, não o código de resposta. Registrado nos RUNBOOKs.
- **`text-stone-400` em 19 de 19 Pages** — padrão sistemático do `os-page-h`, não
  desvio local. Registrado assim para não virar 19 fixes no lugar errado.

## O `ui:lint` (#6415) — e o fix que quase foi pior que o problema

O skip de comentário do R1/R3 não reconhecia a forma do JSX, a única disponível
dentro do render. **Documentar a cor proibida citando o nome dela criava a própria
violação** — três instâncias independentes no corpus, todas comentários que
registravam a *remoção* da coisa proibida.

Medido em 486 arquivos: R1 `101→94`, R3 `55→54`, **todos os 8 falso-positivo**
(3 comentários + 5 referências a `PR #1496`/`OS #103` lidas como hex).

⚠️ **A correção que parecia certa foi MEDIDA e REJEITADA.** Rastrear o bloco de
comentário de verdade classificou como "dentro de comentário" os hex **reais** do
`PwaInstallBanner.tsx:112-114` — **esconderia violação**. Lint que erra pra menos
é pior que lint com ponto cego conhecido. Ficou o fix de um token, com o residual
declarado num teste próprio.

## Os 11 gates que morderam — nenhum falso-positivo

ghost de path morto (`Modules/PontoWr2` citado num RUNBOOK) · allowlist da lane
`ponto-pest` (o teste existiria e nunca rodaria) · `toContain(needle, mensagem)`
duas vezes · primitivos de layout (7 `flex`/`grid` crus num arquivo novo) ·
`SUPERFICIE.md` desatualizada · `uc-id-lint` (prefixo `INTEDIT` = 7, o canônico
admite 6) · `casos` G-6 `last_run` stale · PHPStan (ternário que o schema torna
impossível) · `ui:lint` R1 no meu próprio comentário.

**Reincidi em lápides que eu mesmo cito** — `toContain` (§5 2026-07-28) e a barra
invertida colapsando no transporte (LC-26, **três vezes**). Também caí no `/tmp`
divergente entre Bash e Node (§5 2026-08-21) e sondei o git com mudança
não-commitada (§5 2026-08-20). Isso não é o sistema falhando; é a medida honesta
de quanto eu teria mergeado sem ele.

## Duas coisas que corrigi em mim no meio do caminho

- Marquei 3 UCs como `✅ verde na lane` **cedo demais** e voltei atrás: dois
  acabavam de ser consertados e o terceiro ficara verde num commit *anterior* ao
  fix. Status se lê do manifesto (G-7), não da memória do run passado. O manifesto
  depois confirmou os 8 como `pass`, pelo caminho certo.
- Minha primeira sonda do manifesto devolveu `0` porque chutei a forma do JSON
  (os UCs vivem sob `.ucs`). Olhei a estrutura antes de concluir "não aterrissou",
  que era a conclusão errada esperando pra ser dita.

## Estado MCP no momento do fechamento

> ⚠️ **Este bloco reporta AUSÊNCIA de snapshot, não um snapshot.**

**Não há snapshot MCP — as tools estavam INDISPONÍVEIS a sessão inteira.** O hook
`brief-fetch` do SessionStart reportou *"servidor MCP não respondeu no tempo
(timeout)"* e caiu no fallback de índice; `ToolSearch` por `cycles-active`,
`my-work`, `sessions-recent`, `decisions-search` e `whats-active` devolveu
**"No matching deferred tools found"** nas duas tentativas.

O checklist MCP-first da [ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md)
**não foi cumprido**, e isso fica declarado em vez de simulado — colar um snapshot
que eu não consultei seria a mentira que a própria ADR existe pra impedir.

Substituto parcial, pelo git (que estava acessível):

- **Sessão paralela detectada:** design-sync (#6408, #6414, handoff das 10:40,
  ADR 0384). Trabalho **separado** do meu — nenhum arquivo em comum.
- **ADRs novas no dia:** 0383 (biometria no ponto) e 0384 (recibos por tela).
- **Colisão conferida por git** antes de abrir cada PR: nada recente tocava os
  arquivos que criei.

## Aberto

- **6 telas antigas do Ponto seguem sem `casos.md`** (Welcome, Colaboradores×2,
  Configuracoes×2, Escalas/Index) — fora de escopo por decisão registrada:
  atacar antes os 18 UC órfãos. Mais contrato com a mesma prova zero seria dobrar
  a aposta do presence-gate.
- **`anexo` (PDF/JPG/PNG)** existe no Blade legado e no `IntercorrenciaRequest`,
  mas não foi migrado — o `Create.tsx` também não o oferece, e migrar só de um
  lado criaria assimetria. Está como `[BACKLOG]` no `Edit.casos.md`.
- **Dois Non-Goals do `Edit.charter.md`** estão marcados como **inferência minha,
  pendente de [W]** — não viraram lei.
- **`edit.blade.php` virou o 26º fóssil** do módulo, mantido de propósito como
  contrato de paridade da migração.
