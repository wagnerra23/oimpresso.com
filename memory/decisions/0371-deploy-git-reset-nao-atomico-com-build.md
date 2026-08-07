---
slug: 0371-deploy-git-reset-nao-atomico-com-build
number: 371
title: "Deploy Hostinger — o `git reset` no servidor não é atômico com o build: sob rajada de merge + runner saturado, produção fica com fonte novo e vendor/build velho"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-08-07"
module: infra
quarter: 2026-Q3
tags: [infra, deploy, hostinger, concurrency, classmap, vite, atomicidade, incidente, drift]
supersedes: []
superseded_by: []
related: [0062-separacao-runtime-hostinger-ct100, 0269-opcache-reset-token-self-heal, 0216-governance-audit-diff-only-pr-scan]
---

# ADR 0371 — O `git reset` do deploy não é atômico com o build

## Contexto — o incidente de 2026-08-07

Entre **17:38 e 20:06** de 2026-08-07, produção rodou com **código-fonte de até 10 commits à frente** do `vendor/` e do `public/build-inertia/`. O sintoma observado foi `500 Server Error` em `/sells/create-v3` (tela nova do [PR #5356](https://github.com/wagnerra23/oimpresso.com/pull/5356)), com o log dizendo:

```
live.ERROR: Target class [App\Http\Controllers\SellsV3Controller] does not exist
ReflectionException: Class "App\Http\Controllers\SellsV3Controller" does not exist
route_name: sells.create-v3 · business_id: 1 · user_id: 635
```

O arquivo **estava no disco** (3.555 B). O `vendor/composer/autoload_classmap.php` tinha **0 ocorrências** da classe. O manifest do Vite era de **17:45** e não continha a página.

### As três medições que fecharam o diagnóstico

Cada uma com **controle positivo**, para um zero não passar por medição furada:

| medição | valor | controle positivo |
|---|---|---|
| `grep -c SellsV3Controller vendor/composer/autoload_classmap.php` | **0** | `SellController` = 4 |
| `grep -c CreateV3 public/build-inertia/manifest.json` | **0** | `Sells/Create.tsx` = 3 |
| `/sells/create-v3` autenticado | **500** | rota falsa irmã = **404** |

O 3º par é o que separa *"a rota não existe"* de *"a rota existe e quebra"* — sem ele, o 500 seria ambíguo.

## O mecanismo (é isto que a ADR registra)

O `deploy.yml` executa, **em passos separados do mesmo job**:

```
git reset --hard origin/main     ← fonte avança AQUI
composer install / dump-autoload ← ...
publica bundles (tar → swap)     ← ...e o build chega só AQUI
```

O `reflog` do servidor prova que o 1º passo rodou **9 vezes** entre 18:26 e 20:06 — inclusive em runs que terminaram `cancelled`:

```
28ab6ea83 HEAD@{20:06}: reset: moving to origin/main
f5aeb5e60 HEAD@{20:00}: reset: moving to origin/main
61c770ec0 HEAD@{20:00}: reset: moving to origin/main
f72e3568e HEAD@{19:44}: reset: moving to origin/main
cf72c5abe HEAD@{19:41}: reset: moving to origin/main
26bee4a61 HEAD@{19:34}: reset: moving to origin/main
1302db0ed HEAD@{18:51}: reset: moving to origin/main
c72aa3b35 HEAD@{18:42}: reset: moving to origin/main
5e5c07a95 HEAD@{18:26}: reset: moving to origin/main
```

E o histórico de deploys mostra que **nenhum** chegou ao fim depois das 17:38:

| hora | commit | resultado |
|---|---|---|
| 17:38 | `0c3146e094` | ✅ **último sucesso** |
| 18:11 | `ca7dddb386` | ❌ falha — `ssh: connect to host: Connection timed out` |
| 18:21 | `127446882c` | ⊘ cancelado |
| 18:25 | `df303aa734` | ⊘ cancelado |
| 18:26 | `5e5c07a950` | ⊘ cancelado |
| 18:42 | `c72aa3b353` | ⊘ cancelado |
| 19:04 | `1302db0edf` | ⊘ cancelado (build ✅, job Hostinger cancelado) |
| 20:00 | `61c770ec0c` | ⊘ cancelado |

### A condição que produz isso

Três fatores, e é a **conjunção** que morde:

1. **`concurrency: deploy-production` com `cancel-in-progress: false`.** O GitHub mantém **um só** run em espera por grupo e **cancela** os anteriores quando chega um novo.
2. **Runners saturados.** No pico do incidente a fila do repo tinha **527 runs** aguardando (drenou 527 → 474 → 339 → 296 ao longo de ~40 min). Um deploy fica `queued` por 30+ min antes de pegar runner.
3. **Ritmo de merge alto.** 19 merges em 6h — um a cada ~19 min, mais rápido que o tempo de espera do deploy.

```
deploy fica queued 30+ min  +  merge novo a cada ~19 min  +  1 slot de espera
   ⇒ todo deploy é superado antes de conseguir rodar
   ⇒ mas o reset já rodou nos que chegaram a iniciar
```

Não é azar nem erro de quem mergeou. É o **regime**: enquanto os três fatores coexistirem, o estado "fonte à frente de vendor/build" é o estado de equilíbrio, não a exceção.

### Por que ninguém viu antes

Nenhum gate de CI enxerga isto — os **40 required estavam verdes** com a tela quebrada em produção. O CI mede o repositório; este defeito vive no servidor.

Pior: o `## Infra Contract` do PR prometia como evidência de sucesso `curl /sells/create-v3 → 302 (redirect pro login)`. Medido: **uma rota inexistente sob `/sells/` devolve o mesmo 302**. A receita de smoke do próprio PR **passaria verde no estado quebrado** — consequência compatível, não prova (§"Claim sem evidência").

## Decisão proposta

**Tornar a publicação atômica: o `git reset` deixa de ser um passo solto e passa a valer só quando o build correspondente estiver publicado.**

Três desenhos possíveis — a escolha é de [W]:

| # | desenho | efeito | custo |
|---|---|---|---|
| **A** | **Release dir + symlink swap** — build num diretório novo (`releases/<sha>`), `current` só aponta pra ele no fim | cancelamento no meio **nunca** deixa prod inconsistente; rollback é trocar o symlink | maior — muda a topologia do webroot |
| **B** | **Adiar o reset** — baixar o artefato e só então `reset` + `composer` + swap, tudo no mesmo bloco `ssh_exec` | cancelamento antes do bloco não mexe em prod | pequeno — reordenar passos existentes |
| **C** | **Sentinela de coerência** — check que compara `HEAD` do servidor com o sha que gerou o `manifest.json` e alarma na divergência | não previne, mas **avisa** em vez de 2h de silêncio | pequeno — mas é detecção, não conserto |

Recomendação: **B agora** (barato, resolve a janela de inconsistência) **+ C** como rede (o incidente durou 2h sem ninguém saber). **A** fica como evolução se B não bastar.

⚠️ **B e C não substituem um ao outro.** B fecha a janela; C detecta quando algo fora do previsto (falha de SSH, intervenção manual) recriar a divergência.

## Consequências

- **Positivas:** produção deixa de poder ficar com fonte à frente do build; o modo de falha "classe existe no disco e é invisível ao autoloader" desaparece; rollback fica trivial (A) ou barato (B).
- **Negativas:** B aumenta o tempo de janela do bloco SSH único — e o SSH da Hostinger é **flaky** (falhou 1× hoje, `ConnectTimeout`). Um bloco maior tem mais superfície para cair no meio. Isso precisa ser pesado; talvez exija retry por bloco.
- **Não resolvido por esta ADR:** a saturação de runner (fator 2). Reduzir os ~117 checks por PR é outro tema, com dono próprio ([ADR 0314](0314-poda-gates-onda-2-lei-fusoes.md) / [0271](0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) já estão na linha de subtração de gates).

## Drift declarado — a intervenção manual de 2026-08-07

Com a fila travada e produção em 500, [W] autorizou conserto manual. O que foi feito, **espelhando o `deploy.yml` passo a passo**:

1. `composer dump-autoload -o --classmap-authoritative` (flags idênticas às do workflow) → classmap `SellsV3` **0 → 1**, 20.028 classes.
2. Swap do `build-inertia` usando o **artefato `vite-build` do próprio CI** (run [31209886386](https://github.com/wagnerra23/oimpresso.com/actions/runs/31209886386), job `Build Vite bundles` verde) → manifest `CreateV3` **0 → 5**, chunk `assets/CreateV3-D8VwI9H6.js` (8.191 B) presente.
3. `config:clear` · `route:clear` · `view:clear` · `config:cache` · `package:discover` → boot smoke `artisan about` OK.

**Nenhum arquivo foi forjado à mão** — o bundle é o que o CI construiu a partir do `main`.

**Ressalvas honestas:**
- O artefato é do `1302db0e`; o `main` já estava em `cf72c5abe`. Faltou o rename do [#5401](https://github.com/wagnerra23/oimpresso.com/pull/5401) ("telas dizem Jana, não Copiloto") — visível na aba de `/ia`, que seguia dizendo *"Copiloto"*. Não é dano novo (o build de 17:45 também dizia), e o primeiro deploy canônico bem-sucedido zera.
- Rollback preservado em `public/build-inertia.pre-manual-1786133205`.
- Ficou um `public/.deploy_manual` com o `css` (o hook `block-destructive` barrou o `rm -r` e não foi forçado).
- **Sem maintenance mode**, deliberadamente: não havia migrate nem `composer install` (lock inalterado), o swap é atômico (`mv`), e o SSH já tinha caído 2× no dia — deixar o site em `down` com SSH instável era o risco maior.

Regressão adjacente verificada no browser após a intervenção: `/sells`, `/pos/create` e `/ia` renderizando normalmente.

## Gate de reversão

Se B for implementado e o bloco SSH único aumentar a taxa de falha de deploy (medir: % de deploys `failure` por SSH antes × depois, janela de 30 dias), recuar para o desenho A ou adicionar retry por bloco.

## Evidências

- Log de produção: `storage/logs/laravel.log` — `live.ERROR: Target class [...SellsV3Controller] does not exist`
- `git reflog` do servidor (9 resets, 18:26–20:06) — reproduzido acima
- Histórico de deploys — reproduzido acima; ids de run citados
- Medições com controle positivo — reproduzidas acima
- Sessão: PR [#5356](https://github.com/wagnerra23/oimpresso.com/pull/5356)
