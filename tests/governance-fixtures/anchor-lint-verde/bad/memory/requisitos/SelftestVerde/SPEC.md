---
slug: selftest-verde
status: ativo
---

# SPEC — SelftestVerde (fixture gate verde · G1b Phase B)

## US-SLFV-001 · regra implementada + coberta, mas teste só PULA no JUnit (caso BAD)

> Fixture BAD: US implementada (`anchored_ok`) COM DoD/aceite E teste que declara
> `@covers-us US-SLFV-001` — MAS o JUnit summary marca o arquivo-de-teste como só
> `skipped` (markTestSkipped: passed=0). Pela regra dura `skipped != passed`, isso NÃO
> é verde → `req_teste_vermelho` → `--check-verde` sai 1.
>
> A diferença good/bad está SÓ no `junit/pest-verde-junit.summary.json`: o SPEC,
> o Service e o Test são idênticos — isola a regra verde (não confunde com aceite/cobertura).

**Implementado em:** `Modules/SelftestVerde/Services/VerdeService.php`

**DoD:**
- [x] critério de aceite definido e analisado

**Testado em:** `Modules/SelftestVerde/Tests/Feature/VerdeTest.php`
