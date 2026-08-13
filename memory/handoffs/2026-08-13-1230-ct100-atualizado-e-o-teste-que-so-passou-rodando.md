---
date: "2026-08-13"
slug: ct100-atualizado-e-o-teste-que-so-passou-rodando
hour: "12:30 UTC"
topic: "CT 100 saiu de 2026-07-23 para hoje; o teste que estava no main 'declarado não-executado' rodou e falhou em 3 fixtures"
authors: [C, W]
prs: [5725, 5729]
us: []
tldr: "[W] mandou atualizar o CT 100. Com o staging fresco, rodei o teste do gate de veículos que eu tinha mergeado com rótulo 'não executado' — falhava em 3 pontos de fixture. Corrigido e verde (3 passed/10 assertions). No caminho: 3 achados de infra e o 4º acidente de git da sessão."
outcomes:
  - "CT 100 de 2026-07-23 → main de hoje, com backup em 2 lugares antes de tocar"
  - "PR #5729 mergeado — teste do gate agora RODA: 3 passed, 10 assertions"
  - "3 achados novos: IsOtherFlagTest quebrado · `php artisan test` não sobe no main · cron mv-metabolismo caído"
  - "LC-23 nova + lápide §5 — o `checkout -- <path>` que come trabalho, 2× no mesmo dia"
---

# CT 100 atualizado, e o teste que só passou depois de rodar

## O update

O checkout do `oimpresso-staging` estava em **2026-07-23**. Agora está no `main` de hoje,
com `composer install` aplicado (o lock mudou 165/67 em 20 dias).

**Antes de tocar em qualquer coisa**, backup em dois lugares — o canon registrava que havia
alterações não-commitadas de outra sessão, e havia: **15 itens + 1 stash**.

| onde | o quê |
|---|---|
| `/root/ct100-backups/pre-update-20260813` (host, fora do container) | `tracked.patch` (41KB) · `untracked.tgz` (92MB) · `status-antes.txt` · `head-antes.txt` · `stashes-antes.txt` |
| branch local `backup/ct100-pre-update-20260813` no próprio checkout | commit `ed1493618` com tudo exceto `core.1` e `.env.bak*` |

O hook `block-destructive` **barrou meu `reset --hard origin/main`** — e barrou certo. Troquei
por commit-numa-branch-de-backup + `checkout -B main origin/main`. Sem reset nenhum.

⚠️ O stash de outra pessoa (`WIP on main: 5a73d10b5 fix(jana): buscarHybrid via API REST do
Meilisearch`) **segue intacto** — `checkout` não toca stash.

## O teste: estava no main, com rótulo honesto, e errado

