---
slug: selftest-entry
status: ativo
---

# SPEC — SelftestEntry (fixture gate de entrada · G1b)

## US-SLFE-001 · regra implementada SEM aceite e SEM teste-que-cobre (caso BAD)

> Fixture BAD: a US se diz implementada (path existe) mas NÃO tem DoD/aceite E o teste citado não declara `@covers-us` dela → `req_sem_aceite` + `req_sem_covering_test` → `--check-entry` sai 1.

**Implementado em:** `Modules/SelftestEntry/Services/EntryService.php`

**Testado em:** `Modules/SelftestEntry/Tests/Feature/EntryTest.php`
