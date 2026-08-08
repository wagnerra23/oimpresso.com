---
slug: 0372-audit-card-decisao-automatizada-titular-emenda-0094
number: 372
title: "Emenda à 0094 — princípio 9 (Audit Card): decisão automatizada que afeta titular exige registro, identificação e revisão humana (LGPD Art. 20)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-08-08"
module: governance
quarter: 2026-Q3
tags: [governanca, constituicao, emenda-0094, lgpd, lgpd-art-20, anpd-nt-12-2025, audit-card, decisao-automatizada, titular, hitl, tier-0]
supersedes: []
supersedes_partially: []
superseded_by: []
amends:
  - 0094-constituicao-v2-7-camadas-8-principios
related:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0145-ia-administradora-pivot-ads-fsm-piloto-cobradora
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0257-adr-status-lifecycle-kind-modelo-canonico
  - 0363-governance-incorpora-ads-nucleo-sem-receptor
pii: false
review_triggers:
  - "Primeiro agente/rotina do oimpresso passar a produzir efeito sobre titular (write-action que sai do sistema em direção a cliente final) — a obrigação sai do prospectivo e o Audit Card nasce JUNTO com ela, nunca depois"
  - "ANPD publicar nota técnica nova sobre o Art. 20, ou jurisprudência fixar interpretação diferente de 'decisão automatizada' — reler a redação do princípio"
  - "A redação se mostrar larga na prática (capturar cálculo determinístico que ninguém chamaria de decisão) — ADR sucessora ESTREITA o escopo; nunca apagar o princípio"
  - "CONSTITUTION.md ser reconciliada com a Constituição v2 (hoje o charter_adr dela é a 0079, que a 0094 supersede) — o princípio deve entrar lá no mesmo ato, com label constitution-amendment + audit-*.md (§10.4)"
---

# ADR 0372 — Emenda à 0094: o princípio do Audit Card, que foi decidido duas vezes e nunca escrito

## Contexto

### O achado: a lei existe só por referência

A [ADR 0145](0145-ia-administradora-pivot-ads-fsm-piloto-cobradora.md) declarou `amends: [0094]` no
frontmatter e registrou em §Consequências, textual:

> "**Amends 0094** — adiciona princípio *'Audit Card visível ao cliente final'* como Tier 0 quando há
> decisão automatizada (extensão do princípio 7 Transparência)."

A [ADR 0363](0363-governance-incorpora-ads-nucleo-sem-receptor.md), ao dissolver o ADS e superseder a
0145, foi explícita em preservá-lo (§Herança, textual):

> "**Esse princípio sobrevive à supersessão.** Ele não é do ADS — é do sistema, e a obrigação é legal,
> não arquitetural: não deixa de existir porque uma ADR morreu. Esta ADR o **carrega adiante** como
> obrigação permanente sobre qualquer decisão automatizada futura, em qualquer módulo."

**Mas a emenda nunca foi redigida.** Medido em 2026-08-08, com `grep -F` (string fixa — em regex o `.`
de `"Art. 20"` casa `"estado-da-arte 2026"` e devolve falso-positivo):

```bash
for t in "Audit Card" "Art. 20" "ANPD" "decisão automatizada" "revisão humana"; do
  grep -Fc "$t" memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md
  grep -Fc "$t" memory/governance/CONSTITUTION.md
done
# → 0 para todos os 5 termos, nos dois arquivos
```

E `git grep -l "Audit Card"` devolve **12 arquivos** nesta data — dos quais **2 são ADR canon**
(a 0145, que o criou, e a 0363, que diz carregá-lo); o resto é proposal, handoff, session log, o
BRIEFING do módulo morto e o SPEC da Jana. **Nenhum é o documento-mãe.** Um agente que leia a
Constituição para saber o que é Tier 0 não encontra o princípio.

Vale registrar o que a Constituição **tem** hoje sobre LGPD, porque delimita o buraco com precisão: o
[Artigo 4](../governance/CONSTITUTION.md) enumera **Art. 7º** (consentimento/finalidade) e **Art. 18**
(acesso/correção/exclusão) — e para aí. O Art. 20 (revisão de decisão automatizada) não está em
nenhuma das duas camadas.

### Por que isso é frágil, e não só feio

É a [ADR 0256](0256-knowledge-survival-meia-vida-catraca-sentinela.md) na forma mais crua: *escrito e
lembrado apodrece*. Aqui nem escrito estava — o princípio depende de alguém **lembrar de duas ADRs**,
uma delas `superseded`, para saber que existe. A 0145 saiu da busca default do `decisions-search`
quando foi rebaixada; a 0363 menciona o princípio dentro de uma seção sobre outro assunto (a herança
de um módulo deprecado). Ou seja: o caminho de descoberta da lei passa por um documento morto e por um
parágrafo lateral de um documento sobre deprecação.

