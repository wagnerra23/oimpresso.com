---
date: "2026-09-16"
topic: "Fecha os 3 next_steps do handoff de 11:00 — redacao no ingest vira US, pares de token decididos por medicao, 24 settings.local.json migrados. E 5 erros meus da mesma familia."
authors: [C]
prs: [7395, 7400, 7412, 7415, 7417, 7423]
outcomes:
  - "US-FORJA-011 aberta — redacao de segredo em /api/cc/ingest, o chokepoint que a LC-35 nomeou e sem o qual o cc-watcher nao volta"
  - "Pares de token DECIDIDOS por medicao: #10 vs #30 e #11 vs #21 sao clientes concorrentes; nenhum se revoga"
  - "#22 e #23 revogados a pedido do [W]; 9 -> 7 vivos"
  - "24 settings.local.json migrados de token literal MORTO para forma de referencia; residuo a zero"
  - "2 recibos no ledger (LC-08 n+19 mergeado, n+20 novo) sem lapide nova — a dona do limite ja existia"
  - "Comando fantasma `copiloto:mcp:gerar-token` corrigido no MEMORY_TEAM_ONBOARDING (nunca existiu; 3 ocorrencias, todas doc)"
related_adrs:
  - 0057-tela-team-admin-regras-governanca-tokens-mcp
---

# 2026-09-16 — Os pares, a revogacao, e o dia em que medi errado cinco vezes

> Handoff: [`2026-09-16-1445`](../handoffs/2026-09-16-1445-token-pares-revogacao-e-o-dia-de-medir-errado.md).
> Este log carrega o detalhe; la fica o estado pro proximo.

## TL;DR

Sessao abriu pra abrir e mergear **um** PR pronto e fechou os **3 `next_steps`** do handoff de
11:00, em **6 PRs**. Dois deles **mudaram de forma quando medidos**: *"cria a task MCP"* nao tem
caminho por tool (a `tasks-create` deixou de escrever por desenho, US-COPI-149 `done`), e os *"24
`settings.local.json` com o token literal"* carregavam um token **revogado** — nao era credencial
viva. Os pares de token foram decididos por medicao (concorrentes; nenhum se revoga) e o #22/#23
revogados a pedido do [W]. **Errei 5 vezes**, todas a mesma familia: medir a coisa errada e quase
publicar; **2 chegaram ao [W]**.

## O pedido, e onde ele mudou de forma duas vezes

