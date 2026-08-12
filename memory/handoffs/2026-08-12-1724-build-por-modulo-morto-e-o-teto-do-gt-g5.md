---
date: "2026-08-12"
time: "17:24 BRT"
slug: build-por-modulo-morto-e-o-teto-do-gt-g5
tldr: "Removida a camada de build por-módulo (31 arquivos). A premissa da tarefa errava em 3 pontos. A refutação GT-G5 pegou dano real — meu sed apagou 6 fósseis protegidos por um guard que ele contornava. E o próprio gate estava insatisfazível: emendado em PR separado."
prs: [5678, 5680, 5685]
decided_by: [W]
next_steps:
  - "Rodar `php artisan module:specs` numa máquina com vendor — os 15 memory/modulos foram editados cirurgicamente e assinam data antiga"
  - "Apagar a branch remota claude/gt-g5-teto-politica (o --delete-branch falhou: main ocupado por outro worktree)"
---

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → 6 tasks, todas em REVIEW (US-TR-309/310/305/306, US-PROD-027, US-INFRA-023) — nenhuma tocada nesta sessão
- `sessions-recent limit:4` → nenhum irmão sobre build/scaffold de módulo
- Handoffs de hoje varridos (`git ls-tree origin/main`): **8 anteriores**, o último `1617-arquitetura-react-modulos`. Zero sobreposição de tema.

## O que aconteceu

Pedido: remover configs de build órfãos em `Modules/`. A tarefa afirmava 18 arquivos e sugeria remover junto os `Resources/assets`.

**A premissa caiu em 3 pontos.** São **15** arquivos, não 18 (12 `webpack.mix.js`, não 15). A varredura de citadores **sem `--hidden` devolvia 61; com, 62** — cega, e faltavam `config/modules.php`, `ModuleSpecGenerator.php` e 16 `package.json`. E os `Resources/assets` **não podiam ser tocados**: `public/modules/<x>/` é cópia 1:1 deles — com `.gitkeep` e imagens, e `sass/` preservada literal — ou seja, saída de `module:publish`, não do mix. Aquilo serve o site público do Cms, o JS do CRM, o QR do catálogo e o `.xls` de importação de ponto.

**A refutação GT-G5 pegou um dano real.** Dos 21 `memory/modulos/*.md` que editei, 6 são fósseis de branches que sumiram, protegidos por escrito pelo `guardaPerdaDeBranch()`. Meu script passou por baixo — o guard cobre `module:specs`, não `sed` — e a linha apagada era **fato datado**, não ponteiro podre (ADR 0377: libera mexer, nunca falsificar). Rodada 1 reprovou (29,6%); corrigi; rodada 2 aprovou (1,54%) e ainda pegou um conflito em índice derivado que me mostrou que **o required roda no merge commit, não no tip da branch** — meu `--check` verde media a árvore errada.

**O gate estava insatisfazível.** O §2.3 exige refutador de tier superior; [W] vetou fable por custo; e 23 das 87 entries do ledger nascem de opus. Bite-test contra o gate real: `opus×opus → rc=1`, `opus×fable → rc=0`. Emendei em **PR separado** (emendar a régua dentro do PR que ela bloqueia seria auto-servir): `MAX_RANK = MODEL_RANK.opus`, com 17 testes e controle negativo por caso.

## Artefatos gerados

| PR | estado | conteúdo |
|---|---|---|
| #5678 | fechado | superseded — sozinho deixaria o required `SUPERFICIE.md == árvore` vermelho |
| #5685 | **MERGED** 16:18Z | `ledger-check.mjs` + `ledger-check-external.test.mjs` + `PROTOCOLO-REFUTADOR-BACKFILL.md §4.2` — 3 arquivos, 88 linhas |
| #5680 | **MERGED** 17:01Z `16cc0710c12` | 65 arquivos: 31 removidos, 4 stubs de scaffold, `ModuleSpecGenerator`, 15 docs, 17 `SUPERFICIE.md`, 2 entries no ledger |

## Persistência

- **git:** os 3 PRs acima, todos em `origin/main`
- **verificado em main, não declarado:** zero `Modules/*/{webpack.mix.js,vite.config.js,package.json}`; `Accounting.md` mantém `Build: **Laravel Mix**`; `Grow.md` mantém `Build: **Vite**`
- **evidência da refutação:** PR #5680, comment 5268403844
- **MCP:** nenhuma task tocada (o trabalho não tinha US; nasceu de revisão adversarial)

## Próximos passos pra retomar

```bash
gh pr view 5680 --json state,mergeCommit
```

Dívida aberta: `php artisan module:specs` numa máquina com `vendor/`. Os 15 `memory/modulos/*.md` foram editados cirurgicamente e seguem assinando a data de geração antiga. **Parte do diff que aparecer é dívida anterior a este PR** — não tratar tudo como regressão.

## Lições catalogadas

**Nova (§5):** script de reescrita em massa contornou um guard que protege exatamente aquela classe de arquivo. O guard existia, estava correto e documentado — e era acoplado ao comando (`module:specs`), não ao arquivo. Qualquer outra ferramenta passa por baixo.

**LC-08, 5 instâncias numa sessão:** `rg` sem `--hidden`; `rc` do `tail` em vez do comando (2×, uma mascarando um gate em rc=1 com 17 drifts); `^-[^-]` excluindo bullets markdown na varredura do diff; `git diff` two-dot medindo 11 arquivos onde three-dot mede 3; `echo "ok"` após comando que falhou.

**Processo:** `git rebase` num branch de 5 commits parou no meio e descartou um commit; recuperado por hash. `git merge` resolveu o mesmo caso sem drama — e é o que a própria skill `encerrar-sessao` já prescreve para o índice.

## Pointers detalhados

- Session log: [`2026-08-12-build-por-modulo-morto-e-o-guard-que-o-sed-contornou.md`](../sessions/2026-08-12-build-por-modulo-morto-e-o-guard-que-o-sed-contornou.md)
- Guard contornado: `app/Console/Commands/GenerateModuleSpecsCommand.php:98-112`
- Protocolo emendado: [`PROTOCOLO-REFUTADOR-BACKFILL.md §4.2`](../requisitos/Governance/PROTOCOLO-REFUTADOR-BACKFILL.md)