### Por que agora, e não depois

A exposição hoje é **zero e prospectiva** — a 0363 mediu: nenhuma decisão automatizada chegou a cliente
final (`pr_url` e `commit_sha` em 0; 100% do volume em `business_id=1`, negócio interno; o Audit Card
nunca foi construído; e `client_visible`/`audit_card_url` têm **0 ocorrências em código** — `rg` com
controle positivo, 2026-08-08). Não há passivo a remediar.

O que existe é **ordem**: a §Herança da 0363 define o dever como *"cumprir **antes** do primeiro agente
que volte a decidir sobre titular"*, e o `review_trigger` correspondente já está no frontmatter dela.
O horizonte próximo é a fase de **write-actions HITL da Jana** — a primeira CTA que prepare mensagem
para o cliente da Larissa cruza a linha. Escrever a lei **depois** de ela ser necessária é o modo de
falha que a 0145 quis impedir e a 0363 quis preservar.

> ⚠️ **Precisão de rótulo (para não confundir sessão futura).** O SPEC da Jana chama esse horizonte de
> *"fatia D da fase 2"*. Medido: a `US-COPI-148` (o pedido `JANA-FUSAO-2026-08-06`) é decomposta em
> **4 ondas, todas ENTREGUE** — não tem fatia D; e existe uma **outra** "fatia D" já mergeada em
> 2026-08-08 (a da lista protótipo × produção, sobre motivo no activitylog de `/ia/memoria`). Por isso
> esta ADR define o gatilho por **propriedade** (*produzir efeito sobre titular*), não por rótulo de
> fatia: rótulo apodrece, propriedade não.

## Decisão

**A Constituição v2 ([ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md)) passa a ter um 9º
princípio duro**, com o mesmo peso Tier 0 dos outros oito:

> ### 9. Audit Card — decisão automatizada sobre titular (Tier 0)
>
> Toda decisão tomada por processo automatizado que **afete um titular de dados** deve, **antes de
> produzir efeito sobre ele**:
>
> 1. ser **registrada** de forma auditável — quem decidiu, quando, sob qual regra, a partir de qual dado;
> 2. ser **identificável como automatizada** para o titular;
> 3. oferecer **canal de revisão humana** acessível ao titular.
>
> Vale para **qualquer módulo**, com ou sem IA no caminho — a obrigação é da **decisão**, não da
> tecnologia. Base legal: LGPD Art. 20 + ANPD NT 12/2025.

### O que cada termo quer dizer (para a regra não virar elástico)

| Termo | Leitura vinculante |
|---|---|
| **decisão** | escolha entre alternativas que **produz um efeito** — enviar, bloquear, cobrar, negar, priorizar, cancelar. Cálculo que só exibe número na tela não é decisão. |
| **automatizada** | tomada sem que um humano tenha escolhido **aquele caso**. Regra determinística conta; "tem IA" não é requisito nem isenção. |
| **titular** | pessoa natural — o cliente do nosso cliente inclusive. O caso concreto do oimpresso é o cliente da Larissa (biz=4), não a Larissa. |
| **afeta** | o efeito **sai do sistema em direção ao titular** (mensagem, cobrança, restrição, recusa) ou muda o que ele pode fazer/receber. |
| **antes de produzir efeito** | as três pernas nascem **junto** com o caminho de escrita, não em PR seguinte. Ligar a ação e "fazer o card depois" é violação. |

### Relação com o princípio 7

