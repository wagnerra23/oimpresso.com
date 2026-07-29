---
date: "2026-07-28"
hour: "18:40 BRT"
duration: "7h"
topic: "Censo da camada de IA derivado no system-map, a reincidência que a revisão adversarial pegou, e os 6 deltas atual→alvo"
authors: [W, C]
outcomes:
  - "Censo da camada de IA derivado por CONTRATO (implements), não por pasta — PR #4973 mergeado"
  - "A 1ª entrega reincidiu na própria classe: contou pasta de 1 módulo (39) para descrever registro de 3 (44)"
  - "Self-test do ORÁCULO, não só do classificador — era a lacuna que deixou o bug passar (19 asserts)"
  - "Achado de bug: no chat streaming os tokens são gravados na mensagem do turno anterior"
  - "Doutrina da Jana e as 5 decisões em aberto sumiram do repo com a geração do ARCHITECTURE — resgatadas em proposal"
  - "5 sessões paralelas abertas com áreas de arquivo isoladas"
prs: [4973, 4976, 4983, 4984]
us:  []
related_adrs:
  - "0035-stack-ai-canonica-wagner-2026-04-26"
  - "0053-mcp-server-governanca-como-produto"
  - "0256-knowledge-survival-meia-vida-catraca-sentinela"
---

# Session log 2026-07-28 — o censo que se conta sozinho, e o que ele pegou

## TL;DR

