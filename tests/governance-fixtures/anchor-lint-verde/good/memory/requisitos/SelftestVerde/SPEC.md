---
slug: selftest-verde
status: ativo
---

# SPEC — SelftestVerde (fixture gate verde · G1b Phase B)

## US-SLFV-001 · regra implementada + coberta, prova-verde via JUnit (caso GOOD)

> Fixture GOOD: US implementada (`anchored_ok`) COM DoD/aceite E teste que declara
> `@covers-us US-SLFV-001`, e o JUnit summary marca o arquivo-de-teste como VERDE
> (passed>0, fail=0) → 0 `req_teste_vermelho` → `--check-verde` sai 0.
>
> A diferença good/bad está SÓ no `test-results/pest-verde-junit.summary.json`: o SPEC,
> o Service e o Test são idênticos — isola a regra verde (não confunde com aceite/cobertura).

**Implementado em:** `Modules/SelftestVerde/Services/VerdeService.php`

**DoD:**
- [x] critério de aceite definido e analisado

**Testado em:** `Modules/SelftestVerde/Tests/Feature/VerdeTest.php`