O princípio 7 (Transparência/Explainability) da 0094 cobre a trilha **interna**: input, reasoning,
output e custo de uma decisão, auditáveis por nós. O princípio 9 é a extensão dele **para fora** — a
mesma decisão vista pelo **titular**. Um não substitui o outro: dá para ter trilha interna impecável e
violar o Art. 20, que foi exatamente o estado que a 0145 diagnosticou em 2026-05 (*"backend está
pronto; UI de cliente final visível não existe"*).

### O que esta ADR NÃO decide

- ⛔ **Não constrói `/copiloto/decisoes/{id}/revisao` agora.** A `US-COPI-127` foi reancorada em
  2026-08-08 ([SPEC Jana](../requisitos/Jana/SPEC.md)): o sujeito dela não existe — a tabela
  `mcp_dual_brain_decisions` foi dropada pela 0363 (E5) e as "ações que a Jana sugere" são um `useMemo`
  no frontend (`JanaCockpit.tsx`), não decisão calculada no servidor. **Tela sobre corpus vazio é
  carimbo.** O Audit Card nasce junto com a primeira decisão real, não antes dela.
- ⛔ **Não recria o ADS sob outro nome.** `DecisionRouter` · `RiskEngine` · `ConfidenceEngine` ·
  `BrainBService` · `PatternLearningService` e as 5 tabelas estão proibidos nominalmente
  ([`proibicoes.md`](../proibicoes.md) §5 2026-08-02). O precedente é o retrato da 0363: **36.862**
  linhas com `outcome='cancelled'` — que era o `default` da coluna — exibidas ao humano como
  *"Aguardando você decidir"*.
- ⛔ **Não cria gate.** Não há o que enforçar mecanicamente hoje: a exposição é zero e o corpus é vazio.
  Gate sobre corpus vazio nasce parado — é o padrão `foundation-ratchet`
  ([`proibicoes.md`](../proibicoes.md) §5 2026-07-01: **0 failures em 300+ runs**), e um alarme que não
  pode tocar ensina a ignorar alarme. Quando houver decisão real, a defesa mecânica se discute **com
  corpus para medir falso-positivo antes**, como manda a regra "LIGUE A MÁQUINA" item 4.
- ⛔ **Não edita a 0094 nem a `CONSTITUTION.md`.** ADR canon é append-only
  ([`proibicoes.md`](../proibicoes.md) §Memória/governança). Emenda-se **criando**, que é o mecanismo
  que a própria 0145 usou.
- ⛔ **Não decide o desenho do Audit Card** (rota, tela, cópia, canal). O princípio fixa **o quê**; o
  **como** é da ADR/US que nascer com a primeira decisão automatizada.

## Justificativa

**Por que ADR nova em vez de editar a 0094.** Append-only não é formalidade: a 0094 é lida por agente e
por humano como retrato datado de 2026-05-06. Reescrevê-la apagaria o fato de que o 9º princípio chegou
14 meses depois, por um caminho específico (0145 → 0363 → aqui). O mecanismo `amends` existe para isso e
tem precedente direto — a própria 0145 o usou sobre a mesma 0094.

**Por que princípio novo e não nota de rodapé no 7.** O 7 é sobre **nós auditarmos**; o 9 é sobre **o
titular exercer um direito**. Enfiar o segundo como parágrafo do primeiro é como o buraco nasceu: a
obrigação vira detalhe de outra coisa e some. Princípio numerado tem endereço próprio e aparece na
leitura de "o que é Tier 0".

**Por que a redação é curta e por propriedade.** Toda tentativa de listar *quais* módulos/rotas/tabelas
estão sujeitos envelheceria no primeiro refactor — e este projeto já tem quatro lápides de guard
sintático que reprovava o legítimo (§5: allowlist-de-pasta · guard `@scope` · vocabulário 130 FP ·
`toHaveKey` 100% FP). A regra é semântica de propósito. O custo assumido é que ela **não é grepável**;
o benefício é que ela não caduca.

**Por que agora, com exposição zero.** Exatamente **porque** é zero. Escrever lei com passivo em curso é
resposta a incidente; escrever antes é governança. E o custo é o mais baixo que vai ser: nenhuma tela
para retrofitar, nenhum dado para remediar, nenhum cliente para avisar.

**Quando reabrir.** Se a redação capturar coisa que ninguém chamaria de decisão (um cálculo
determinístico de frete, por exemplo), a correção é **ADR sucessora estreitando o escopo** — nunca
apagar o princípio, que é obrigação legal e não escolha de arquitetura.

## Cascade Review (§10.4)

A [§10.4 da Constituição](../governance/CONSTITUTION.md) exige que ADR que modifique uma camada audite
as camadas abaixo. Auditado em 2026-08-08:

| Camada | Precisa mudar? | Por quê |
|---|---|---|
| **L1 MCP CORE** | **não hoje** | a perna *registro* tem candidato natural em `mcp_audit_log` (já append-only, com hash chain desde `2026_06_20_000001_add_hash_chain_to_mcp_audit_log`). Fiar o Audit Card nele é decisão da ADR que nascer com a primeira decisão real — fiar agora seria desenhar contra corpus vazio. |
| **L2 ADS Universal** | **não** | dissolvido pela 0363; sem receptor. |
| **L3 Skills** | **não hoje** | nenhuma skill enforça o princípio, e criar uma agora seria instrução em prompt sobre caminho que não existe. |
| **L4 Playbooks** | **não** | nenhum runbook toca decisão sobre titular. |
| **L5 ADRs canon** | **é esta** | 0145 `superseded` (correto — o *plano* morreu), 0363 carrega a herança (correto), esta escreve a lei. Nenhuma outra ADR contradiz. |
| **L6 Charters** | **não hoje** | o `Revisao.charter.md` que a 0145 previa nunca existiu e não deve nascer sozinho (§O que esta ADR NÃO decide). |
| **L7 Daily Brief** | **não** | nada a reportar enquanto a exposição for zero. |

**Nada abaixo precisou de update** — e isso é consequência direta de a exposição ser zero, não de a
auditoria ter sido rasa. No dia em que a primeira decisão automatizada nascer, L1 (registro), L6
(charter da tela) e provavelmente L3 (skill de pré-flight) mudam **no mesmo PR** que a criar.

⚠️ **Achado adjacente, registrado e NÃO corrigido aqui.** A
[`CONSTITUTION.md`](../governance/CONSTITUTION.md) declara `charter_adr: 0079` e `version: 1.1.0` — mas
a 0079 foi **superseded pela 0094** em 2026-05-06. Os dois documentos-mãe estão dessincronizados desde
então, e isso é anterior e independente desta ADR. Corrigir exige label `constitution-amendment` +
`audit-*.md` no mesmo PR (§10.4) e é decisão [W] — está no `review_trigger` desta ADR. **Não fiz junto**
porque é outro intent (1 PR = 1 intent) e porque tocar a `CONSTITUTION.md` acorda o gate de cascata
sobre dívida que não é desta emenda ([`proibicoes.md`](../proibicoes.md) §5 2026-07-12 + emenda
2026-07-27).

## Consequências

**Positivas.** A obrigação passa a ter **endereço próprio** no documento que a torna Tier 0, em vez de
depender de duas ADRs — uma delas morta — e de alguém lembrar. O gatilho vira **propriedade
verificável** (*produz efeito sobre titular?*), não rótulo de fatia. E o custo de cumprir é o mínimo
possível: nada a retrofitar.

**Negativas / trade-offs assumidos.** (1) O princípio é **semântico, logo não-grepável** — nenhuma
máquina o defende hoje, e a defesa é cultural até haver corpus (ver §NÃO decide). (2) A redação pode ser
**larga demais** e capturar algo que ninguém chamaria de decisão; o remédio previsto é sucessora que
estreita, não exceção informal. (3) A `CONSTITUTION.md` continua sem o princípio — quem ler **só** ela
não o encontra; o ponteiro é este `review_trigger` e nada mais.

**Riscos mitigados.** Nenhum passivo LGPD é criado ou descoberto por esta ADR — ela **antecede** a
exposição. Multi-tenant: nada é tocado; cliente piloto (`biz=4`) não é afetado em superfície alguma.

## Ratificação

O agente propõe; não ratifica (R10). Duas saídas, ambas de [W]:

1. **Ratificar agora** — editar neste PR a linha `status: proposto` → `aceito` (opcionalmente com
   `accepted_via` citando as palavras da decisão) antes do merge. Como o arquivo é **novo** (`A` no
   diff), o gate `Append-only canon` não se aplica e **não** é preciso o rito de flip nem a label
   `adr-metadata-normalization` ([README](README.md) §Como ratificar).
2. **Mergear como `proposto`** — legítimo (a `0367` está assim), mas ⚠️ **não fecha o buraco**: ADR
   `proposto` fica fora do escopo default do `decisions-search`, então a lei continua invisível para
   quem consultar o canon. O flip posterior é que a torna vigente, e aí sim pelo rito do README.

**Recusar também é resposta válida.** Se [W] entender que o princípio não deve ser Tier 0, o caminho é
`status: recusado` + `rejected_at`/`rejected_via`/`rejected_reason` (schema exige os três) — e aí o
`review_trigger` da 0363 fica sem destino e precisa ser reaberto lá.

## Referências

- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2; **emendada** por esta
- [ADR 0145](0145-ia-administradora-pivot-ads-fsm-piloto-cobradora.md) — quem declarou o `amends: [0094]` e nunca o redigiu (`superseded` pela 0363)
- [ADR 0363](0363-governance-incorpora-ads-nucleo-sem-receptor.md) — §Herança, que carregou o princípio adiante e criou o `review_trigger`
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — por que não se constrói a tela antes do sinal
- [ADR 0256](0256-knowledge-survival-meia-vida-catraca-sentinela.md) — *derivado e enforçado sobrevive; escrito e lembrado apodrece*
- [`CONSTITUTION.md`](../governance/CONSTITUTION.md) — Artigo 4 (LGPD Art. 7º + 18) e §10.4 (Cascade Review)
- [`proibicoes.md`](../proibicoes.md) — §5 2026-08-02 (o ADS não volta) · §5 2026-07-01 (`foundation-ratchet`) · §Memória/governança (append-only)
- [SPEC Jana](../requisitos/Jana/SPEC.md) — `US-COPI-127`, reancorada em 2026-08-08
- [Proposal 2026-08-08](proposals/2026-08-08-emenda-0094-audit-card-lgpd-art-20.md) — o quadro medido que originou esta ADR
