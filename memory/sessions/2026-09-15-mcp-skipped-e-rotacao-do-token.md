---
date: "2026-09-15"
topic: "O MCP não estava desligado — estava sendo PULADO por falta de `type`; as 3 travas em série, o `tasks-create` que perde tasks no deploy, e a rotação do token"
authors: [C]
prs: [7366, 7369, 7372, 7378, 7379]
outcomes:
  - "Causa achada pelo runtime (session_connectors_status não listava o servidor em estado nenhum) e confirmada pelo recibo literal do `claude mcp list`: Skipped por `url` sem `type`"
  - "3 travas em série consertadas — type, approval nominal e o token via ambiente; medido que `mcpServers` do settings.local.json é IGNORADO pelo cliente e serve só de cofre pros hooks"
  - "US-COPI-149 aberta: `tasks-create` responde ✅ e o dado morre no próximo pull do servidor (provado com 3 leituras + controle negativo)"
  - "Token rotacionado por `teammcp:token:rotate` (revoke+issue atômico) e 7 revogados: 9 → 3 vivos; a contagem inicial de 10 era minha, por medir ignorando SoftDeletes"
---

## TL;DR

O MCP não estava desligado: o cliente **2.1.257** passou a exigir `"type"` em entrada `mcpServers` com `url`, e sem ele a entrada sai como **`Skipped`** — some da sessão inteira, nem `failed` nem `pending`. Consertar revelou mais duas travas (approval e OAuth/419), e a terceira revelou que o `settings.local.json` não alimenta o cliente há tempos. Depois: o `tasks-create` foi flagrado perdendo tasks no deploy, e o token foi rotacionado com 9 → 3 vivos.

# O MCP não estava desligado — estava sendo pulado

## O diagnóstico, na ordem em que as medições caíram

A pergunta era *"porque o MCP está desligado?"*. Antes de responder, medi o que **não** era:

| hipótese | medição |
|---|---|
| servidor fora do ar | `tools/list` → **200**, com `brief-fetch` |
| token expirado | mesmo request com o Bearer → **200** |
| rede/DNS | **401 em 1,3s** sem token |
| config ausente | as 4 definições existiam e parseavam |

O oráculo decisivo foi o runtime, não o JSON: `session_connectors_status` listava `laravel-boost` (kind `project`, failed) e **não listava `oimpresso` em estado nenhum**. Servidor que não aparece nem como `failed` não foi sequer registrado.

`claude mcp list` deu o recibo literal: `Skipped — has a "url" but no "type"`.

## As três travas

1. **`type` ausente** → `Skipped`. `laravel-boost` usa `command` (stdio), não precisa — por isso era o único visível.
2. **`Pending approval`** → `enabledMcpjsonServers=[]` em 852/852 projects.
3. **`419 CSRF`** → o cliente tentou OAuth porque a entrada viva não tinha headers.

A (3) exigiu um experimento: entrada de teste em `settings.local.json` → **não apareceu**. `mcpServers` ali é ignorado pelo cliente. O arquivo segue necessário como cofre dos hooks — varredura contada mostrou 3 consumidores.

## Por que ficou invisível

`Skipped` ≠ `failed`. E o brief continuava chegando, porque o hook usa `curl` sem passar pelo cliente MCP. Silêncio indistinguível de saúde — LC-13 na camada de config.

## O bug do `tasks-create`

Ao criar as 4 tasks de aviso, a tool respondeu `✅ criada` para todas. Nenhuma sobreviveu:

- `tasks-detail US-INFRA-049` → "não encontrada", **idêntico** ao controle negativo (`US-INFRA-9999`)
- SPEC versionado: 0 de 4
- checkout do servidor (`/var/www/html`): tinha as 5, não-commitadas

Entre a 1ª e a 2ª leitura o servidor puxou o main (`HEAD → 84af54af3`) e o SPEC caiu de **89.531 → 82.068 bytes**. O pull apagou. O ramo `$written=true` escreve onde ninguém pode salvar. Virou US-COPI-149.

## Rotação

`teammcp:token:rotate` (revoke+issue atômico) em vez de `mcp:token:gerar` (só emite). #4 → #30. Depois: 5 órfãos nunca usados + #5 + #24. **9 → 3 vivos.**

Antes de revogar o #24 conferi se o workflow `mcp-drift-sentinel` (roda a cada 30min, success hoje) dependia dele. **Não depende** — o `MCP_DRIFT_TOKEN` é `openssl rand -hex 32` no `.env` do host, não um registro de `mcp_tokens`. Conferido antes, não depois.

## Os erros que cometi

- contei ativos com query builder cru (ignora SoftDeletes) → 10 virou 9 na errata
- olhei `/app` no container e quase conclui que a máquina não existia — o checkout é `/var/www/html`
- `grep -c '^-[^-]'` leu 4 remoções onde havia 54 (linhas `- ` viram `-- `)
- imprimi "(vazio = nenhuma falha)" com o comando quebrado, na conferência final

O acerto do dia — achar o `teammcp:token:rotate` — veio de desconfiar de um `head -6`. A mesma disciplina que os quatro erros violaram.

## Refs

PRs [#7366](https://github.com/wagnerra23/oimpresso.com/pull/7366) · [#7369](https://github.com/wagnerra23/oimpresso.com/pull/7369) · [#7372](https://github.com/wagnerra23/oimpresso.com/pull/7372) · [#7378](https://github.com/wagnerra23/oimpresso.com/pull/7378) · [#7379](https://github.com/wagnerra23/oimpresso.com/pull/7379)
