---
date: "2026-09-03"
time: "09:20 BRT"
slug: ct100-fora-breaker-e-veredito-unico
tldr: "Circuit breaker (#6593) separa transporte de erro real no mcp:sync-memory; ct100_reachability (#6611) dá 1 veredito no lugar de N sintomas. Ambos em main. Pest no CT 100 NÃO rodou — a máquina é o próprio assunto. A premissa 'fora desde 28/08' que abriu a sessão está refutada pela ERRATA das 11:30: foram DUAS quedas."
decided_by: [W]
prs: [6593, 6611, 6595]
related_adrs:
  - 0062-separacao-runtime-hostinger-ct100
  - 0053-mcp-server-governanca-como-produto
next_steps:
  - "Rodar o Pest quando o CT 100 voltar: tailscale ssh root@ct100-mcp \"docker exec oimpresso-staging php artisan test --filter=Ct100\" — os 18 casos entraram em main sem nunca terem executado."
  - "Ler storage/logs/mcp-cron.log em prod e conferir QUAL exceção o sync lança (a linha 'Sync falhou: <msg>'). Se não for da família tipada, o breaker não morde e o ruído continua."
  - "Conferir se SCOUT_DRIVER=meilisearch no .env do Hostinger. Se não for, o probe é no-op (applicable=false) e só o classificador de exceção atua."
  - "[W] decide: os 2 baselines RAGAS (governance/jana-ragas-baseline.json e -real-) estão parados desde 2026-07-01 e derrubam o watchdog G6 em TODO PR do repo. Não é required, mas é vermelho permanente."
  - "[W] decide: registrar (ou não) US no SPEC.md/BRIEFING.md da Jana — tocar SPEC acorda anchor-lint/doneness-lint."
---

# CT 100 fora do ar — circuit breaker no sync + veredito único no health-check

## O fato medido

Hostinger, `storage/logs/laravel.log`, contagem/dia de
`Scheduled command [mcp:sync-memory --reason=cron] failed with exit code [1]`:

| 08-24 | 08-27 | 08-28 | 08-29 | 08-30 | 08-31 | 09-01 | 09-02 (parcial) |
|---|---|---|---|---|---|---|---|
| 1 | 45 | 144 | 148 | 116 | 146 | 147 | 88 |

O cron roda a cada 5min → **288 execuções/dia**, ~145 delas viravam ERROR. Isso treina o
operador a ignorar o canal onde o ERROR de verdade aparece.

### ⚠️ Correção de enquadramento — foram DUAS quedas, não uma contínua

O pedido que abriu esta sessão trazia "CT 100 fora desde ~27-28/08" como janela única, e eu
carreguei a premissa. Ela está **refutada** por
[2026-09-03 11:30 — ERRATA](2026-09-03-1130-ct100-errata-duas-quedas-nao-uma.md), de sessão
irmã: o [PR #6587](https://github.com/wagnerra23/oimpresso.com/pull/6587) (mergeado 02/09
17:35 BRT) prova **acesso pleno** ao CT 100 naquele dia — `df -h`, `docker exec`, `tools/list`
do MCP. Quadro correto: 27/08→02/09 (queda 1), de pé até ~17:35 de 02/09, fora de novo a
partir de 19:59 (queda 2).

**Minha própria medição corroborava a errata e eu não percebi:** ao abrir a sessão em 02/09 à
noite, o `tailscale status` dizia `ct100-mcp ... offline, last seen 5m ago` — cinco minutos,
não seis dias. Era a queda 2 recém-começada; li aquilo como confirmação da premissa herdada em
vez de como o dado que a contradizia. Classe LC-09 (importar premissa sem checar se vale
aqui), agravada por ler um número pelo que eu esperava dele.

