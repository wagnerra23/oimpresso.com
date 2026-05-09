"""
Inferência de FKs por convenção `COD<TABELA>` no Delphi WR Comercial.

Convenção documentada em `memory/dominios/wr-comercial/CONVENCOES.md`:
  - CODIGO         → PK da tabela atual (não FK)
  - CODIGO_<x>     → não-FK (string externa: CODIGO_CEDENTE, CODIGO_TRANSMISSAO)
  - COD<TABELA>    → FK pra <TABELA>(CODIGO)
  - COD<X>_<sufix> → FK pra <X>(CODIGO) com qualificador semântico
"""

from __future__ import annotations

import re

# Regex: começa com COD, NÃO seguido de IGO (pra excluir CODIGO/CODIGO_*)
COD_FK_RE = re.compile(r"^COD(?!IGO)(\w+)$", re.IGNORECASE)

# Exceções hardcoded — colunas que parecem FK por nome mas não são
NOT_FK_OVERRIDES: set[str] = {
    "CODIGO",
    "CODIGO_CEDENTE",
    "CODIGO_TRANSMISSAO",
    "CODIGO_BKP",
    "CODIGO_MIGRADO",
    "CODIGO_DATA_ARQUIVO",
}

# Tabela canônica → mapping virtual (FORNECEDOR não existe; é vista sobre PESSOAS)
TABLE_ALIASES: dict[str, str] = {
    "FORNECEDOR": "PESSOAS",
    "FORNECEDORES": "PESSOAS",
    "CLIENTE": "PESSOAS",
    "CLIENTES": "PESSOAS",
    "FUNCIONARIO": "PESSOAS",
    "FUNCIONARIOS": "PESSOAS",
    "REPRESENTANTE": "PESSOAS",
    "REPRESENTANTES": "PESSOAS",
    "ASSOCIADO": "PESSOAS",
    "ASSOCIADOS": "PESSOAS",
}


def _try_singular_plural_match(candidate: str, known: set[str]) -> str | None:
    """Tenta resolver candidate vs conjunto de tabelas conhecidas.

    Tenta na ordem: match exato, alias, plural (+S), singular (-S).
    """
    if candidate in known:
        return candidate

    # Alias hardcoded (FORNECEDOR → PESSOAS)
    aliased = TABLE_ALIASES.get(candidate)
    if aliased and aliased in known:
        return aliased

    # Plural: BANCO → BANCOS
    if (candidate + "S") in known:
        return candidate + "S"

    # Singular: BANCOS → BANCO (raro mas possível)
    if candidate.endswith("S") and candidate[:-1] in known:
        return candidate[:-1]

    return None


def infer_fk(column_name: str, all_tables: set[str]) -> str | None:
    """Retorna nome da tabela alvo da FK, ou None se col não é FK.

    Args:
        column_name: nome da coluna (CODBANCO, CODCONTA, CODEMPRESA, etc)
        all_tables: set de nomes de tabelas conhecidas (uppercase)

    Returns:
        Nome da tabela alvo (uppercase) ou None.

    Exemplos (assumindo all_tables = {'BANCOS', 'CONTAS', 'EMPRESA', 'FINANCEIRO_BOLETO', 'PESSOAS'}):
        infer_fk('CODBANCO', ...)              → 'BANCOS'
        infer_fk('CODCONTA', ...)              → 'CONTAS'
        infer_fk('CODEMPRESA', ...)            → 'EMPRESA'
        infer_fk('CODFINANCEIRO_BOLETO', ...)  → 'FINANCEIRO_BOLETO'
        infer_fk('CODFORNECEDOR', ...)         → 'PESSOAS'  (alias)
        infer_fk('CODBANCO_COOPERATIVA', ...)  → 'BANCOS'   (strip _COOPERATIVA)
        infer_fk('CODIGO', ...)                → None       (PK, not FK)
        infer_fk('CODIGO_CEDENTE', ...)        → None       (override)
        infer_fk('CODIGO_TRANSMISSAO', ...)    → None       (override)
    """
    upper = column_name.upper().strip()

    # Override explícito
    if upper in NOT_FK_OVERRIDES:
        return None

    # Não bate com pattern COD<X>
    m = COD_FK_RE.match(upper)
    if not m:
        return None

    candidate = m.group(1)
    upper_known = {t.upper() for t in all_tables}

    # 1. Match exato/alias/plural do candidate inteiro
    result = _try_singular_plural_match(candidate, upper_known)
    if result:
        return result

    # 2. Strip trailing _XXX progressivamente
    parts = candidate.split("_")
    while len(parts) > 1:
        parts = parts[:-1]
        prefix = "_".join(parts)
        result = _try_singular_plural_match(prefix, upper_known)
        if result:
            return result

    # 3. Não resolvido
    return None


def infer_fks_for_table(
    columns: list[str], all_tables: set[str]
) -> dict[str, str]:
    """Pra todas as colunas de uma tabela, retorna {col_name: target_table}.

    Apenas colunas com FK detectada aparecem. Use isso pra enriquecer
    documentação e gerar grafo de dependências.
    """
    return {
        col: target
        for col in columns
        if (target := infer_fk(col, all_tables)) is not None
    }


def topological_order(
    table_to_deps: dict[str, set[str]]
) -> list[str]:
    """Retorna ordem de import respeitando dependências (Kahn's algorithm).

    Tabelas com 0 dependências vão primeiro. Auto-FK ignorada (não-bloqueante).

    Args:
        table_to_deps: {table: {dep1, dep2, ...}}
    """
    # Remove self-deps + deps fora do conjunto
    all_keys = set(table_to_deps.keys())
    cleaned = {
        t: {d for d in deps if d != t and d in all_keys}
        for t, deps in table_to_deps.items()
    }

    in_degree = {t: len(deps) for t, deps in cleaned.items()}
    queue = sorted([t for t, deg in in_degree.items() if deg == 0])
    result: list[str] = []

    while queue:
        current = queue.pop(0)
        result.append(current)
        for t, deps in cleaned.items():
            if current in deps:
                in_degree[t] -= 1
                if in_degree[t] == 0:
                    queue.append(t)
                    queue.sort()  # mantém deterministico

    # Tabelas restantes têm ciclo — adiciona no fim em ordem alfabética
    remaining = sorted(set(cleaned.keys()) - set(result))
    result.extend(remaining)
    return result
