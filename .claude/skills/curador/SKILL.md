---
name: curador
description: ATIVAR quando user pedir "ingerir conhecimento", "triar D:\\Conhecimento", "organizar arquivos do computador", "ler tudo e classificar", "/curador <subcomando>", OU mencionar ingestão de batch de docs/manuais/PDFs pra alimentar Jana/oimpresso. Pipeline 5-fase (DISCOVER→CLASSIFY→REPORT→REVIEW→APPLY) com heurística-first (70-80% determinístico) e Claude-second (só itens ambíguos). Estado persistente em `scripts/curador/db/files.jsonl` (sobrevive /clear, /compact, reboot). NUNCA aplica sem aprovação humana batch-a-batch. Sensitive (.env, .pfx, .rdp, .key, XML cliente) BLOQUEIA commit. Multi-usuário consent-first (LGPD). ADR 0124.
tier: B
---

# Curador — pipeline canônico de ingestão

> **Status:** Tier C — invocada via slash command `/curador <subcomando>`
> **ADR:** [0124](../../../memory/decisions/0124-curador-conhecimento-pipeline.md)

## Subcomandos

### `/curador status`

Mostra estado atual do pipeline:
- contagens por bucket no `db/files.jsonl`
- batches pendentes em `db/batches.jsonl` (status: pending/reviewed/approved/applied)
- sensitive flagged aguardando Vaultwarden
- métricas de saúde (pct_auto_classified, dedupe_count, ambiguous_count)

```bash
node scripts/curador/discover.mjs --stats
```

### `/curador scan <path> [--user <nome>]`

Roda **DISCOVER + CLASSIFY + REPORT** em sequência:

```bash
node scripts/curador/discover.mjs --source "<path>" --user "<nome>"
node scripts/curador/classify.mjs
node scripts/curador/report.mjs --batch-size 500
```

Default `--user wagner` (assume pasta do Wagner). Pra outro usuário, **exige consent registrado** ([ADR 0124](../../../memory/decisions/0124-curador-conhecimento-pipeline.md) §6 LGPD):

```bash
/curador consent maiara  # registra opt-in antes de scanear C:\Users\Maiara\
```

### `/curador review <batch-id>`

Abre `D:\Conhecimento\_TRIAGEM\YYYY-MM-DD-batch-NNN.md`. Claude lê **só os itens `bucket=ambiguous`** e propõe classificação refinada (não toca nos já determinísticos). Wagner marca aprovação com `[x]` em cada item.

> **Anti-padrão:** Claude NÃO sobrescreve classificação determinística. Se rule_matched != null, não muda — só itens com `bucket=ambiguous` ficam pra Claude decidir.

### `/curador apply <batch-id>`

Após Wagner aprovar o batch.md, executa:

```bash
node scripts/curador/apply.mjs --batch <id> --approved
```

