---
title: "Emenda à 0094 — escrever o princípio do Audit Card (LGPD Art. 20) que a 0145 declarou e nunca redigiu"
status: proposta
date: "2026-08-08"
owners: [W]
parent_module: Jana
related_adrs: [94, 105, 145, 363]
related_specs:
  - memory/requisitos/Jana/SPEC.md (US-COPI-127)
---

# Emenda à 0094 — o princípio declarado que nunca foi escrito

> **Esta proposal não constrói nada.** Ela corrige uma lei ausente. Nenhuma tela, nenhuma
> tabela, nenhum gate. O que ela pede é que um princípio **já decidido** duas vezes passe a
> existir no texto do documento que o obriga.

## O achado

A [ADR 0145](../0145-ia-administradora-pivot-ads-fsm-piloto-cobradora.md) (`aceito`, 2026-05-15)
declarou no frontmatter `amends: [0094]`, acrescentando à Constituição o princípio Tier 0
*"Audit Card visível ao cliente final"* — LGPD Art. 20 + ANPD NT 12/2025: decisão automatizada
que afete o titular exige informar que é automatizada e oferecer canal de revisão humana.

A [ADR 0363](../0363-governance-incorpora-ads-nucleo-sem-receptor.md) (2026-07-31), ao matar o
ADS, foi explícita em preservá-lo (§Herança, textual):

> "**Esse princípio sobrevive à supersessão.** Ele não é do ADS — é do sistema, e a obrigação é
> legal, não arquitetural: não deixa de existir porque uma ADR morreu. Esta ADR o **carrega
> adiante** como obrigação permanente sobre qualquer decisão automatizada futura, em qualquer
> módulo."

**Mas a emenda nunca foi redigida.** Varredura contada em 2026-08-08:

```bash
git grep -l "Audit Card"
```

devolve **9 arquivos** — a 0145 (que o criou), a 0363 (que diz carregá-lo), `proibicoes.md`
(a lápide do ADS), o BRIEFING do módulo morto, dois docs da Jana e três session logs.
**Nenhum deles é o documento-mãe.** Nem [`0094`](../0094-constituicao-v2-7-camadas-8-principios.md)
nem [`CONSTITUTION.md`](../../governance/CONSTITUTION.md) contêm "Audit Card", "Art. 20",
"automatizada" ou "ANPD".

O princípio existe hoje **só por referência nas ADRs que falam sobre ele**. Um agente que leia
a Constituição para saber o que é Tier 0 não o encontra.

## Por que isso importa agora

A fase 2 da Jana (pedido [CC] `JANA-FUSAO-2026-08-06`, fatia D) propõe ações HITL: a CTA
"ativar régua de cobrança" prepararia mensagem para o **cliente da Larissa** — titular de dados.
Isso dispara um `review_trigger` **declarado no frontmatter da 0363**:

> "Decisão automatizada passar a afetar cliente final — a obrigação LGPD Art. 20 herdada da
> 0145 (§Herança) sai do prospectivo e vira entrega"

A §Herança define a ordem: *"há um dever a cumprir **antes** do primeiro agente que volte a
decidir sobre titular"*. A fatia D **é** esse primeiro agente. Sem a lei escrita, o dever depende
de alguém lembrar de duas ADRs — uma delas superseded.

## O que se propõe

**Uma ADR nova com `amends: [0094]`** que redija o princípio. ⚠️ **Não** editar a 0094: ADRs
canon são **append-only** ([`proibicoes.md`](../../proibicoes.md) §Memória/governança — *"NUNCA
editar accepted records"*). O mecanismo correto é o mesmo que a 0145 usou.

Texto proposto do princípio (a redigir na ADR, não aqui):

> **Audit Card (Tier 0).** Toda decisão tomada por processo automatizado que afete um titular de
> dados deve, antes de produzir efeito sobre ele: (1) ser **registrada** de forma auditável —
> quem/quando/qual regra/qual dado a originou; (2) ser **identificável como automatizada** para o
> titular; (3) oferecer **canal de revisão humana** acessível ao titular. Vale para qualquer
> módulo, com ou sem IA no caminho — a obrigação é da decisão, não da tecnologia.

Se [W] quiser o princípio também na [`CONSTITUTION.md`](../../governance/CONSTITUTION.md), o PR
precisa da label `constitution-amendment` + `audit-*.md` no mesmo PR (§10.4 Cascade Review,
[`proibicoes.md`](../../proibicoes.md)).

## O que esta proposal NÃO propõe

- ⛔ **Construir `/copiloto/decisoes/{id}/revisao` agora.** A [US-COPI-127](../../requisitos/Jana/SPEC.md)
  foi reancorada no mesmo PR: o sujeito dela não existe (`mcp_dual_brain_decisions` dropada;
  `acoes` é `useMemo` no frontend). Tela sobre corpus vazio é carimbo.
- ⛔ **Recriar o ADS sob outro nome.** `DecisionRouter`/`RiskEngine`/`ConfidenceEngine` e as 5
  tabelas estão proibidos nominalmente (§5 2026-08-02). O precedente: 36.862 linhas com
  `outcome='cancelled'` (default de coluna) exibido como *"Aguardando você decidir"*.
- ⛔ **Gate novo.** Não há o que enforçar mecanicamente hoje — a exposição é zero e prospectiva.
  Um gate sobre corpus vazio nasceria parado (o padrão `foundation-ratchet`, §5 2026-07-01).

## Risco de não fazer

Baixo hoje, alto no primeiro write-action. A exposição atual é **zero** e a 0363 já a mediu como
tal. O risco é de **sequência**: a fatia D nasce, ninguém relê duas ADRs, e a primeira decisão
automatizada sobre titular sai sem registro nem canal de revisão — que é exatamente o que a
0145 quis impedir e a 0363 quis preservar.

## Gate de reversão

Se a redação se mostrar larga demais na prática (ex.: capturar cálculo determinístico que
ninguém chamaria de "decisão"), a correção é ADR sucessora estreitando o escopo — nunca apagar
o princípio, que é obrigação legal e não escolha de arquitetura.

## Ratificação

Merge deste PR **não** ratifica o princípio — proposal não é canon. A ratificação é o merge da
ADR que esta proposal pede, e é ato de [W] (R10). Este documento existe para que a decisão seja
tomada com o quadro medido, não para tomá-la.

> ➡️ **A ADR foi escrita em 2026-08-08:**
> [ADR 0372 — princípio 9 (Audit Card)](../0372-audit-card-decisao-automatizada-titular-emenda-0094.md),
> `amends: [0094]`, nasce `status: proposto`. Ela redige o princípio, define os termos
> (*decisão* · *automatizada* · *titular* · *afeta*), carrega a Cascade Review §10.4 e mantém as
> três exclusões desta proposal (não constrói a tela · não recria o ADS · não cria gate).
> **O que ainda falta é só o ato de [W]** — ratificar (flip `proposto → aceito`), recusar, ou pedir
> mudança de redação. Ver §Ratificação de lá para as duas saídas e o custo de cada uma.
