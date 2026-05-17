# Modules/TeamMcp — CHANGELOG

## [Wave 27] - 2026-05-17

### Test (D2 — Pest +6 Wave 27 rotate edge cases)
- `Tests/Feature/Wave23ScorecardRotateTest.php` — +6 testes Wave 27:
  - rotate em cadeia A→B→C revoga A+B mantém C ativo (chain integrity)
  - rotate de token JÁ soft-deleted retorna null (Tier 0 segredo idempotência)
  - rotate raw NÃO aparece em log estruturado (defesa em profundidade — Log::listen)
  - rotate cross-user (B tenta rotacionar token A) retorna null sem efeito colateral
    (count tokens userB permanece igual — anti-cross-user attack)
  - countActive ignora tokens soft-deleted (consistente com rotate semantics)
  - chain rotate cleanup helper sintetiza pattern Wave 25 D3

### UI (D6 — Pages tsx Deferred wrapper Team/Index)
- `resources/js/Pages/team-mcp/Team/Index.tsx` — wrap `<Deferred data="team">`
  + `<Deferred data="stats_globais">` em torno do conteúdo caro (backend
  TeamController já fazia `Inertia::defer` Wave 11; frontend agora trata o
  loading state com skeleton animate-pulse em vez de undefined crash).
  Pattern alinhado com [`Admin/GovernanceV4Dashboard.tsx`](../../resources/js/Pages/Admin/GovernanceV4Dashboard.tsx).
  Props `team` e `stats_globais` viram `Props?` opcional com defaults sentinela.

### Docs
- `CHANGELOG.md` (entry Wave 27 atual).

## [Wave 25] - 2026-05-16

### Added (D4 — Service extract Scorecard)
- `Services/ScorecardBuilderService.php` — extrai buildFacts + buildChecks +
  4 helpers (checkSchema, checkBriefRecente, checkTokensSemOrphan,
  checkCustoMedioSanidade) antes embutidos em `ScorecardController`.
- ScorecardController refatorado pra thin (auth + render Inertia + proxies
  pra preservar contrato Pest Wave 23 reflection).

### Added (D3 — McpTokenIssuer::rotate canônico)
- `Services/McpTokenIssuer.php` — método `rotate(userId, oldTokenId, note)`
  faltava (RotateTokenCommand já chamava). Implementação atômica:
  ownership guard fora da transaction (fail-fast), DB::transaction + lockForUpdate
  no re-fetch, issue novo + revoke old na mesma transação. Raw devolvido 1×
  no array (Tier 0 segredo IRREVOGÁVEL — ADR 0081).

### Test (D6 — Pest +6 Wave 25)
- `Tests/Feature/Wave23ScorecardRotateTest.php` — +6 testes Wave 25:
  ScorecardBuilderService load via container, expõe 6 métodos públicos,
  checkSchema retorna ok=false pra tabela inexistente, ScorecardController
  delega corretamente pra Service (assert source code), rotate preserva note
  custom, rotate sem note usa default, rotate retorna null pra token inexistente.

### Docs
- `CHANGELOG.md` (este arquivo — novo).

## [Wave 23] - 2026-05-16

### Added (G1 + G3 FICHA W22)
- `Http/Controllers/ScorecardController.php` — esqueleto tela /team-mcp/scorecard
  pattern Facts+Checks (separar dado de juízo).
- `Console/Commands/RotateTokenCommand.php` — comando `teammcp:token:rotate`
  com `--detail` (não `--verbose` Symfony reserved) + `--dry-run`.

### Test (D6 — Pest +10)
- `Tests/Feature/Wave23ScorecardRotateTest.php` — rotate atômico (revoke+issue
  mesma transação), guard ownership cross-user, scorecard route+builders,
  comando registrado + signature.

## [Wave 18 RETRY] - 2026-05-16

### Added (D4 — Services extracted)
- `Services/TeamUsageAggregator.php` — extrai agregação de uso MCP
- `Services/McpTokenIssuer.php` — extrai issue/revoke (raw Tier 0 IRREVOGÁVEL)
- `Services/UsageCsvExporter.php` — extrai stream CSV
- `Services/CcIngestService.php` — extrai upsert sessions+messages
- `Services/ActorResolver.php` — extrai resolução actor pra MCP
- `Services/McpActorRepository.php` — query actor por business

### Added (D8 — FormRequests +5)
- `Http/Requests/UpdateQuotaRequest.php`
- `Http/Requests/ExportUsageCsvRequest.php`
- `Http/Requests/IssueActorTokenRequest.php`
- `Http/Requests/CcIngestRequest.php`
- `Http/Requests/StoreActorRequest.php`