A sessao abriu pra **abrir e mergear um PR ja pronto** ([#7395](https://github.com/wagnerra23/oimpresso.com/pull/7395)),
e virou o fechamento dos 3 `next_steps` do handoff de 11:00. Dois deles mudaram de forma quando
medidos:

**"Cria a task MCP da redacao no ingest".** Nao ha caminho por tool, e o motivo principal **nao** e o
MCP estar fora. A **US-COPI-149** esta `done` desde ontem e o conserto dela foi *tirar* a escrita:
`createCanonical()` nao escreve mais no SPEC (`written` sempre `false`) e a tool deixou de afirmar
durabilidade. Logo **mesmo conectado, `tasks-create` nao cria nada duravel** — gera id e texto; quem
persiste e o git. O precedente esta na propria US (as 4 tasks de ontem *"acabaram escritas a mao no
SPEC de Infra"*). O `next_step` anterior atribuia o bloqueio so ao MCP; estava incompleto.

Dono de codigo do endpoint: **`Modules/Forja`** (`CcIngestController`, `CcIngestRequest`, rota
`Http/routes.php:177`). `MEM-CC-1` so aparece nos SPECs como *dependencia* da US-INFRA-006, e
`Modules/Forja/SCOPE.md` nao existe.

**"Os 24 settings.local.json residuais".** Os 24 carregavam **um unico** token, identificado por
`sha256_token`: o **#4**, revogado E soft-deleted em 2026-09-15 19:01:12 — o instante da rotacao
#4 -> #30. **Nao era credencial viva**, era string morta que devolve 401. O handoff dizia "seguem
com o token literal", verdade literal que soa muito pior do que e.

## O que a medicao mostrou no eixo tokens

**Discriminador de par.** Nao e IP (o tag dominante cobre **11 de 16** tokens — rede compartilhada
entre Wagner e Maiara) nem `user_agent` (`node` nos quatro). E o **`endpoint` do `mcp_audit_log`**:

| | handshake-only (Claude Desktop) | trabalha |
|---|---|---|
| Wagner (user 1) | **#10** — 8.992 req, 0 `tools/call` | **#30** — 615 req, `tools/call` real |
| Maiara (user 74) | **#21**, o mais NOVO | **#11**, o mais VELHO |

#10 e #30 usados com **84 segundos de diferenca**. E a armadilha e o par da Maiara: *"revoga o
antigo"* mataria o token **util** dela.

**Revogacao (#22, #23).** Por `McpTokenIssuer::revoke($id, 1)` — `revoked_at`/`revoked_by` +
soft-delete, linha preservada, reversivel. Evidencia antes: #22 parado 20 dias (940 chamadas, todas
ate 27/08); #23 com **2 chamadas na vida**, 5 segundos de intervalo em 17/06.

**Migracao dos 24.** Cirurgica (so o valor do `Authorization`), com backup e teste de identidade:
`permissions` preservado em **24/24**, JSON valido em 24/24. Mas o quadro honesto e que **23 dos 24
nao voltam a funcionar hoje** — hasheei o hook e achei **3 versoes distintas**, e so **1** expande
`${ENV}`. A migracao nunca e pior (token morto tambem falha) e passa a funcionar sozinha quando o
worktree atualizar o hook.

## Cinco erros meus, todos a mesma familia

Nenhum instrumento avisou — todos devolveram numero plausivel. O que pegou foi rodar de novo
perguntando outra coisa.

1. **Arvore errada** — rodei o predicado do Check L na minha branch, 3 commits atras, onde a ADR nem
   existia; quase reportei "o detector tem falso-positivo". E o `rec` n+19 que eu tinha mergeado
   naquela manha.
2. **Janela lida como recencia** — `30d=188` do #22 eram todas de UM dia, 20 dias atras. Publiquei
   como "contradiz o que te reportei". Virou o `rec` n+20.
3. **Presenca em vez de comportamento** — meu detector de `${ENV}` casava `process.env` em qualquer
   lugar. Refiz por hash + bite-test e o "24 sabem" virou "1 de 3 versoes".
4. **`grep -P` sem suporte no locale** — fabricou uma lista assustadora de "47 required sem
   conclusao". Era falha do instrumento, nao veredito.
5. **"7 PRs"** no relatorio final; contados, sao **6**.

Sobre o n+20: **nao escrevi lapide nova**. A dona do limite ja existe — §5 **2026-08-13**, item (d)
(*"antes de usar a saida de uma sonda, enunciar a pergunta que a sonda de fato responde"*) com o
corolario *"a propria certeza e o gatilho pra checar"*. Escrever outra duplicaria regua consolidada
(§5 2026-07-09).

## O vermelho herdado que travou o #7400 duas vezes

`main` esteve com a **ADR 0401 `proposto`** citada por codigo que roda -> `memory-health` (required)
vermelho na **arvore inteira**, bloqueando todo PR aberto. Medido: `origin/main` puro dava
`1 🔴 fail`; meu diff nao tinha ADR nenhuma.

Duas licoes operacionais: **(a)** o **re-run nao recompoe a base** — voltou com a mesma falha apesar
de `main` ja consertado, porque replica o payload do evento original; so `git merge origin/main`
resolveu. **(b)** o custo do push ficou medido: **117 -> 84 lanes**, as 33 advisory de `opened` nao
re-rodam, entao o `0 fail` delas e do SHA anterior.

## Achado adjacente

`MEMORY_TEAM_ONBOARDING.md:88` mandava `php artisan copiloto:mcp:gerar-token --user-email=...`. Esse
comando **nunca existiu** — varredura repo-inteiro com controle positivo: **3 ocorrencias, todas
documentacao, zero implementacao**; o nome nasceu de um checklist `- [ ]` nao-feito da ADR 0055. O
real e `mcp:token:gerar --user=<ID>`. Corrigido, junto com *"Token vive ate revogar manualmente /
Sem TTL automatico"*, que caducou com o #7388.

## Metodo

Trabalhei o eixo tokens **so por id e metadado**; o IP nunca saiu em claro, so como tag derivada, e
o token nunca. E a classe que a LC-35 documentou naquela mesma manha.
