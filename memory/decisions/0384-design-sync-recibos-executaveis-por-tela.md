---
slug: 0384-design-sync-recibos-executaveis-por-tela
number: 384
title: "Design Sync deriva o estado da tela de recibos executáveis"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-08-28"
module: governance
tags: [design, cowork, protocolo, recibo, teste, smoke, fiscal]
supersedes: []
superseded_by: []
related:
  - 0379-bundle-design-transacao-manifesto-delta-staging
  - 0282-protocolo-v2-colapso-ratificacao
  - 0290-comparacao-visual-nao-hermetica-fora-do-ci
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
---

# Design Sync deriva o estado da tela de recibos executáveis

> Nasce `proposto`. [W] autorizou a implementação do fluxo em 2026-08-28; o merge de uma
> ratificação própria continua sendo o ato formal previsto pela ADR 0257.

## Contexto

A ADR 0379 separou recepção de aplicação e ligou a evidência aos hashes da fonte e do alvo.
O primeiro consumidor, porém, ainda aceitava uma referência em texto livre e tratava uma lista
de nomes como prova de teste. O smoke pós-merge limpava uma flag temporária quando o navegador
era aberto, sem produzir recibo durável por tela. Assim, o relatório podia dizer "testada" sem
ter executado teste e não conseguia distinguir teste verde de validação em produção.

O incidente ficou concreto no Fiscal: o bundle continha a família de fontes fiscais, enquanto
os charters ainda declaravam ausência de protótipo. O transporte estava completo, mas a tela
permanecia órfã no inventário de aplicação.

## Decisão

**D-1 — Uma projeção, nenhum ledger paralelo.** `scripts/design-sync/status.mjs` e
`scripts/design-sync/state/application-report.json` continuam sendo a projeção operacional.
`applications.json` continua sendo o único ledger de evidências do Design Sync.

**D-2 — Estados derivados por tela.** A projeção usa a sequência
`anchored → compared → applied → tested → validated`. `blocked`, `to-create` e `review` são
estados de exceção. Nenhum estado é editado diretamente: ele é recomputado das fontes e recibos.

**D-3 — Comparação é um artefato real.** Tela semântica só chega a `compared` quando um
`*.map.json` existente relaciona fonte e alvo. O ledger guarda path e SHA-256 do mapa.

**D-4 — Aplicação exige evidência durável.** `applied` exige arquivo existente dentro do
repositório, com SHA-256 registrado. Texto livre, nome de PR e entrada de chat não bastam.

**D-5 — Teste é executado pelo registrador.** O comando é recebido como vetor de argumentos,
executado sem shell e só produz recibo com exit code zero. O recibo guarda comando, runner,
hash da saída e hashes atuais da fonte e do alvo. Nome de teste informado manualmente não vale.

**D-6 — Smoke é posterior ao teste.** `validated` exige rota, SHA do deploy, screenshot durável,
resultado positivo e tenant de smoke permitido. Smoke manual de produção usa `biz=1`; `biz=4`
é recusado. Testes automatizados continuam usando o tenant fictício previsto pela ADR 0358.

**D-7 — Invalidação em cascata.** Mudança da fonte invalida comparação e tudo depois dela;
mudança do alvo invalida aplicação, teste e smoke; mudança do mapa ou da evidência invalida o
estado que depende do arquivo; mudança do screenshot invalida somente o smoke.

**D-8 — Catraca gradual.** A checagem de lifecycle exige seletor explícito de fonte ou módulo.
Ela não bloqueia todo o legado. Fiscal é o piloto Tier 0; outras telas entram quando forem
tocadas ou formalmente colocadas em onda.

## Consequências

**Positivas:** “recebido”, “comparado”, “aplicado”, “testado” e “validado em produção” deixam de
ser sinônimos; recibos stale caem sozinhos; relações 1:N são visíveis; o relatório pode negar uma
declaração otimista sem depender da memória do agente.

**Custos:** a aplicação precisa manter mapas e evidências no git; o runner de testes deve ser o
ambiente correto; screenshots de smoke precisam ser persistidos quando usados como prova; telas
legadas não ganham estado final retroativamente.

**Não decidido:** este ADR não promove um novo check global a required e não autoriza comparação
visual não-hermética no CI. Promoção futura continua sujeita ao registry de gates e à evidência de
mordida exigida pelas decisões vigentes.
