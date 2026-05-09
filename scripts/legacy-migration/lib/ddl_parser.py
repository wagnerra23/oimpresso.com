"""
Parser DDL pragmático pro UpdateSQL.txt do legacy Delphi WR Comercial.

NÃO é parser SQL completo. Reconhece:
  - CREATE TABLE NAME (col TYPE [NOT NULL], col TYPE, ...)
  - ALTER TABLE NAME ADD COL TYPE [modifiers]
  - ALTER TABLE NAME DROP COL
  - ALTER TABLE NAME ALTER [COLUMN] COL TO NEWNAME
  - ALTER TABLE NAME ALTER [COLUMN] COL TYPE NEWTYPE
  - DROP TABLE NAME

Ignora silenciosamente:
  - INSERT INTO ... VALUES (...)
  - UPDATE table SET ... (DML)
  - CREATE / RECREATE / ALTER PROCEDURE
  - CREATE INDEX, CREATE TRIGGER
  - EXECUTE PROCEDURE / EXECUTE BLOCK
  - COMMENT ON
  - SET TERM
  - ALTER TABLE ... ADD CONSTRAINT (não é mudança de coluna)
"""

from __future__ import annotations

import re
from dataclasses import dataclass, field


@dataclass
class Column:
    name: str
    type: str
    nullable: bool = True
    added_at_v: int = 0
    last_modified_v: int = 0


@dataclass
class TableEvent:
    """Um evento aplicado a uma tabela numa versão."""
    version: int
    operation: str  # CREATE, ADD_COL, DROP_COL, RENAME_COL, ALTER_TYPE, DROP_TABLE
    detail: str  # texto humano-legível: "+ DESCRICAO VARCHAR(50)" / "× X→Y" / etc


@dataclass
class Table:
    name: str
    created_at_v: int = 0
    last_modified_v: int = 0
    columns: dict[str, Column] = field(default_factory=dict)
    history: list[TableEvent] = field(default_factory=list)
    dropped_at_v: int | None = None


# ============================================================================
# Splitter — separa arquivo em statements terminados por ;
# ============================================================================

# Comentários multi-linha /* ... */ podem ser muito grandes; greedy com flag DOTALL
BLOCK_COMMENT_RE = re.compile(r"/\*.*?\*/", re.DOTALL)
LINE_COMMENT_RE = re.compile(r"--[^\n]*")


def split_statements(text: str) -> list[str]:
    """Separa SQL em statements (sem comentários, terminados por ; em fim de linha)."""
    text = BLOCK_COMMENT_RE.sub("", text)
    text = LINE_COMMENT_RE.sub("", text)
    # Split por ; seguido de whitespace ou EOL
    parts = re.split(r";[ \t]*(?:\n|$)", text)
    return [s.strip() for s in parts if s.strip()]


# ============================================================================
# Regex DDL
# ============================================================================

UPDATE_HEADER_RE = re.compile(r"^UPDATE\s+(\d+)\s*$", re.IGNORECASE)

# CREATE TABLE NAME ( col TYPE [NOT NULL], col TYPE, ... )
CREATE_TABLE_RE = re.compile(
    r"^\s*CREATE\s+TABLE\s+(\w+)\s*\((.*)\)\s*$", re.IGNORECASE | re.DOTALL
)

# ALTER TABLE NAME ADD COL TYPE [modifiers]
# Exclui ADD CONSTRAINT, ADD PRIMARY KEY, ADD FOREIGN KEY
ALTER_ADD_RE = re.compile(
    r"^\s*ALTER\s+TABLE\s+(\w+)\s+ADD\s+"
    r"(?!CONSTRAINT\b|PRIMARY\s+KEY\b|FOREIGN\s+KEY\b|UNIQUE\b|CHECK\b)"
    r"(\w+)\s+(.+?)\s*$",
    re.IGNORECASE | re.DOTALL,
)

# ALTER TABLE NAME DROP COL
ALTER_DROP_RE = re.compile(
    r"^\s*ALTER\s+TABLE\s+(\w+)\s+DROP\s+(?!CONSTRAINT\b)(\w+)\s*$",
    re.IGNORECASE,
)

# ALTER TABLE NAME ALTER [COLUMN] COL TO NEWNAME
ALTER_RENAME_RE = re.compile(
    r"^\s*ALTER\s+TABLE\s+(\w+)\s+ALTER\s+(?:COLUMN\s+)?(\w+)\s+TO\s+(\w+)\s*$",
    re.IGNORECASE,
)

# ALTER TABLE NAME ALTER [COLUMN] COL TYPE NEWTYPE
ALTER_TYPE_RE = re.compile(
    r"^\s*ALTER\s+TABLE\s+(\w+)\s+ALTER\s+(?:COLUMN\s+)?(\w+)\s+TYPE\s+(.+?)\s*$",
    re.IGNORECASE | re.DOTALL,
)

