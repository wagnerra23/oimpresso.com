---
date: "2026-09-21"
hour: "18:55 BRT"
topic: "Incidente — auto-merge ligando sozinho, em lote, com o token do [W]"
authors: [C]
outcomes:
  - "Dois PRs entraram em main sem aprovacao (#7637, #7640) e foram revertidos no #7654"
  - "Seis suspeitos eliminados por medicao; causa NAO identificada"
  - "Evento chama-se auto_squash_enabled no timeline, nao auto_merge_enabled"
  - "Draft e a unica mitigacao que nao depende do diagnostico"
prs:
  - 7654
  - 7655
---

# Incidente 2026-09-21 — auto-merge ligando sozinho, em lote, com o token do [W]


## ⚠️ ERRATA 2026-09-22 — GitHub Apps ELIMINADOS, e o numero era 18, nao "13+"

> O corpo abaixo fica como esta (fato do dia). Esta errata corrige duas coisas e
> **fecha o caminho que o registro original declarava como principal pendencia**.

### 1. GitHub Apps estao ELIMINADOS (o registro original os deixou em aberto)

O corpo diz que `repos/OWNER/REPO/installation` devolve **401** e que a lista de Apps
"nunca foi vista". Verdade — mas a pergunta era **outra**, e tinha resposta por um
caminho que eu nao tentei: **eventos de timeline carregam `performed_via_github_app`**.

```bash
gh api --paginate "repos/OWNER/REPO/issues/<N>/timeline" --jq '.[] | select(.event=="auto_squash_enabled") | .performed_via_github_app.slug'
```

**Medido em 2026-09-22, nos 9 PRs envolvidos: 18 ativacoes, `performed_via_github_app`
= `null` em 18 de 18.**

**Com CONTROLE POSITIVO** (sem ele o resultado seria cego): o campo **e** preenchido
neste repo — comentarios do `github-actions` em #7654, #7655 e #7664 trazem
`performed_via_github_app: github-actions`. A sonda discrimina.

Logo: **nenhuma ativacao passou por GitHub App.** App instalado, webhook e GitHub Action
registrariam a atribuicao; as 18 nao tem. Isso vale como eliminacao — e **nao** como
prova de que Apps nao existem no repo: `user/installations` da **403** com o token
OAuth do `gh`, que nao e autorizado para App.

### 2. O numero: 18 ativacoes, das quais 16 espontaneas

O corpo diz "13+ vezes" — estimativa repetida, nunca contada. Contado:

| PR | ativacoes |
|---|---|
| #7637 | 5 |
| #7641 | 4 |
| #7639 | 4 |
| #7640 · #7646 · #7659 · #7654 · #7664 | 1 cada |

**18 no total.** Duas sao legitimas e minhas (#7654 as 18:10:26 e #7664 as 18:56:37,
ligadas com autorizacao do [W]). **16 foram espontaneas.**

### 3. O que sobra, agora que Apps cairam

As 18 vieram de **token de usuario sem App associado**. Isso e consistente com **o app
desktop ou uma sessao remota usando a credencial OAuth do [W]**, e inconsistente com
App, webhook ou Action.

**Caminho unico que resta:** sessoes **Remote Control e cloud** — os transcripts delas
nao estao na maquina local, e havia varias vivas no repo. Nao foi possivel varrer daqui.

### 4. Licao de metodo desta errata

O registro original tratou o **401** como "inalcancavel" e parou ali. O 401 era de **um
endpoint**; a pergunta tinha outro caminho. **Impossibilidade de medir declarada em canon
vira instrucao de desistencia para quem ler depois** — e quase funcionou contra o proprio
autor, um dia depois.

## TL;DR

