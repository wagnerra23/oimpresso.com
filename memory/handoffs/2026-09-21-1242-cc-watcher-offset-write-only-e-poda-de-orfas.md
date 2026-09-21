---
date: "2026-09-21"
time: "12:42 UTC"
slug: cc-watcher-offset-write-only-e-poda-de-orfas
tldr: "O run 'incremental' do cc-watcher não incrementava: o `lineCount` era gravado no state e nunca lido como offset. 188 POSTs/30.189 msgs por run para ~3 mil novas, contra um balde de rate-limit por IP compartilhado com as outras sessões da máquina. Consertado em 3 pernas (offset, caminho vazio, progresso sob 429) + poda das 501 entradas órfãs. Medido: 27 POSTs/3.322 msgs. O vigia de CI que armei tinha a própria sonda quebrada e isso fica registrado."
prs: [7603, 7608]
decided_by: [W]
next_steps:
  - "Reavaliar o gatilho de 10 min da tarefa `oimpresso-cc-watcher`: com o run caindo de ~77 min para segundos, o `IgnoreNew` deixa de engolir disparos e a cadência passa a ser efetiva — medir a duração REAL em produção antes de mexer, a projeção aqui não é medição"
  - "O runner não guarda log (stdout vai para o Task Scheduler, que descarta) — se a duração em produção virar pergunta recorrente, redirecionar para arquivo é o passo barato"
  - "Guarda 2 da poda (só ENOENT) não é exercitável no Windows: os 3 caminhos testados dão ENOENT. Roda na lane Linux do CI; validada à mão no CT 100"
---

# O offset que existia no state e nunca cortava — e as 501 órfãs

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → **sem tasks ativas** pra `@wr23`
- `sessions-recent limit:3` → `blade-migration-plan`, `arte-agentes-ia-ui-guardrails`, `como-integrar-scorecard` (todos indexados 2026-09-21, nenhum deste tema)
- `whats-active` (2h) → **15 sessões**; nenhuma tocou `scripts/cc-watcher/**` além desta (`c4287672`)
- PRs da sessão: **#7603 e #7608, ambos MERGED**
- Handoffs irmãos de hoje: `0800-funil-de-design-e-o-vigia-que-nunca-vigiou`, `1139-revogacao-divergencia-declarada-e-o-cast-que-mentia` — nenhum deste tema

## O defeito, em três pernas

O enunciado veio de uma sessão irmã com um sintoma medido e **sem diagnóstico** — e ela foi explícita: *"não presumas que o defeito está no mtime só porque foi por aí que eu apontei; mede."* Medido, eram três coisas independentes:

| | Defeito | Evidência |
|---|---|---|
| **A** | `readJsonl(filePath)` lia o arquivo inteiro; o `lineCount` era gravado no state (`:342`) e lido só como `> 0` (`:308`) — **write-only** como offset | varredura contada: 5 ocorrências do símbolo, nenhuma cortando. Arquivo com **43 linhas novas de 2.627** reenviava as 2.627 |
| **B** | o caminho `empty` não gravava state → arquivo sem linha nova era relido do disco em todo run, para sempre | `real=3214 guardado=3214 novas=0` |
| **C** | o state só era gravado **depois** de todos os batches: um `throw` de 429 jogava fora o progresso já confirmado, e o run seguinte reenviava tudo — gerando mais 429 | teste vermelho **antes** do conserto: servidor confirmou 400 mensagens, run seguinte reenviou 700 |

A perna **C** saiu do indício que a sessão irmã trouxe (**455 sessões POSTadas** num momento em que só **19** dos 883 `.jsonl` tinham mudado em 24h) — tratado como hipótese e provado por teste, não aceito por autoridade. A hipótese adjacente dela (chave do state não bater) ficou **refutada**: das 1.379 entradas, 501 eram órfãs, mas só **5** dos 883 arquivos não tinham entrada.

## Por que doía — não era latência

A rota declara `['api','mcp.auth']` **sem `throttle:` próprio** (`Modules/Forja/Http/routes.php:181`). Quem limita é o grupo `api`: `Limit::perMinute(60)->by($request->user()?->id ?: $request->ip())`. Como `api` roda **antes** do `mcp.auth`, não há user autenticado no instante do throttle e a chave cai no `?:` — o **IP**, compartilhado por todas as sessões Claude da máquina. Cada run queimava o balde **das vizinhas**.

