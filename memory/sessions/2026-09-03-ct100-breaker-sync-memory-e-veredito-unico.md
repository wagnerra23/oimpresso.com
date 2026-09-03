---
date: "2026-09-03"
hour: "09:20 BRT"
topic: "CT 100 fora do ar — circuit breaker no mcp:sync-memory e veredito único no jana:health-check"
authors: [C, W]
prs: [6593, 6611, 6595]
related_adrs:
  - 0062-separacao-runtime-hostinger-ct100
  - 0053-mcp-server-governanca-como-produto
outcomes:
  - "#6593 mergeado: Ct100CircuitBreaker separa falha de TRANSPORTE (1 warning/30min + exit 0) de erro REAL (exit 1 intacto)"
  - "#6611 mergeado: check ct100_reachability (10g, duro) consolida mcp+langfuse+meilisearch num veredito só, sem sondar nada de novo"
  - "#6595 fechado sem merge: base em branch fazia 21 dos 45 required NUNCA nascerem; refeito sobre main fresco"
  - "Achado de processo: jana-pest.yml roda LISTA de arquivos, não diretório — teste novo precisa de 1 linha em .github/ci-sqlite-pest.list ou não roda"
  - "Pendência declarada: os 18 casos Pest entraram em main SEM nunca terem executado (CT 100 é a máquina fora)"
---

# CT 100 fora do ar — breaker no sync + veredito único

## Pedido

[W] trouxe o fato já medido em prod: `mcp:sync-memory --reason=cron` gravando
`failed with exit code [1]` ~145×/dia desde ~27-28/08, com `mcp.oimpresso.com` em HTTP 000 e
o host Proxmox inteiro offline. Pediu (1) circuit breaker que transforme falha de transporte
em 1 alarme com backoff, preservando exit 1 pro erro real; (2) um check
`ct100_reachability` consolidando mcp + langfuse + meilisearch, **sem duplicar régua**;
(3) Pest no CT 100, tenant 98, bite-test com HTTP mock.

## Como foi

**Confirmei o estado antes de codar** em vez de aceitar o relato: `curl` → `http_code=000`
(rc=28) e `tailscale status` → `ct100-mcp`/`pve-empresa` offline. Bateu — mas **só o estado
naquele instante**, e é aí que eu errei o enquadramento: a saída dizia `last seen 5m ago`, e
eu li isso como confirmação de "fora desde 28/08" em vez de como o dado que a contradizia.
A [ERRATA de sessão irmã](../handoffs/2026-09-03-1130-ct100-errata-duas-quedas-nao-uma.md)
provou depois que foram **duas quedas** (o PR #6587 teve acesso pleno à máquina em 02/09
17:35). Não muda o breaker — ele reage a transporte fora *agora*, não à duração — mas muda o
que eu podia afirmar. Só encontrei a errata ao inserir minha linha no índice de handoffs; o
`whats-active`, que responderia isso, é tool MCP e estava fora junto com o resto.

**Achei o mecanismo lendo o caminho, não supondo:** o comando não fala com o MCP server —
lê `memory/` e escreve em `mcp_memory_documents`; o salto de rede é o observer do Scout
(`McpMemoryDocument` usa `Searchable`), já registrado em `SPEC.md:430`. Logo o destino a
sondar é o **Meilisearch do Scout**, não o `mcp.oimpresso.com` — sondar este mediria a
propriedade errada.

**A linha traçada** é semântica: *não respondeu* = transporte (warning + exit 0);
*respondeu mal* = erro real (exit 1). Classificação por **tipo** de exceção, nunca por texto
— o §5 tem cinco lápides de guard sintático medidas. E fail-safe: tipo desconhecido ⇒
exit 1 como antes.

**Sobre "não duplicar régua":** o `consolidateCt100()` **não faz rede**. Correlaciona o que
os donos já mediram no mesmo run e lê a perna meilisearch do breaker — que é quem atravessa
o caminho 288×/dia contra 1×/dia do health-check. Acoplamento por campo estruturado
(`'ct100' => ['service','reachable']`), nunca casando texto da mensagem dos outros checks.
`mcp_webhook_5xx_2h` ficou de fora **declaradamente**: mede a leitura do GitHub sobre o
webhook, e entrega que nem conectou não é 5xx.

## Erros meus nesta sessão (e como apareceram)

1. **Heredoc com `*/5 * * * *` num docblock PHP** — o `*/` fecharia o comentário. Peguei ao
   ver o heredoc quebrar, não por revisão.
2. **Grep de uma linha só de JSON** devolveu o array inteiro e eu quase li como "filtro". A
   segunda passagem, com `--jq` por linha, deu a resposta certa (1 PR, não 20).
3. **`/tmp` entre Bash e Node no Windows** — caí no ENOENT que é lápide do §5. Corrigi com
   caminho absoluto.
4. **Escape de `\` em grep no shell** devolveu 0 pra um `use` que estava presente; só não
   virou conclusão errada porque rodei controle positivo ao lado.
5. **Digitei lixo no início de um comando** (`cd "D:/oimpresso.com/.digit`), erro de sintaxe.

## O que sobreviveu como conhecimento

- **`jana-pest.yml` roda lista explícita de arquivos, não o diretório.** Teste registrado no
  `phpunit.xml`, em `Modules/Jana/Tests/Feature`, com o workflow disparando em
  `Modules/Jana/**`, **ainda assim não roda**. Caminho: 1 linha em
  `.github/ci-sqlite-pest.list`. Sem isso eu teria entregue cobertura que nunca executa —
  pior que ausência, porque parece cobertura.
- **PR empilhado em branch não nasce com os required.** O #6595 mostrou 51/51 verdes com
  **21 dos 45 required ausentes**. Só rebaseando pra `main` os 116 checks apareceram.
- **`SUPERFICIE.md` gerado + repo com churn = conflito em série.** Conflitou 2× (main andou
  5, depois 7 commits). Resolver sempre regenerando (`--write` + `--check`).
- **`strict_required_status_checks_policy = false`** neste repo: branch não precisa estar
  atualizado com main pra mergear — o que bloqueia é conflito textual, não staleness.

## Decisões que respeitei em vez de contornar

- **Force-push recusado pelo `block-destructive`**: usei branch novo (#6611) em vez de pedir
  exceção. O hook estava certo.
- **Não mergeei com required pendente** nem usei `--admin`: armei auto-merge, que disparou
  sozinho às 12:00Z quando o `DS gate` saiu da fila do repo (39 de 40 runs enfileirados na
  hora — inanição de runner, não do PR).
- **Não toquei nos baselines RAGAS** parados há 63d, que derrubam o `watchdog G6` em todo PR
  do repo. Não é required; a decisão entre "cron parou de entregar" e "artefato curado à mão
  envelheceu" exige varrer escritores e é de [W] (precedente #4822).

## Aberto

Pest no CT 100 (bloqueado — máquina fora há 16h); qual exceção o sync lança em prod
(`storage/logs/mcp-cron.log`); se `SCOUT_DRIVER=meilisearch` no Hostinger. Detalhe e
comandos no handoff `2026-09-03-0920-ct100-fora-breaker-e-veredito-unico.md`.
