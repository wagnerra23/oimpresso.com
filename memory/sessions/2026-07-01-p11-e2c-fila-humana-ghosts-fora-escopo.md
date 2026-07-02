---
date: "2026-07-01"
topic: "P11 E2c — fila humana: renomes mortos em docs vivos FORA do escopo do detector (memory/ raiz + reference + governance) corrigidos; decisão: NÃO estender knowledge-drift.mjs"
authors: [C]
related_adrs: [0088-module-rename-php-only, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes, 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento]
---

# P11 E2c — fila humana dos ghosts fora do escopo do detector

Continuação direta do E2b ([2026-07-01-p11-e2b-reseed-meilisearch-e3-distiller.md](2026-07-01-p11-e2b-reseed-meilisearch-e3-distiller.md) §"Fila humana"): o re-seed provou que a busca do MCP serve nomes de módulo MORTOS a partir de docs VIVOS que o `knowledge-drift.mjs` não varre (ele só cobre `memory/requisitos/**`). Renames Classe A do `ghost-rename-map.json`: `Copiloto→Jana`, `PontoWr2→Ponto`, `MemCofre→SRS`, `DocVault→SRS`.

## 1. Confirmação (mesma regex do detector)

Grep token-boundary `Modules/(Copiloto|PontoWr2|MemCofre|DocVault)\b` em `memory/**/*.md` + disco confirmado: `Modules/Jana`, `Modules/Ponto`, `Modules/SRS` EXISTEM; os 4 nomes antigos ABSENT. Paths de destino citados verificados um a um (`Modules/SRS/Services/ChatAssistant.php`, `Modules/SRS/Entities/DocChatMessage.php`, `Modules/Jana/Mcp/Tools/`, `Modules/Ponto/Tests/`, `memory/requisitos/SRS/` — todos existem). `DocVault` = 0 citações em doc vivo (só tombstones).

## 2. Classificação e correção (caso a caso)

**Referência VIVA → corrigida (13 arquivos):**

| Doc | Fix |
|---|---|
| `memory/what-oimpresso.md` (⚠️ @import do CLAUDE.md, carrega em TODA sessão) | diagrama: `Modules/Copiloto`→`Modules/Jana`; `Modules/MemCofre (cofre senhas)`→`Modules/SRS` (rótulo corrigido pro real: Software Requirements System, ex-MemCofre) |
| `memory/why-oimpresso.md` | lista de módulos: MemCofre→SRS ex-MemCofre |
| `memory/00-user-profile.md` · `02-technical-stack.md` · `04-conventions.md` · `05-preferences.md` | `Modules/PontoWr2/`→`Modules/Ponto/` (com "nasceu PontoWr2 — ADR 0088" onde a frase é narrativa de origem) |
| `memory/reference/mcp-endpoints.md` | `Modules/Copiloto/Mcp/Tools/`→`Modules/Jana/Mcp/Tools/` (path real verificado) |
| `memory/reference/ideia-chat-ia-contextual.md` | plug-points "como conecta com o que já existe": `Modules/MemCofre/*`→`Modules/SRS/*`; `requisitos/MemCofre`→`requisitos/SRS`; `"module": "PontoWr2"`→`"Ponto"`. Identificadores legacy REAIS mantidos (`/memcofre/chat`, `@memcofre`, `DOCVAULT_AI_ENABLED` — ADR 0088 manteve URLs/env) |
| `memory/reference/trigger-guarde-no-cofre.md` | módulo→`Modules/SRS` (ex-MemCofre); comandos `memcofre:*` + URL `/memcofre/*` mantidos (legacy real). Variações FALADAS ("registra no MemCofre") mantidas — é o que Wagner diz, não path |
| `memory/reference/feedback-claude-aprova-merge-verde-criticos-nao.md` | módulo crítico LGPD: `Modules/Copiloto/`→`Modules/Jana/` (ex-Copiloto) |
| `memory/onboarding/team/maiara-suporte.md` | modules_blocked: `Modules/Copiloto/`→`Modules/Jana/` |
| `memory/legacy-delphi/MAPEAMENTO-DELPHI-LARAVEL.md` | coluna "alvo Laravel HOJE": `Modules/Copiloto`→`Modules/Jana` (2×) |

