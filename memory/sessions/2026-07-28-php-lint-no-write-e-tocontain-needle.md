# 2026-07-28 — `php -l` onde o arquivo nasce, e os 38 asserts que nunca podiam passar

> Sessão-pai da Onda 4/5 do [passo 5 SDD](../requisitos/_Governanca/programa-ondas/passo-5-sdd-por-modulo.md).
> Os session logs dos chips (KB · TeamMcp · Vestuario · ComunicacaoVisual · NfeBrasil ·
> RecurringBilling) são irmãos deste e contam o trabalho **deles**; este conta a
> consolidação, a máquina que nasceu no meio e os erros do pai.

## 0. O pedido

[W], em uma frase: **"Arrumar a máquina sempre é melhor que resolver na mão e teste"**.

Não era pedido de tarefa — era diretriz sobre COMO fazer o resto. Ela chegou logo depois
de eu ter consertado **à mão** um erro de sintaxe que custou três rodadas de diagnóstico.

## 1. A máquina já existia — o defeito era LATÊNCIA, não cobertura

A primeira coisa que a diretriz produziu foi uma pergunta melhor. Em vez de *"que gate eu
crio?"*, a pergunta virou *"por que a máquina que existe não me serviu?"*.

`.github/workflows/ci.yml` roda, no job **required** `PHP / Pest (Unit)`:

```
find app Modules -name '*.php' -not -path '*/vendor/*' | xargs -0 -n1 -P4 php -l
```

