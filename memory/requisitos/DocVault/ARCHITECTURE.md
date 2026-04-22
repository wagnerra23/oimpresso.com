# Arquitetura · DocVault

## Stack

- **Backend**: Laravel 9.51 (alvo: migrar pra 13) · PHP 8.4 (Herd)
- **Banco**: MySQL 8 (Laragon local / Hostinger produção)
- **Front**: Inertia + React 19 + Tailwind 4 + shadcn/ui
- **Padrão do módulo**: UltimatePOS (igual Modules/PontoWr2)

## Camadas

```
┌──────────────────────────────────────────────────────┐
│ UI (Inertia + React)                                 │
│ resources/js/Pages/DocVault/                         │
│   Dashboard · Ingest · Inbox · Modulo                │
├──────────────────────────────────────────────────────┤
│ HTTP (Controllers)                                   │
│ Modules/DocVault/Http/Controllers/                   │
│   Dashboard · Ingest · Inbox · Modulo · Data         │
├──────────────────────────────────────────────────────┤
│ Services                                             │
│   RequirementsFileReader (parse .md)                 │
│   [futuro] ClassifierAgent, LinkerAgent              │
├──────────────────────────────────────────────────────┤
│ Persistência                                         │
│   docs_sources · docs_evidences                      │
│   docs_requirements · docs_links                     │
│   Arquivos em memory/requisitos/{Modulo}/*.md        │
└──────────────────────────────────────────────────────┘
```

## Modelo de dados

### `docs_sources`
Fonte bruta da evidência. `type` ∈ {screenshot, chat, error, file, text, url}. Guarda `storage_path`, `source_url`, `title`, `business_id`.

### `docs_evidences`
Pedaço extraído (anotado) de uma fonte. `kind` ∈ {bug, rule, flow, quote, screenshot, decision}. `status` ∈ {pending, triaged, applied, rejected, duplicate}. Aponta pra `source_id`.

### `docs_requirements`
Requisito estruturado (user story ou regra Gherkin). Sincroniza com os arquivos .md sob `memory/requisitos/`.

### `docs_links`
Grafo de relações entre qualquer par `(source_type, source_id) → (target_type, target_id)`. Futuro: `relation` (derived_from, affects, duplicate_of, leads_to) + `weight` (int).

## Decisões técnicas

### D1. File-based + DB espelhado
**Decisão**: specs vivem em `memory/requisitos/{Modulo}/` (arquivos .md versionados no git) E em `docs_requirements` (MySQL).

**Por quê**: arquivos .md viajam com o código no git (rastreável via `git blame`/`git log`). DB permite busca/relações sem parsear MD a cada request.

**Sincronização**: via `php artisan module:requirements` (regera o DB a partir dos arquivos).

### D2. Padrão UltimatePOS (não módulo stand-alone)
**Decisão**: DocVault vive em `Modules/DocVault/` seguindo nwidart/laravel-modules.

**Por quê**: consistência com os outros 20+ módulos do projeto. Facilita ativar/desativar via `modules_statuses.json`.

### D3. IA opcional e desligada por padrão
**Decisão**: `DOCVAULT_AI_ENABLED=false` no .env. Classificação é manual no Inbox.

**Por quê**: validação humana evita divergência/alucinação. IA vira sugestão (Fase 3), não automação cega.

### D4. Uma pasta por módulo com 4 arquivos padrão
**Decisão**: `memory/requisitos/{Modulo}/` contém `README.md`, `ARCHITECTURE.md`, `SPEC.md`, `CHANGELOG.md`.

**Por quê**: separa preocupações (arquitetura muda pouco, spec muda sempre, changelog acumula histórico). Fácil de ler — você abre o README e navega pra onde precisa.

**Retrocompat**: módulos que ainda têm só `memory/requisitos/{Modulo}.md` continuam funcionando — o reader faz fallback.

## Fluxos principais

### F1. Ingestão de evidência
```
Usuário → Ingest.tsx → IngestController::store
  → salva DocSource (arquivo/URL/texto)
  → cria DocEvidence (status=pending, módulo inferido do contexto)
  → redireciona pra Inbox
```

### F2. Triagem no Inbox
```
Usuário no Inbox.tsx → escolhe evidência
  → define kind, module_target, suggested_story_id
  → status: pending → triaged → applied
  → triaged_by + triaged_at carimbados
```

### F3. Apply (manual na Fase 1, automático na Fase 3)
```
Fase 1: botão "Apply" só marca status=applied
Fase 3: ao apply, o sistema edita memory/requisitos/{Mod}/SPEC.md
  adicionando a story/regra sugerida, e regrava o .md.
```

## Pontos de extensão (futuro)

- **Scout + MySQL fulltext**: busca de similaridade em evidências/requirements sem precisar Meilisearch.
- **Meilisearch** (opcional, quando volume > 5k): vector + hybrid search.
- **Graph viewer**: react-force-graph em cima de `docs_links`.
- **Auto-apply IA**: `ClassifierAgent` preenche sugestões; `ApplierAgent` regrava .md.
