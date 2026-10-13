---
sessao: "errata-indice"
titulo: "Forja — a thread 09 está entregue (#8985); a prova procura o teste com um nome que ele não tem"
executor: "[CL]"
data: 2026-10-13
base: wagnerra23/oimpresso.com@main 37b907474
---
# Errata do `00-INDICE.md` da Forja, nº 3 (pedido ao Cowork)

O Code não edita o `00-INDICE.md` no espelho (§5 2026-09-24), nem as erratas nº 1 e nº 2 de
2026-10-07, que tratam das threads 07 e 05. Esta é uma errata nova. Ela não é recibo: o placar lê
só `_saida-<id>.md`.

## O que o placar mostra

```
09 [em curso ] Tier 0: decompose de project sem business_id … — Modules/Forja/Tests/Feature/ForjaDecomposeTenantTest.php (arquivo ausente)
```

## Por quê

A única prova da 09 é `{"tipo": "arquivo", "path": "Modules/Forja/Tests/Feature/ForjaDecomposeTenantTest.php"}`.
O teste foi entregue pelo #8985 com outro nome: **`Modules/Forja/Tests/Feature/ProjectDecomposeTenantTest.php`**.
O `_saida-09.md` (2026-10-08) já cita esse nome.

Medido em `37b907474`:

- o arquivo existe e tem os dois casos do UC-ADPS-03 (`:78` serviço, `:93` HTTP);
- ele está na lane `forja-pest.yml` (`:229`);
- `ForjaDecomposeTenantTest.php` não existe, nem no histórico do repo
  (`git log --all -- 'Modules/Forja/Tests/Feature/ForjaDecompose*'` não devolve nada).

O prefixo da thread também cita o nome antigo (`Modules/Forja/Tests/Feature/ForjaDecomposeTenantTest.php`).

## O que mudar no índice

Na thread `09`, nos dois lugares (`prefixo` e `provas`), trocar
`ForjaDecomposeTenantTest.php` por `ProjectDecomposeTenantTest.php`:

```json
"provas": [
  {
    "tipo": "arquivo",
    "path": "Modules/Forja/Tests/Feature/ProjectDecomposeTenantTest.php"
  }
]
```

Com a troca, a 09 sai como `feito` pelo `_saida-09.md` que já existe.
