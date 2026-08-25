---
date: "2026-08-25"
hour: "09:05 BRT"
topic: "Aprofundamento do Fluxo de Design com processos internos, armadilhas e testes adversariais"
authors: [W, C]
outcomes:
  - "FLUXO-DESIGN aprofundado em E0 de ativação mais nove etapas operacionais"
  - "Provas existentes reconciliadas com scripts, testes e workflows reais"
  - "Oito testes residuais especificados; o wiring da conformidade foi reconciliado como fechado pelo PR #6232"
prs: []
us: []
related_adrs:
  - "0114-prototipo-ui-cowork-loop-formalizado"
  - "0282-protocolo-v2-colapso-ratificacao"
  - "0299-figma-nao-e-fonte-de-design"
---

# Session log 2026-08-25 — Fluxo de Design como playbook operacional

## TL;DR

O pedido foi aprofundar a referência publicada, explicar as armadilhas e mostrar os fluxos internos. A auditoria encontrou que a versão anterior chamava várias provas de ausentes apesar de elas já existirem e estarem ligadas ao CI. O documento foi corrigido e ampliado sem tocar código produtivo.

## Método

1. conferir o `main` e os donos canônicos `PROTOCOL.md` e `protocolo.config.mjs`;
2. localizar testes e wiring por nome real nos workflows;
3. separar “teste no disco”, “teste invocado”, “context required” e “mordida no mundo”;
4. desenhar E0–E9 com entradas, decisões, retornos e estados de recusa;
5. propor apenas testes que ainda cobrem lacunas residuais.

## Correções factuais principais

- bundle, payload, grafo, promoção, rollback e invalidação de evidência já são cobertos pelas baterias de `design-sync`;
- mirror freshness já cobre edição manual, cobertura parcial, live-only, dependências CSS/fontes, SLA e rollback;
- âncora tem selftest, teste de conteúdo e wiring; a lacuna real é identidade semântica da tela;
- contrato de região e trio charter/casos/teste já têm controles negativos ligados;
- o meta-teste de conformidade existe, mas não foi localizada invocação do `npm run test:conformance` nos workflows;
- o hook pós-merge grava apenas `timestamp|PR` e qualquer ferramenta reconhecida de navegador limpa a flag; URL, tenant e SHA não são conferidos hoje;
- `integrity-check` tem prova de wiring e árvore corrente verde, mas não fixture isolada ruim por IT.

## Testes propostos e avanço concorrente

T1 matcher de comparação; T2 cobertura viva fail-closed; T3 âncora da tela errada; T4 identidade/ordem dos snapshots; T5 estabilização do render; T7 escrita segura do placar; T8 integridade em miniárvore quebrada; T9 recibo do smoke ligado ao merge e à tela. O T6, wiring do meta-teste de conformidade, foi fechado no `main` pelo PR #6232 enquanto esta referência era elaborada e entrou apenas como fato reconciliado.

## Verificação

- schema da referência: verde;
- protocolo executável: selftest verde;
- integridade da memória: verde;
- design-memory wiring/bite: verde;
- deadlinks: sem piora e selftest verde;
- whitespace do diff: verde.

## Estado de publicação

Wagner autorizou o merge e um commit local foi criado na branch `codex/fluxo-design-detalhado`.
No fechamento deste registro ainda não havia push, PR ou merge.

## Reconciliação antes da publicação

Wagner autorizou o merge. O `main` avançou com #6230–#6233; o commit foi rebaseado sem conflito e o
playbook passou a registrar o T6 como fechado pelo #6232. No estado rebased, o selftest de âncora
do próprio `main` apresentou duas falhas de staging, e o `npm` global local não conseguiu iniciar
por ausência de `npm-cli.js`. Nenhum dos dois foi mascarado nem incorporado ao escopo documental;
o PR deve deixar o CI limpo executar a conformidade e registrar a falha-base de âncora.

## Referências

- Handoff: [`2026-08-25-0905-fluxo-design-playbook-detalhado.md`](../handoffs/2026-08-25-0905-fluxo-design-playbook-detalhado.md)
- Referência: [`FLUXO-DESIGN.md`](../reference/FLUXO-DESIGN.md)
