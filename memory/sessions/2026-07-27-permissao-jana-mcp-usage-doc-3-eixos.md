---
date: "2026-07-27"
topic: "Documentação alinhada ao `jana.mcp.usage.all` (#4853 deixou a doc pra trás) — e a lápide §5 2026-07-12 acordando gate em 3 eixos, não 1"
authors: [C, W]
module: Jana
tags: [permissoes, spatie, documentacao, casos-gate, anchor-lint, charter, gates-diff-aware]
pii: false
---

# Permissão `jana.mcp.usage.all` na documentação — e os 3 eixos da lápide

> **Pedido do dono:** o [#4853](https://github.com/wagnerra23/oimpresso.com/pull/4853) (`632c5182e2`) migrou a permissão `copiloto.mcp.usage.all` → `jana.mcp.usage.all` em todo o PHP e deixou a camada de documentação para trás. Trocar a menção nos 42 arquivos, com atenção à [lápide §5 2026-07-12](../proibicoes.md) (tocar legado acorda gate diff-aware).
>
> **Entregue:** [PR #4886](https://github.com/wagnerra23/oimpresso.com/pull/4886) — 51 menções em 31 arquivos, **34/34 required verdes**. Aguarda merge [W] (R10).

---

## 1. O que foi medido antes de tocar

Repo completo (`git rev-parse --is-shallow-repository` = `false`, então as contagens sustentam conclusão — [§5 2026-07-24](../proibicoes.md)):

```
git grep -l "copiloto\.mcp\.usage\.all"            → 42 arquivos (26 .md + 16 .tsx)
git grep -l "copiloto\.mcp\.usage\.all" -- '*.php' → 0
```

**Oráculo de runtime** (não leitura): o `route:list` do CT 100 mostrava `can:copiloto.mcp.usage.all` porque o staging estava no snapshot `36432987` (2026-07-23), **anterior** ao #4853 — o código vivo diz `jana.mcp.usage.all` (`ForjaController`, `GovernancaController`, `QualidadeController`, `topnav.php`, `permissions.php`, seeder). Confirmado por `git grep` no PHP: 46 arquivos com o nome novo, 0 com o velho.

## 2. O critério de corte: claim em presente × história

O que separou os 31 tocados dos 11 não-tocados **não foi o tipo de arquivo** — foi o **tempo verbal**:

| natureza | exemplo | ação |
|---|---|---|
| claim em **presente** sobre o código atual | `Permissão X no construtor` · `middleware can:X` · `usuário sem X recebe 403` | **corrigir** — está errado |
| **história** datada | ADR `status: aceito` de 2026-04-30 · session log · errata | **não tocar** — estava certo na data |

Isso é a mesma régua da [§5 2026-07-16](../proibicoes.md) (*"prosa pode ter fato datado em passado; nunca afirmação em presente"*), aplicada à leitura em vez da escrita.

## 3. O achado: a lápide §5 2026-07-12 tem 3 eixos, não 1

A lápide fala em `memory/requisitos/*/SPEC.md`. **O alcance real é maior** — uma troca de string em comentário e doc acordou gate diff-aware em **três** dimensões independentes:

| eixo | gate | mordeu? | por quê |
|---|---|---|---|
| **SPEC** | `anchor-lint` + `anchor entry/covers` (required) | ❌ não | dívida do ProjectMgmt já no grandfather (`7 aceite + 7 teste isentos`); trocar string não cria mentira nova (`no-new-lie`) |
| **casos** | `Casos-coverage · ratchet` **G-6** (required) | ✅ **sim** | `if (tsxDate > lastRun)` — compara **data-git** do `.tsx` contra `last_run` do `casos.md`, **não o conteúdo**. Até comentário invalida |
| **charter** | `charter related_us join` (advisory) | ✅ **sim** | exige `related_us`; 127 de 238 charters (46,6%) não têm, protegidos por não-toque |

`distiller_freshness` **não move** — medido: `ProjectMgmt` e `TeamMcp` têm `BRIEFING.md` **sem carimbo `distilled_at`** → code path `if (!m) continue` (pendente, não stale); o da Jana está carimbado hoje.

**Lição perene:** medir *um* eixo da lápide e concluir "não morde" é incompleto por construção. A varredura tem que ser por **glob de gate diff-aware tocado**, não por intuição de qual gate é o relevante.

## 4. Os 4 grupos excluídos — e por que cada um

### 4.1 História (5) — bloqueio mecânico + doutrina

ADRs [0057](../decisions/0057-tela-team-admin-regras-governanca-tokens-mcp.md), [0059](../decisions/0059-governanca-memoria-estilo-anthropic-team.md), [0100](../decisions/0100-projectmgmt-ui-redesign.md) (todas `status: aceito` + `lifecycle: ativo`), 1 findings datado, 1 session log.

Conferi a **regex real** do `governance-gate` em vez de confiar na doutrina:

```
grep -E '^M\s+memory/decisions/[0-9]{4}-.*\.md$'
```

Editá-las reprovaria o merge. E elas registram a permissão **correta na data** — não são stale.

### 4.2 Os 2 RUNBOOKs da Jana — recusa por não-fabricação

`memory/requisitos/Jana/RUNBOOK-governanca-mcp.md` e `RUNBOOK-qualidade-admin.md` seguem stale (8 menções).

O glob `memory/requisitos/**/RUNBOOK*.md` **não tem `grace: true`** no `memory-schema-gate` → STRICT → `continue-on-error: false`. Tocá-los acorda dívida **pré-existente**:

```
/ must have required property 'owner'
/ must have required property 'last_validated'
/status must be equal to one of the allowed values   ("active"; enum é rascunho|ativo|arquivado|historical)
```

**Medido na árvore limpa: falham idênticos SEM a mudança.** Pagar exigiria inventar `owner` (enum `W|F|M|L|E` — atribuição de pessoa, soberania [W]) e `last_validated`, que o schema define como *"última data que rodou o RUNBOOK e o resultado bateu — dispara alerta se >30d"*. **Não rodei esses runbooks** — data ali seria claim fabricado alimentando alarme, a família de campo auto-declarado que o §5 já matou ([2026-07-01](../proibicoes.md), [2026-07-09](../proibicoes.md)).

### 4.3 Os 2 `.tsx` com `casos.md` — mesma recusa, outro campo

`Forja/Cockpit.tsx` e `Scorecard/Index.tsx`. A saída seria bumpar `last_run` — que afirmaria *"revalidei"* casos que são `⬜ manual` (exigem alguém abrir a rota). **Mesma fabricação recusada em 4.2; critério mantido nos dois lugares.**

Recibo do mecanismo: em `origin/main` o `.tsx` foi tocado em **2026-06-17** e `last_run: "2026-06-17"` — igual, não-stale (`>` é estrito). Qualquer commit meu re-data o arquivo.

### 4.4 Forja — a ressalva que se resolveu contra mim

Reportei ao [W] que `Cockpit.casos.md`/`Cockpit.charter.md` **não estavam corrigidos** (3 menções cada, nenhum PR aberto). **Estava certo naquele instante** — o [#4879](https://github.com/wagnerra23/oimpresso.com/pull/4879) ainda não existia. Ele mergeou depois e fez o trabalho **corretamente**, deixando **erratas** que citam o nome antigo de propósito:

> *"Errata 2026-07-27: este bloco dizia `copiloto.mcp.usage.all`. O nome mudou pra `jana.mcp.usage.all` no #4853…"*

Trocar a string ali **destruiria o sentido**. **Lição:** contei ocorrências e chamei de veredito; ler o que a ocorrência **diz** era parte da medição. Parente de [§5 2026-07-17](../proibicoes.md) (oráculo errado) na forma leve — o instrumento (`grep -c`) responde uma pergunta *parecida* com a feita.

## 5. As 3 falhas de CI que não eram defeito

Todas artefato de **base móvel** — o `main` mergeou 6 e depois 8 commits enquanto o CI rodava (429 runs enfileirados, 16 executando):

| falha | causa real |
|---|---|
| `Preflight + contratos ativos` | base atrás; a regra é literal — *"branch atrás → rebase"* |
| `baseline-tamper-guard` | o #4879 **encolheu** o `casos-coverage-baseline` (−5 órfãos UC-FORJA); minha branch carregava o baseline antigo → relativo ao main novo parecia ter **crescido**. **Meu PR não toca baseline nenhum** — conferido |
| `Casos-coverage · ratchet` | baseline mudou 220 → 215 no mesmo commit |

**Método que evitou retrabalho:** antes de agir em evento de CI, comparar o SHA da falha com o `headRefOid` do PR. Dois eventos descreviam estado morto — um de PR **fechado**, outro de **commit superado**.

## 6. Por que branch nova (o #4881 morreu)

O #4881 nasceu 2 commits atrás do main. Rebase+amend resolvia, mas exige **force-push** — barrado pelo hook `block-destructive` (pede autorização [W]). O `git reset --hard` também foi barrado.

Tentei o caminho **forward-only** (merge do main + revert dos `.tsx` em commit novo) e **não funciona**: o próprio commit de revert re-data o arquivo, e o G-6 segue vermelho. **Só história que nunca toca aqueles `.tsx` fecha o gate.** Daí branch limpa a partir do main — alternativa que o próprio hook sugere, sem operação destrutiva.

## 7. Estado final

[PR #4886](https://github.com/wagnerra23/oimpresso.com/pull/4886) · `679392fcd3` · 31 arquivos · 51 menções · **34/34 required VERDES**.

Única falha: `charter related_us join` — **advisory de fato**, verificado no dono da verdade (`governance/required-checks-baseline.json`, 34 contexts), porque nome de check pode mentir sobre enforcement ([§5 2026-07-16](../proibicoes.md)).

`strict: false` na proteção do main → branch atrás **não bloqueia** merge; `enforce_admins: true`.

Os 14 `.tsx` restantes são 100% linha de comentário (`0` linhas alteradas fora de `//`). Diff 51/51, sem dano de line-ending.

## 8. Resíduo honesto (decisão [W])

| # | item | custo | por que não fiz |
|---|---|---|---|
| 1 | 2 RUNBOOKs Jana (8 menções) | 3 linhas cada | `owner` + `last_validated` **não existem** no arquivo → seria invenção |
| 2 | 2 `.tsx` do G-6 (2 menções) | bump `last_run` | afirmaria revalidação de caso `⬜ manual` não rodado |
| 3 | `ProjectMgmt/BRIEFING.md` (grace) | 4 linhas | **é rename, não invenção** — `modulo`→`module`, `owner: Wagner [W]`→`W`, `updated`→`updated_at`, prosa→`parcial`+`status_nota`. Mas é **outro intent** e é o caso literal da lápide §5 2026-07-12 (backfill de frontmatter legado) |
| 4 | 5 charters sem `related_us` | — | `Board/DetailSheet` **documenta a omissão** ("História PMG-004, não é ID `US-`"); nos outros seria inventar ID, e anti-padrão inventado em charter é pior que ausente |

## 9. Proposta ao [W] — emenda de lápide

A §5 2026-07-12 documenta **só o eixo SPEC**. Esta sessão mostrou 3 eixos (§3). Sugiro emenda append-only registrando que a classe é *"tocar legado acorda gate diff-aware"* **genérica**, com a receita: antes de tocar legado, **enumerar os globs de gate diff-aware que o arquivo casa** e medir cada um — não só o que a lápide cita.

**Não escrevi por conta própria** — o ledger é do agente, mas emenda de lápide existente em `proibicoes.md` é append-only sobre canon, decisão [W].
