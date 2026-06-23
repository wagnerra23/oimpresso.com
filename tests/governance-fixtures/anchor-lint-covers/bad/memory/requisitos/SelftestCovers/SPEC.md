---
slug: selftest-covers
status: ativo
---

# SPEC — SelftestCovers (fixture do covers-check · G1a)

## US-SLFC-001 · teste NÃO declara @covers-us (caso BAD)

> Fixture BAD: o teste citado existe (não é dead_test) mas NÃO declara `@covers-us US-SLFC-001` → 1 `testado_sem_covers` → `--check-covers` sai 1 com acusação.

**Implementado em:** _pendente_ — fixture (tela não construída, estado legítimo ADR 0273)

**Testado em:** `Modules/SelftestCovers/Tests/Feature/CoversTest.php`