O [#5725](https://github.com/wagnerra23/oimpresso.com/pull/5725) mergeou o teste do gate de
veículos **declarado como não-executado** (Pest é proibido local; o CT 100 estava velho).
Com o staging fresco, rodei. Falhava em **3 pontos independentes**, nenhum visível por leitura:

| # | erro | por quê |
|---|---|---|
| 1 | `business_id => 98` → FK violation | staging tem business **1, 99, 2**. Não tem 98. Fixar id **assume seed** |
| 2 | `Business::factory()` → BadMethodCall | `App\Business` **não usa `HasFactory`**; não existe `BusinessFactory` no repo |
| 3 | `contacts.created_by` ausente → 1452 | FK NOT NULL pra `users.id` |

Mais dois que teriam feito passar **pelo motivo errado**: `Role` com sufixo `#{biz}`
(`roles.business_id` é NOT NULL + FK) e `user_type='user'` + `allow_login=1`, sem os quais o
`CheckUserLogin` aborta 403 — o caso "MORDE" ficaria verde por middleware, não pelo gate.

**Recibo** (CT 100, MySQL real, arquivo byte-idêntico ao commit): `3 passed (10 assertions)`.
Dez asserções, não zero — executou, não pulou. Corrigido no
[#5729](https://github.com/wagnerra23/oimpresso.com/pull/5729).

## Três achados que só apareceram porque rodei

1. **`tests/Feature/Cliente/IsOtherFlagTest.php` está quebrado.** Foi de lá que copiei
   `Business::factory()->create()`. Como o método não existe, aquele teste **quebra se
   alguém o rodar**. Ninguém roda.
2. **`php artisan test` não sobe no `main`.**
   `tests/Feature/Ads/AdsProjectsRoutesContratoTest.php` (rastreado, está no main) causa
   `TestCaseAlreadyInUse` e derruba o bootstrap do Pest. O CI passa porque as lanes rodam
   Pest **escopado** (`vendor/bin/pest Modules/<X>/Tests`). ⚠️ **Consequência:** "a nightly
   roda a árvore" pode ser falso — e era exatamente onde o teste do Crm ia rodar.
   **Não verifiquei** como a nightly invoca; se for suíte inteira, ela está morta.
3. **Cron `mv-metabolismo.yml` falhando** desde 2026-08-13T10:36Z. O watchdog G6 acusa em PR
   de todo mundo (`23 ok · 1 🔴`). É **advisory** (não está entre os 45 required), então não
   trava merge — mas é cron real caído.

## O 4º acidente de git da sessão — e o que ele prova

`git checkout <branch> -- <path>` **sobrescreveu a versão do teste que acabara de passar**.
Foi a segunda vez no dia com o mesmo comando (a 1ª comeu a lápide da LC-22), e a segunda
aconteceu **horas depois** de eu escrever, nessa mesma lápide, a regra contra ela.

Regra escrita, minha, fresca, específica — e não segurou. É [ADR 0256](../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)
na forma mais limpa que já vi: *derivado+enforçado sobrevive; escrito+lembrado apodrece*.

Registrado como **LC-23** + lápide §5. **Candidato a defesa mecânica registrado mas NÃO
instalado:** o predicado é decidível (`git status --porcelain <path>` não-vazio) e o dono
existe (`block-destructive.mjs`) — mas falta **medir o FP**, porque `checkout <ref> -- <path>`
sobre path sujo é *também* a forma canônica de descartar mudanças de propósito. Instalar sem
medir repetiria a lápide vizinha do mesmo dia.

Nos dois casos houve recuperação **por acaso**, não por desenho: no 1º o conteúdo estava no
meu contexto; no 2º, a cópia base64 que eu tinha mandado pro host do CT 100 pra rodar o teste.
**Sorte não é procedimento.**

## Estado do CT 100 ao fechar

- `HEAD` = `main` @ `499299401` (2026-08-13, #5728) · working tree com 3 itens
  (`core.1`, os 2 `.env.bak*` — deixados de propósito, são de outra sessão)
- branch `backup/ct100-pre-update-20260813` presente · stash alheio intacto
- vendor coerente com o lock · MySQL `oimpresso_staging` respondendo
- ⚠️ **não deixei nada meu no container** — o arquivo de teste que copiei foi restaurado
  por `git checkout --` (aqui legítimo: eu queria descartar minha cópia)

## Aberto

- **Como a nightly invoca o Pest** — se for suíte inteira, está morta pelo achado #2.
- **Ligar o Crm numa lane de PR**: `test-lane-coverage --modulo Crm` = **13/13 fora do PR**.
  Adicionar `Crm` à matrix do `modules-pest.yml` transformaria 13 testes nunca-rodados em
  gate de uma vez — **medir antes**, agora que o CT 100 está fresco e dá pra rodar.
- **Medir o FP do candidato da LC-23** no corpus de transcripts.
- Os 8 itens de decisão [W] da mesa de fronteiras (§4 do proposal, em main).

## Estado MCP no momento do fechamento

- `cycles-active` → nenhum cycle ATIVO em COPI (mesmo de 21:15Z).
- `sessions-recent` → índice segue devolvendo logs de maio/junho indexados hoje.
- Nada registrado em `mcp_tasks`.
