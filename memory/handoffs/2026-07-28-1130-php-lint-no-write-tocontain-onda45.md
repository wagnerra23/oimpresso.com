# Handoff 2026-07-28 11:30 — `php -l` onde o arquivo nasce · os 38 asserts · Onda 4/5 do SDD fechada

> Session log irmão: [`2026-07-28-php-lint-no-write-e-tocontain-needle.md`](../sessions/2026-07-28-php-lint-no-write-e-tocontain-needle.md)
> (conta o trabalho). Este conta o **estado** pro próximo.

## O que mudou pro próximo agente — leia isto primeiro

**Todo `Write`/`Edit` de `.php` agora passa por `php -l` na hora.** Hook
[`php-syntax-after-write.mjs`](../../.claude/hooks/php-syntax-after-write.mjs) (PostToolUse,
[#4911](https://github.com/wagnerra23/oimpresso.com/pull/4911), **no main**). Se você escrever PHP que não compila, o erro volta em
~0,3s citando a linha do parser — não em 7min depois do push.

- **Sem PHP na máquina** (Mac/Linux sem Herd) → **silêncio total**, `exit 0`. Não é bug;
  é desenho. Sua rede continua sendo o CI.
- **Desligar** (raro): `OIMPRESSO_PHP_LINT_MODE=off` · emergência `OIMPRESSO_PHP_LINT_OVERRIDE=1`.
- A mensagem ensina um sintoma específico: **se o erro NÃO ANDA DE LINHA quando você mexe
  naquela linha, a causa está ANTES dela** — suspeito nº 1 é `*/` dentro de docblock
  (glob tipo `Modules/*/Tests/*` em comentário fecha o bloco ali).

## Estado MCP no momento do fechamento

Consultado 2026-07-28 (prova, não promessa):

- **`cycles-active`** → `Nenhum cycle ATIVO em COPI.`
- **`my-work`** → 6 tasks, **todas em REVIEW**: `US-TR-309`, `US-TR-310` (triage),
  `US-PG-008` (linkage `cobranca_id`), `US-PROD-027` ([V0] preço zero em tabela),
  `US-TR-305`, `US-TR-306` (inbox).
- **`sessions-recent`** → 6 session logs de chips hoje (KB · TeamMcp · Vestuario ·
  ComunicacaoVisual · NfeBrasil · RecurringBilling) + este.
- **Brief** (início da sessão) → 680 US não atribuídas / 537 sem dono; SDD composta 55,4.

## Onda 4/5 do passo 5 — FECHADA, 7 PRs mergeados

| PR | O quê |
|---|---|
| [#4904](https://github.com/wagnerra23/oimpresso.com/pull/4904) · [#4905](https://github.com/wagnerra23/oimpresso.com/pull/4905) · [#4906](https://github.com/wagnerra23/oimpresso.com/pull/4906) | KB · TeamMcp · Vestuario |
| [#4913](https://github.com/wagnerra23/oimpresso.com/pull/4913) | NfeBrasil — 13 CU, 19 UC ancorados |
| [#4914](https://github.com/wagnerra23/oimpresso.com/pull/4914) | RecurringBilling — 14 CU, 36 UC, 0 órfãos |
| [#4911](https://github.com/wagnerra23/oimpresso.com/pull/4911) | o hook `php -l` |
| [#4918](https://github.com/wagnerra23/oimpresso.com/pull/4918) | 38 asserts `toContain` corrigidos |

**Medido no `main`** (`git ls-files "memory/requisitos/*/SDD-*.md"`, contado):
**14 módulos com SDD** — Cliente, Compras, ComunicacaoVisual, Financeiro, Fiscal, KB,
NfeBrasil, OficinaAuto, Ponto, Produto, RecurringBilling, Sells, TeamMcp, Vestuario.
Campanha começou em **1**.

> ⚠️ A contagem crua devolve **15**: inclui `_DesignSystem`, que é o **template**. 14 é o
> número honesto — não repita o 15.

## O bug que vale conhecer: `toContain` é VARIÁDICO

`toContain(mixed ...$needles)` (`Mixins/Expectation.php:184`) asserta **cada
argumento**. Não há parâmetro de mensagem. `toContain($x, "explicação")` procura a frase
inteira → falha sempre, e o erro diz `To contain: <a explicação>`, despistando.

Quem aceita mensagem é `toBeTrue(string $message = '')`.

Varrido: 1.522 arquivos · 59 chamadas · **38 positivas corrigidas** · 21 negativas
(`->not->`) deixadas de propósito (passam por acidente; mexer sem poder rodar é risco sem
ganho) · **0 needles legítimos**.

**Não virou lint** — cai na lápide do `toHaveKey` (§5 07-26: lint que julga assert pela
forma sintática do matcher). Lápide registrada hoje no §5.

## ⚠️ Armadilha estrutural que mordeu 2× hoje

**Defeito em teste que não roda é invisível até a lane ligar.**

- O ComVis ficou vermelho porque a lane dele ligou — acusando uma migration **correta**.
- O `ComprasContratoFiltrosTest` só ficou vermelho quando meu codemod o **tocou** e o pôs
  em execução (`[modified]` no log). São `UC-CMP-06`/`UC-CMP-07`, **failing-first por
  desenho**, documentados em [`compras-pest.yml:135`](../../.github/workflows/compras-pest.yml).

**Se você ligar uma lane e ela vier vermelha, provavelmente é dívida acumulada, não
regressão sua.** Confira a linha que falha antes de concluir.

## Higiene de consolidação (herde isto)

1. **Derivado se REGENERA, nunca se copia** — `SUPERFICIE.md`/`_BACKLOG-GENERATED.md` dos
   chips nasceram sobre base 42 commits atrás.
2. **`module-surface.mjs` EXIGE `<Mod>` ou `--all`** — sem argumento ele imprime o modo de
   uso e sai com **sucesso**. Não redirecione a saída pro `/dev/null` (foi assim que eu
   "regenerei" nada e o gate required reprovou).
3. **Não faça `git checkout -B origin/main` na worktree suja** — medi **22 arquivos
   tracked** divergindo de main (de chips já mergeados); o checkout arrastaria trabalho
   alheio. Copie pra um worktree já em `main` limpo.
4. **Guarda de escopo no `git add`** — abortar se vazar fora do módulo.

## Aberto — decisão [W], nada bloqueado

Dois 🔴 com prova, que **não** foram consertados de propósito:

1. **`toggleAutoEmission` liga emissão automática de documento fiscal sem gate nenhum** —
   `grep -n "can(\|abort"` no `TributacaoController` → **0**; rota-group sem middleware de
   permissão. (NfeBrasil SDD §9)
2. **Assinatura com valor negociado nunca vira fatura** — `store()` casa plano por `ciclo`
   **e** `valor` exatos; sem match, `plan_id=null` e o gerador descarta com **uma linha de
   log, sem alarme**. 3 remédios que se anulam. (RecurringBilling SDD §9.1)

Herdado dos chips anteriores: `has_return` do Sells · censurar CPF no Cliente · global
scope no `Contact` · os 2 bugs do Ponto · import CSV do NfeBrasil resolvendo tenant 2×.

## Módulos que ainda NÃO têm SDD

Rode a porta viva antes de escolher — não confie nesta lista amanhã:

```bash
git ls-files "memory/requisitos/*/SDD-*.md" | sed -E 's#memory/requisitos/([^/]+)/.*#\1#' | sort -u
```
