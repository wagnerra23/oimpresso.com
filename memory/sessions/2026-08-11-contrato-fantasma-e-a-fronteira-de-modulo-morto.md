---
date: "2026-08-11"
hour: "13:36 UTC"
topic: "Contrato que descrevia um controller nunca construído, e um grafo que chamava módulo morto por ADR de 'fronteira futura' — mais o plano que eu recomendei antes de medir se funcionava"
authors: [C]
prs: [5566, 5582]
outcomes:
  - "Os 5 endpoints fantasma do PaymentGateway EXISTIAM sob outro controller (Settings\\PaymentGatewaysController) — era ponteiro errado, não capacidade ausente"
  - "Medir a §6 inteira em vez de só as 5 linhas revelou mais 3 divergências, incluindo uma frase sobre '301 redirect após cutover' cujo cutover nunca aconteceu"
  - "4 dos 5 módulos na 'fronteira' do catalog-graph eram removidos por ADR; só Notas é futuro de fato"
  - "ERRO MEU: recomendei a [W] corrigir por doc e só depois medi que não funcionava — moduleRefsIn casa qualquer menção, então a correção exigiria apagar a lápide"
  - "A verdade dos 4 mortos já estava curada em ghost-rename-map.json com ADR e data; o catalog-graph só não consultava"
  - "Escopo mínimo por decisão [W]: mudei só a mensagem, com catalog.json intocado (freshness OK), porque o job é required desde a ADR 0370"
related_adrs:
  - 0170-paymentgateway-extracao-camada-cobranca
  - 0370-module-surface-catalog-graph-required-emenda-0314
---

## TL;DR

Duas correções de doc que começaram pequenas e cresceram quando medi o entorno em vez de só o
alvo. Nas duas, o defeito era o mesmo: **artefato descrevendo um plano como se fosse o presente.**

## Parte 1 — o contrato que descrevia um controller inexistente

O ponto de partida era um resíduo que o próprio `SCOPE.md` já tinha **declarado e não tocado**
em 2026-08-10: a §6 do `CONTRACTS.md` citava 5 endpoints `→ PaymentGatewayController@*`.

Primeira medição, `git grep`: **10 hits, todos em documentação, zero em código.** O controller
nunca existiu. Mas a pergunta certa não era "existe o controller?" e sim "existe a capacidade?" —
e existia: `Settings\PaymentGatewaysController`, prefixo `/settings/`, método `healthCheck`
(não `runHealthCheck`), entregue na Onda 4d.3. Os 5 eram **ponteiro errado**, não vaporware.

A raiz estava no cabeçalho do doc, que eu só li porque fui procurar convenção de formato:
`v0.1 (rascunho Onda 0)`. A §6 nunca foi um contrato — era um **plano**, escrito antes do código,
e nunca reconciliado. Isso mudou o escopo: se a origem é essa, o resto da seção tem o mesmo
defeito. Medi, e tinha:

| Divergência | Realidade |
|---|---|
| `CobrancaController@*` em `/cobranca` | vive em `Modules/Financeiro`, em `/financeiro/cobranca` |
| 4 webhooks em `/webhooks/{gw}` | são **7**, em `/paymentgateway/webhooks/{gw}/{businessId}` |
| "301 redirect durante 30 dias após cutover Onda 3" | **o cutover nunca aconteceu** — zero redirect nos 56 arquivos de rota, e o RecurringBilling segue servindo em paralelo |

As capacidades planejadas que não viraram rota (`show`/`cancelar`/`segunda-via`/`refund`) saíram
do bloco de contrato e ficaram declaradas na nota, para a intenção não se perder. O `refund`
existe na camada de serviço (contrato + 7 drivers), sem endpoint HTTP.

### O que quase passou