**Corrupção histórica RESTAURADA (2 arquivos):** `memory/governance/ARCHITECTURE.md` + `memory/governance/MODULE-DRIFT-MIGRATION-PLAN.md` — um replace antigo transformou a coluna "De" das tabelas de rename em nonsense ("renames **Jana→Jana**", "a criar via rename de **Jana**", "Pages/**Jana**/ mantidas legacy"). Restaurado `Modules/Copiloto` na coluna "De"/narrativa da época (6 pontos) — o nome antigo AÍ é tombstone intencional, é o próprio registro do rename. ARCHITECTURE.md §3 ganhou nota: "renames executados PHP-only (ADR 0088); nomes antigos na coluna De = registro histórico". **Lição (o limite):** replace cego de nome morto em doc que DOCUMENTA o rename destrói o documento — é exatamente por isso que `ghost-fix.mjs` pula `adr/` e a fila fora de requisitos é HUMANA, não codemod.

**Citação histórica datada → NÃO tocada:** `memory/CHANGELOG.md` entries (log cronológico; ganhou só nota de época no header apontando pro ghost-rename-map), `reference/post-mortem-v4-go-live.md` (post-mortem cita o rename como fato), audits datados (`memory/audits/`, `sprints/`, `comparativos/`, `reference/legacy-audit-*`, `reference/modules-cms-landing`), `memory_backup/**` (backup congelado), decisions/sessions/handoffs (append-only Tier 0).

## 3. Decisão: NÃO estender o escopo do knowledge-drift.mjs (registrada no P11)

Detalhe completo em [P11 §"Estado 2026-07-01 — E2c"](../requisitos/_Governanca/roadmap/P11-kl-e2-renames-reseed-distiller.md). Resumo: (1) `ghost_count` está **armado** (value 8, direction down) sobre `memory/requisitos/**` — mudar escopo muda a definição da série; o precedente canônico (anchor_coverage na própria baseline) é APOSENTAR a série e re-armar com 3 medições, nunca esticar por dentro; (2) estender só o detector recriaria o descasamento detector×corretor que o P11 acabou de reconciliar (#3155); (3) pós-E2c, referência viva Classe A fora de requisitos = 0 — o risco residual (rename futuro) vira passo de processo: **PR de rename Classe A novo inclui grep `Modules/<Nome>` em `memory/` INTEIRO**. Gatilho de reabertura: two-strikes (grep manual falhar 2×) → extensão simétrica como métrica NOVA (`ghost_count_full`), armada do zero.

## Fila residual (fora deste PR)

- Nomes puros (fora da regex `Modules/<Nome>`) em docs vivos: `reference/ultimatepos-integracao.md` (`Module::find('PontoWr2')` — guidance de código que quebraria), `reference/financeiro-integracao.md`, `reference/feedback-topnav-i18n-pattern.md`.
- `memory/modulos/PontoWr2.md` — doc-módulo legado; candidato a tombstone junto com os ghost-dirs `memory/requisitos/{MemCofre,PontoWr2,Copiloto}` (fila KL já aberta no E3: portas que o `--all` re-escreveria).
- `.github/CODEOWNERS:32-33` aponta `Modules/Copiloto/` (morto) → **Jana está sem code-owner desde o rename** — task chip spawnada pra correção em PR próprio (infra de governança, R10).

## Verificação

- `node scripts/governance/knowledge-drift.mjs --check` → OK, nenhum ghost novo (escopo requisitos intocado neste PR).
- `node scripts/governance/sdd-scorecard.mjs --ratchet` → sem regressão vs baseline (ghost_count segue 8).
- Grep de re-conferência: 0 citações `Modules/(Copiloto|PontoWr2|MemCofre|DocVault)` restantes em doc VIVO não-tombstone fora de requisitos.
