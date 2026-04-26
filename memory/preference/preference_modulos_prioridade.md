# Preferência — Prioridade de módulos para testes

Data: 2026-04-26 (criada por inferência durante o lote 5).

Ordem de prioridade declarada no briefing original do lote:

1. **Modules/Grow** — não existe ainda; aguarda merge.
2. **Modules/BI** — bridge OAuth2; lote 5 cobre auth dos controllers web.
3. **Modules/Dashboard** — boilerplate ainda; lote 5 cobre sanidade do
   endpoint.
4. **Modules/Essentials** — HRM e essenciais; lote 5 cobre auth dos
   controllers principais.

Próximos lotes devem manter esta ordem até cobertura razoável (>= 60%
dos controllers públicos com pelo menos um teste de auth).
