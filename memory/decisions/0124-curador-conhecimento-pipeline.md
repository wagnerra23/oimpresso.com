---
slug: 0124-curador-conhecimento-pipeline
number: 124
title: "Curador — pipeline canônico de ingestão de conhecimento (computador → empresa → MCP)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-09"
module: governance
supersedes: []
related:
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0105-cliente-como-sinal-guiar-sem-mandar
---

## Contexto

Wagner tem **D:\Conhecimento** (3.253 arquivos curados + 79k arquivos de clones OSS) e prevê expandir o escopo pra **todo o computador dele e depois toda a empresa** — separando por usuário (Wagner/Maiara/Felipe/Luiz/Eliana[E]) e organizando.

Triagem manual da sessão 2026-05-09 (relatório `D:\Conhecimento\_TRIAGEM-2026-05-09.md`) revelou padrões repetitivos:
- Material sensível misturado com canon (3 `.env` Dify/Jana com 36KB cada, 3 cópias de cert SEFAZ `.pfx`, 8 `.rdp` de clientes, 2 XMLs NF-e PII)
- ~96% de volume é **descartável determinístico** (clones OSS chatwoot/dify/janaAi/webapp + node_modules)
- Manuais técnicos de bancos (CNAB) seguem padrão regex previsível
- `Todo.md` antigo (~1 ano) ≠ US ativa ([ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md))

Triagem 100%-Claude não escala: ler 3k arquivos consome ~50-100x mais contexto que necessário (90% das decisões são determinísticas).

## Decisão

Adotar o **Curador** — pipeline 5-fase, heurística-first, Claude-second, humano-gateado:

```
DISCOVER → CLASSIFY → REPORT → REVIEW → APPLY
(node)     (regras)    (md)    (humano)  (node+git)
```

### Princípios duros

1. **Heurística antes de Claude.** Regras determinísticas (extensão, path pattern, hash dedup) classificam 70-80% dos arquivos sem custar Claude. Claude entra só nos `bucket=ambiguous`.
2. **Estado persistente fora da conversa.** `db/files.jsonl` (append-only) sobrevive `/clear`, `/compact`, reboot. Sem isso, cada sessão refaz triagem do zero.
3. **Humano-gateado em batch.** Relatórios markdown ≤500 itens cada. Wagner aprova bloco-a-bloco. NADA é movido/commitado sem `apply.mjs --batch <id> --approved`.
4. **Destino canônico = `memory/requisitos/<Mod>/`.** Não cria 3º lugar paralelo. Respeita [ADR 0061](0061-conhecimento-canonico-git-mcp-zero-automem.md) — git/MCP é canônico.
5. **Sensitive nunca em git.** `.env*`, `.pfx`, `.pem`, `.rdp`, `.key`, `id_rsa*`, `*.kdbx`, regex CPF/CNPJ → `bucket=sensitive` + commit BLOQUEADO. Vai pra Vaultwarden manualmente.
6. **Consent-first multi-usuário (LGPD Art. 7º).** Scanear `C:\Users\<outro_dev>\` exige opt-in explícito do dono + log da autorização em `db/consent.jsonl`. Wagner não pode scanear arquivos privados de Maiara/Felipe/Luiz/Eliana[E] sem consentimento documentado.
7. **Zero deps externas.** Node 24 built-in (fs/promises, crypto). JSONL append-only em vez de SQLite. Sem `npm install`.

### Estrutura

```
scripts/curador/
├── discover.mjs          # enumera + MD5 → db/files.jsonl
├── classify.mjs          # aplica regras → db/classifications.jsonl
├── report.mjs            # gera reports/YYYY-MM-DD-batch-NNN.md
├── apply.mjs             # após approval, move/commita
├── lib/
│   ├── rules.mjs         # 18+ heurísticas (ver SKILL.md)
│   ├── db.mjs            # JSONL read/write helpers
│   └── owner.mjs         # detecção owner (path/git/Office meta)
├── db/                   # gitignored
│   ├── files.jsonl
│   ├── classifications.jsonl
│   ├── batches.jsonl
│   └── consent.jsonl
├── reports/              # gitignored (vai pra D:\Conhecimento\_TRIAGEM\)
├── config.example.json
├── README.md
└── .gitignore

