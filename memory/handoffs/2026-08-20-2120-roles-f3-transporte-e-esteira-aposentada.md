---
date: "2026-08-20"
time: "21:20"
slug: roles-f3-transporte-e-esteira-aposentada
tldr: "A F3 de /roles (Blade -> Inertia) está bloqueada pelo MESMO teto de transporte do Backup, remedido aqui. Achado novo: a esteira cowork-inbox, que seria a rota alternativa sem transcrição, foi APOSENTADA em #3878 e desemboca no mesmo DesignSync — não é saída. O backend da área já está no main — o dono desse tema é o handoff irmão das 21:24 (#6074), que saiu 4 min depois desta sessão."
decided_by: [W]
prs: [5959, 5960, 5962, 5964, 5970, 5971]
next_steps:
  - "[CC] gerar a URL curta do sync/payload.json (rota do sync/README.md: curl -sL <URL> -o /tmp/payload.json) ou emitir sync/payload-NN.json de até 256 KiB — o applier junta lotes"
  - "Com o payload: aplicar-payload.mjs --dry --require-complete-shell, revisar o grafo, aplicar"
  - "Só então: ancora.mjs Roles/Index, criar-tela.mjs, e a F3"
related_adrs:
  - 0374-emenda-0315-espelho-cowork-e-rota-prevista
  - 0282-protocolo-v2-colapso-ratificacao
  - 0104-processo-mwart-canonico-unico-caminho
---

# Handoff — /roles: F3 bloqueada por transporte; a esteira alternativa está morta

## Por que este handoff existe

O [handoff das 11:38](2026-08-20-1138-backup-ondas-mergeadas-f3-bloqueada-por-transporte.md)
já catalogou o teto de transporte para o **Backup**. Este acrescenta duas coisas que ele não
tinha: a medição refeita para **/roles**, e o resultado da busca por uma rota alternativa —
que existia, e está aposentada. Registrar isso evita que a próxima sessão pague a mesma busca.

## O teto, remedido aqui (não herdado)

| Sonda | Resultado |
|---|---|
| `get_file` de `prototipo-ui/cowork/acessos/funcoes-page.jsx` | `truncated:false`, entregue **inline no contexto** |
| `get_file` de `prototipo-ui/cowork/acessos/funcoes-perms.jsx` | `truncated:false`, **inline** |
| `list_files` filtrado por `sync/` | só `payload.json` — **nenhum** `payload-NN.json` emitido |

Inline significa que a única forma de pôr aquilo em disco seria eu redigitar: transcrição, a
classe do STALE de 2026-08-11 e o motivo de o `aplicar-payload.mjs` existir. Logo, sem rota fiel.

## O achado: a esteira `cowork-inbox` foi aposentada

`docs/cowork-inbox-onda-d-core-handoff.md` descreve, **em tempo presente**, um publisher
Cowork→repo (`.github/workflows/cowork-inbox.yml` + `.github/scripts/cowork-inbox.py`) que
pousava `.md` e `resources/js/**` vindos do Cowork **sem passar pelo contexto do agente**. Lido
hoje, ele se apresenta como a saída exata para este bloqueio.

Ele não existe mais. Medido pelas três pernas, porque é claim de ausência:

| Perna | Resultado |
|---|---|
| `rg --hidden -g '!.git/**' "cowork-inbox" .github/` | 0 ocorrências |
| `git log --all` do path do workflow e do script | `92ca254d3c` — *"chore(cowork): aposenta esteira cowork-inbox (migrado p/ DesignSync) [CC] (#3878)"* |
| `gh workflow list --all` | 0 |

**Consequência:** a esteira foi migrada para o DesignSync, que é justamente onde está o teto —
então ela não é uma segunda rota, é a mesma. O doc em `docs/` recebeu um banner de lápide neste
PR para não voltar a induzir a busca.

## A rota que o lado do design declara

`sync/README.md` (lido no projeto Cowork) nomeia o caminho previsto, e não é o `get_file`:

```
Cowork ──gera──▶ sync/payload.json ──URL curta──▶ curl ──▶ aplicar-payload.mjs ──▶ prototipo-ui/cowork/
```

O próprio README declara o limite: a URL é curta (~1h, poucos fetches) e precisa ser gerada a
cada rodada — *"automático" = um comando seu, sem transcrição, não cron sem humano*. Portanto o
destrave depende do [CC] emitir a URL ou os lotes; não é acionável do lado do código.

## Grafo mínimo da tela (para quem for montar o pedido)

