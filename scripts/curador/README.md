# Curador — pipeline de ingestão de conhecimento

> Ler todo o computador → separar por usuário → organizar → alimentar o oimpresso.
> Heurística-first (70-80% determinístico), Claude-second (só ambíguos), humano-gateado.
> ADR canônico: [0124](../../memory/decisions/0124-curador-conhecimento-pipeline.md).
> Skill no Claude Code: `/curador <subcomando>` (`.claude/skills/curador/SKILL.md`).

## Por que existe

Wagner tem `D:\Conhecimento` (3.253 arquivos curados + 79k arquivos de clones OSS) e prevê expandir pra **computador todo** e depois **empresa toda**, separando por usuário (Wagner/Maiara/Felipe/Luiz/Eliana[E]).

Triagem manual da sessão 2026-05-09 (em `D:\Conhecimento\_TRIAGEM-2026-05-09.md`) revelou que **96% do volume é descartável determinístico** (clones OSS, node_modules) e **apenas 4% precisa decisão semântica**. Curador aplica isso programaticamente.

## Arquitetura

```
DISCOVER → CLASSIFY → REPORT → REVIEW (humano) → APPLY
discover.mjs  classify.mjs  report.mjs    batch.md          apply.mjs
                                              ↑
                                    Wagner marca [x]
                                       em cada row
```

**Estado persistente** em `db/*.jsonl` (gitignored). Sobrevive `/clear`, `/compact`, reboot.

## Quick start

### 1. Setup (1×)

```bash
cp scripts/curador/config.example.json scripts/curador/config.json
# (opcional) editar paths
```

### 2. Scan da pasta D:\Conhecimento

```bash
node scripts/curador/discover.mjs --source "D:\Conhecimento" --user wagner
```

**Saída esperada:** `[discover] DONE in ~XXs added=3253 errored=0`

### 3. Classificação automática

```bash
node scripts/curador/classify.mjs
```

Aplica 18 heurísticas → `db/classifications.jsonl`. Imprime contagem por bucket + `pct_auto_classified`.

### 4. Gera relatórios markdown

```bash
node scripts/curador/report.mjs --batch-size 500
```

Cria `D:\Conhecimento\_TRIAGEM\YYYY-MM-DD-batch-001.md`, `batch-002.md`, etc. Cada batch ≤500 itens, agrupado por bucket.

### 5. Wagner revisa cada batch

Abre `batch-001.md`, marca `[x]` na coluna **Approve** dos itens que quer aplicar:

```markdown
| Approve | Path | Size | Rule | Destination | Flags |
|:-:|---|--:|---|---|---|
| [x] | `D:\...\Itau\Cnab240_Itau.pdf` | 4.0MB | cnab_Itau | memory/requisitos/Financeiro/CNAB-Itau/ | |
| [ ] | `D:\...\Foo.txt` | 100B | short_scrap | - | |
```

`[ ]` = pula. `[x]` = aplica. Default seguro (nada é executado se não marcar).

### 6. Aplicar

```bash
node scripts/curador/apply.mjs --batch 2026-05-09-001 --approved
# OU dry-run primeiro:
node scripts/curador/apply.mjs --batch 2026-05-09-001 --approved --dry-run
```

Move sensitive pra `_VAULT-PENDING/`, copia memory/user pro repo + `git add` (NÃO commita), move discard pra `_DESCARTADO/` (quarentena).

### 7. Wagner commita

```bash
git status   # ver o que foi adicionado
git commit -m "feat(curador): batch 2026-05-09-001 ingested

Refs: curador-2026-05-09-001"
```

## Multi-usuário (LGPD)

Pra scanear pasta de outro dev, exige opt-in registrado:

```bash
/curador consent maiara   # registra autorização da Maiara em db/consent.jsonl
node scripts/curador/discover.mjs --source "C:\Users\Maiara\Documents" --user maiara
```

Sem entrada em `consent.jsonl`, `discover.mjs --user <outro>` aborta.

## Buckets

| Bucket | Destino | Reversível |
|---|---|---|
| `sensitive` | `D:\Conhecimento\_VAULT-PENDING\<cat>\` | manual |
| `discard` | `D:\Conhecimento\_DESCARTADO\` (quarentena) | sim, até deletar |
| `memory` | `<repo>/memory/requisitos/<Mod>/` | git revert |
| `user` | `<repo>/memory/users/<user>/` | git revert |
| `spec` | task MCP (manual) | `tasks-update status:archived` |
| `ambiguous` | aguarda Claude na fase REVIEW | n/a |

## Heurísticas (18 — `lib/rules.mjs`)

Ver [ADR 0124 §"Anti-padrões catalogados"](../../memory/decisions/0124-curador-conhecimento-pipeline.md).

## FAQ

**Q: O Curador commita sozinho?**
Não. `git add` apenas. Wagner commita preservando `commit-discipline` (1 PR = 1 intent ≤300 linhas).

**Q: Posso desfazer?**
- `discard` → quarentena em `_DESCARTADO/`, `move` de volta funciona
- `memory/user` → `git restore --staged <path>` antes de commitar; após commit, `git revert`
- `sensitive` → manual move de volta de `_VAULT-PENDING/`

**Q: O que faço com `bucket=ambiguous`?**
Use `/curador review <batch-id>` — Claude lê esses itens e propõe classificação refinada.

**Q: Re-scan da mesma pasta vai duplicar?**
Não. `discover.mjs` é idempotente (skipa paths já em `db/files.jsonl`). Pra re-classificar, use `classify.mjs --reclassify`.

**Q: E se a heurística errar?**
Marque o item `[ ]` (pula) no batch.md. Edite manualmente depois OU adicione regra em `lib/rules.mjs` e roda `classify.mjs --reclassify`.

## Métricas de saúde

- `pct_auto_classified` ≥ 70% (senão regras estão fracas)
- `sensitive_count` baixo (alto = root scan errado?)
- `dedupe_count` (alto = origem caótica)
- `ambiguous_count` ≤ 30% (senão Claude vira gargalo)

## Roadmap

- **MVP (atual):** discover/classify/report/apply + 18 regras + skill + ADR
- **Fase 2:** Office meta owner detection, modo `--meilisearch` (indexa em vez de migrar git)
- **Fase 3:** daemon background, UI web em `/copiloto/admin/curador`, sync Hostinger/CT 100/OneDrive
