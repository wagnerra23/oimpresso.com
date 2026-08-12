---
proposal_id: adr-paths-0375-classificacao-e-o-hook-sem-label
status: proposed
created: 2026-08-11
proposed_by: claude-code
decided_by: wagner
parent_adr: 0375 (SCOPE.md sai de Modules para memory/requisitos)
related_adrs: [0375, 0094, 0095, 0257, 0297, 0363, 0367, 0086, 0170, 0080, 0100, 0360]
type: decisao-de-governanca
---

# Os paths antigos nas ADRs canon — a classificação, e o hook que não conhece o label

- **Status:** proposto (decisão [W]). Merge = ratificação (R10).
- **Data:** 2026-08-11
- **Autor:** [CC]. **Origem:** o item declarado aberto no [handoff 18:10](../../handoffs/2026-08-11-1810-scope-sai-de-modules-e-o-append-only-suspenso.md) — *"13 ADRs append-only ainda citam paths antigos. [W] autorizou editar; falta classificar ocorrência a ocorrência entre ponteiro vivo e fato datado."*
- **O que esta proposta NÃO faz:** não edita nenhuma ADR. Entrega a classificação e expõe o bloqueador que a impede.

## 1. O bloqueador — a autorização entrou em UMA das duas camadas

[W] autorizou (*"remover o append-only para mim"*). O [#5602](https://github.com/wagnerra23/oimpresso.com/pull/5602) implementou a exceção **no CI**, sob label `adr-body-edit-W`, e mergeou às 17:58Z. O label **não existia** no repositório e foi criado nesta sessão — medido com controle positivo:

```
grep -Fx "adr-body-edit-W"            → rc=1   ausente  (antes)
grep -Fx "adr-metadata-normalization" → rc=0   controle positivo: existe
```

**Mas o append-only tem DUAS camadas, e só uma foi tocada:**

| camada | mecanismo | conhece o label? | efeito hoje |
|---|---|---|---|
| CI (pré-merge) | `governance-gate.yml` job *Append-only canon* | ✅ sim (`#5602`, em `main`) | libera com o label |
| **Local (pré-escrita)** | hook PreToolUse [`block-memory-drift.mjs`](../../../.claude/hooks/block-memory-drift.mjs) | ❌ **não — zero ocorrências** | **bloqueia sempre** |

Medido: `grep -c "adr-body-edit" .claude/hooks/block-memory-drift.mjs` → **0** (`rc=1`). O único escape que o hook implementa é `OIMPRESSO_MEMORY_OVERRIDE=1`. Os dois casam o mesmo alvo (`^memory/decisions/\d{4}-[a-z0-9-]+\.md$`), então **todo** Edit em ADR canon morre antes de chegar ao CI.

Consequência prática: a autorização de [W] é **inexercível pelo caminho normal**. O primeiro `Edit` desta sessão foi bloqueado exatamente assim. Isso não é defeito do hook — ele faz o que foi construído para fazer; é uma camada que ninguém atualizou junto.

> ⚠️ Um hook PreToolUse **não pode** consultar label: roda antes de existir PR. Então "ensinar o label ao hook" não é opção literal — as opções reais estão em §4.

## 2. A classificação — 16 ocorrências, e o corte não é o esperado

Escopo medido (`git grep -nE 'Modules/[A-Za-z]+/(SCOPE|LICOES-OPERACAO)\.md' -- 'memory/decisions/[0-9][0-9][0-9][0-9]-*.md'`): **16 ocorrências em 9 ADRs**.

O corte que decide não é *ponteiro × prosa* — é **o módulo ainda existe?**. Medido arquivo por arquivo:

| módulo citado | `Modules/<X>/` existe? | `memory/requisitos/<X>/SCOPE.md` existe? | ocorrências |
|---|---|---|---|
| Governance | sim | ✅ | 3 (`0086:160`, `0360:80`, `0363:239`) |
| Jana | sim | ✅ | 4 (`0148` ×4) |
| Forja | sim | ✅ | 2 (`0367:50`, `0367:122`) |
| PaymentGateway | sim | ✅ | 1 (`0170:323`) |
| RecurringBilling | sim | ✅ | 1 (`0170:352`) |
| **ADS** | **NÃO** (deletado, [ADR 0363](../0363-governance-incorpora-ads-nucleo-sem-receptor.md)) | **ausente** | 2 (`0080:84`, `0080:171`) |
| **ProjectMgmt** | **NÃO** (renomeado → Forja) | **ausente** | 2 (`0099:32`, `0100:257`) |
| **Admin** | **NÃO** (deprecado, [ADR 0360](../0360-deprecacao-admin-center-supersede-0122.md)) | **ausente** | 1 (`0360:37`) |

**O achado que muda a instrução:** para os **5** de módulo morto, repathar **cria um SEGUNDO link morto** — e pior, um que *aparenta* ter sido verificado. O documento não mudou de endereço; ele deixou de existir. Repathar ali seria falsificar frescor, não consertar ponteiro.

Dentro dos 11 de módulo vivo, o subcorte é o de sempre (§5 2026-07-16: *aponta pro dono, não restateia*):

| forma | ocorrências | tratamento |
|---|---|---|
| **link markdown** cujo alvo existe | `0170:352`, `0363:239`, `0367:50`, `0367:122` | **repathar o target** para `../requisitos/<X>/SCOPE.md` (convenção medida: 19× `../requisitos/Sells`, e a própria `0367:123` já usa essa forma). Prosa **intacta** |
| **prosa/checklist** sem link | `0086:160`, `0148` ×4, `0170:323`, `0360:80` | **preservar** — são fatos datados (*"SCOPE.md criado"*, *"precisa update"*, `- [ ]`). Nada quebra: sem link, não há navegação a consertar |

### O falso positivo que eu quase cometi

Ia tratar `0363:239` como claim presente-podre: ela diz *"Essa fronteira **vive hoje** no `not_contains` do SCOPE.md:15"*, e medi que a linha citada **não existe mais** como declaração (hoje é comentário na linha 20 dizendo que *saiu*). Lendo 13 linhas adiante, a própria ADR resolve: linha 252, textual — *"só a linha 15 do `not_contains` sai, no PR que executar a incorporação (parte 6)"*. A prosa descreve o pré-estado e **revoga em seguida**; está correta e coerente. **A ref sempre esteve certa; quem estava errado era eu** (§5 2026-08-10). Registrado porque a próxima sessão vai olhar essa linha e ter o mesmo reflexo.

## 3. O que NÃO entra na varredura (e por que o número "13" não reconcilia)

O padrão largo (`Modules/<X>/*.md`, qualquer `.md`) dá **25 ocorrências em 14 arquivos**. As **9** extras são referências que a **ADR 0375 nunca moveu** e que não devem ser varridas junto:

`0136` charter de tela · `0151`/`0152` SPEC de módulo feature-wish que não existe · `0170` `README.md`/`CONTRACTS.md` · `0216` RUNBOOK a criar · `0265` CHANGELOG a apendar.

Sweep largo aqui seria inventar destino para arquivo que ninguém moveu. **Nem 9 nem 14 dá "13"** — o número do handoff não reconcilia com nenhum dos dois padrões; fica declarado em vez de repetido (§5 2026-07-17: número em canon carrega o comando, não a memória).

## 4. A decisão que é de [W]

**(a) Rota para os 4 repaths de módulo vivo** — as duas que existem hoje:

| rota | custo | risco |
|---|---|---|
| `OIMPRESSO_MEMORY_OVERRIDE=1` na sessão que executa | zero | escape *emergencial Tier 0* virando rotina; o hook avisa alto, mas quem lê o log é o agente |
| Ensinar o hook a liberar por **sinal versionado** (ex.: marcador no corpo do PR não serve — hook roda antes; um arquivo-sentinela commitado no branch, sim) | ~1 PR + bite-test | mecanismo novo → exige FP medido antes (regra *LIGUE A MÁQUINA* item 4) |

**Recomendo a primeira, uma vez, declarada no corpo do PR** — 4 links, escopo fechado, e o CI já tem a trava consciente por label. Mecanismo novo para 4 linhas é over-engineering, e a §5 tem 4 lápides de guard sintático que reprovava o legítimo.

**(b) Os 5 de módulo morto** — três saídas, e nenhuma é repathar:
1. **deixar** (a dívida já está no `deadlink-baseline.json`, contada por arquivo citante: `0080`→2, `0367`→2, `0363`→2, `0170`→3);
2. **de-linkar**, virando citação em texto (`` `Modules/ADS/SCOPE.md` (à data desta ADR) ``) — o fato sobrevive, o link morto sai;
3. apontar para a ADR que matou o módulo.

Minha leitura: **(2)** é a única que reduz dívida sem falsificar frescor. Mas é edição de prosa em ADR aceita — logo, ato de [W].

**(c) A cascata LC-10, que segue aberta e é a mais urgente:** `CONSTITUTION.md` Art. 3, `CLAUDE.md` (tabela *"Mudança ADR canon = ❌ NÃO. Append-only"*) e [`proibicoes.md`](../../proibicoes.md) ainda afirmam append-only **absoluto**, em presente. Com o label em `main`, isso é artefato afirmando enforcement que não tem mais — a classe que a §5 2026-07-16 nomeia. Tocar a Constituição exige label `constitution-amendment` + `audit-*.md` no mesmo PR: **PR próprio**.

## Consequências

- **Se aceito:** [W] escolhe a rota de (a) e (b); os 4 repaths saem num PR com `adr-body-edit-W`, provando *antes→depois* de cada link e a queda no `deadlink-baseline`; a cascata (c) sai noutro.
- **Se recusado:** os 16 ficam como estão. Custo real é só navegacional (4 links mortos de módulo vivo); nenhum gate quebra, porque a dívida já está grandfathered no baseline.
- **Reversão:** trivial nos repaths (4 linhas). A cascata (c) é que tem raio de verdade.