D:\Conhecimento\
├── _INBOX\               # você dropa arquivos novos aqui
├── _TRIAGEM\             # relatórios markdown gerados
├── _VAULT-PENDING\       # sensíveis aguardando você mover pro Vaultwarden
└── _DESCARTADO\          # quarentena pré-delete (Wagner deleta quando quiser)

memory/
├── requisitos/<Mod>/     # destino canônico (já existe)
└── users/                # NOVA pasta
    ├── wagner/
    ├── maiara/
    ├── felipe/
    ├── luiz/
    └── eliana/
```

### Buckets (5 + 1 fallback)

| Bucket | Destino | Exemplo de regra |
|---|---|---|
| `sensitive` | `D:\Conhecimento\_VAULT-PENDING\<categoria>\` | `/\.env(\.\|$)/` → SECRETS |
| `discard` | `D:\Conhecimento\_DESCARTADO\` (não git) | path matches `/node_modules/`, `/\.git/`, ou hash duplicado |
| `memory` | `memory/requisitos/<Mod>/` (canon) | `/CNAB.*Itau/` → `Financeiro/CNAB-Itau/` |
| `user` | `memory/users/<user>/` | inferido por path `C:\Users\<user>` ou Office meta |
| `spec` | inbox MCP `tasks-create` (manual gate) | só com sinal qualificado [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — Wagner aprova item-a-item |
| `ambiguous` (fallback) | aguarda Claude na fase REVIEW | `.docx > 1MB`, `.xlsx`, `.md < 1KB` |

### Anti-padrões catalogados (heurísticas que disparam classificação automática)

Aprendidos da triagem 2026-05-09 — implementados em `lib/rules.mjs`:

1. **Clones OSS:** path matches `/node_modules/`, `/\.git/objects/`, OU pasta com `package.json`+`README.md`+`CONTRIBUTING.md` na raiz → `discard`
2. **Sensitive por extensão:** `.env*`, `.pfx`, `.p12`, `.pem`, `.key`, `.crt`, `.rdp`, `.kdbx`, `id_rsa*`, `id_ed25519*` → `sensitive`
3. **PII NF-e:** path matches `/XML.*Cliente/` ou `/Clientes?\/.*\.xml$/` → `sensitive` (PII PJ/PF)
4. **Duplicata por hash:** 2ª+ ocorrência do mesmo MD5 → `discard` com `reason=duplicate_of:<path1>`
5. **Material descritivo ≠ ADR:** KB/manual/FAQ → `memory`, NUNCA `adr` (ADR é decisão arquitetural append-only)
6. **Wishlist antiga ≠ US:** arquivos `Todo.md`/`TODO.txt` mtime > 6 meses → `ambiguous` (Wagner decide se vira US-* ou descarta)
7. **PDF/DOCX grande:** size > 1MB AND extensão in `[pdf, docx, xlsx]` → `memory` com **estratégia INDEX-no-git** (gera `<dir>/INDEX.md` apontando pro arquivo local; binary fica fora git)
8. **Office Comercial Delphi legacy:** path contém `/Manuais? Técnicos\/` ou `/TelasDoSistema\/` → `memory` mapeado pra `requisitos/<Mod>/legacy-spec-*.md` por palavra-chave (Produto/Venda/Produção/Financeiro/etc)
9. **CNAB bancos:** regex `/CNAB|Cnab/i` AND um de `[BB|Bradesco|CEF|Itau|Santander|Sicoob|Sicred|Unicred|Banrisul|Cresol]` → `memory/requisitos/Financeiro/CNAB-<banco>/`
10. **SPED/EFD/SEFAZ:** `/SPED|EFD|ICMS|SEFAZ/i` → `memory/requisitos/NfeBrasil/`
11. **Atas/Pautas antigas:** `/Ata|Pauta|Reuniao/i` AND mtime > 12 meses → `discard`
12. **Tamanho zero:** `size_bytes == 0` → `ambiguous` (manual)
13. **Owner por path:** `C:\Users\<user>\...` → `owner_user=<user>`; falta consent log → bloqueia processamento
14. **Owner por Office meta:** `.docx`/`.xlsx` com `creator=<user>` → `owner_user=<user>` (best-effort)
15. **Texto curto rascunho:** `.md`/`.txt` com `size < 1KB` → `ambiguous`
16. **Cert/PFX antigo:** `.pfx`/`.p12` mtime > 12 meses → `sensitive` + flag `expired_likely`
17. **Backup com PII:** path contém `/Backup|backup\/` AND tamanho > 10MB → `sensitive` + `discard_after_review`
18. **README/CHANGELOG OSS:** README.md/CHANGELOG.md com size > 50KB E não-em-`memory/` → provável OSS clone → `discard`

### Workflow

```bash
# Fase 1: descoberta (rápida, segura, sem mexer em nada)
node scripts/curador/discover.mjs --source D:\Conhecimento --user wagner

