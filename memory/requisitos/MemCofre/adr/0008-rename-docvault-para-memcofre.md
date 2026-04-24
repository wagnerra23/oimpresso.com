# ADR 0008 · Renomear DocVault para MemCofre (label "Cofre de Memórias")

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner, Claude
- **Categoria**: arq

## Contexto

O módulo nasceu como **DocVault** — "Document Vault", inspiração técnica em sistemas de doc-as-code. Wagner identificou que a metáfora real do que ele construiu é o **Vault do Obsidian** (cofre de notas/memórias inter-conectadas), não um sistema de docs corporativo. Pediu rebrand pra refletir essa identidade:

> "Gostaria que DocVault se tornasse MemCofre. No minha cabeça é o Cofre de memórias do Obsidian."

> "Pode ser o label 'Cofre de Memórias' e pasta MemCofre."

Adicionalmente, definiu que **"guarde no cofre"** vira gatilho conversacional pra IA (Claude/Cursor) salvar artefatos no módulo. Ver `trigger_guarde_no_cofre.md` na auto-memória.

## Decisão

Rebrand completo de identidade, mantendo schema de dados intocado:

### O que muda

| Camada | De | Para |
|---|---|---|
| Pasta módulo | `Modules/DocVault/` | `Modules/MemCofre/` |
| Namespace PHP | `Modules\DocVault\*` | `Modules\MemCofre\*` |
| Service Provider | `DocVaultServiceProvider` | `MemCofreServiceProvider` |
| Pages React | `resources/js/Pages/DocVault/` | `resources/js/Pages/MemCofre/` |
| Memory docs | `memory/requisitos/DocVault/` | `memory/requisitos/MemCofre/` |
| URL prefix | `/docs/*` | `/memcofre/*` |
| Comandos artisan | `docvault:audit-module`, `docvault:validate`, ... | `memcofre:audit-module`, `memcofre:validate`, ... |
| Lang namespace | `docvault::lang.*` | `memcofre::memcofre.*` |
| modules_statuses.json | `"DocVault": true` | `"MemCofre": true` |
| `module.json` alias | `"docvault"` | `"memcofre"` |
| Permission | `docvault.access`, `docvault.admin` | `memcofre.access`, `memcofre.admin` |
| Comentário sync-pages | `// @docvault` | `// @memcofre` |
| Composer name | `nwidart/docvault` | `nwidart/memcofre` |
| Storage directory | `public/docvault/` | `public/memcofre/` |
| Label visível ao usuário | "DocVault" | **"Cofre de Memórias"** |

### O que NÃO muda (intencional)

- **Tabelas DB com prefixo `docs_*`** (`docs_sources`, `docs_evidences`, `docs_requirements`, `docs_links`, `docs_chat_messages`, `docs_pages`, `docs_validation_runs`) — rename de tabela é destrutivo, prefixo é detalhe interno invisível ao usuário.
- **Class names dos models** (`DocSource`, `DocEvidence`, `DocChatMessage`, `DocLink`, `DocPage`, `DocRequirement`, `DocValidationRun`) — representam o conceito de "documento" que ainda é válido. Renomear viraria 50+ refs e zero ganho.
- **Sessions notes antigas** (`memory/sessions/2026-04-22-*.md` etc.) — preservar histórico cronológico, não reescrever passado.

### Nome interno vs label visível

Convenção adotada:
- **Pasta/namespace/URL/comando**: `MemCofre` / `memcofre` (single-word, sem espaço)
- **Label pro usuário** (sidebar, headers, breadcrumbs, page titles): **"Cofre de Memórias"** (com espaço, dois acentos)

Ambos vivem no lang file `Modules/MemCofre/Resources/lang/pt/memcofre.php`.

## Consequências

**Positivas:**
- Identidade alinhada com a metáfora real (Vault de Obsidian).
- Comando "guarde no cofre" fica natural ("vou pôr no cofre" = ir pro MemCofre).
- Diferenciação clara de DocVault genéricos do mundo (vários produtos com esse nome).

**Negativas:**
- Bookmarks externos pra `/docs/*` quebram (sem redirect 301 implementado por enquanto — usuário praticamente único é Wagner, aceita aprender URL nova).
- Sessions/ADRs/auto-memórias antigas referenciam "DocVault" — ficam como rastro histórico (foram atualizadas onde fazia sentido, mas algumas .bak files e arquivos de backup mantêm o nome antigo).
- Quem rodar `docvault:*` em produção antiga vai falhar — comando aliasado não foi mantido.

## Alternativas consideradas

- **Apenas rebrand visual** (manter código DocVault, trocar só labels) — rejeitado: esquizofrenia entre interno e externo confunde dev novo.
- **Renomear tabelas `docs_*` → `memcofre_*`** — rejeitado: migration destrutiva, downtime, zero ganho UX (prefixo de tabela é invisível).
- **Manter alias `docvault:*` deprecated** pros comandos artisan — não implementado (pode ser feito depois se necessário).

## Validação pós-deploy

- `php artisan list | grep memcofre` → 7 comandos (audit-module, gen-test, install-hooks, migrate-module, sync-memories, sync-pages, validate)
- Acessar `/memcofre` → Dashboard renderiza com header "Cofre de Memórias"
- Topnav: "Cofre de Memórias / Dashboard / Ingest / Inbox / Memória / Chat"
- Tab title: "Cofre de Memórias — Dashboard · OI Impresso"
- `Modules\MemCofre\` namespace resolve em PSR-4 (composer dump-autoload OK)

## Pós-rename

Adicionada memória `trigger_guarde_no_cofre.md` na auto-memória descrevendo o gatilho conversacional pra IA. Quando Wagner disser "guarde no cofre", a IA classifica e salva no local apropriado (ADR, SPEC, evidência, ou auto-memória).
