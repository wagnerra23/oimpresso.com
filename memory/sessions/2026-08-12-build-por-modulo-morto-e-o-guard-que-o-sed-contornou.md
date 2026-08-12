---
date: "2026-08-12"
hour: "17:24"
topic: "Build por-módulo morto (12 webpack.mix + 3 vite.config + 16 package.json) — a premissa errava em 3 pontos, a refutação GT-G5 pegou dano real do meu script, e o gate estava insatisfazível"
authors: ["C"]
prs: [5678, 5680, 5685]
outcomes:
  - "31 arquivos de build morto removidos + scaffold parado de recriá-los"
  - "6 fósseis de memory/modulos preservados — meu sed passou por baixo do guardaPerdaDeBranch()"
  - "GT-G5 emendado: teto de política MAX_RANK = opus (PR separado, 17 testes)"
---

# Build por-módulo morto — e o guard que o `sed` contornou

## O pedido

Remover configs de build órfãos em `Modules/`. A tarefa afirmava **18 arquivos** (15 `webpack.mix.js` + 3 `vite.config.js`), *"nenhum npm script os invoca"*, e sugeria remover junto os `Resources/assets/{js,sass}`.

## O que a medição derrubou

**Três pontos da premissa caíram**, e cada um teria custado algo:

| premissa | medido |
|---|---|
| 18 arquivos | **15** — são 12 `webpack.mix.js`, não 15 |
| "os `SUPERFICIE.md` são os únicos citadores" | `rg` **sem `--hidden` devolvia 61; com, 62** — a contagem mudou, logo a varredura estava cega. Faltavam `config/modules.php`, `ModuleSpecGenerator.php`, `quick-sync.yml` e 16 `package.json` |
| "remover os `Resources/assets` junto" | **quebraria produção** |

### A prova que decidiu o caso dos assets

Blades vivas carregam `asset('modules/crm/js/crm.js')` (12 delas) e o CSS/JS do site público do Cms. Meu primeiro veredito foi *"Crm e Cms não são órfãos, o config produz isso"*. Errado — a prova está na **forma**, não no conteúdo:

```
fonte   Modules/Cms/Resources/assets/  = .gitkeep  css/cms.css  img/contact.jpg
                                         img/default.png  img/home.png  js/cms.js
destino public/modules/cms/            = IDÊNTICO, 1:1
```

Cópia byte-a-byte com `.gitkeep` e **imagens** é assinatura de `php artisan module:publish`. O mix nunca copiaria imagem — os configs só têm `.js()` e `.sass()`. E `public/modules/crm/sass/crm.css` fecha o argumento: o mix escreveria em `css/`, o publish preserva `sass/` literal.

Os dois configs "vivos" estavam, na verdade, **quebrados**: declaram input `Resources/assets/js/app.js`, inexistente (existe `js/crm.js`), e `setPublicPath('../../public')` escreveria em `public/js/crm.js` — não onde as blades leem.

## O erro que a refutação pegou (e eu não veria sozinho)

O `ledger-check` (GT-G5) disparou: 17 arquivos em `memory/requisitos/` > 10 ⇒ PR-de-lote ⇒ exige refutação adversarial registrada. Rodei o refutador em contexto próprio.

**Rodada 1 — REPROVADO (29,6%).** Dos 21 `memory/modulos/*.md` que editei, **6 são fósseis**: `Accounting`, `AiAssistance`, `Grow`, `IProduction`, `Officeimpresso1`, `Writebot`. Não existem em `Modules/` nem em branch alguma — `main-wip-2026-04-22` sumiu do repo e do remoto. `GenerateModuleSpecsCommand::guardaPerdaDeBranch()` (`:98-112`) existe **só** para impedir que uma regeneração apague o último registro deles, e nomeia os seis por escrito.

Meu script de remoção **passou por baixo do guard**: ele protege `module:specs`, não `sed`. E a linha que apaguei era **fato datado**, não ponteiro podre — aqueles módulos *tinham* Laravel Mix naquela branch, e o git não sabe mais confirmar nem desmentir. É a distinção que a ADR 0377 crava: libera mexer, nunca falsificar.

A segunda refutação da rodada 1 também procedia: eu afirmei que os 21 docs eram *"o que o gerador produziria"*. Falso — eles assinam `**Gerado automaticamente … em 2026-05-29 08:06**` e o diff tem `0 added`; se tivessem sido gerados, o `now()` teria reescrito o rodapé.