**O que isso NÃO muda:** o comportamento do breaker. Ele reage a *transporte fora agora*, não
à duração da janela — uma queda de 6 dias e duas de horas produzem o mesmo ruído e recebem o
mesmo tratamento. As medições `http_code=000` de 02/09 (~20:0x) e 03/09 (09:20, "last seen
16h ago") seguem válidas para os instantes em que foram tomadas.

## O mecanismo (por que o sync quebra sem o CT 100)

O `mcp:sync-memory` **não fala com o MCP server**: lê `memory/` do filesystem e escreve em
`mcp_memory_documents`. O único salto de rede dele é o observer do Scout
(`McpMemoryDocument` usa `Searchable`), que reindexa/reembeda no CT 100 — comportamento já
registrado em `memory/requisitos/Jana/SPEC.md:430`. Por isso o probe do breaker pergunta ao
**Meilisearch do Scout**, não ao `mcp.oimpresso.com`: sondar o MCP pra decidir se um sync de
DB roda mediria a propriedade errada.

## O que entrou

**#6593 — `Ct100CircuitBreaker` + `mcp:sync-memory`.** Separa dois casos que colidiam:

| Situação | Antes | Agora |
|---|---|---|
| **Não respondeu** (refused/timeout/DNS) | exit 1 a cada 5min | 1 WARNING por janela de 30min, exit 0 |
| **Respondeu mal** (5xx, 4xx, deadlock, dado inconsistente) | exit 1 | exit 1 — **sem mudança** |

Classificação por **tipo** de exceção andando a cadeia `getPrevious()` (Scout/Laravel
embrulham a do cliente HTTP), nunca por texto da mensagem — `proibicoes.md` §5 tem cinco
lápides medidas de guard sintático que reprovava o legítimo. **Fail-safe:** tipo não
reconhecido ⇒ exit 1 como antes; o breaker só cala o alarme com prova do tipo.

**#6611 — `ct100_reachability` (check 10g, DURO).** Um veredito no lugar de N sintomas.
**Não sonda nada**: correlaciona o que os donos já mediram no mesmo run
(`memoria_recall_backend` p/ o MCP, o probe existente p/ o Langfuse) mais a perna
meilisearch/sync, que vem do breaker porque quem atravessa aquele caminho é o cron 288×/dia.
Regra **2-de-N**: um serviço fora é problema dele (o dono continua duro); dois ou mais é o
host — aí nasce o veredito e os sintomas explicados viram advisory, sem sumir da tabela.

`mcp_webhook_5xx_2h` **não** virou perna, e está declarado no docblock: ele mede a leitura
que o GitHub tem do webhook, e entrega que nem conectou não é 5xx. ⚠️ É leitura da semântica
da API, **não medida** contra as entregas reais desta janela.

## Estado MCP no momento do fechamento

⚠️ **O checklist MCP-first da [ADR 0130] NÃO pôde ser executado** — `cycles-active`,
`my-work`, `sessions-recent` e `decisions-search` vivem no MCP server do CT 100, que é
exatamente a máquina fora do ar. Registrar isto é o honesto; inventar um snapshot seria pior.
Substituto usado: `gh` (PRs/checks) + `git` contra `origin/main` fresco.

Custo real dessa cegueira, medido nesta sessão: **não vi a sessão irmã** que já tinha provado
as duas quedas (o `whats-active`, que responderia isso, é tool MCP). Só encontrei a ERRATA ao
inserir minha linha no índice de handoffs e ver a dela ali.

## Armadilhas que quase passaram (valem para a próxima sessão)

1. **`*/5 * * * *` dentro de docblock PHP fecha o comentário** no `*/`.
2. **`jana-pest.yml` roda uma LISTA EXPLÍCITA de arquivos, não o diretório.** Estar em
   `Modules/Jana/Tests/Feature` + `phpunit.xml` + o workflow disparando em `Modules/Jana/**`
   **não** faz o teste rodar. Caminho: 1 linha em `.github/ci-sqlite-pest.list` (tem
   `merge=union`). Classe do §5 2026-08-02.
3. **`SUPERFICIE.md` é gerado e conflita em série** num repo com churn alto: conflitou 2×
   (main andou 5 e depois 7 commits). Resolver **sempre** com `module-surface.mjs --write` +
   `--check`, nunca escolhendo lado.
4. **PR empilhado em branch (não em `main`) não nasce com os required**: o #6595 tinha 51/51
   verdes e **21 dos 45 required ausentes**. Assinatura do §5 2026-08-08.
5. **`#6593` entrou como squash**, então o branch do #6595 (que o carregava via merge) passou
   a conflitar por construção. Refeito como #6611 a partir de `origin/main` fresco.
6. **Backtick dentro de `python -c "..."` com aspas duplas vira substituição de comando** no
   bash: apagou os trechos entre crases da linha do índice e chegou a executar arquivos de
   teste como shell. Detectado por `numstat` + inspeção; refeito com heredoc `<<'EOF'`.

## Verificação — o que rodou e o que não

**Rodou:** `php -l` verde nos 4 arquivos, **com controle positivo** (arquivo quebrado →
exit 255); `validate.mjs` verde nestes 2 documentos, **com controle positivo** (frontmatter
inválido → FAIL com 4 violações); CI verde nos required (#6611 fechou com **AUSENTES: 0** dos
45); conteúdo conferido **em `origin/main`**, não na afirmação do PR.

**NÃO rodou:** **Pest no CT 100** — a máquina é o assunto. Rodar local é proibido
(`proibicoes.md` + hook `block-test-fora-ct100.mjs`) e o worktree não tem `vendor/`. Os 18
casos estão em `main` sem nunca terem executado.

**Não medido:** qual exceção o sync lança em prod; se `SCOUT_DRIVER=meilisearch` no
Hostinger. Ver `next_steps`.

## Higiene

`#6595` fechado sem merge (substituído pelo #6611, motivo no comentário). Branch órfão
`claude/ct100-reachability-health-check` deletado do remoto **depois** de provar que nada dele
ficava só lá (teste idêntico a `main`; as 3 linhas exclusivas eram versões *antigas* que
`main` já substituíra). Force-push foi **recusado** pelo hook `block-destructive` e **não** foi
contornado — usei branch novo.
