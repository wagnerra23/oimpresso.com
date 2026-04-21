# ADR 0003 — Marcações append-only com triggers MySQL + proteção na aplicação

**Status:** ✅ Aceita
**Data:** 2026-04-18

## Contexto

A Portaria MTP 671/2021 exige que uma marcação de ponto, uma vez registrada, **não possa ser alterada nem excluída**. Correções ocorrem por **anulação** (marcação de origem `ANULACAO` que cancela uma anterior) e nova inclusão — nunca por UPDATE ou DELETE.

A auditoria fiscal pode exigir a reconstrução da trilha completa de eventos. Se permitirmos UPDATE/DELETE:

- Um bug, um DBA descuidado, ou má fé podem apagar evidência
- Fica impossível comprovar que o sistema é tamper-evident em auditoria
- Violamos o conceito de "registro inviolável" exigido pela portaria

## Decisão

**Defesa em profundidade em 3 camadas:**

1. **MySQL triggers** `BEFORE UPDATE` e `BEFORE DELETE` em `ponto_marcacoes` e `ponto_banco_horas_movimentos` que fazem `SIGNAL SQLSTATE '45000'` com mensagem explicando a proibição. Uma query direta no banco **falha**.

2. **Application layer:** os models `Marcacao` e `BancoHorasMovimento` sobrescrevem `update()` e `delete()` para lançar `RuntimeException`. Código aplicação falha antes mesmo de chegar ao banco.

3. **Hash encadeado:** cada marcação armazena `hash_anterior` + `hash` do conteúdo. Qualquer violação de ordem ou conteúdo é detectável via recomputação.

Correções sempre via `anular($motivo)` que cria uma marcação `ANULACAO` apontando para a original.

## Consequências

### Positivas

- Compliance demonstrável em auditoria
- Proteção contra bugs, erros operacionais e má fé
- Trilha completa preservada — toda mudança é visível

### Negativas

- Testes precisam usar `DB::unprepared` para desabilitar trigger em limpeza de fixtures, ou rodar em banco de teste separado
- Migrations em produção não podem "corrigir dados" diretamente
- Precisamos ensinar devs novos: "não tente dar UPDATE em marcação, use `anular()`"

### Riscos

- **MariaDB** pode comportar-se diferente do MySQL em triggers (sintaxe de `SIGNAL` é compatível mas DDL completo merece teste). Mitigação: documentado que requisito é MySQL 8, não MariaDB, no momento.
