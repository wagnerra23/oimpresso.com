---
slug: selftest-entry-baseline
status: ativo
---

# SPEC — SelftestEntryBaseline (fixture ARMING grandfather · SA-A2-ter)

## US-SLEB-001 · regra implementada SEM aceite e SEM teste-que-cobre

> Fixture: a US se diz implementada (path existe → `anchored_ok`) mas NÃO tem DoD/aceite
> E o teste citado não declara `@covers-us` dela → seria `req_sem_aceite` + `req_sem_covering_test`.
> O veredito depende do `--baseline`: GOOD grandfathera US-SLEB-001 → exit 0. (BAD grandfathera
> só um decoy US-SLEB-999, então US-SLEB-001 é mentira NOVA e MORDE.) Prova o no-new-lie.

**Implementado em:** `Modules/SelftestEntryBaseline/Services/EntryService.php`

**Testado em:** `Modules/SelftestEntryBaseline/Tests/Feature/EntryTest.php`
