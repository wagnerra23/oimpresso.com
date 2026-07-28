---
date: "2026-07-27"
slug: permissao-jana-mcp-usage-doc-3-eixos
tldr: "Documentação alinhada ao `jana.mcp.usage.all` que o #4853 já pôs no PHP — PR #4886 com 51 menções em 31 arquivos, 34/34 required VERDES, aguardando merge [W]. Achado: a lápide §5 2026-07-12 (tocar legado acorda gate diff-aware) tem 3 eixos, não só o SPEC que ela cita — casos/G-6 e charter/related_us também morderam. 11 arquivos ficaram de fora por 4 razões distintas, 2 delas recusa de fabricar `last_validated`/`last_run` que eu não medi."
time: "17:50"
topic: "Permissão jana.mcp.usage.all na doc (#4853 deixou pra trás) + a lápide §5 2026-07-12 acordando gate em 3 eixos"
authors: [C, W]
type: handoff
module: Jana
pii: false
prs:
  - 4886
  - 4881
us: []
related_adrs:
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0341-memory-schema-charter-spec-required
next_steps:
  - "[W] mergear o #4886 (34/34 required verdes; única falha é advisory)"
  - "[W] decidir os 4 resíduos honestos (2 RUNBOOKs Jana, 2 .tsx do G-6, BRIEFING ProjectMgmt, 5 charters sem related_us)"
  - "[W] decidir se quer a emenda da lápide §5 2026-07-12 registrando os 3 eixos"
---

# Handoff — permissão na doc + os 3 eixos da lápide

## O que entrou

**[PR #4886](https://github.com/wagnerra23/oimpresso.com/pull/4886)** · `679392fcd3` · **51 menções em 31 arquivos** · **34/34 required VERDES** · aguarda merge [W] (R10).

O [#4853](https://github.com/wagnerra23/oimpresso.com/pull/4853) migrou `copiloto.mcp.usage.all` → `jana.mcp.usage.all` em todo o PHP (0 PHP com o nome velho, medido) e deixou a doc pra trás (42 arquivos).

Critério de corte: **claim em presente × história**. Corrigi o que afirma sobre o código atual (`Permissão X no construtor`, `usuário sem X recebe 403`); não toquei o que registra a permissão **correta na data** (ADR aceita, session log, errata).

## O achado que vale pra próxima sessão

**A lápide §5 2026-07-12 tem 3 eixos, não 1.** Ela fala em `memory/requisitos/*/SPEC.md`; uma troca de string em comentário acordou gate diff-aware em três dimensões:

| eixo | gate | mordeu? |
|---|---|---|
| SPEC | `anchor-lint` + `entry/covers` (required) | ❌ grandfather cobre (`7 aceite + 7 teste isentos`) |
| **casos** | `Casos-coverage · ratchet` **G-6** (required) | ✅ `if (tsxDate > lastRun)` — **data-git**, não conteúdo |
| **charter** | `charter related_us join` (advisory) | ✅ 127/238 charters sem `related_us` |

`distiller_freshness` não move (ProjectMgmt/TeamMcp têm BRIEFING **sem `distilled_at`** → `if (!m) continue`).

**Receita:** antes de tocar legado, enumerar **os globs de gate diff-aware que o arquivo casa** e medir cada um. Medir só o eixo que a lápide cita é incompleto por construção.

## Duas recusas de fabricação (mesmo critério, 2 lugares)

1. **RUNBOOKs Jana** — `owner` e `last_validated` **não existem** no frontmatter. `last_validated` é definido como *"última data que rodou o RUNBOOK e o resultado bateu — dispara alerta se >30d"*. Não rodei.
2. **2 `.tsx` do G-6** — bumpar `last_run` afirmaria revalidação de caso `⬜ manual` não rodado.

Em ambos: gate exige campo, campo é claim de verificação, eu não verifiquei → **não preencho**. Fica resíduo honesto.

## Erro meu, registrado

Reportei que os arquivos da Forja "não estavam corrigidos" contando `grep -c`. Estava certo **naquele instante** (o #4879 não existia ainda), mas o #4879 mergeou e deixou **erratas** que citam o nome antigo **de propósito** — trocar destruiria o sentido. **Contar ocorrência não é ler o que ela diz.**

## 3 falhas de CI que não eram defeito

`Preflight`, `baseline-tamper-guard` e `Casos-coverage` falharam por **base móvel** (main andou 6 e depois 8 commits; 429 runs enfileirados / 16 executando). Meu PR **não toca baseline nenhum** — o tamper-guard acusava porque o #4879 **encolheu** o baseline e minha branch carregava o antigo.

**Método:** antes de agir em evento de CI, comparar o SHA da falha com o `headRefOid`. Dois eventos descreviam estado morto (um de PR fechado, outro de commit superado).

## Por que o #4881 morreu

Base atrás + G-6. Rebase+amend exige **force-push** (barrado pelo hook `block-destructive`); `reset --hard` idem. O caminho forward-only **não resolve o G-6** — o commit de revert re-data o arquivo. Só história que nunca toca o `.tsx` fecha o gate → branch limpa do main, sem operação destrutiva.

## Resíduo (decisão [W])

| # | item | natureza |
|---|---|---|
| 1 | 2 RUNBOOKs Jana (8 menções) | exigiria **invenção** |
| 2 | 2 `.tsx` do G-6 (2 menções) | exigiria **invenção** |
| 3 | `ProjectMgmt/BRIEFING.md` (grace, warn-only) | **é rename, não invenção** — 4 linhas prontas; mas é outro intent + caso literal da lápide |
| 4 | 5 charters sem `related_us` (advisory) | `DetailSheet` **documenta a omissão**; nos outros seria inventar ID |

## Estado MCP no momento do fechamento

- `my-work` (@wagner): **10 tasks**, todas em REVIEW — US-TR-309/310/305/306/311/307, US-PG-008, US-PROD-027/025, US-FIN-023
- `cycles-active`: **timeout** (MCP error -32001) — registro a falha em vez de inventar o estado
- `sessions-recent`: 5 session logs em 2026-07-27 (`arte-permissoes-ia-erp`, `auditoria-camada1-sdd-mordida`, `orfaos-ligados-elo-hitl`, `produto-3-achados-tier0-fechados`, `sdd-produto-fluxos-sem-tela`) — conferido por `ls` que nenhum cobre este tema (anti-duplicação)
- Handoffs anteriores hoje: `0905-sdd-produto-fechado-cadeia-requisitos`, `1135-produto-3-achados-tier0-fechados`, `1445-orfaos-ligados-elo-hitl`
- Proteção do main (live): **34 required contexts**, `strict: false`, `enforce_admins: true`

## Próximo passo

Merge do #4886 é seu (R10). Depois, os 4 resíduos e a eventual emenda da lápide.

Session log: [`2026-07-27-permissao-jana-mcp-usage-doc-3-eixos.md`](../sessions/2026-07-27-permissao-jana-mcp-usage-doc-3-eixos.md).
