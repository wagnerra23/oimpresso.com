# fixture `anchor-lint-covers` (G1a · ADR 0303 emenda)

Prova que `anchor-lint --check-covers` MORDE o estado novo `testado_sem_covers`:

- **good/** → o teste citado em `**Testado em:**` declara `// @covers-us US-SLFC-001` → 0 `testado_sem_covers` → **exit 0**.
- **bad/** → o teste EXISTE (não é dead_test) mas **NÃO** declara `@covers-us` → **exit 1**, acusação `não declara @covers-us`.

A brecha real fechada: `**Testado em:** \`SpatiePermissionsTest\`` (teste genérico — `NfeBrasil/SPEC.md:281`, só existe em `Modules/Ponto`) passa o existence-check mas não prova nada sobre a US. `covers` = **marcador grep**, NÃO atributo PHP (os testes do repo são closures Pest `uses(Tests\TestCase::class)` + `it()`; atributo não anexa a closure).

Camada 2 do `gate-selftest.mjs` (GT-G6, ADR 0256): o detector de mentira é ele próprio testado. Em produção o `anchor-drift.yml` roda `--check` (NÃO `--check-covers`) → `testado_sem_covers` é **advisory** até armar por calendário (ADR 0275).
