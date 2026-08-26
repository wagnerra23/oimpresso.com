---
date: "2026-08-26"
topic: "O 503 do balcão da ROTA LIVRE era o nosso próprio deploy — 4 correções no ar, 3 propostas minhas refutadas por medição"
prs: [6309, 6330]
---

# O 503 que a ROTA LIVRE fotografou era o nosso deploy

## O pedido

[W] trouxe print de WhatsApp da Larissa (ROTA LIVRE, biz=4): `503 Service Unavailable`
no meio da venda, *"fica acontecendo com bastante frequência"*, e **anotando código de
produto no papel** para lançar depois.

## O diagnóstico

Não é instabilidade de servidor. É `php artisan down` do próprio `deploy.yml`.

| medido (GitHub Actions API, 7 dias) | |
|---|---|
| janelas de 503 | **76** |
| mediana / p90 / máx | **78s / 89s / 101s** |
| total | 93 min (~12,3 min/dia) |
| na manhã de 26/08 | 4 janelas entre **08:34:13** e **09:45:17** BRT |

A foto dela é compatível com a quarta janela. Não existe `resources/views/errors/` no
servidor, então o maintenance servia a página padrão do Laravel — que é a do print. O
503 do LiteSpeed tem texto próprio e não aparece ali.

## As 3 propostas MINHAS que foram refutadas antes de virar código

Três adversários independentes atacaram. Nenhuma sobreviveu inteira.

**1 · `dump-autoload` condicional — INSEGURA.** O detector de classmap era sólido
(2435/2435 declarações). O que mata é o **range**: `concurrency` com
`cancel-in-progress: false`, **36% dos runs cancelados**, servidor sincroniza com o tip
de `origin/main` no instante do pull, e **nenhum passo lê o SHA do servidor**. 47% dos
deploys abrangem >1 commit; 2 casos reais em 2 dias em que o detector diria SKIP e uma
classe nova entraria sem classmap. **E a rede de segurança que eu prometi não existia**
— repeti o comentário do YAML sem ler `verify-classmap.php`, que confere só 4 arquivos
e só o namespace `App\`.

**2 · Release por hardlink + ponteiro — INVIÁVEL.** Três premissas falsas: (a) `cp -al`
vaza para o release no ar, porque `composer dump-autoload` e `config:cache` usam
`file_put_contents`, e `vendor/`+`bootstrap/cache/` não são rastreados pelo git;
(b) 35 chamadas de `public_path()` e 12 diretórios de XML fiscal escritos em runtime
iriam para onde o LiteSpeed não serve — **perda de documento fiscal**; (c) 22 de 288
migrations recentes são destrutivas no `up()`.

**3 · Reinventei o Capistrano.** `releases/ + current` + `shared_dirs` tem 20 anos.
`ln -sfn` não é atômico (o Deployer usa `mv -T`).

## O que foi entregue (PR #6309, mergeado e deployado)

1. **`Sells/Create.tsx`** — o `onOpenChange` do diálogo de rascunho chamava
   `handleDraftDiscard()` → `localStorage.removeItem()`. Esc, clique fora, qualquer
   fechamento que não fosse "Recuperar" apagava a venda montada, **em silêncio e sem
   volta**. Agora só o botão "Descartar" apaga.
2. **`resources/views/errors/503.blade.php`** (novo) — PT-BR, tempo decorrido, sonda de
   reconexão. **HTML puro, zero diretiva Blade** — requisito, não estilo: o comando de
   manutenção termina em `|| true`, então view que não renderize deixaria o site NO AR
   durante o `composer install`.
3. **`deploy.yml`** — `--render='errors::503'` com fallback + `--refresh` 60→15.
4. **`app.tsx`** — handler de `httpException`: 503 vira aviso, a tela permanece.

### Smoke real, capturado na janela de produção às 20:25:38Z

```
HTTP/1.1 503 Service Unavailable
Retry-After: 60
Refresh: 15                              <- era 60
'Estamos atualizando o sistema':  1      <- a página nova
'rascunho da venda':              1
'Service Unavailable' (padrão):   0      <- a do framework NÃO foi servida
```

Janela: `20:25:35Z → 20:26:55Z` = **80s** (bate com a mediana de 78s). Bundle publicado
contém `oi-deploy-503` (controle positivo: 19 ocorrências da string antiga).

## O defeito que eu mesmo deixei — 1 de 3 (PR #6330)

Conferindo a família **depois** do merge: existem **três** invocações executáveis de
`artisan down`. Consertei uma. As outras duas são os failsafes — e o efeito não era
"faltou flag": o re-`down` **sobrescreve** `maintenance.php` com versão SEM template,
desfazendo a página pré-renderizada, no caminho em que o framework está quebrado. No
pior cenário, 500 para o cliente com o site "em manutenção".

## Lições de método (candidatas a LC / §5)

- **Instrumento que afirma verde sem ter medido:** `gh pr checks --watch` sai com
  **exit 0** quando o retorno é *"no checks reported"*. Repassar aquele código teria
  virado "CI verde" sobre PR com zero checks. Conferência = contagem de runs no SHA.
- **Comparar runs de MODOS diferentes:** o verde de `main` era `workflow_dispatch` em
  modo **update** (step de comparação `skipped`); três PRs verdes tinham o step
  **skipped** por raio confiável. Nenhum era comparável. Caí nisso 2×, peguei as 2.
- **`git show <ref>:<path>` mangleia no MSYS** → devolveu "0 ocorrências" de uma string
  que existia. Controle positivo é o que denuncia.
- **Flags de ripgrep (`--hidden`, `-g`) no `grep` do GNU** → saída vazia lida como
  ausência.
- **`gh pr update-branch` cria commit com committer `GitHub`** → **não dispara**
  workflow de `pull_request`. Zero runs no SHA novo.
- **`startup_failure` não pode ser re-executado** (`cannot be retried`) — 16 runs
  mortos por fila do Actions, com durações de 1h48m num scan de segundos.
- **Meu próprio comentário sabotou o arquivo:** escrevi *"não use `@if`"* dentro de
  comentário HTML num `.blade.php` — o Blade compila diretiva dentro de comentário.
  A checagem que eu tinha acabado de escrever pegou.

## Decisões [W] nesta sessão

- **"módulo não muda de pasta"** vale também para o caminho absoluto → diretório de
  release está **fora**. Alvo estrutural passa a ser encolher a janela para ~10–20s,
  não zerá-la.
- **Deploy em horário comercial NÃO é segurado** — velocidade de entrega é valor
  deliberado; o problema se resolve pelo lado técnico.

## Referências

- Pesquisa de deploy sem downtime em shared hosting:
  [`2026-08-26-arte-deploy-zero-downtime-shared-host.md`](2026-08-26-arte-deploy-zero-downtime-shared-host.md)
- Baseline do gate visual: `governance/required-checks-baseline.json` (entrada 2026-08-26 —
  `visual-regression` demovido a advisory; **rebake não fecha o gap** quando o render não
  é determinístico)