Em 2026-09-21 o auto-merge foi ativado **13+ vezes** em PRs da Jana com o token
do [W], sem acao dele — e **dois PRs entraram em `main` sem aprovacao** (#7637,
#7640), revertidos no #7654.

**A causa NAO foi identificada.** Seis suspeitos foram eliminados por medicao,
incluindo o `"Auto-fix pull requests"` do app — que estava **desativado 20 min**
antes do evento decisivo. O mecanismo age em **lote sobre uma lista** (3 PRs em
4s, 2s de intervalo), o que descarta gatilhos por-PR.

Duas notas tecnicas que custaram caro: o evento no timeline chama-se
**`auto_squash_enabled`** (nao `auto_merge_enabled`), e **`actor=wagnerra23` nao
distingue** o [W], o app e as sessoes — e o mesmo token.

Mitigacao que funciona sem depender do diagnostico: **`draft`**.

Este registro existe para a proxima investigacao comecar daqui, em vez de refazer
as mesmas seis hipoteses — refaze-las custou uma tarde.

> **Estado: NÃO RESOLVIDO.** A causa não foi identificada. Seis suspeitos foram
> eliminados **por medição** (não por opinião) e o perfil do mecanismo está
> caracterizado. Este registro existe para que a próxima investigação **comece de
> onde esta parou** em vez de refazer as mesmas seis hipóteses — refazê-las custou
> uma tarde.

## O fato

Entre **16:50Z e 18:35Z** de 2026-09-21, o auto-merge foi **ativado 13+ vezes** em
PRs da Jana, sempre com `actor=wagnerra23`. O [W] confirmou, em chat, que **não foi
ele**. Desligar não resolvia: em dois casos medidos, religou **5min11s** e
**5min20s** depois de ser desligado.

**Dois PRs entraram em `main` sem aprovação** antes de a proteção por `draft` ser
aplicada — `#7640` (17:15:15Z) e `#7637` (17:42:34Z). Ambos revertidos no `#7654`
por decisão do [W].

## O evento que caracteriza o mecanismo

```
18:35:26Z  #7646  auto_squash_enabled
18:35:28Z  #7639  auto_squash_enabled
18:35:30Z  #7659  auto_squash_enabled
```

**Três PRs em 4 segundos, espaçados por 2s** — exatamente os 3 que uma sessão tinha
abertos. Isso é **varredura em lote sobre uma lista**, não gatilho por PR. O
espaçamento regular é assinatura de **script com rate-limit de API**.

Os dois primeiros **mergearam sozinhos**: `#7639` às 18:47:21Z, `#7646` às
18:50:23Z. Recibo de que não foi a sessão: o comando dela chegou depois e o GitHub
respondeu `! Pull request was already merged`.

Entre ativar e mergear passaram **12-15 min** com os PRs em `BLOCKED` esperando CI
— logo a ativação **não** foi disparada por "ficou mergeável".

## Suspeitos ELIMINADOS, com a medição ao lado

| suspeito | como foi eliminado |
|---|---|
| **O [W] interagindo** | 0 de 13 ativações dentro de 30s de uma mensagem real dele; a mais próxima a 1min44s. ⚠️ Ver "erro de dado" abaixo — a 1ª medição estava contaminada |
| **Sessões Claude (CLI)** | zero comandos `gh pr merge --auto` executados após 16:00Z, contando só blocos `tool_use` (nunca prosa) |
| **Tool `set_auto_merge` do app** | zero usos no dia inteiro |
| **`bind_pr` / `set_monitor`** | últimos às 12:10Z, fora de toda janela |
| **`gh` alias / extensão / config** | limpos (só `co: pr checkout`) |
| **Workflows do repo** | os 7 que usam `--auto` agem só em PRs que eles próprios criam (`steps.cpr.outputs.pull-request-number`) |
| **Webhook `/api/mcp/sync-memory`** | processa `pull_request`, mas só para ligar task↔PR; sem chamada de auto-merge |
| **"Auto-fix pull requests" do app** | **desativado pelo [W] ~18:14Z** — e as 3 ativações em lote ocorreram **18:35Z**, 20 min depois |
| **Loop rodado por alguma sessão** | varredura 18:34-18:36Z: 5 comandos, **todos posteriores** às ativações |

## Nota técnica que custou caro descobrir

**O evento no timeline do GitHub chama-se `auto_squash_enabled`, NÃO
`auto_merge_enabled`.** Quem filtrar pelo segundo recebe vazio e conclui, errado,
que "o GitHub não registra ativação" — foi o que eu conclui e afirmei. Só
`auto_merge_disabled` usa o prefixo `auto_merge`.

```bash
gh api --paginate "repos/OWNER/REPO/issues/<N>/timeline" \
  --jq '.[] | select(.event != null) | select(.event|test("auto_")) | "\(.created_at)  \(.event)  \(.actor.login)"'
```

E: **`actor=wagnerra23` não distingue ninguém** — é o mesmo token usado pelo [W],
pelo app e pelas sessões. Essa coluna não serve como prova de autoria em nenhuma
direção.

## O que NÃO foi investigado (o caminho que sobra)

1. **Sessões Remote Control e cloud** — os transcripts não estão nesta máquina.
   Havia várias vivas no repo.
2. **GitHub Apps instalados** — `gh api repos/OWNER/REPO/installation` devolve
   **401** (precisa de JWT de App). A lista de integrações com acesso ao repo
   nunca foi vista.
3. **Se o [W] agiu fora do chat** (botão no GitHub ou no app) por volta de 18:35 —
   ele não interagiu *no chat*, mas isso não cobre ação direta na UI.

## Mitigação que funcionou

**Converter o PR para `draft`.** Draft não mergeia nem com auto-merge ligado, o CI
continua rodando, e reverte com um clique. É a única proteção que **não depende de
acertar o diagnóstico** — desligar o auto-merge é corrida perdida contra algo que
religa em minutos.

⚠️ **Mas vigia com auto-reversão é perigoso quando a ordem muda:** o vigia que eu
armei reconvertia para draft ao detectar religamento. Quando o [W] mandou mergear
os PRs, esse vigia teria **desfeito a ordem dele automaticamente**, sem ninguém
perceber. Foi parado a tempo. Proteção que sobrevive ao próprio propósito vira
sabotagem silenciosa.

## Lições de método (erros desta investigação, para não se repetirem)

1. **Dado contaminado na origem.** A 1ª correlação "ativações × mensagens do [W]"
   usou 13 timestamps — e **só 2 eram mensagens dele**. Cross-session messages,
   system-reminders e task-notifications aparecem como `type:user` no transcript.
   Filtrar por `type:user` sem excluir esses produz a conclusão **invertida**.
2. **Correlação sem baseline de acaso.** "12 de 13 ativações dentro de 2 min de uma
   interação" parece esmagador e **é exatamente o esperado por acaso** — com uma
   mensagem a cada ~3 min, uma janela de ±2 min cobre 92% do tempo.
3. **Tabela recortada.** A 1ª versão mostrava 7 das 13 linhas, omitindo justamente
   as que não ajudavam. Série completa ou nada.
4. **Periodicidade a partir de um ponto.** "5min11s" virou "cron de 5 minutos" com
   **uma** amostra. O segundo evento derrubou.
5. **Vigia mudo.** O 1º vigia tinha `|| echo ""`: se o `gh` falhasse, a saída vazia
   seria lida como "não religou". Corrigido com ramo explícito `SONDA FALHOU`.
6. **Teste com duas variáveis.** O teste "desativei o Auto-fix, testa de novo" mudou
   Auto-fix **e** interação ao mesmo tempo — e ainda por cima rodou numa sessão onde
   o Auto-fix **continuava ligado**. Inconclusivo por construção.

Nas correções 1, 2 e 4 quem derrubou foram **sessões irmãs medindo**, não eu.

## Custo medido

- 2 PRs em `main` sem aprovação (`#7637`, `#7640`) → revertidos no `#7654`
- ~1 tarde de investigação com 6 hipóteses derrubadas
- 1 vazamento **Tier 0** quase entrando junto (`#7641`, valor em `R$` em mensagem de
  commit, com `squash_merge_commit_message: COMMIT_MESSAGES`) — barrado pelo `draft`
  e substituído pela branch limpa no `#7655`

## Se isto voltar

1. Rode o timeline com **`auto_squash_enabled`** (não `auto_merge`).
2. Confira se as ativações são **em lote** (vários PRs, segundos de intervalo) — se
   forem, o alvo é algo que **enumera uma lista**, não um gatilho por PR.
3. Proteja com **draft**, não com `--disable-auto`.
4. Ataque primeiro o que ficou sem investigar: **GitHub Apps** (via API autenticada
   de App) e **sessões remotas**.
