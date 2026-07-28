---
id: governance-realocacao-documental
---

# Realocação documental — runbook

> Porta operacional única para classificar, mover e religar documentos. O fluxo
> implementa as três camadas da [ADR 0334](../decisions/0334-modelo-3-camadas-invariante-anti-atrofia-inteligencia-negocio.md)
> sem fazer migração mecânica em massa.

## Regra de segurança

Trabalhe com **um arquivo ou um lote pequeno e coeso por vez**. A ordem é obrigatória:

```text
classificar → revisar → adversário → dry-run → git mv + relink → validar → commit
```

O classificador propõe. O adversário pode impedir. Só o executor movimenta. O executor
exige worktree limpa, usa `git mv`, limita os paths alteráveis ao plano e restaura o
estado anterior se qualquer verificação falhar.

## Legado com referrer append-only — `move-with-tombstone`

Um documento legado pode ter **referrers append-only** (ADR, session, handoff) que citam o
path antigo. Reescrever esses links é proibido (Tier 0 — histórico imutável), então um move
normal é **rejeitado** pelo adversário (`IMMUTABLE_REFERRER` / `MISSING_REWRITE`), corretamente.

O modo **`move-with-tombstone`** (proposal `estrutura-canon-memoria` §II.5 passo 7) destrava
esse caso: move o conteúdo para o destino canônico **e deixa um _stub_ de redirecionamento no
path antigo**. Como o path antigo continua existindo, os links que apontam para ele **seguem
resolvendo sem qualquer edição**. Referrers **livres** são relinkados para o canônico; o stub
serve os **não-relinkáveis**, que são de duas classes:

- **append-only** (`memory/decisions|sessions|handoffs/`) — editar viola Tier 0 (`IMMUTABLE_REFERRER`);
- **sob gate diff-aware** (`memory/requisitos/**`, `resources/js/Pages/**/*.charter.md`) — relinkar
  põe o arquivo no diff e/ou muda a data-git do doc mais novo do módulo, acordando anchor-lint /
  schema / `distiller_freshness` sobre dívida pré-existente (lápide 2026-07-12); barrado por
  `GATE_GUARDED_REFERRER`. `memory/reference/*.md` **não** entra (schema em grace/warn-only, fora
  do ratchet) — é relinkado normalmente.

Regras que o adversário aplica (todas com selftest que morde):

- o stub só é aceito quando existe **de fato** um referrer não-relinkável (`TOMBSTONE_UNJUSTIFIED`
  caso contrário — não é escape-hatch para pular relink de referrer livre);
- a isenção de relink é **escopada aos não-relinkáveis**: referrer livre não declarado ainda
  quebra o plano (`MISSING_REWRITE`);
- isenção de relink **não** é permissão de editar: declarar rewrite sobre append-only continua
  `IMMUTABLE_REFERRER`; sobre arquivo gate-guarded, `GATE_GUARDED_REFERRER`.

Gerar o plano (opt-in explícito via `--tombstone`; sem a flag o lote **exclui** esses sources):

```powershell
# lote coeso de uma pasta legada inteira:
npm run --silent docs:relocation:classify -- --dir memory/comparativos --tombstone > $docPlan
# ou um source só:
npm run --silent docs:relocation:classify -- --source memory/ARQUIVO.md --tombstone > $docPlan
```

O executor grava o stub após o `git mv` e valida no pós-check que o path antigo permanece com o
marcador `tombstone: true`. O commit ganha um trailer `Document-Tombstone: <antigo> -> <novo>`
além do `Document-Move`, e o `docs:relocation:history` continua listando o deslocamento.

> Regressão evitada (lápide 2026-07-12): o stub mora sob a pasta legada (ex.: `memory/comparativos/`)
> e o alvo sob `memory/research/` — nenhum dos dois casa glob de gate diff-aware. E, por deixar os
> referrers gate-guarded apontando para o stub, o diff **não inclui** nenhum arquivo sob
> `memory/requisitos/**` — logo não acorda `anchor-lint`, `memory-schema-gate` (SPEC/RUNBOOK/BRIEFING/
> tópico/charter) nem `distiller_freshness` (que keia na data-git de qualquer `.md` do módulo, via
> `git log -1 --format=%cs`). **Provar antes** de cada convergência — listar os arquivos relinkados e
> confirmar 0 sob glob de gate — faz parte do passo 9.

## As três camadas

| Camada | Conteúdo | Porta-mãe |
|---|---|---|
| Produto ERP | módulos usados pelo cliente | `memory/requisitos/<Modulo>/BRIEFING.md` |
| Produto IA | Jana e memória do negócio do cliente | `memory/requisitos/Jana/BRIEFING.md` |
| IA-OS | processo de construção, gates, hooks e governança | [ADR 0094](../decisions/0094-constituicao-v2-7-camadas-8-principios.md) |