`funcoes-page.jsx` lê `window.FUNCOES_PERMS` (de `funcoes-perms.jsx`) **e** `window.AcessosDS`
(de `acessos-ds.jsx`). É a mesma armadilha do `backup-page.jsx`: sem o terceiro a tela não
degrada, quebra. Mais `acessos-page.css` / `usuarios-page.css` e o shell. A lista **não deve ser
curada à mão** — com o payload, o `--require-complete-shell` fecha o transitivo mecanicamente.

Também aguardam descida, em `cowork-inbox/acessos/` do projeto Cowork:
`PEDIDO-PARA-CODE.md`, `repo/resources/js/Pages/Roles/Index.charter.md`, `Index.casos.md`,
`repo/prototipo-ui/contrato/funcoes.contract.json`.

## Estado do código da área — o dono é o handoff irmão

⚠️ **Escrito às 21:20; o [handoff das 21:24](2026-08-20-2124-acessos-backend-mergeado-telas-bloqueadas-por-transporte.md) (#6074) saiu 4 minutos depois, de sessão paralela, e é o DONO deste tema** — ele cobre o mesmo
backend com **8** PRs (inclui #5972 e #6025, que eu não tinha) e traz o achado dos 3 testes que
passavam pelo motivo errado. Não repito os números aqui: dupla-fonte drifa (§5 2026-07-17).

O que os dois handoffs medem em comum, e bate: os PRs de backend do grupo Usuários estão no
`main`, e as telas estão bloqueadas pelo **mesmo** transporte do handoff das 11:38.

O que é só deste handoff: a **esteira `cowork-inbox` aposentada** (seção acima) e o banner de
lápide no fóssil que a anunciava viva — o irmão não toca nisso (medido: 0 ocorrências de
`esteira`/`3878`/`92ca254`/`cowork-inbox.yml` nele).

Estado da tela, medido contra `origin/main` fresco: `/roles` segue em Blade
(`resources/views/role/{index,create,edit}.blade.php` + `partials/module_permissions.blade.php`);
`resources/js/Pages/Roles/` não existe; `node prototipo-ui/ancora.mjs Roles/Index` responde
*"sem charter — NÃO invente âncora"*; `--preview-ds` sai **completo** (exit 0), então o DS runtime
está reposto e falta só o protótipo de `acessos/`.

## O ponto de projeto que o #5964 abre

O `PermissionCatalog` é o dono do catálogo no backend e tem teste (`RolePermissionCatalogTest`)
que quebra no drift contra as views. O protótipo carrega **o seu próprio** catálogo em
`funcoes-perms.jsx`, organizado em 8 domínios que colapsam os grupos do `/roles` legado em 5
formas de controle. Reconciliar as duas fontes — a tela consumindo o catálogo do backend em vez
de hardcodar o seu — é o trabalho central da F3, e é decisão a tomar **antes** de escrever a tela.

## O que NÃO foi feito, e por quê

- **Não criei o trio** com `criar-tela.mjs`: o charter e os casos do [CC] existem e vão descer;
  um stub agora colidiria com eles e carimbaria `related_prototype` sem âncora resolvida
  (§5 2026-08-10).
- **Não gerei a tela ancorado só no DS canon**: a [ADR 0282](../decisions/0282-protocolo-v2-colapso-ratificacao.md)
  manda gerar **quando falta a fonte**. Aqui a fonte existe, está identificada por ID e é
  específica (rail de domínios, 5 formas de controle, barra de diferenças `+N −M`) — gerar por
  cima produziria uma segunda fonte divergente, não uma tela.

## Estado MCP no momento do fechamento

⚠️ **Sem snapshot de MCP.** Nenhuma tool do servidor oimpresso está disponível nesta sessão
(`ToolSearch` por `cycles-active`/`my-work`/`sessions-recent`/`whats-active` retornou vazio) —
worktree filho sem MCP conectado, caso em que o fallback filesystem é o caminho previsto em
[`how-trabalhar.md`](../how-trabalhar.md) §Fallback. O hook de `SessionStart` entregou o brief
em cache (#546), que **não** substitui `cycles-active`/`my-work`.

Registrar a ausência é deliberado: inventar um snapshot seria pior. Quem retomar deve rodar
`brief-fetch` + `whats-active` antes de assumir estado de cycle/task — e antes de mexer em
`prototipo-ui/cowork/`, porque a sessão das 11:38 trabalhava no mesmo transporte.

O que substituiu o MCP como fonte de estado aqui: `gh pr view` para os 6 PRs, `git log --all`
para a esteira, `DesignSync.list_files`/`get_file` para o projeto Cowork, e os próprios scripts
do protocolo (`ancora.mjs`, `--preview-ds`) para o estado do espelho.