[W] pediu um mapa técnico da IA. Ao montar, os números que escrevi à mão estavam **errados** — 14
agentes eram 22, 16 provedores eram 15. A correção certa não foi corrigir os números: foi tirar a mão
do meio. O `system-map.mjs` passou a derivar o censo da árvore ([PR #4973](https://github.com/wagnerra23/oimpresso.com/pull/4973), mergeado).

Aí veio a parte instrutiva: **a primeira entrega do contador reincidiu na classe que ela existia para
matar.** Publicou `Tools MCP expostas: 39` medindo a pasta de um módulo, quando o servidor registra
44 em três. Passou `--check` verde, self-test verde, deadlink verde. Número errado com selo de
"derivado" é pior que número errado à mão — o leitor perde o direito de duvidar.

Quem pegou foi revisão adversarial de dois céticos independentes. Nenhum gate pegaria: o self-test
cobria o **classificador** e não o **oráculo** (a função que escolhe a fonte não era exportada, logo
era intestável por construção).

## O que ficou de máquina

- Censo por **contrato** (`implements Agent`), com a varredura por pasta como contra-medida; a
  divergência entre as duas vira alarme que **nomeia o arquivo**.
- Tools por **registro** (`$tools` do servidor), arquivos como contra-medida.
- Rótulo `registradas`, nunca `expostas` — exposição é runtime (`MCP_TOOLS_EXPOSED`); no Hostinger é
  zero. Dizer "expostas" a partir de arquivo é presence-gate.
- Sem `git grep`, emite **"não medido"**, nunca `0`.
- 19 asserts, com bite-test do erro real (`caching.embeddings` não é provider) e controles negativos.
- Gatilho do workflow ampliado para acordar quando alguém mexe em IA — com o **resíduo declarado** em
  comentário: agente fora da convenção só é pego pelo cron.

## Achados que viraram trabalho

| Achado | Como apareceu |
|---|---|
| Tokens do chat gravados na mensagem do **turno anterior** | cético adversarial; confirmado por leitura própria — o comentário do controlador documenta a dependência herdada |
| 5 tabelas órfãs + 1 só-seeder + 1 referenciada **sem migração** | varredura das 58 tabelas |
| Só **2** tabelas têm imutabilidade real; outras prometem em comentário | `grep` dos triggers |
| 4 flags lidas fora de `config/` — uma **desliga a redação de PII** | inventário das 46 chaves |
| Erro de infra indistinguível de "não achei" (2 módulos) | leitura dos caminhos de falha |

Cinco viraram sessão paralela, com áreas de arquivo isoladas declaradas em cada prompt.

## A perda que quase passou

O [#4975](https://github.com/wagnerra23/oimpresso.com/pull/4975) — sessão paralela, mesmo dia — fez
`Jana/ARCHITECTURE.md` virar **gerado**. Direção certa. Mas o anterior era **curado**, e duas peças
não-deriváveis sumiram do repositório inteiro:

```bash
git grep -il "não é BI tradicional" origin/main -- memory/   # → 0
git grep -il "trajetória projetada"  origin/main -- memory/  # → 0
```

A doutrina de posicionamento e as cinco decisões em aberto — as duas que a auditoria adversarial do
mesmo dia tinha marcado como "preservar". Resgatadas na
[proposal](../decisions/proposals/2026-07-28-camada-ia-atual-x-alvo-e-doutrina-resgatada.md)
([PR #4976](https://github.com/wagnerra23/oimpresso.com/pull/4976)), junto dos 6 deltas atual→alvo.

**Lição:** quando um documento curado vira gerado, a prosa não migra sozinha — e some sem alarme,
porque nenhum gate mede ausência de doutrina.

## Erros meus, registrados

- **LC-08 ocorrência 24** — censo contado à mão (14 agentes, 16 provedores, 2 pipelines de RAG).
  Corolário: *quando o número que você vai escrever pode ser contado, a correção certa é o contador,
  não o número.*
- **Emenda no mesmo dia** — a 1ª entrega do contador reincidiu. Corolário adicional: *fixture do
  classificador nunca pega erro de oráculo; teste também a escolha da fonte.*
- Publiquei os dois artefatos visuais **sem ver renderizado** — validei sintaxe e integridade das
  referências, não o resultado. O primeiro tinha nós ilegíveis; [W] apontou por print.

## Segunda metade da sessão — o que veio depois do primeiro fechamento

### As sessões paralelas fecharam 4 dos 6 deltas em horas

| PR | O que resolveu |
|---|---|
| [#4977](https://github.com/wagnerra23/oimpresso.com/pull/4977) | flags fora de `config/` — e o achado foi **maior**: estavam **inoperantes em prod** |
| [#4978](https://github.com/wagnerra23/oimpresso.com/pull/4978) | `mcp_handoff_drafts` fantasma — custo nunca persistia |
| [#4979](https://github.com/wagnerra23/oimpresso.com/pull/4979) | retrieval do KB distingue degradação de ausência |
| [#4980](https://github.com/wagnerra23/oimpresso.com/pull/4980) | tokens do chat gravam no turno correto |

O #4977 é o de maior valor: a sessão **mediu em produção**, com controle negativo
(`env('APP_ENV')` → `NULL`), e provou que `config:cache` fazia aquelas chaves devolverem sempre o
default. Não era higiene — era kill-switch morto, incluindo o que desliga a redação de PII. Era
exatamente a verificação que o chip pedia.

### O Codex executou a proposta

[#4981](https://github.com/wagnerra23/oimpresso.com/pull/4981) criou
[`Jana/OBSERVABILITY.md`](../requisitos/Jana/OBSERVABILITY.md) — 631 linhas, dez etapas com aceite e
rollback. Deu casa à doutrina no `BRIEFING.md` (respondendo a pergunta que o #4976 fazia), declarou
os donos por PR (`Dedup-ack`) e estendeu em vez de abrir paralelo.

### A pergunta da régua, e a resposta medida

[#4983](https://github.com/wagnerra23/oimpresso.com/pull/4983) — [W] perguntou como integrar as
métricas do relatório na régua. **Resposta: não integrar.** `env()` fora de `config/` já tem gate
required; as notas são opinião e o ratchet exige 3 medições reais; o denominador não fecha (a régua
mede diretório, a camada atravessa 5). E o slot de IA **já existe e está vazio**:
`recall_eval_violations` = `not_yet_measured`.

O gargalo tem causa declarada no código — depende do índice ser alcançável do cron de produção,
**decisão de infra**, não de trabalho. E é a mesma janela que destrava a Etapa 3 do plano do Codex e
a promoção do scorecard por bucket que [W] aprovou em maio.

### Medição do falso-positivo do D9

Publicada no #4983. Dos serviços que a régua penaliza por não mencionar OTel, **24 de 176 (14%) são
estruturalmente não-instrumentáveis** — interface, exceção, DTO, Null Object, e a própria
infraestrutura de telemetria. O KB perde **1,2 de 4** por ter três DTOs; a Jana é penalizada por
`RetrievalTelemetryDecorator` e `LangfuseClient` — a observabilidade contada como não-observável.

**Não propus consertar:** o critério exigiria classificar qualidade por forma sintática, a família
rejeitada 4× no §5. Fica como dado para calibrar se a dimensão for reescrita.

### [#4984](https://github.com/wagnerra23/oimpresso.com/pull/4984) — append-only honesto

Seis entidades se declaram append-only; só duas têm trigger. A pior afirmava **"Tabela IMUTÁVEL"**.
As quatro sem garantia passaram a dizer que é convenção; as duas com garantia passaram a **nomear os
triggers** — antes nem elas diziam. Zero linhas fora de comentário.

O gate de superfície ficou vermelho nesse PR **de comentários**. Verifiquei antes de consertar: o
drift era **pré-existente** no main (564→566 arquivos). Meu toque só acordou o gate diff-aware — a
lápide §5 2026-07-12 na prática.

## Erros meus na segunda metade

- **LC-08 ocorrência 25** — afirmei em 4 artefatos que o streaming não emitia rastro. Emite. Medi o
  **método** para uma pergunta sobre um **listener global**, e a evidência contrária estava no mesmo
  arquivo, 3 linhas do trecho que citei. Corrigido por sessão paralela.
- **51 → 53** serviços com OTel: usei `grep -E` sem `-i`, e o predicado real é case-insensitive.
  Pego antes de virar conclusão.

## Dois near-miss que valem registro

- **`git stash`**: rodei um para testar o main limpo. A árvore estava commitada, então **nada foi
  criado** — e o `stash@{0}` era de outra sessão (16 na pilha). Um `pop` por reflexo teria puxado
  trabalho alheio. É a lápide §5 2026-07-27.
- **`--write` sem argumento**: rodei `module-surface.mjs Jana --write` **com** o módulo e conferindo
  a saída. Sem o módulo, o script imprime o uso e sai com sucesso — indistinguível de ter funcionado.

## Limite honesto

Tudo veio de leitura de código. **Nada foi medido contra banco ou runtime.** Onde se lê "roda", leia
"o código declara que roda". As contagens do dia valem para `origin/main` de 2026-07-28.
