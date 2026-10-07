---
sessao: "errata-indice"
titulo: "Módulos — a 01 está entregue; o placar a mostra em curso porque as duas provas do índice medem a coisa errada"
executor: "[CL]"
data: 2026-10-07
base: wagnerra23/oimpresso.com@main ea4b4f0985
---
# Errata do `00-INDICE.md` de Módulos (pedido ao Cowork)

O Code não edita o `00-INDICE.md` no espelho: a correção vale só quando o Cowork a fizer e o
próximo retorno a trouxer. Este arquivo diz o que mudar e por quê. O diagnóstico é o do
`_saida-01.md`; aqui fica só o pedido, medido de novo.

## Placar medido

`node scripts/qa/placar.mjs --indice` em `ea4b4f0985`:

```
Modules: entregue 1 de 4 · próximo 0 · em curso 1 · pendente 2 · bloqueada 0
  01 [em curso ] P1+P2: install falho volta a inativo e "Com erro" acende (com prova) — tests/Unit/Services/ModuleErroFixtureTest.php (arquivo ausente)
  04 [pendente ] PR-8: remover o legado /manage-modules (último, com portão) — sem _saida; depende de 01 (não feita); …
```

A 04 fica presa atrás da 01 por causa disso.

## As duas provas da 01, e o que elas medem de fato

| prova no json | medição | por que não serve |
|---|---|---|
| `arquivo tests/Unit/Services/ModuleErroFixtureTest.php` | ausente | Criar o arquivo duplicaria os 3 `UC-MOD-20` que já existem em `tests/Feature/Modules/ModuleManagerServiceTest.php`, e em `tests/Unit/` ele quebraria: `tests/Pest.php` só liga `Tests\TestCase` em `Feature/`, `Browser/` e KB, e o teste usa `storage_path()` e a facade `File`. |
| `contem app/Services/ModuleManagerService.php "setActive($name, false)"` | 1 ocorrência, na `:314` | A `:314` está em `uninstall()` (começa na `:312`), não no `catch` de `install()` (`:218`). Ela já existia antes do índice. O conserto real é `setActive($name, $estadoAnterior)`, que volta ao estado **anterior**, e não a `false`: um "Reinstalar" que falhasse com `false` derrubaria um módulo que estava ativo. |

O que a thread entregou foi pôr o teste na lane de PR ([#8840](https://github.com/wagnerra23/oimpresso.com/pull/8840)).
Antes, o `ModuleManagerServiceTest.php` rodava só no nightly do CT 100.

## Pedido

No json, trocar as duas provas da thread `01` por:

```json
"provas": [
  { "tipo": "contem", "path": ".github/ci-sqlite-pest.list", "padrao": "tests/Feature/Modules/ModuleManagerServiceTest.php" },
  { "tipo": "contem", "path": "app/Services/ModuleManagerService.php", "padrao": "setActive($name, $estadoAnterior)" }
]
```

E tirar `tests/Unit/Services/ModuleErroFixtureTest.php` do `prefixo` da 01.

Medido em `ea4b4f0985`: as duas casam, 1 ocorrência cada (`grep -c`). O mesmo arquivo de teste
tem os `UC-MOD-13` (instalar e reinstalar que falham) e os `UC-MOD-20` (`module.json` com defeito),
em 5 linhas.

## Depois do retorno

Com a troca, a 01 vira `feito` (as duas provas casam e o `_saida-01.md` existe) e a 04 deixa de
estar presa pela dependência. Placar esperado: `entregue 2 de 4`. A 02 segue esperando a decisão
D4, e a 04 segue com as provas próprias dela.

## Por que só Módulos, entre as threads triadas em 2026-10-07

As outras threads classe (a) da mesma triagem não têm índice errado:
- `repair/02`, `officeimpresso/09` e `recepcao-pacote/01` estão em curso porque a prova é de recibo,
  e o avaliador de recibo saiu do repo (ADR 0397). A prova está certa, só não é medível pelo placar.
- `venda-menu/07` (entregue no #8631 e no #8633) espera a `00` (PUXAR as telas de Vendas), que está
  em andamento em outra sessão. Quando a `00` tiver o `_saida-00.md`, a 07 vira `feito` sem mexer no índice.