# DROP TABLE NAME
DROP_TABLE_RE = re.compile(
    r"^\s*DROP\s+TABLE\s+(\w+)\s*$", re.IGNORECASE
)

# Statements explicitamente ignorados (silently)
IGNORED_PREFIXES = (
    "INSERT INTO",
    "UPDATE ",  # DML — note: header UPDATE N; já foi tratado fora
    "DELETE FROM",
    "MERGE INTO",
    "CREATE PROCEDURE",
    "CREATE OR ALTER PROCEDURE",
    "RECREATE PROCEDURE",
    "ALTER PROCEDURE",
    "ALTER TABLE PROCEDURE",  # sintaxe inválida usada pelo dev (não é DDL real)
    "DROP PROCEDURE",
    "CREATE INDEX",
    "CREATE UNIQUE INDEX",
    "CREATE ASC INDEX",
    "CREATE DESC INDEX",
    "DROP INDEX",
    "CREATE TRIGGER",
    "DROP TRIGGER",
    "ALTER TRIGGER",
    "RECREATE TRIGGER",
    "CREATE OR ALTER TRIGGER",
    "EXECUTE PROCEDURE",
    "EXECUTE BLOCK",
    "COMMENT ON",
    "SET TERM",
    "SET GENERATOR",
    "GRANT",
    "REVOKE",
    "CREATE GENERATOR",
    "CREATE SEQUENCE",
    "ALTER SEQUENCE",
    "DROP SEQUENCE",
    "CREATE EXCEPTION",
    "ALTER EXCEPTION",
    "RECREATE EXCEPTION",
    "CREATE DOMAIN",
    "ALTER DOMAIN",
    "DROP DOMAIN",
    "CREATE VIEW",
    "RECREATE VIEW",
    "ALTER VIEW",
    "DROP VIEW",
    "CREATE OR ALTER VIEW",
    "DECLARE EXTERNAL FUNCTION",
)


# ============================================================================
# Parsers de fragmentos
# ============================================================================

def _parse_column_def(col_def: str) -> Column | None:
    """Parseia 'NAME TYPE [NOT NULL] [outros]' em Column. Retorna None se não bate."""
    col_def = col_def.strip().rstrip(",")
    if not col_def:
        return None

    # Skip linhas que são CONSTRAINT dentro do CREATE TABLE
    upper = col_def.upper()
    if upper.startswith(("CONSTRAINT ", "PRIMARY KEY", "FOREIGN KEY", "UNIQUE ", "CHECK ")):
        return None

    parts = col_def.split(None, 1)
    if len(parts) < 2:
        return None
    name = parts[0]
    rest = parts[1]
    nullable = "NOT NULL" not in rest.upper()
    # Tipo é o "rest" sem NOT NULL e sem default verbose
    type_ = re.sub(r"\bNOT\s+NULL\b", "", rest, flags=re.IGNORECASE).strip()
    type_ = re.sub(r"\s+", " ", type_)
    return Column(name=name, type=type_, nullable=nullable)


def _split_create_table_columns(body: str) -> list[str]:
    """Separa o corpo de CREATE TABLE em definições de coluna respeitando parênteses balanceados."""
    parts: list[str] = []
    current: list[str] = []
    depth = 0
    for char in body:
        if char == "(":
            depth += 1
        elif char == ")":
            depth -= 1
        if char == "," and depth == 0:
            parts.append("".join(current).strip())
            current = []
        else:
            current.append(char)
    last = "".join(current).strip()
    if last:
        parts.append(last)
    return parts


# ============================================================================
# Aplicador de DDL
# ============================================================================

