# SPEC — Módulo Grow

## ⚠️ Status: AUSENTE no repositório

Em 2026-04-26, ao executar o lote `claude/tests-batch-5-grow-bi-dash`,
o agente verificou que **NÃO existe** `Modules/Grow/` no working tree
do branch base disponível (`main`).

## Evidência
```
$ ls Modules/ | grep -i grow
(no output)
```

Branches existentes:
- `main`
- `remotes/origin/main`

O branch `6.7-bootstrap` referenciado no preâmbulo do lote **não existe**
no remoto. A criação do branch de trabalho foi feita a partir de `main`.

## TODO
- Confirmar com o time se o módulo Grow está em outro fork/branch ainda
  não sincronizado, ou se o nome real difere (ex.: "Crescimento",
  "Growth").
- Quando o módulo aterrissar, replicar a estrutura
  `Modules/Grow/Tests/Feature/GrowTestCase.php` + testes de auth para
  cada controller público, seguindo o template usado em BI/Essentials
  neste mesmo lote.

## Placeholder de cobertura
- `Modules/Grow/Tests/Feature/.gitkeep` — apenas para fixar a estrutura
  de pastas e facilitar o lote subsequente.
