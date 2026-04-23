# ADR TECH-0001 (PontoWr2) · AFD importer em chunks com transação

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner, Claude
- **Categoria**: tech

## Contexto

AFD (Arquivo Fonte de Dados — formato padrão SEFIP/REP-P) pode ter 500k+ linhas num arquivo só. Importar sem estratégia gera:
- Timeout PHP (>30s em arquivos médios)
- Memory overflow (carregando tudo em array)
- Perda parcial se qualquer linha quebrar no meio

## Decisão

`ImportAfdCommand` usa:
1. **Leitura linha-a-linha** via `fopen` + `fgets` (nunca `file_get_contents` em arquivo AFD).
2. **Chunks de 500 linhas** — commit parcial a cada chunk.
3. **Transação por chunk** — se falhar, só perde as 500 últimas.
4. **Tipo 9 (trailer) valida contagem total** antes de persistir — se contagem bater, importação é íntegra.
5. **Pré-validação** via `ponto:afd-inspecionar` antes de rodar import (lista PIS que não estão cadastrados).

## Consequências

**Positivas:**
- Arquivo de 1M linhas importa em ~2min sem blow up de memória.
- Falha parcial isola dano (500 linhas perdidas vs 1M).
- Inspeção prévia evita surprise durante import.

**Negativas:**
- Código mais complexo que um único `Marcacao::insert()`.
- Se trailer não bater, é mais chato diagnosticar.

## Alternativas consideradas

- **Laravel Queue com jobs por linha**: rejeitado — overhead Redis absurdo pra 1M jobs triviais.
- **LOAD DATA INFILE do MySQL**: rapido mas pula validações de modelo (Portaria 671 exige hash).
- **Lib externa de CSV parsing**: AFD não é CSV (é fixed-width), libs genéricas não servem.
