# Handoff 2026-07-28 13:10 — varrer o não-salvo achou uma porta viva mentindo verde

> **Delta** do [handoff das 12:30](2026-07-28-1230-baseline-vozdocliente-medicao-vira-artefato.md).
> Um PR: [#4940](https://github.com/wagnerra23/oimpresso.com/pull/4940).

## O achado

`requisitos-status.mjs` resolvia `resources/js/Pages/${mod}` **cru**. Para `TeamMcp` a
pasta é `team-mcp` — então a porta imprimia **"0 telas / nenhuma lacuna" sobre um módulo
inteiro**. Não é cosmético: é porta viva **mentindo verde**.

| | Telas | com `casos.md` | UC declarados |
|---|---:|---:|---:|
| `main` antes | 0 | 0 | 0 |
| com o fix | **5** | **2** | **22** |

Fonte única, não cópia: importa `PAGES_NS` de `module-surface.mjs` (o mapa já existia) em
vez de redeclarar. **Alcance medido: 6 módulos** com namespace divergente do nome — ADS,
Governance, KB, NFSe, Superadmin, TeamMcp.

⚠️ `requisitos-status.mjs` **não tem selftest** (conferido: o `.test.mjs` não existe). A
prova é o antes/depois rodado, não fixture. Construir o selftest é trabalho próprio.

## A lição de método — varra o não-salvo ANTES de encerrar

"Salvar tudo" me levou a varrer os **52 arquivos tracked modificados** da worktree. O
resultado justifica o hábito:

| classificação | quantos |
|---|---:|
| resíduo **idêntico** ao main | **42** |
| diferiam do main | 10 |
| **valor real entre os 10** | **1** (este fix) |

Os outros 9, classificados um a um:
- `settings.json` — **era só EOL**, zero conteúdo (`--ignore-cr-at-eol` zerou o diff)
- `governance-script-tests.yml` — versão antiga, com o step duplicado que eu já removi
- `gates-registry.json` — local cataloga `cliente-pest.yml` e `oficinaauto-pest.yml`, que
  **não existem no main** (lanes removidas). Commitar registraria **gate fantasma**
- `Cliente/SPEC.md` + `SDD` — divergência de 1 e 3 linhas, cosmética
- 2 `SUPERFICIE.md` — derivados stale
- `_HOOKS-INDEX.md` — **sem diff nenhum**

## ⚠️ Duas armadilhas de MEDIÇÃO nesta varredura (as duas quase mentiram)

1. **`git show origin/main:<path>` no Git Bash** devolveu vazio para paths começando com
   `.` (`.claude/`, `.github/`) — MSYS mangleia o `:`. O diff então mostrava o arquivo
   **inteiro** como adição, sugerindo trabalho novo onde não havia. O instrumento correto
   é `git diff origin/main -- <path>` (sem revspec com `:`). Lição já catalogada em
   `memory/` — reincidi mesmo assim.
2. **Contagem `+N/-N` idêntica em arquivo inteiro é assinatura de line ending**, não de
   conteúdo. `--ignore-cr-at-eol` separa o joio: o `settings.json` zerou.

## Higiene de stash

Usei `git stash` pra medir o antes/depois. Conferi a pilha depois (lápide **LC-12**): as
entries são de **outras branches** (`claude/pr6-paymentgateway-redistill`,
`claude/improve-readme-9641db`) — a minha foi criada e consumida. Confirmar é barato;
consumir stash alheio não.

## Estado MCP

- `my-work` → 4 em REVIEW (`US-COPI-123` p0 · `US-TR-309` · `US-TR-310` · `US-PG-008`)
- 8 PRs desta sessão: 7 MERGED + `#4940` com auto-merge armado

## Aberto — segue com [W]

Inalterado: `toggleAutoEmission` liga emissão fiscal automática **sem gate**; assinatura
com valor negociado **nunca vira fatura**.