O [#5548](https://github.com/wagnerra23/oimpresso.com/pull/5548) **moveu o `CONTRACTS.md`** para
`memory/requisitos/PaymentGateway/` no meio do trabalho. O PR nasceu `CONFLICTING` e dois
instrumentos discordaram: `git merge-tree` disse limpo (detecção de rename), o GitHub disse
conflito. Resolver por medição, e não por teoria, expôs o dano que **nenhum dos dois apontava**:
o link `[Routes/web.php](Routes/web.php)` que eu escrevera era relativo ao path velho e quebraria
o `deadlink-gate`, que é required (ADR 0347).

## Parte 2 — "isso deveria estar na fronteira?"

[W] leu a linha `ℹ️ 5 módulo(s) referenced-only (fronteira futura/legada): Accounting, Admin,
Notas, Project, SRS` num output que eu tinha mostrado e perguntou se aquilo devia estar ali.

Devia não. `Accounting` (ADR 0172), `Admin` (0360) e `SRS` (0357) foram **removidos**; `Project`
tem tombstone sem data; só `Notas` é fronteira futura. O caso mais agudo: o `Governance/SCOPE.md`
diz textualmente *"a fronteira não existe mais"* — e é exatamente essa linha que faz o parser
criar o nó de fronteira. Doc e grafo se contradiziam no mesmo fato.

### O erro que eu cometi, e é a lição da sessão

Recomendei a [W] corrigir os `not_contains` dos SCOPEs. Ele aprovou. **Só então** medi
`moduleRefsIn`: `/Modules\/([A-Z]\w+)/g` sobre a string inteira — qualquer menção cria o nó,
inclusive dentro da própria lápide "módulo REMOVIDO". A correção por doc só funcionaria apagando
o nome do módulo, destruindo o registro histórico que o §5 manda preservar.

Voltei a [W] em vez de executar um "sim" que não entregaria nada. É LC-08 na variante
**proposta**: a hora de medir é antes de recomendar, não depois de aprovado. Ter aprovação não
transforma um plano inviável em viável.

### O que a medição achou de bom

O `ghost-rename-map.json` já curava os 4 com `removed_at` e `removed_by_adr`. Não precisei criar
registro, régua nem gate — só **ler o dono** que já existe, usando `removed_at` como qualificador
(mesma régua do `knowledge-drift`). Por isso `Project`, sem data, virou "fila humana" e não
"removido": o tombstone diz literalmente que é decisão humana pendente.

### Escopo travado por decisão [W]

O job `catalog.json == SCOPEs + Classes B` é **required** desde 2026-08-05 (ADR 0370), e o
precedente de deadlink por gate required está no próprio `proibicoes.md`. Perguntei antes de
mexer, e [W] escolheu o mínimo: **só a mensagem** — nenhum nó, aresta ou campo do `catalog.json`.
Recibo: `freshness: OK (committed == regerado)`.

```
ANTES   5 módulo(s) referenced-only (fronteira futura/legada): Accounting, Admin, Notas, Project, SRS
DEPOIS  3 REMOVIDOS (com ADR+data) · 1 fila humana (Project) · 1 fronteira futura (Notas)
```

Testes 18 → 23, com mordida provada por mutação (ignorar o tombstone derruba os 2 BITE, `rc=1`;
restaurado, `rc=0`) e 3 controles negativos — incluindo um que garante que mapa vazio **não
inventa morte**.

## Meta — o que se repetiu nas duas partes

Cada vez que medi **o entorno** em vez de só o alvo, o escopo real apareceu: nas 5 linhas achei
uma seção inteira; na pergunta do [W] achei um registro curado que ninguém lia. E cada vez que
**afirmei antes de medir** — o plano dos 4 docs, e um `gh pr checks` que eu contei em vez de ler —
errei. A assimetria é consistente o bastante para ser regra prática, não impressão.

## Caveats

- **MCP indisponível a sessão inteira** (timeout no `SessionStart`). Nenhuma task criada ou
  atualizada; os resíduos ficaram declarados nos docs, não em `mcp_tasks`.
- Os 2 PRs foram mergeados por **[W] via auto-merge**, não por mim.
- Nenhuma lápide nova no §5: os dois erros desta sessão são instâncias de classes já catalogadas
  (LC-08), e inflar o contador com recibo repetido é o vício que o próprio ledger alerta.
