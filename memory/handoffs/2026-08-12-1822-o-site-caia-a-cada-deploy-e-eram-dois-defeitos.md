---
date: "2026-08-12"
time: "18:22 UTC"
slug: o-site-caia-a-cada-deploy-e-eram-dois-defeitos
tldr: "[W] pediu que o site parasse de cair quando compila. Medindo, eram DOIS defeitos: 503 de ~1min por deploy (52% deles sem tocar produção) e chunk 404 quebrando a sessão de quem já estava com o sistema aberto. #5690 mergeado: job sync-light + assets mesclados + composer condicional. Em prod: merge de bundles e skip do composer PROVADOS; encurtamento da janela NÃO — 1ª amostra deu 74s contra 54/59/63s, em host degradado."
prs: [5690]
decided_by: [W]
next_steps:
  - "Coletar 3-5 amostras de janela em host saudavel antes de concluir sobre o encurtamento"
  - "Se a janela nao cair: dobrar o dump-autoload de volta na MESMA chamada SSH do composer (1 conexao + skip)"
  - "sync-light ainda sem exercicio real — depende de um push so de governanca/docs"
---

## Estado MCP no momento do fechamento

⚠️ **MCP NÃO exposto nesta sessão** — `ToolSearch` por `cycles-active`/`my-work`/`sessions-recent`/`decisions-search` retornou *"No matching deferred tools found"*. O `brief-fetch` do `SessionStart` entregou o brief (hook), mas as tools não estão chamáveis. Passo 1 do R12 cumprido por **fallback filesystem** (`how-trabalhar.md` §Fallback), declarado em vez de fingido:

- **Handoffs irmãos de hoje:** 6 (`0740`, `0749`, `0925`, `0934`, `1043`, `1231`) — nenhum sobre deploy/infra de publicação ⇒ sem duplicação
- **Session logs de hoje:** 3 — idem
- **Base:** `claude/site-crash-compilation-31e667`, mergeada em `main` (`8ddc90fb584`)

## O que aconteceu

[W]: *"fazer não cair mais o site quando estiver compilando isso é muito chato"*. A tentação era encurtar a janela de manutenção. **A medição mostrou que isso teria resolvido metade do problema.**

**Defeito A — 503 por deploy.** Janela medida em 3 runs: **54s / 59s / 63s**, com ~23 deploys/dia. Pior: **14 dos 27** últimos deploys bem-sucedidos (**52%**) puseram o site em manutenção sem tocar UMA linha servida em produção — eram `.github/`, `.claude/`, `scripts/`, `governance/*.json`.

**Defeito B — o que realmente doía.** As páginas são chunks lazy (`import.meta.glob` no `app.tsx`) e o deploy fazia `mv build-inertia build-inertia.old`: quem estava com o sistema **aberto** tinha o manifest antigo em memória e a próxima navegação pedia arquivo que sumira → 404 → tela morta até F5. **Isso acontece DEPOIS da janela** — encurtar o 503 não o tocaria. Não havia tratamento de erro de chunk em lugar nenhum.

**Terceira medição:** `composer.lock` mudou em **0 de 27** deploys ⇒ os ~27s de `composer install` rodavam dentro do 503 sem ter o que instalar, em 100% das vezes.

**A verificação achou 2 defeitos MEUS** (é o valor dela, não formalidade):
1. **O sério:** o `sync-light` fazia `reset --hard origin/main` = tip **atual**, não o SHA do run. Com runs enfileirados, publicaria runtime de um push mais novo **sem** dump-autoload/cache clear/OPcache — a receita exata dos 500 de 2026-06-18/06-23. Corrigido: reseta em `github.sha` e, se a main andou, **sai limpo**.
2. A regex de chunk não casava `Unable to preload` (helper do Vite).

## Artefatos gerados