# Fase 2: classificação automática
node scripts/curador/classify.mjs

# Fase 3: relatório por batch (default: 500 itens)
node scripts/curador/report.mjs --batch-size 500
# Gera: D:\Conhecimento\_TRIAGEM\2026-05-09-batch-001.md, batch-002.md, ...

# Fase 4: REVIEW (humano)
# Wagner abre cada batch.md, lê, marca [x] APROVAR ou [x] PULAR
# Salva o batch.md em _TRIAGEM/

# Fase 5: aplicar só os aprovados
node scripts/curador/apply.mjs --batch 001 --approved
# - Move sensitive pra _VAULT-PENDING/
# - Copia memory/* pro repo + git add (NÃO commita — Wagner commita)
# - Move discard pra _DESCARTADO/
```

### Slash command (skill `/curador`)

- `/curador status` — mostra contagens por bucket + batches pendentes
- `/curador scan <path>` — chama discover+classify+report
- `/curador review <batch-id>` — abre relatório, Claude ajuda em itens `ambiguous`
- `/curador apply <batch-id>` — após approve, executa
- `/curador consent <user>` — registra opt-in pra scanear paths de outro usuário

### Métricas de saúde

Logs estruturados em `db/metrics.jsonl`. Checks pra rodar pós-batch:
- `pct_auto_classified` ≥ 70% (senão regras estão fracas)
- `sensitive_count` (espera-se baixo; alto pode indicar root scan errado)
- `dedupe_count` (alto = origem caótica)
- `ambiguous_count` ≤ 30% (senão Claude vira gargalo)

## Alternativas consideradas

### A. SQLite com better-sqlite3
**Rejeitado.** Adiciona npm dep + binário nativo (problema cross-platform). JSONL é grep-able, append-only robusto até ~1M registros (~500MB) — suficiente pro escopo "computador + empresa".

### B. Apache Tika + Meilisearch (extract+index)
**Adiada (Fase 2).** Pra Jana fazer recall on-demand sem migrar git, faz sentido. Hoje stack `MeilisearchDriver` ([ADR 0035](0035-stack-ai-canonica-wagner-2026-04-26.md)) já existe. Mas Fase 1 precisa de classificação humana primeiro — sem isso, indexar tudo no Meilisearch só replica o caos.

### C. Ferramenta off-the-shelf (DocFetcher, Recoll, Obsidian + plugins)
**Rejeitada.** Não fala git/MCP do oimpresso. Wagner precisa do destino ser `memory/requisitos/<Mod>/` que vira contexto-de-Jana automático via webhook.

### D. 100% Claude (sem heurística)
**Rejeitada.** Custo + latência inviáveis pra 3k+ arquivos. Triagem sessão 2026-05-09 já mostrou que 96% é determinístico (clones OSS, .env, .pfx, hash dup).

### E. Refazer estrutura (greenfield em `D:\Conhecimento_v2\`)
**Rejeitada.** Cria 3º lugar paralelo. Quebra [ADR 0061](0061-conhecimento-canonico-git-mcp-zero-automem.md). Plus: nunca termina migração e fica com 2 fontes de verdade.

## Consequências

✅ **Boas:**
- Escala de "minha pasta de conhecimento" pra "computador inteiro" pra "empresa toda" sem reescrever
- Estado persistente sobrevive /clear, /compact, reboot
- Heurística filtra 70-80% sem Claude → custo e tempo controlados
- Multi-usuário com LGPD gate (consent-first)
- Sensitive bloqueia commit automaticamente (anti-vazamento)
- Destino canônico = sem 3º lugar

⚠️ **Tradeoffs:**
- Heurística pode errar bucket — falso-positivo em `discard` é o pior caso (perde info útil). Mitigação: `discard` vai pra `_DESCARTADO/` quarentena, NÃO `rm`. Wagner pode resgatar até deletar manualmente.
- Multi-usuário só com consent → pasta de Maiara/Felipe não é scaneada sem opt-in deles
- JSONL append-only não suporta UPDATE in-place — pra "remover classificação errada" precisa-se reescrever arquivo (script `compact.mjs` faz isso periodicamente)
- Apply não commita automático — Wagner commita pra preservar `commit-discipline` (1 PR = 1 intent ≤300 linhas)

## Plano de adoção

> **2026-05-09 amendment (Wagner):** Curador é **princípio para construir o Centro de Operações Admin** ([ADR 0122](0122-admin-center-ct100.md)) que vai gerenciar toda a infra da empresa. Scripts Node locais ficam (precisam acessar `D:\`/`C:\Users\`); UI/governance vira **widget do Admin Center** em F2 (não módulo standalone).
>
> **2026-05-09 amendment 2 (Wagner — "todo arquivo anexado deve cair lá"):** Curador deixa de ser sistema standalone. Vira **engine de classificação compartilhada** dentro de [Modules/Arquivos](../requisitos/Arquivos/SPEC.md) (DMS backbone, [ADR 0123](0123-modules-arquivos-backbone.md)):
> - `scripts/curador/lib/rules.mjs` permanece (filesystem-aware local, descobre D:\Conhecimento)
> - Mesma lógica portada pra `Modules/Arquivos/Services/CuradorEngine.php` (server-side, classifica todo upload)
> - ParityTest JS×PHP obrigatório (mesmo MD5+path → mesmo bucket)
> - `apply.mjs` futuramente deixa de mexer filesystem direto — vira "submit pro Admin API" (US-ARQ-017)

**Fase 1 — MVP (esta sessão 2026-05-09):**
- ADR 0121 (este documento)
- skill `/curador` (Tier C slash command)
- `scripts/curador/{discover,classify,report,apply}.mjs`
- `scripts/curador/lib/rules.mjs` com 15+ heurísticas
- README + config.example.json
- ❌ NÃO scaneia nada ainda — Wagner roda manualmente quando quiser

**Fase 2 — UI dentro do Admin Center ([ADR 0122](0122-admin-center-ct100.md), ~jun/2026):**

Curador NÃO ganha módulo standalone. Vira **widget + página dedicada** dentro de `Modules/Admin/` no CT 100 (Tailscale-only, role `superadmin#1`).
- Página `Pages/Curador/Batches.tsx` (substitui marcação `[x]` markdown)
- API `POST /admin/curador/api/upload-batch` recebe JSONL do script Node local (auth via Bearer token gerado em `/admin/tokens`)
- Apply server-side em queue Horizon job `ApplyBatchJob`
- Audit log integral em `mcp_curador_audit_log`
- Migration `mcp_curador_batches`, `mcp_curador_files`, `mcp_curador_audit_log`, `mcp_curador_consent`
- Detecção owner por Office metadata + git author
- Modo `--meilisearch` (indexa em vez de migrar git — Jana retorna recall on-demand)

**Fase 3 (condicional, ≥2026-Q3):**
- Daemon background (Tailscale-aware) monitora pastas e roda discover incremental
- Sync push pra MCP server CT 100 (knowledge propagation cross-dev em tempo real)

## Validação

- ✅ Re-trabalhar a triagem D:\Conhecimento (3.253 arquivos) com Curador deve produzir relatório equivalente ao manual de hoje em <5 min, com `pct_auto_classified` ≥ 70%
- ✅ Sensitive scan deve detectar os 14 arquivos sensíveis (3 `.env`, 3 `.pfx`, 8 `.rdp`) sem falso-negativo
- ✅ Dedupe deve identificar `Manuais Técnicos/Venda.txt` ↔ `Suporte ao Cliente/.../Venda.txt` (size 25501 idêntico)
