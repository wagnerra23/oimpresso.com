---
date: "2026-08-12"
time: "13:10 BRT"
slug: mcp-orcamento-do-request-353ms-por-query
tldr: "Complemento do handoff das 10:26 (append-only, por isso arquivo novo). A metade que faltava: CACHE_DRIVER=array no CT 100 era no-op total, e o orçamento do request MCP foi medido linha a linha — ~10 queries × ~360ms de latência ao MySQL do Hostinger. initialize caiu de 5,2s para 3,8s só com o cache. 2 PRs novos (#5682 verde, #5687 aberto) atacam os 2 maiores itens restantes: memo do gate Spatie (1102ms) e throttle do carimbo de uso (720ms)."
prs: [5682, 5687]
decided_by: [W]
related_adrs: [0053-mcp-server-governanca-como-produto, 0062-separacao-runtime-hostinger-ct100, 0294-mcp-audit-log-hash-chain-tamper-evident]
next_steps:
  - "[W] mergear #5682 (108 checks verdes) e depois #5687 (branch parte dele)"
  - "Medir o initialize DEPOIS dos 2 merges — previsão ~1,83s/request; se não bater, o orçamento abaixo diz onde procurar"
  - "Reativar a extensão e confirmar o handshake (era ~15s: 4 chamadas × ~3,7s)"
  - "NÃO mexer no audit hash-chain (Tier 0, ADR 0294) — depois dos 2 PRs ele vira ~40% do custo e continua intocável"
---

# O orçamento do request MCP: 353ms por query, e o cache que nunca existiu

Complemento do [handoff das 10:26](2026-08-12-1026-extensao-mcp-loop-sync-git-sha.md) — aquele fecha na descoberta do `CACHE_DRIVER`; handoff é append-only, então o que veio depois mora aqui.

## Estado MCP no momento do fechamento

- `cycles-active` → nenhum cycle ATIVO em COPI
- `gh pr view` → **#5663, #5669, #5672, #5676 MERGED** · **#5682** 108 pass / 0 fail aguardando · **#5687** aberto, CI rodando
- Base: branch nova de `origin/main` fresco por PR; o #5687 parte do #5682 (dependência de arquivo declarada no corpo)

## O que a medição mostrou

O `initialize` custava 5,2s. Não era um gargalo — era **~10 queries em série contra um banco remoto**:

| Etapa | Queries | Custo |
|---|---:|---:|
| `encontrarPorRaw` | 1 | 360 ms |
| `User::find` | 1 | 383 ms |
| **`can('jana.mcp.use')` (Spatie)** | **3** | **1102 ms** |
| `QuotaEnforcer` | 1 | 364 ms |
| **`registrarUso`** | **2** | **~720 ms** |
| Audit hash-chain (Tier 0) | 2 | ~720 ms |
| **Total** | **~10** | **~3,65 s** |

**`SELECT 1` mede 353ms** — o MCP roda no CT 100, o MySQL é o do Hostinger. Não é código ineficiente; é distância. [W] vetou mexer nisso (é a [ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md)), então o caminho é **reduzir o número de queries**, não aproximá-las.

## `CACHE_DRIVER=array` — o multiplicador invisível

`array` é cache **por-processo**: descartado ao fim de cada request. Com ele, nenhum `Cache::` do MCP funcionava. Dois efeitos:

1. O Spatie está configurado para cachear permissões por **24h** e recebia **zero**.
2. O `Cache::lock('mcp:sync-memory')` — cujo comentário diz existir para impedir *"webhook + cron disparando juntos"* — **nunca barrou ninguém**.

Trocado para `file`: **5,2s → 3,8s** medido (5 amostras; a 1ª veio a 5,8s com cache frio).

⚠️ **Havia DOIS `.env` e o primeiro que editei não valia.** O compose usa `env_file: - .env` **relativo ao diretório do compose** (`docker/oimpresso-mcp/.env`), e env var de container **vence** o `.env` do Laravel. Só o segundo teve efeito — e exigiu `up -d --force-recreate`, porque `docker restart` **não relê** `env_file`. Backups `.env.bak-cache-2026-08-12` nos dois.

## Duas hipóteses minhas que a medição derrubou

- **"as 39 tools fazem query ao serem montadas"** — instanciam em **11 ms com ZERO queries**. Não era ali.
- **"o cache derruba para ~1,2-1,5s"** — errei; sobrou 3,8s. O Spatie cacheia o **catálogo global** de permissões, não as relações por usuário: 5 queries viraram 4, não 1.

## Os 2 PRs

- **#5682** — memoiza o gate `jana.mcp.use` (TTL 60s, `0` desliga, `esquecerPermissao()` invalida na hora). O TTL **é** o trade-off: janela em que permissão revogada segue valendo.
- **#5687** — throttle no `registrarUso` (janela 60s). Custo declarado: troca de IP atrasa até o fim da janela. É telemetria, não autorização — nenhuma decisão de acesso lê esses campos; **se um dia ler, reavaliar**.

Previsão: **~3,65s → ~1,83s** por request. Aí o audit ([ADR 0294](../decisions/0294-mcp-audit-log-hash-chain-tamper-evident.md)) vira ~40% do custo e **continua intocável** — é escrita com lock, tamper-evident.

## Armadilhas que custaram rodada (para a próxima sessão)

- **`curl` contra o CT 100 pode pegar a janela de recreate.** Os 3 endpoints voltaram `404` por ~20s durante a coleta do Infra Contract: o `self-update` recria o container a cada 15min. Não era regressão — refeito com `healthy`. **Medição naquele host precisa conferir `docker ps` antes.**
- **O gate `Infra Contract` exige cole LITERAL** (`curl -sv` ou `< HTTP/…`). `curl -s -w "%{http_code}"` traz o status certo e **não passa** — está no `grep` do workflow.
- **O log do PHPStan não mostra o erro no filtro óbvio.** A mensagem "REGRESSÃO vs baseline" aparece, os erros ficam ~300 linhas acima. Foram dois: `Cannot access property $id on class-string|object` (**corrigido**, id passou a vir de `$token->user_id`, Model tipado) e `env() fora de config/` (**baselineado 84→85→86**, porque config de **módulo** não conta como config directory e o arquivo já estava lá).