Ou seja: **o lint sempre existiu e sempre cobriu `Modules/`**. Medido no PR [#4905](https://github.com/wagnerra23/oimpresso.com/pull/4905),
o veredito chega em **7m11s** — e só depois do push. Entre o `Write` e a resposta cabem
commit, push, espera e (foi o caso) três rodadas de diagnóstico à mão.

| | antes | depois |
|---|---:|---:|
| veredito de sintaxe PHP | 7m11s, após o push | **~0,3s**, no `Write` |

O hook [`php-syntax-after-write.mjs`](../../.claude/hooks/php-syntax-after-write.mjs)
(PostToolUse, [PR #4911](https://github.com/wagnerra23/oimpresso.com/pull/4911)) roda o
**mesmo oráculo** no ponto onde o arquivo nasce.

### 1.1 Por que ele escapa das 4 lápides de guard sintático

[proibicoes §5](../proibicoes.md) tem quatro guards mortos — allowlist-de-pasta (06-30),
`@scope` (07-09), vocabulário-de-enforcement (130 FP, 07-16), `toHaveKey` (100% FP, 07-26).
Todos tinham o mesmo defeito: **o critério era palpite do autor** sobre o que "parece" errado.

Aqui o critério é o **parser do PHP** — o mesmo oráculo do CI, o mesmo que decide se o
arquivo roda em produção. Ou compila ou não. **FP medido ANTES de instalar**, como a regra
exige, no corpus real:

```
4.477 arquivos (app/ + Modules/, sem vendor) · 142s · 0 erros = 0 FP
```

### 1.2 Fail-open, e o CI ao contrário

Sem PHP na máquina (Mac/Linux do time MCP sem Herd) → `exit 0` **silencioso**. Hook que
reclama de ferramenta ausente vira ruído e ensina a ignorar hook; a rede de quem não tem
PHP local segue sendo o CI, que não mudou.

No CI é o **inverso**: o teste **falha** se `CI=true` e não houver PHP — senão pular a
mordida e imprimir verde seria o falso-verde por **não-execução** (§5 2026-07-24).

Não viola a [ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md): `php -l` é
lint de sintaxe — não roda a suíte, não toca DB, não boota o app. O hook
`block-test-fora-ct100` casa `php artisan test`/pest/phpstan, **não** casa `php -l`.

### 1.3 Invocação PROVADA, não só escrita

Escrevi um `.php` quebrado com o tool `Write` e **o hook disparou no fluxo real**, com a
mensagem completa. Chokepoint real, não fantasma (§5 2026-07-09 `flag:set`).

## 2. A máquina pagou no primeiro dia — três vezes

1. **Achou um arquivo quebrado que eu não sabia que tinha**: o `ScorecardContratoTest.php`
   ainda estava com o defeito **commitado no HEAD desta worktree** — o fix tinha ido só
   pra branch do chip.
2. **Validou o codemod** dos 38 asserts: 16 arquivos tocados, `php -l` em todos antes do
   commit, 0 quebrados. Codemod sobre 15 arquivos PHP sem lint é exatamente onde se
   introduz erro de sintaxe em silêncio.
3. **Lintou os 23 `.php` dos chips** antes de consolidar: 7s para o que custaria 7min por PR.

## 3. O bug de verdade: `toContain` é VARIÁDICO

O PR do NfeBrasil quebrou numa lane de **outro módulo** (ComunicacaoVisual). Investigando:

```php
// vendor/pestphp/pest/src/Mixins/Expectation.php:184
public function toContain(mixed ...$needles): self
{
    foreach ($needles as $needle) { /* asserta CADA UM */ }
```

**Não existe parâmetro de mensagem.** Quem escreve `toContain($x, "explicação")` — por
analogia com `assertStringContainsString($n, $h, $msg)` — faz o Pest procurar a **frase
inteira** no haystack. No positivo, falha **sempre**. E o erro sai como
`To contain: <a explicação>`, apontando pro lado errado: foi assim que um assert acusou a
migration `create_cv_substratos_table` de não ter `ncm`/`cfop_padrao`/`csosn_padrao`.
**A migration tem os três** (3.260 bytes, conferido). Falso-vermelho.

### 3.1 A extensão, medida antes de consertar

| | |
|---|---:|
| arquivos de teste varridos (sem `head_limit`) | 1.522 |
| chamadas com mensagem-como-needle | **59** |
| **positivas** — falham sempre que executadas | **38** |
| negativas (`->not->toContain`) — passam por acidente | 21 |
| needles legítimos entre as 59 | **0** |

As negativas são inofensivas: a mensagem nunca está no haystack, então o `not` segue
verdadeiro **por ela** e o assert ainda detecta o que deve. Sujas, mas mexer sem poder
rodar (CT 100) é risco sem ganho — não tocadas.

### 3.2 Por que 37 das 38 nunca apareceram

**As lanes delas não executam esses arquivos.** O próprio
[`compras-pest.yml:135`](../../.github/workflows/compras-pest.yml) documenta o regime:
*"entra na allowlist SEM ser verde — é FAILING-FIRST por desenho"*.

É a lição estrutural do dia: **defeito em teste que não roda é invisível até a lane ligar**.
O ComVis explodiu porque a lane dele ligou. E quando meu codemod tocou o
`ComprasContratoFiltrosTest`, o arquivo **entrou em execução** (`[modified]` no log) e o
vermelho intencional apareceu — não era regressão minha, era o achado funcionando.

### 3.3 Consertado por codemod, não à mão

36 das 38 por transformação determinística ([PR #4918](https://github.com/wagnerra23/oimpresso.com/pull/4918)); as 2 do ComVis à mão, onde o tipo
do haystack é conhecido e dá pra preservar a mensagem via `toBeTrue(string $message = '')`.
Varredura pós-fix: **38 → 0 positivos**.

A transformação foi conservadora de propósito — a mensagem vira comentário
`// FALHA AQUI SIGNIFICA: …` e o `toContain` fica com o needle real. Trocar tudo por
`str_contains(...)->toBeTrue($msg)` preservaria a mensagem no output, mas exige saber o
**tipo** do haystack (`str_contains` quebra com array).

### 3.4 Por que NÃO virou lint

Classificar "mensagem vs needle" é textual, e a lápide de **anteontem** (`toHaveKey`,
100% FP medido) proíbe lint que julgue assert pela **forma sintática do matcher**. O
conserto é o entregável; a defesa é a lápide (registrada no §5 hoje).

## 4. Meus erros — os dois da mesma classe (LC-08)

1. **`resolvePhp` não honrava o `env` recebido**: a função aceitava `env` mas o `spawnSync`
   usava `process.env`. No Linux isso achava o `php` do runner e meu teste de unidade
   "sem PATH → null" **media outra coisa**. Pego pelo CI. LC-08 dentro da máquina feita
   pra pegar erro.
2. **`module-surface.mjs --write` sem argumento de módulo, com saída pro `/dev/null`**:
   o script imprimiu o **modo de uso** e saiu. Eu afirmei ter regenerado o `SUPERFICIE.md`
   e não regenerei. O gate do NfeBrasil reprovou — e aí achei o mesmo defeito no
   RecurringBilling **antes** do CI chegar nele. Silenciar stdout de comando cujo sucesso
   você não confere é a família do `cmd || echo` que mente (§5 2026-07-17).

E um erro de escopo: **duplicei régua**. Somei um step no `governance-script-tests.yml`
sem ver que o `gate-selftest.yml` **já varre `*.test.mjs` sozinho** — ele rodou meu teste
sem eu registrar nada. Step removido; o dono do tema segue único.

## 5. Consolidação da Onda 4/5

| PR | Módulo | Estado |
|---|---|---|
| [#4904](https://github.com/wagnerra23/oimpresso.com/pull/4904) | KB | MERGED |
| [#4905](https://github.com/wagnerra23/oimpresso.com/pull/4905) | TeamMcp | MERGED |
| [#4906](https://github.com/wagnerra23/oimpresso.com/pull/4906) | Vestuario | MERGED |
| [#4911](https://github.com/wagnerra23/oimpresso.com/pull/4911) | **hook `php -l`** | MERGED |
| [#4913](https://github.com/wagnerra23/oimpresso.com/pull/4913) | NfeBrasil — 13 CU, 19 UC | MERGED |
| [#4914](https://github.com/wagnerra23/oimpresso.com/pull/4914) | RecurringBilling — 14 CU, 36 UC | MERGED |
| [#4918](https://github.com/wagnerra23/oimpresso.com/pull/4918) | os 38 asserts | MERGED |

**Medido no `main` fresco** (não afirmado): **14 módulos com SDD** — Cliente, Compras,
ComunicacaoVisual, Financeiro, Fiscal, KB, NfeBrasil, OficinaAuto, Ponto, Produto,
RecurringBilling, Sells, TeamMcp, Vestuario. Começou a campanha em **1**.

> ⚠️ A contagem crua `git ls-files "memory/requisitos/*/SDD-*.md"` devolve **15** porque
> inclui `_DesignSystem` — que é o **template**, não módulo. 14 é o número honesto.

## 6. Higiene que virou regra na consolidação

- **Derivado se REGENERA, nunca se copia**: os `SUPERFICIE.md` dos chips nasceram sobre
  base 42 commits atrás. Copiá-los plantaria drift.
- **`git checkout -B origin/main` na worktree suja é armadilha**: medi **22 arquivos
  tracked** divergindo de main (quase todos de chips já mergeados) — o checkout arrastaria
  trabalho alheio. Consolidei copiando pra um worktree que já estava em `main` limpo.
- **Guarda de escopo no `git add`**: aborta se algo vazar fora do módulo. Herdado do
  vazamento do commit do KB (Onda 4).

## 7. Aberto — decisão [W]

Dois 🔴 dos chips, com prova, que **não** tomei por conta:

1. **`toggleAutoEmission` liga emissão automática de documento fiscal sem gate nenhum** —
   `grep -n "can(\|abort"` no `TributacaoController` → **0**, e o rota-group não tem
   middleware de permissão. (NfeBrasil §9)
2. **Assinatura com valor negociado nunca vira fatura** — o `store()` casa plano por
   `ciclo` **e** `valor` exatos; sem match, `plan_id=null` e o gerador descarta com **uma
   linha de log, sem alarme**. Há 3 remédios que se anulam entre si. (RecurringBilling §9.1)

Mais o backlog herdado dos chips anteriores: `has_return` do Sells, censurar CPF no
Cliente, global scope no `Contact`, os 2 bugs do Ponto.