| Arquivo | Δ | O quê |
|---|---|---|
| [`.github/workflows/deploy.yml`](../../.github/workflows/deploy.yml) | +238/−10 | job `sync-light` · `runtime_changed` fail-safe · `composer install` condicional ao hash do lock · `dump-autoload` sempre · bundles **mesclados** com poda +3d |
| [`resources/js/app.tsx`](../../resources/js/app.tsx) | +75 | rede de segurança: `vite:preloadError` + `unhandledrejection` → GET navega, POST/PUT só avisa (nunca recarrega por cima de formulário) |

## Persistência

- **git:** [#5690](https://github.com/wagnerra23/oimpresso.com/pull/5690) MERGED (`8ddc90fb584`), 44 required verdes
- **prod:** deploy `31624700876` — `merged build-inertia (chunks mortos ha +3d podados: 0)` · `Composer install` **skipped** · `Dump-autoload` executado · bundle servido `app-CgPPlA1k.js` contém `vite:preloadError` ×2
- **MCP:** não atualizado (tools indisponíveis)

## O que ficou PROVADO — e o que não

✅ **Defeito B morto em produção.** Assets mesclados, rede de segurança no bundle servido, site 200, zero erro de console (screenshot).
✅ `composer install` **skipped** pelo hash, `dump-autoload` sempre.
❌ **Encurtamento da janela: EM ABERTO, com 1ª amostra contra.** Deu **74s** (contra 54/59/63s). O `dump-autoload` sozinho levou **46s**, sendo que antes `install+dump` **juntos** levavam 26-28s — como o dump não pode ser mais lento que install+dump, o host estava degradado (SSH deu timeout de 7min pouco antes). Contaminada ≠ inocente: **eu separei 1 chamada SSH em 2**, e cada conexão ao Hostinger custa caro. Uma amostra não decide.
⏸️ `sync-light` **sem exercício real** — precisa de push só de governança/docs.

## Lições catalogadas

- **LC-08 (nova ocorrência, minha, corrigida no mesmo turno):** declarei *"a fila do CI está congelada"* olhando os **60 runs mais recentes** — que estão em `queued` **por construção**, já que todo run nasce assim. Nos 200: 32 completed / 12 in_progress / 142 queued = congestionada, drenando. **Amostra enviesada pela própria ordenação.** Cheguei a levantar hipótese de cota do GitHub em cima disso. Corolário: *ao medir fila/backlog, a janela recente é o pior lugar pra olhar.*
- **LC-13 evitado por controle:** o 1º teste do guard "main andou" passou nos DOIS casos — mas o caso 2 **nunca aconteceu** (o push falhou por branch `master` e a main nunca andou). Só apareceu porque testei **qual ramo executou**, não só o resultado. *Asserção verde sem prova de que o cenário rodou é LC-13 com maquiagem.*
- **Falha de deploy que NÃO foi minha:** run `31620561239` morreu em `ssh: connect to host: Connection timed out` (exit 255) no **passo 7, antes do `artisan down`** ⇒ **zero downtime**. A ordem do pipeline (checar servidor antes de derrubar) fez o trabalho — é o mesmo princípio que o `sync-light` leva ao limite.
- **O CI não roda `tsc`.** `typecheck` existe no `package.json` e **nenhum workflow o chama**; o Vite usa esbuild, que só remove tipos. Erro de tipo em `.tsx` passa batido hoje. Não mexi — é buraco real, decisão [W].
- **Pré-existente que NÃO toquei:** o job `deploy` builda bundles do SHA do run mas reseta o **fonte** pro tip de `origin/main` — com fila, publica PHP mais novo que os bundles. Foi o análogo disso que bloqueei no caminho leve.

## Pointers detalhados

- Session log: [`2026-08-12-deploy-nao-derruba-o-site.md`](../sessions/2026-08-12-deploy-nao-derruba-o-site.md)
- PR com a validação completa: [#5690](https://github.com/wagnerra23/oimpresso.com/pull/5690)
