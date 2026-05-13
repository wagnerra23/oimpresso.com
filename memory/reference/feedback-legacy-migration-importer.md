---
name: Legacy Delphi → oimpresso = Python importer canônico
description: Migração de dados de WR Comercial Delphi (Firebird) pra oimpresso (MySQL) sempre via scripts/legacy-migration/ Python — idempotente com bridge legacy_map. Nunca tinker direto.
type: feedback
---
Migração de dados legacy Delphi WR Comercial → oimpresso Laravel **é sempre via importer Python em `scripts/legacy-migration/`**, NUNCA via tinker direto ou SQL manual.

**Why:** validado em prod 2026-05-11 com migração de 19 contas bancárias + 4 empresas (CODEMPRESA 1/2/3/4) da biz=1 Wagner. Pattern entrega:
- (a) idempotência via bridge `accounts_legacy_map`/`contacts_legacy_map` com (business_id, legacy_source, legacy_id) — re-rodar é seguro;
- (b) dry-run obrigatório antes de prod gera SQL pra revisão;
- (c) `--target dry-run|local|prod` + `--confirm` obrigatório em prod;
- (d) SSH tunnel automático (`migrar-tudo.py` orquestra `-L 127.0.0.1:33069:127.0.0.1:3306` IPv4-bound porque MySQL Hostinger user não tem GRANT pra `::1`);
- (e) segredos (CERTIFICADO PKCS#12, CSC, WEB_SERVICE_SENHA, APPKEY, NFE_NUMSERIE, KEYFILE/CLIENTSECRET Inter) ficam só no legacy — nunca migram pro oimpresso sem ADR de Vaultwarden integration;
- (f) campos NOT NULL em `contacts` (mobile etc) — filtrar Nones antes do INSERT pra MySQL usar default;
- (g) `account_type_id` global no MySQL pode pular IDs (Cartão de Crédito virou id=10 na biz=1 mesmo só tendo 3 tipos lá) — usar `ensure_account_types` com lookup-then-insert por (business_id, name).

**How to apply:** se Wagner pedir "migra X do legacy pra oimpresso" — abrir `scripts/legacy-migration/`, ver se já existe importer pra entidade, estender se sim (UPSERT + bridge legacy_map), criar novo seguindo `import-contas-bancarias.py` / `import-empresas.py` se não. Sempre: `--target dry-run` primeiro, mostrar SQL gerado pra Wagner, só então `--target prod --confirm`. Bridge table `<tabela>_legacy_map` é obrigatória (preserva CODIGO Delphi pra re-mapping futuro e dedupe). Wrapper `migrar-tudo.py` orquestra SSH tunnel + roda 2+ scripts em sequência — copiar esse pattern pra novos batches.

**Bug visto e fix:** SSH tunnel default no Windows resolve `localhost` pra `::1` IPv6, MySQL Hostinger nega — sempre usar `-L 127.0.0.1:port:127.0.0.1:3306` + `-o AddressFamily=inet` explícito.

**`gh pr merge --admin` fallback quando worktree usa main:** se `gh pr merge N --admin --squash` falha com `'main' is already used by worktree at ...`, o merge **já aconteceu server-side** (o erro é só no checkout local pós-merge). Confirmar com `gh pr view N --json state` (vai mostrar `MERGED`). Se PR ainda estiver `OPEN`, usar `gh api -X PUT "repos/<owner>/<repo>/pulls/N/merge" -f merge_method=squash` direto via REST — não toca local. Pattern descoberto 2026-05-11.

**Bug Inertia `(array) $eloquent`** descoberto após migration: telas que faziam `Collection::map(fn ($a) => (array) $a)` em models Eloquent rendiam `undefined` em todos os campos (PR #596). Sempre `$a->toArray()`. Detalhes: feedback-eloquent-array-cast-inertia.md.