O README atribuía o 429 a cota MCP e mandava esperar o reset das 00:00 BRT. Errado na prática: `claude-code-usage-self` reporta `quota: 0` em 7 dias. Os dois 429 se distinguem pela mensagem do corpo — `Too Many Attempts.` (ThrottleRequests) × `Quota excedida` (QuotaEnforcer).

## Medição

Dois braços partindo do **mesmo state congelado**, com o POST apontado para servidor local — nada foi para a rede, zero 429 real, state de produção intocado:

```
ANTES (HEAD)  188 POSTs   30.189 mensagens
DEPOIS         27 POSTs    3.322 mensagens     −89,0% msgs / −85,6% POSTs
```

Sonda read-only independente concorda na razão: 65.322 linhas reenviadas para 7.258 novas (−88,9%), com controle positivo (65 pares `(real,guardado)` distintos de 66).

## Poda das órfãs

501 de 1.385 entradas, 280 KB; 496 sob `worktrees`. **501/501 `ENOENT`**. Como o lixo renasce a cada worktree deletada, virou regra do mecanismo, não limpeza à mão. Três guardas: varredura vazia não poda (proxy do disco desmontado), só `ENOENT` (LC-33), e só dentro do `PROJECT_GLOB`.

⚠️ A alternativa óbvia foi **medida e descartada**: exigir a pasta-pai viva limparia só **122 de 501** (379 têm o pai também ausente). A guarda certa é a raiz, não o pai.

Aplicado no state real com backup e teste de identidade: `1.385 → 884`, 0 removidas que ainda existem, 0 valores alterados, 0 arquivos vivos perdidos, 0 órfãs restantes.

## Erros meus, registrados

**Dois asserts não discriminavam** e só apareceram na mutação — o valor esperado coincidia com o que o bug produz (§5 2026-09-05). `M4` só morde assertando o **mtime**, não o `lineCount` que o 1º run já deixava certo. `M6` (mtime real no progresso parcial, que causa **perda silenciosa**) só morde quando o teste **para de tocar o mtime** entre os runs — o `utimesSync` mascarava o skip.

**O vigia de CI que armei tinha a sonda quebrada.** A expressão `jq` `[[A]|length, [B]|length, (length)]` produz **quatro** campos por precedência do `|`, e o `cut -f3` lia o campo errado (`107 107 0 0` em vez de `107 0 110`). Ele anunciou `DENOMINADOR VAZIO` como se tivesse detectado algo — não detectou nada. Sem esse desvio acidental, eu teria relatado **107 falhas inexistentes**. É a classe que eu estava consertando no `cc-watcher`, cometida dentro do vigia, logo depois de eu elogiá-lo em voz alta. Corrigido validando a expressão antes de armar + guard de contagem exata de campos.

## Notas operacionais

- **O force-push foi barrado corretamente.** O #7606 (poda, empilhado sobre o #7603) ficou `DIRTY` quando a base foi mergeada por **squash**. Rebasear exigiria reescrever histórico remoto, e o `block-destructive` exige autorização explícita de [W]. Fechado sem merge; o commit rebaseado foi publicado em branch nova com push normal → **#7608**.
- **O fix chega à produção sozinho.** `~/.claude/cc-watcher-run.cmd` faz `fetch origin main` + `checkout --detach origin/main` a cada disparo, e **aborta** (exit 3/4) em vez de rodar versão velha. Confirmado no corpo executável, não só no cabeçalho: às 12:40 o checkout dedicado já tinha o #7603 (`LastTaskResult: 0`) e o run terminou **dentro da janela de 10 min**, contra os ~77 min de antes.
- **Gate:** `scripts/cc-watcher/incremental.test.mjs`, exercitando o **CLI de fora** contra servidor HTTP real — assert sobre helper exportado não provaria o pipeline (§5 2026-07-30). Registrado **por nome** na lane `governance-script-tests` (ela não usa glob). Lane **advisory**.
- **Mordida por mutação**, com controle positivo antes: M1 chamador sem offset · M2 helper sem corte · M3 sem guarda de truncamento · M4 sem `saveState` no vazio · M5 sem avanço por batch · M6 mtime real no parcial · M7 sem guarda de volume · M8 sem guarda `ENOENT` (só Linux) · M9 sem guarda de escopo · M10 poda desligada — todos mordem. **M11** (`vivos.has`) sobrevive e está documentado no código como fast-path, não guarda.
