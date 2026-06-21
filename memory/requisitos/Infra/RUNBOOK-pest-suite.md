# RUNBOOK — Adicionar módulo à suite Pest (`phpunit.xml`)

> **Erro recorrente**: criar `Modules/X/Tests/Feature/*Test.php` SEM registrar o diretório em `phpunit.xml` → testes existem no repo mas **CI nunca roda**, dando falsa sensação de cobertura.
>
> Wagner, 2026-05-06: "esse erro é constante guarde na memória para não ter mais isso".

## A regra invariante

**Antes de commitar qualquer arquivo de teste novo em `Modules/<Nome>/Tests/`, verifique:**

```bash
grep -q "Modules/<Nome>/Tests" phpunit.xml || echo "❌ falta registrar em phpunit.xml"
```

Se não estiver listado, **adicione** dentro de `<testsuite name="Feature">`:

```xml
<directory>./Modules/<Nome>/Tests/Feature</directory>
<directory>./Modules/<Nome>/Tests/Unit</directory>  <!-- se houver Unit -->
```

## Quando este erro acontece

1. Criar módulo novo (skill `criar-modulo`) e esquecer phpunit.xml
2. Adicionar primeiro teste a módulo existente que ainda não estava na suite (esp. módulos antigos: Financeiro, NfeBrasil, Jana — todos faltavam até 2026-05-06)
3. Criar pasta `Tests/` dentro de `Modules/X/Resources/` ou `Modules/X/Database/Seeders/Tests/` (sub-paths não cobertos por glob simples)

## Como verificar antes do PR

```bash
# 1. Lista módulos que têm testes
find Modules -path "*/Tests/Feature/*Test.php" -o -path "*/Tests/Unit/*Test.php" | \
  awk -F'/' '{print $2}' | sort -u

# 2. Lista módulos no phpunit.xml
grep -oE "Modules/[A-Za-z0-9]+/Tests" phpunit.xml | awk -F'/' '{print $2}' | sort -u

# 3. Diff = módulos com testes mas SEM registro
comm -23 <( ... ) <( ... )
```

Ou simplesmente: rodar `php artisan test` localmente — se o teste novo não aparecer no output, está fora da suite.

## Hook futuro (não implementado ainda)

`.claude/hooks/check-pest-suite-coverage.ps1` — detecta `Modules/*/Tests/*/*Test.php` sem entrada correspondente em `phpunit.xml`, bloqueia commit se houver gap. **TODO** quando alguém tiver tempo.

## Módulos já na suite (snapshot 2026-05-06)

- ✅ `tests/Unit`, `tests/Feature` (raiz Laravel)
- ✅ `Modules/Ponto/Tests/{Unit,Feature}`
- ✅ `Modules/Essentials/Tests/Feature`
- ✅ `Modules/Cms/Tests/Feature`
- ✅ `Modules/Jana/Tests/Feature` (adicionado 2026-05-06 com fix do MCP)
- ✅ `Modules/RecurringBilling/Tests/Feature` (adicionado 2026-05-06 com US-RB-040)

Módulos com tests **fora da suite** (a auditar):
- `Modules/Financeiro/Tests/` (criação prévia, não verificado)
- `Modules/NfeBrasil/Tests/` (idem)
- demais módulos sem Tests/ ainda

## Referências

- `phpunit.xml` linha 16-23 (testsuite Feature)
- ADR 0089 (Capterra-driven Module Evolution) — capacidade #1 de qualquer módulo é "tem suite Pest registrada"
- Skill `criar-modulo` (checklist 8 peças)