## Procedimento

### 1. Gere e leia o plano

```powershell
$docPlan = Join-Path ([System.IO.Path]::GetTempPath()) 'oimpresso-plano-realocacao.json'
npm run --silent docs:relocation:classify -- --source memory/ARQUIVO.md > $docPlan
```

Confira no JSON: `target`, `classification.layer`, `classification.door`, `confidence`
e todos os `rewrites`. O plano fica fora do repositório porque o executor exige worktree
limpa. Para indicar uma pasta de destino já existente — o arquivo alvo ainda não pode existir:

```powershell
npm run --silent docs:relocation:classify -- --source memory/ARQUIVO.md --target memory/governance/arquivo.md > $docPlan
```

Não aprove plano com receita possivelmente antiga sem revisão humana. Não mova ADR,
session, handoff, porta canônica nem arquivo gerado: o adversário deve rejeitá-los.

### 2. Rode a contraprova e o ensaio

```powershell
npm run docs:relocation:adversary -- --plan $docPlan
npm run docs:relocation:execute -- --plan $docPlan
```

O primeiro comando precisa retornar `APPROVE`; o segundo, `DRY_RUN_OK`. `REJECT` não pode
ser sobreposto pelo agente.

`REVIEW` (confiança < 0,90) só destrava com **aprovação humana assinada no plano** — o
adversário valida de verdade (reviewer autorizado `W/F/M/L/E` + sha256 do plano canônico;
hash errado é `APPROVAL_INVALID` e vira `REJECT`). Editar `confidence` na mão não libera nada:

```powershell
# 1) obter o hash que o revisor assina (muda se o plano mudar):
npm run --silent docs:relocation:adversary -- --plan $docPlan --digest
# 2) o REVISOR adiciona ao JSON do plano:
#    "approvals": [{ "reviewer": "W", "date": "2026-07-22", "plan_sha256": "<hash do passo 1>" }]
```

### 3. Aplique e inspecione

```powershell
npm run docs:relocation:execute -- --plan $docPlan --apply
git diff --cached --stat
git diff --cached --check
```

Nesse ponto a mudança está apenas staged. Confira se o arquivo antigo sumiu, o destino
existe, os links vivos apontam para a autoridade correta e documentos históricos estão
marcados como históricos. Para uma operação que não exige curadoria posterior, prefira
combinar `--commit` com `--apply`: o recibo é gravado automaticamente.

Se houver curadoria manual entre apply e commit, copie do dry-run os três trailers
`Document-Plan-SHA256`, `Document-Base-SHA` e `Document-Move` para o commit. Sem eles o
movimento não aparecerá em `docs:relocation:history`.

### 4. Valide e consulte o rastro

```powershell
npm run docs:relocation:execute:selftest
npm run docs:relocation:adversary:selftest
node scripts/governance/onboarding-paths-check.mjs
node scripts/governance/system-map.mjs --check
npm run docs:relocation:history
git log --follow -- memory/governance/arquivo.md
```

`docs:relocation:history` é a lista durável dos deslocamentos. Ela deriva dos trailers
`Document-Plan-SHA256`, `Document-Base-SHA` e `Document-Move` gravados no Git; não existe
um segundo ledger manual para ficar divergente.

## Quando parar

Pare sem mover se ocorrer qualquer um destes casos:

- confiança abaixo de 0,90 ou receita possivelmente stale;
- backlink ou link de saída sem rewrite exato;
- destino colide, não existe ou discorda da camada/porta;
- seria necessário editar histórico append-only;
- o plano foi gerado em outro SHA;
- a worktree contém mudanças não relacionadas;
- `CONFLICTING_REWRITE` (residual): um `literal-path` (replace do caminho **cru**) coexiste com
  uma reescrita **estruturada** (`markdown-link`/`code-span`) do mesmo literal no mesmo arquivo —
  o replace cru sobrepõe as ocorrências `](from)`/`` `from` ``. O classificador nunca gera esse par
  (o extrator pula o que já é estruturado); é backstop para plano hand-crafted. Resolva à mão.
  > O relink é **contexto-consciente** (`searchReplaceFor`): o mesmo literal como `[x](arq.md)`
  > (markdown-link → `./rel`) **e** `` `arq.md` `` (code-span → `root/path`) recebe destinos
  > diferentes sem colidir. Foi assim que o lote `memory/comparativos/` (2026-07-22), densamente
  > cruzado com estilos de link mistos, deixou de cair em `CONFLICTING_REWRITE` e convergiu.

O rollback automático cobre falhas durante a execução. Se a operação já foi commitada,
a correção é um novo commit explícito; não apague nem reescreva o histórico publicado.