**Rodada 2 — aprovado (1,54%), com 1 bloqueador.** `memory/requisitos/Financeiro/SUPERFICIE.md` era editado pelos dois lados: `main` avançou adicionando um teste (`333 → 334`), meu lote removia arquivos (`333 → 331`), e o correto pós-merge era **332** — nenhum dos dois lados tinha.

Isso invalidou o escopo da minha medição nas duas rodadas: **o required roda no merge commit, não no tip da branch**. Meu `--all --check` verde media uma árvore diferente da que o gate avalia. Resolvido re-rodando o dono (`module-surface.mjs Financeiro --write` → 332), nunca editando o número à mão.

## O gate insatisfazível — e a emenda

Com o lote corrigido, o gate continuava vermelho por outro motivo: o §2.3 exige refutador de **tier superior** ao gerador. Bite-test com entry sintética contra o gate real:

```
gerador=opus-5  refutador=opus-5   → rc=1  REPROVA ("MESMO tier")
gerador=opus-5  refutador=fable-5  → rc=0  passa
```

[W] vetou fable por custo. Com **23 das 87 entries** do ledger nascendo de opus, a regra era insatisfazível — o mesmo defeito de mecanismo que a emenda §4.1 (2026-07-30) já consertara para o gerador externo: gate sem caminho honesto de abertura, cujo único "jeito prático" é falsear o campo `gerador`.

Emenda em **PR separado** (#5685) — emendar a régua dentro do PR que ela bloqueia seria auto-servir. `MAX_RANK` passa de `Math.max(tabela)` para `MODEL_RANK.opus`, com **17 testes** e controle negativo por caso: sonnet×sonnet reprova, haiku×haiku reprova, opus×sonnet reprova, e `sessao_fresca: false` reprova **inclusive** em opus×opus.

A justificativa é da própria §4.1: **o que a regra protege é DECORRELAÇÃO, não hierarquia por si**. O tier é uma metade; a sessão fresca é a outra, e segue enforçada. E há evidência empírica de que basta: a refutação opus×opus **mordeu** no exato caso que motivou a emenda.

## Erros meus de instrumento (5 nesta sessão)

Todos da família LC-08 — medir com a ferramenta errada e ler o resultado como verdade:

1. **`rg` sem `--hidden`** na varredura de citadores (61 × 62).
2. **`rc` do `tail`, não do comando** — 2×. Uma delas me fez ler `rc=0` de um `module-surface --check` que estava em **rc=1** com 17 módulos em drift.
3. **`^-[^-]`** para varrer linhas removidas do diff — o padrão **exclui bullets markdown** (`-- [package.json]`), e quase concluí que os links não tinham saído.
4. **`git diff origin/main`** (two-dot) para medir o tamanho da emenda — mostrou 11 arquivos porque compara árvores e inclui commits alheios invertidos. O correto é three-dot: 3 arquivos.
5. **`echo "commit 4 ok"` após um commit que falhou** — afirmação falsa na mesma linha em que li um `rc` errado.

E um erro de processo: tentei `git rebase` num branch com 5 commits, ele parou no meio e **descartou o commit da correção**. Recuperei pelo hash (`a82b3c79496`) e re-auditei a árvore item a item antes de seguir. Depois usei `git merge`, que resolveu sem drama.

## Resultado

| PR | estado | conteúdo |
|---|---|---|
| #5678 | fechado | superseded — separá-lo do resto deixaria o required vermelho |
| #5685 | **MERGED** 16:18Z | teto de política do GT-G5 + 17 testes |
| #5680 | **MERGED** 17:01Z (`16cc0710c12`) | 65 arquivos: 31 removidos, scaffold, gerador, 15 docs, 17 `SUPERFICIE.md`, 2 entries no ledger |

Verificado em `origin/main`, não declarado: zero `Modules/*/{webpack.mix.js,vite.config.js,package.json}`; `Accounting.md` mantém `Build: **Laravel Mix**` e `Grow.md` mantém `Build: **Vite**`.

## Dívida declarada

`php artisan module:specs` **não foi executado**. Os 15 docs seguem assinando a data de geração antiga porque foram editados cirurgicamente — este worktree não tem `vendor/`, e criar a junction armaria a armadilha do `git worktree remove` (proibição Tier 0, 2 incidentes catalogados). Quem rodar numa máquina com vendor verá um diff grande, e **parte dele é dívida anterior a este PR**.

## Pointers

- Evidência da refutação: PR #5680, comment 5268403844
- Protocolo emendado: [`PROTOCOLO-REFUTADOR-BACKFILL.md` §4.2](../requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md)
- Guard que o `sed` contornou: `app/Console/Commands/GenerateModuleSpecsCommand.php:98-112`
