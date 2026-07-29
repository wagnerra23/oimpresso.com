---
date: "2026-07-28"
slug: "censo-ia-fechado-e-a-janela-ct100"
tldr: "Os 6 deltas da camada de IA fecharam (4 por sessões paralelas, 2 viraram decisão). O que sobra não é código: a janela CT 100 destrava três coisas de uma vez — a métrica que nunca mediu, a Etapa 3 do plano de observabilidade e a promoção do scorecard por bucket aprovada em maio."
hour: "21:10 BRT"
topic: "Fechamento da camada de IA: 4 PRs mergeados, a régua respondida com não-integrar, e o gargalo que é de infra"
authors: [W, C]
prs: [4973, 4976, 4983, 4984]
related_adrs:
  - "0035-stack-ai-canonica-wagner-2026-04-26"
  - "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes"
  - "0256-knowledge-survival-meia-vida-catraca-sentinela"
---

# Handoff 2026-07-28 21:10 — o censo fechou; o que sobra é uma janela de infra

> Continua o [handoff das 18:45](2026-07-28-1845-censo-ia-derivado-e-deltas.md). Aquele registrou o
> meio da sessão; este fecha.

## Estado final

**Mergeados:** [#4973](https://github.com/wagnerra23/oimpresso.com/pull/4973) censo derivado ·
[#4976](https://github.com/wagnerra23/oimpresso.com/pull/4976) deltas + doutrina resgatada ·
[#4983](https://github.com/wagnerra23/oimpresso.com/pull/4983) métricas na régua ·
[#4984](https://github.com/wagnerra23/oimpresso.com/pull/4984) append-only honesto.

**Sessões paralelas fecharam 4 dos 6 deltas** — #4977 flags · #4978 tabela fantasma · #4979
degradação vs ausência · #4980 tokens no turno correto. O Codex entregou
[`Jana/OBSERVABILITY.md`](../requisitos/Jana/OBSERVABILITY.md) ([#4981](https://github.com/wagnerra23/oimpresso.com/pull/4981)).

## O que a próxima sessão precisa saber

### 1. O gargalo é uma janela de infra, não trabalho de código

`recall_eval_violations` está `not_yet_measured` no scorecard. A causa está escrita no gerador:

```
notYet('down', 0, 'golden set recall (KL-C2) — depende do alias map das 13 colisões ADR')
```

E o cabeçalho de `.github/workflows/jana-recall-eval.yml`: *"o caminho real fica pronto-pra-ligar via
schedule (`environments=['live']`, CT 100). Liga quando o índice `mcp_memory_documents` for alcançável
do cron prod. Depende de janela CT 100 (decisão de infra), NÃO de secret."*

**A mesma janela destrava três coisas:** a métrica que nunca mediu, a Etapa 3 do plano do Codex, e a
promoção do scorecard por bucket que [W] aprovou em 2026-05-17 (`module.json` → `bucket: ai_central`;
`memory/scorecards/jana.yaml` com A1..A6, `Status: EXPERIMENTAL`).

### 2. Não integrar a grade do relatório em régua nenhuma

Medido e registrado no #4983: `env()` fora de `config/` já tem gate **required**; as notas são
opinião e o ratchet exige 3 medições reais da fonte; o denominador não fecha (a régua mede
**diretório**, a camada atravessa 5 módulos). Não re-propor.

### 3. Dois medidores de IA rodam em mock — por razões diferentes

- **RAGAS gate**: mock em PR **por desenho de custo** (`github.event_name == 'pull_request' && 'mock'`).
  Escolha deliberada, não pendência.
- **recall-eval**: mock **por bloqueio** — a janela acima.

Não tratar os dois igual.

### 4. Achado registrado, deliberadamente NÃO consertado

`D9 Observability` conta **menção** a OTel. Medi o falso-positivo: **24 de 176 serviços (14%)** são
estruturalmente não-instrumentáveis (interface, exceção, DTO, Null Object, e a própria telemetria).
O KB perde 1,2 de 4 por ter três DTOs. **Não consertar sem critério novo** — classificar qualidade
por forma sintática é a família rejeitada 4× no §5. O número fica para calibrar.

### 5. O que ainda espera decisão

- **5 tabelas órfãs** — confirmado zero consumidores; remover é decisão [W].
- **`Jana/OBSERVABILITY.md`** está `status: proposto`, `cycle: não apostado`. A Etapa 1 (trace raiz
  do chat) está **desbloqueada** desde que o #4980 mergeou.
- **Promover trigger** nas 4 tabelas que prometem append-only só em comentário (delta D5).

## Erros meus nesta metade — LC-08 foi a 25

- Afirmei em **4 artefatos** que o streaming não emitia rastro. **Emite.** Medi o *método* para uma
  pergunta sobre um *listener global*, e a evidência contrária estava no mesmo arquivo, 3 linhas do
  trecho que citei. Pego pelo Codex, não por mim. Nota de observabilidade da grade: 4 → 6.
- Reportei 51 serviços com OTel; são **53** — `grep -E` sem `-i`, e o predicado real é
  case-insensitive.

**Corolário que entrou no ledger:** *pergunta do tipo "o sistema emite/dispara/escuta X?" não se
responde no caminho — se responde em quem assina o evento.*

## Dois near-miss

- **`git stash`**: rodei um com a árvore já commitada, então **nada foi criado** — e o `stash@{0}`
  era de outra sessão (16 na pilha). `pop` por reflexo teria puxado trabalho alheio (§5 2026-07-27).
- **`--write` sem argumento**: rodei `module-surface.mjs Jana --write` **com** o módulo e conferindo
  a saída. Sem ele, o script imprime o uso e sai 0 — indistinguível de sucesso.

## Estado MCP no momento do fechamento

- `cycles-active` → nenhum cycle ativo em COPI.
- `tasks-list module:Jana` → 30 ativas; nenhuma cobria os achados do dia.
- **Vizinhas a vigiar:** US-COPI-125 (ACL no `KbRagService`) e US-COPI-126 (renames Copiloto→Jana em
  ~112 PHP — colide com quase tudo).

## Limite honesto

Tudo veio de leitura de código e consulta ao git. **Nada medido contra banco ou runtime.** As
contagens valem para `origin/main` de 2026-07-28. E os dois artefatos visuais publicados nunca foram
vistos renderizados por mim — validei sintaxe e referências, não o resultado.