- `sensitive` → move pra `D:\Conhecimento\_VAULT-PENDING\<categoria>\` (Wagner move pro Vaultwarden manualmente)
- `discard` → move pra `D:\Conhecimento\_DESCARTADO\` (quarentena, não `rm`)
- `memory` → copia pra `memory/requisitos/<Mod>/` + `git add` (NÃO commita; Wagner commita preservando `commit-discipline` 1 PR = 1 intent ≤300 linhas)
- `user` → copia pra `memory/users/<user>/`
- `spec` → cria task no MCP via `tasks-create` (só se Wagner marcou explícito; default = ambiguous → Wagner descarta ou converte)

### `/curador consent <user>`

Registra opt-in pra scanear pasta de outro dev. Append em `db/consent.jsonl`:

```json
{"user":"maiara","granted_by":"maiara","granted_at":"2026-05-09T14:30:00-03:00","scope":"C:\\Users\\Maiara\\Documents","authorized_by":"wagner"}
```

> **Bloqueio duro:** sem entrada em `consent.jsonl`, `discover.mjs --user <outro>` aborta com erro.

## Buckets canônicos (do ADR 0124)

| Bucket | Destino | Nunca commita | Reversível |
|---|---|---|---|
| `sensitive` | `D:\Conhecimento\_VAULT-PENDING\<cat>\` | ✅ bloqueado | manual |
| `discard` | `D:\Conhecimento\_DESCARTADO\` (quarentena) | ✅ não vai pro git | sim, até Wagner deletar |
| `memory` | `memory/requisitos/<Mod>/` (canônico) | ❌ commitável | git revert |
| `user` | `memory/users/<user>/` | ❌ commitável | git revert |
| `spec` | task MCP (`tasks-create`) | n/a | `tasks-update status:archived` |
| `ambiguous` | aguarda Claude na fase REVIEW | n/a | n/a |

## Heurísticas (15+ regras em `lib/rules.mjs`)

Aprendidas da triagem manual 2026-05-09. Detalhes no [ADR 0124](../../../memory/decisions/0124-curador-conhecimento-pipeline.md) §"Anti-padrões catalogados".

Resumo:
1. **Clones OSS** (`/node_modules/`, `/\.git/objects/`, README+CONTRIBUTING+SECURITY+CODE_OF_CONDUCT raiz) → `discard`
2. **Sensitive por extensão** (`.env*`, `.pfx`, `.p12`, `.pem`, `.key`, `.crt`, `.rdp`, `.kdbx`, `id_rsa*`) → `sensitive`
3. **PII NF-e** (`/XML.*Cliente/`, `/Clientes?\/.*\.xml$/`) → `sensitive`
4. **Duplicata por hash** → `discard` com reason `duplicate_of:<path>`
5. **KB/manual ≠ ADR** — material descritivo nunca classifica como `adr`
6. **Wishlist antiga** (`Todo.md` mtime > 6 meses) → `ambiguous`
7. **PDF/DOCX > 1MB** → `memory` com **estratégia INDEX-no-git** (`<dir>/INDEX.md` aponta pro arquivo local; binary fica fora git)
8. **Office Comercial Delphi** (`/Manuais Técnicos\/`, `/TelasDoSistema\/`) → `memory/requisitos/<Mod>/legacy-spec-*.md`
9. **CNAB bancos** (`/CNAB/i` + nome banco) → `memory/requisitos/Financeiro/CNAB-<banco>/`
10. **SPED/EFD/SEFAZ** → `memory/requisitos/NfeBrasil/`
11. **Atas antigas** (mtime > 12 meses) → `discard`
12. **Tamanho zero** → `ambiguous`
13. **Owner por path** (`C:\Users\<user>\`) → `owner_user=<user>`, requer consent
14. **Owner por Office meta** → best-effort
15. **Texto < 1KB** → `ambiguous`
16. **Cert antigo** (`.pfx` mtime > 12m) → `sensitive` + flag `expired_likely`
17. **Backup grande com PII** → `sensitive` + `discard_after_review`
18. **README/CHANGELOG OSS gigante** → `discard`

## Quando ATIVAR esta skill

✅ Wagner pede:
- "ingerir D:\algumacoisa"
- "/curador scan ..."
- "ler tudo do meu computador e organizar"
- "triar pasta X"
- "classificar manuais"
- "ler conhecimento da empresa"

❌ NÃO ativar:
- Pra arquivo único pequeno colado na conversa (use triagem direta)
- Pra commit/PR review (use skill `commit-discipline` ou `/ultrareview`)
- Pra criar ADR/SPEC sozinho (use `decisions-search` + write manual)

## Anti-padrões (NÃO fazer)

❌ **Aplicar classificação sem batch.md aprovado** — toda mudança em git ou movimento de sensitive exige Wagner revisar relatório
❌ **Scanear pasta de outro usuário sem consent registrado** — viola LGPD Art. 7º
❌ **Confiar 100% na heurística** — sempre gerar relatório markdown pra Wagner ver
❌ **Reclassificar item já no `_DESCARTADO/`** — quarentena é one-way; resgate é manual
❌ **Commitar `db/*.jsonl`** — está em `.gitignore`, contém paths absolutos do PC do Wagner
❌ **Migrar tudo de `Docs/Técnica/Manuais_de_Usuário/` (2.954 arquivos) cegamente** — usar estratégia INDEX-no-git (regra 7) com `INDEX.md` apontando pro local
❌ **Tratar `Sistema de Agentes de IA para Gestão Empresarial.md` como ADR ativo** — é visão histórica superada por [ADR 0035](../../../memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md). Bucket: `memory/decisions/_historical/`

## Métricas de saúde

Após cada batch, `report.mjs` imprime:

| Métrica | Alvo | Alerta se |
|---|---|---|
| `pct_auto_classified` | ≥ 70% | < 50% (regras fracas) |
| `sensitive_count` | baixo | súbito spike (root scan errado?) |
| `dedupe_count` | qualquer | > 50% (origem caótica — investigar) |
| `ambiguous_count` | ≤ 30% | > 50% (Claude vira gargalo) |

## Roadmap

- **MVP (sessão 2026-05-09):** discover/classify/report/apply + 18 regras + skill + ADR
- **Fase 2 (≥jun/2026):** Office meta owner detection, consent log multi-dev, modo `--meilisearch`
- **Fase 3 (≥jul/2026):** daemon background, UI web `/copiloto/admin/curador`, sync Hostinger/CT 100/OneDrive

## Referências

- [ADR 0124](../../../memory/decisions/0124-curador-conhecimento-pipeline.md) — decisão canônica
- [ADR 0061](../../../memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) — git/MCP é canônico
- [ADR 0094](../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0105](../../../memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal qualificado
- `D:\Conhecimento\_TRIAGEM-2026-05-09.md` — primeira triagem manual (referência)
