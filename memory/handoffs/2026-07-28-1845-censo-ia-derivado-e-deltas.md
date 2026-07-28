---
date: "2026-07-28"
slug: "censo-ia-derivado-e-deltas"
tldr: "O censo da camada de IA virou máquina (#4973 mergeado). Os 6 deltas atual→alvo e a doutrina que a geração do ARCHITECTURE apagou estão em proposal (#4976), esperando decisão de [W]. Cinco sessões paralelas em voo — áreas isoladas listadas abaixo."
hour: "18:45 BRT"
topic: "Censo da camada de IA derivado + 6 deltas atual→alvo + 5 sessões paralelas em voo"
authors: [W, C]
prs: [4973, 4976]
related_adrs:
  - "0035-stack-ai-canonica-wagner-2026-04-26"
  - "0256-knowledge-survival-meia-vida-catraca-sentinela"
---

# Handoff 2026-07-28 18:45 — o censo virou máquina; o alvo virou proposta

## Onde parou

**Mergeado:** [PR #4973](https://github.com/wagnerra23/oimpresso.com/pull/4973) — o `system-map.mjs`
passou a derivar o censo da camada de IA (agentes por contrato, tools por registro, provedores,
implementações de memória e reranker). Vive em `PAINEL-SISTEMA.md` §Camada de IA e o cron diário
mantém.

**Aberto:** [PR #4976](https://github.com/wagnerra23/oimpresso.com/pull/4976) — proposal com os 6
deltas atual→alvo e o resgate da doutrina perdida. **Espera decisão de [W]**, não merge automático:
a pergunta é *onde* a doutrina e as 5 decisões em aberto devem morar.

## O que a próxima sessão precisa saber

### 1. Cinco sessões paralelas estão em voo

Cada uma com área de arquivo isolada, declarada no prompt. **Não invada:**

| Sessão | Área exclusiva |
|---|---|
| tokens no turno errado | `Services/Ai/LaravelAiSdkDriver.php` · `Http/Controllers/ChatController.php` |
| tabelas sem uso | `Database/Migrations/**` · `Mcp/Tools/HandoffDraftTool.php` |
| flags fora de config | `Config/config.php` + os `env()` soltos |
| kb-answer falha silenciosa | `Services/Kb/**` · `Entities/Mcp/McpMemoryDocument.php` · `Mcp/Tools/KbAnswerTool.php` |
| KB Unificado 200 OK | `Modules/KB/**` |

**Bloqueado de propósito:** instrumentar o caminho de streaming (Langfuse/OTel) — é o mesmo método da
sessão dos tokens. Abrir só depois que aquela mergear.

### 2. O bug que vale conferir primeiro

No chat com streaming, os tokens de cada turno são gravados na resposta **do turno anterior** — o
driver atualiza "a última resposta" de dentro do gerador, e o controlador cria a resposta atual
depois. Confirmado por leitura; **não reproduzido em runtime**. Se for confirmar, o oráculo é o banco,
não o código.

### 3. Uma perda de conhecimento aconteceu hoje

O #4975 fez `Jana/ARCHITECTURE.md` virar gerado — direção certa. Mas a doutrina de posicionamento
(*não é BI · não é dashboard · é agente orientado a decisão*) e as 5 decisões em aberto **sumiram do
repositório**. Estão resgatadas no #4976, ainda sem casa definitiva.

Antes de consolidar doc curado em gerado: `git grep` das frases que a versão anterior tinha de único.
Nenhum gate mede ausência de doutrina.

### 4. O que ficou sem fazer

- As 4 melhorias que eu mesmo listei para os artefatos visuais: falta **baseline** (a grade é medição
  1, sem série) e **derivar a parte contável** do `PAINEL-SISTEMA` em vez de escrever à mão. Pesos por
  gravidade e separação decisão×defeito já entraram.
- Nunca vi os dois artefatos renderizados — validei sintaxe e referências, não o resultado visual.

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ativo em COPI** (consultado 2026-07-28).
- `tasks-list module:Jana` → 30 ativas. Nenhuma cobria os achados desta sessão — por isso viraram
  chips, não duplicatas. Vizinhas a vigiar: **US-COPI-125** (ACL pre-retrieval no `KbRagService`)
  toca os mesmos arquivos da sessão do KB Unificado; **US-COPI-126** (propagar renames
  Copiloto→Jana em ~112 PHP) colide com quase tudo — não iniciar enquanto as 5 estiverem em voo.
- Sessions irmãs do dia: `2026-07-28-jana-architecture-canonica-viva` e
  `2026-07-28-planta-ia-documentacao-viva` (ambas do #4975, tema vizinho, trabalho distinto).

## Limite honesto deste handoff

Tudo que está escrito aqui veio de leitura de código e de consulta ao git. **Nada foi medido contra
banco ou runtime.** As contagens valem para `origin/main` de 2026-07-28.