class SchemaState:
    """Estado in-memory do schema reconstruído versão a versão."""

    def __init__(self) -> None:
        self.tables: dict[str, Table] = {}
        self.unrecognized_statements: list[tuple[int, str]] = []  # (version, snippet)

    def apply_block(self, version: int, block_lines: list[str]) -> None:
        """Aplica todos os statements de um bloco UPDATE N;."""
        body = "\n".join(block_lines)
        for stmt in split_statements(body):
            self._apply_statement(stmt, version)

    def _apply_statement(self, stmt: str, version: int) -> None:
        upper = stmt.upper().lstrip()

        # Ignorados conhecidos (DML, procedures, etc)
        for prefix in IGNORED_PREFIXES:
            if upper.startswith(prefix):
                return

        # CREATE TABLE
        if m := CREATE_TABLE_RE.match(stmt):
            self._create_table(m.group(1), m.group(2), version)
            return

        # DROP TABLE
        if m := DROP_TABLE_RE.match(stmt):
            self._drop_table(m.group(1), version)
            return

        # ALTER TABLE ... ALTER ... TO ...  (rename — bater antes de TYPE)
        if m := ALTER_RENAME_RE.match(stmt):
            self._rename_column(m.group(1), m.group(2), m.group(3), version)
            return

        # ALTER TABLE ... ALTER ... TYPE ...
        if m := ALTER_TYPE_RE.match(stmt):
            self._alter_column_type(m.group(1), m.group(2), m.group(3), version)
            return

        # ALTER TABLE ... DROP ...
        if m := ALTER_DROP_RE.match(stmt):
            self._drop_column(m.group(1), m.group(2), version)
            return

        # ALTER TABLE ... ADD ...  (mais permissivo, vem por último)
        if m := ALTER_ADD_RE.match(stmt):
            self._add_column(m.group(1), m.group(2), m.group(3), version)
            return

        # ALTER TABLE constraint adds (PK/FK/UNIQUE) — silently skip
        if re.match(
            r"^\s*ALTER\s+TABLE\s+\w+\s+ADD\s+(CONSTRAINT|PRIMARY|FOREIGN|UNIQUE|CHECK)\b",
            stmt,
            re.IGNORECASE,
        ):
            return

        # ALTER TABLE drop constraint
        if re.match(
            r"^\s*ALTER\s+TABLE\s+\w+\s+DROP\s+CONSTRAINT\b", stmt, re.IGNORECASE
        ):
            return

        # Reportar pro relatório de cobertura
        snippet = stmt[:120].replace("\n", " ")
        self.unrecognized_statements.append((version, snippet))

    # ---- operations ----

    def _ensure_table(self, name: str, version: int) -> Table:
        upper_name = name.upper()
        if upper_name not in self.tables:
            t = Table(name=upper_name, created_at_v=version, last_modified_v=version)
            self.tables[upper_name] = t
        return self.tables[upper_name]

    def _create_table(self, name: str, body: str, version: int) -> None:
        t = self._ensure_table(name, version)
        t.created_at_v = version
        t.last_modified_v = version
        t.dropped_at_v = None
        # Parse cada coluna
        added: list[str] = []
        for col_def in _split_create_table_columns(body):
            col = _parse_column_def(col_def)
            if col:
                col.added_at_v = version
                col.last_modified_v = version
                t.columns[col.name.upper()] = col
                added.append(col.name)
        t.history.append(
            TableEvent(version, "CREATE", f"CREATE TABLE com {len(added)} colunas")
        )

    def _drop_table(self, name: str, version: int) -> None:
        upper_name = name.upper()
        if upper_name in self.tables:
            self.tables[upper_name].dropped_at_v = version
            self.tables[upper_name].last_modified_v = version
            self.tables[upper_name].history.append(
                TableEvent(version, "DROP_TABLE", "DROP TABLE")
            )

    def _add_column(self, table: str, col_name: str, rest: str, version: int) -> None:
        t = self._ensure_table(table, version)
        col_def = f"{col_name} {rest}"
        col = _parse_column_def(col_def)
        if col is None:
            return
        col.added_at_v = version
        col.last_modified_v = version
        t.columns[col.name.upper()] = col
        t.last_modified_v = version
        t.history.append(
            TableEvent(version, "ADD_COL", f"+ {col.name} {col.type}")
        )

    def _drop_column(self, table: str, col_name: str, version: int) -> None:
        t = self._ensure_table(table, version)
        upper_col = col_name.upper()
        if upper_col in t.columns:
            del t.columns[upper_col]
        t.last_modified_v = version
        t.history.append(TableEvent(version, "DROP_COL", f"- {col_name}"))

    def _rename_column(
        self, table: str, old_name: str, new_name: str, version: int
    ) -> None:
        t = self._ensure_table(table, version)
        upper_old = old_name.upper()
        upper_new = new_name.upper()
        if upper_old in t.columns:
            col = t.columns.pop(upper_old)
            col.name = new_name
            col.last_modified_v = version
            t.columns[upper_new] = col
        t.last_modified_v = version
        t.history.append(
            TableEvent(version, "RENAME_COL", f"× {old_name} → {new_name}")
        )

    def _alter_column_type(
        self, table: str, col_name: str, new_type: str, version: int
    ) -> None:
        t = self._ensure_table(table, version)
        upper_col = col_name.upper()
        new_type = re.sub(r"\s+", " ", new_type).strip()
        if upper_col in t.columns:
            t.columns[upper_col].type = new_type
            t.columns[upper_col].last_modified_v = version
        t.last_modified_v = version
        t.history.append(
            TableEvent(version, "ALTER_TYPE", f"~ {col_name} TYPE {new_type}")
        )

    # ---- API pública ----

    def alive_tables(self) -> dict[str, Table]:
        """Retorna só tabelas vivas (não dropped) na versão atual."""
        return {n: t for n, t in self.tables.items() if t.dropped_at_v is None}

    def coverage_report(self, total_statements_estimated: int) -> dict:
        return {
            "total_tables": len(self.tables),
            "alive_tables": len(self.alive_tables()),
            "dropped_tables": sum(1 for t in self.tables.values() if t.dropped_at_v),
            "unrecognized_count": len(self.unrecognized_statements),
            "unrecognized_sample": self.unrecognized_statements[:10],
        }
