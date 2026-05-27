"""
Helper sync_checkpoint — leitura/escrita do checkpoint dual-sync.

Suporta o daemon dual-sync canônico:
  - read_last_sync_at(con, biz, sync_type)        → datetime | None
  - read_last_codigo_processed(con, biz, st)      → str | None
  - mark_running(con, biz, sync_type)             → consume-once flag
  - mark_success(con, biz, sync_type, rows, codigo) → UPDATE sucesso
  - mark_failed(con, biz, sync_type, error_msg)   → UPDATE falha
  - mark_chunk_progress(con, biz, st, codigo)     → resumability per-chunk

Default safe: se tabela `sync_checkpoint` não existir (migration não rodada em prod),
todos métodos retornam sentinela (`None` em leitura, `False` em escrita) + warn no stderr.
Importer continua com FULL SYNC (lição: ADR proposal §3 ponto 6 — default safe).

Multi-tenant Tier 0 (ADR 0093): sempre filtra por business_id no WHERE.

Refs:
  - database/migrations/2026_05_14_180000_create_sync_checkpoint.php
  - memory/decisions/proposals/dual-system-delphi-oimpresso-sync-realtime.md §3
"""

from __future__ import annotations

import sys
from datetime import datetime
from typing import Any


def _table_exists(con) -> bool:
    """Default safe — se sync_checkpoint não existe, retorna False (sem quebrar daemon)."""
    try:
        with con.cursor() as cur:
            cur.execute("SHOW TABLES LIKE 'sync_checkpoint'")
            return cur.fetchone() is not None
    except Exception:
        return False


def read_last_sync_at(con, business_id: int, sync_type: str) -> datetime | None:
    """Lê timestamp última sincronização sucesso. None se nunca rodou ou tabela ausente."""
    if not _table_exists(con):
        print(
            f"[sync_checkpoint] WARN — tabela ausente. FULL SYNC pra biz={business_id} type={sync_type}",
            file=sys.stderr,
        )
        return None
    with con.cursor() as cur:
        cur.execute(
            "SELECT last_sync_at FROM sync_checkpoint "
            "WHERE business_id=%s AND sync_type=%s LIMIT 1",
            (business_id, sync_type),
        )
        row = cur.fetchone()
        if not row:
            return None
        val = row["last_sync_at"] if isinstance(row, dict) else row[0]
        return val


def read_last_codigo_processed(con, business_id: int, sync_type: str) -> str | None:
    """Lê último CODIGO processado (chunk resumability). None se nunca rodou."""
    if not _table_exists(con):
        return None
    with con.cursor() as cur:
        cur.execute(
            "SELECT last_codigo_processed FROM sync_checkpoint "
            "WHERE business_id=%s AND sync_type=%s LIMIT 1",
            (business_id, sync_type),
        )
        row = cur.fetchone()
        if not row:
            return None
        val = row["last_codigo_processed"] if isinstance(row, dict) else row[0]
        return val


def _upsert_row(
    con,
    business_id: int,
    sync_type: str,
    last_status: str,
    rows_processed: int | None = None,
    last_codigo: str | None = None,
    error_msg: str | None = None,
    update_last_sync_at: bool = False,
) -> bool:
    """UPSERT linha (business_id, sync_type). Idempotente."""
    if not _table_exists(con):
        return False

    with con.cursor() as cur:
        cur.execute(
            "SELECT id FROM sync_checkpoint "
            "WHERE business_id=%s AND sync_type=%s LIMIT 1",
            (business_id, sync_type),
        )
        existing = cur.fetchone()

        fields = ["last_status = %s"]
        values: list[Any] = [last_status]
        if update_last_sync_at:
            fields.append("last_sync_at = NOW()")
        if rows_processed is not None:
            fields.append("rows_processed = %s")
            values.append(rows_processed)
        if last_codigo is not None:
            fields.append("last_codigo_processed = %s")
            values.append(last_codigo)
        # error_msg sempre — NULL limpa erro anterior, valor seta diagnóstico
        fields.append("error_msg = %s")
        values.append(error_msg)
        fields.append("updated_at = NOW()")

        if existing:
            row_id = existing["id"] if isinstance(existing, dict) else existing[0]
            values.append(row_id)
            cur.execute(
                f"UPDATE sync_checkpoint SET {', '.join(fields)} WHERE id = %s",
                tuple(values),
            )
        else:
            # INSERT inicial
            cur.execute(
                "INSERT INTO sync_checkpoint "
                "(business_id, sync_type, last_status, rows_processed, "
                "last_codigo_processed, error_msg, "
                + ("last_sync_at, " if update_last_sync_at else "")
                + "created_at, updated_at) VALUES "
                "(%s, %s, %s, %s, %s, %s, "
                + ("NOW(), " if update_last_sync_at else "")
                + "NOW(), NOW())",
                (
                    business_id,
                    sync_type,
                    last_status,
                    rows_processed or 0,
                    last_codigo,
                    error_msg,
                ),
            )
    return True


def mark_running(con, business_id: int, sync_type: str) -> bool:
    """Marca status='running' — sinaliza que daemon está processando agora."""
    return _upsert_row(con, business_id, sync_type, last_status="running", error_msg=None)


def mark_success(
    con,
    business_id: int,
    sync_type: str,
    rows_processed: int,
    last_codigo: str | None = None,
) -> bool:
    """Marca sucesso — atualiza last_sync_at=NOW() (filtro próxima rodada)."""
    return _upsert_row(
        con,
        business_id,
        sync_type,
        last_status="success",
        rows_processed=rows_processed,
        last_codigo=last_codigo,
        error_msg=None,
        update_last_sync_at=True,
    )


def mark_partial(
    con,
    business_id: int,
    sync_type: str,
    rows_processed: int,
    last_codigo: str | None = None,
    error_msg: str | None = None,
) -> bool:
    """Marca sucesso parcial (alguns chunks ok, último falhou) — last_sync_at NÃO avança."""
    return _upsert_row(
        con,
        business_id,
        sync_type,
        last_status="partial",
        rows_processed=rows_processed,
        last_codigo=last_codigo,
        error_msg=error_msg,
    )


def mark_failed(
    con,
    business_id: int,
    sync_type: str,
    error_msg: str,
) -> bool:
    """Marca falha total — last_sync_at preserva valor anterior (retry vai reprocessar tudo desde último sucesso)."""
    return _upsert_row(
        con,
        business_id,
        sync_type,
        last_status="failed",
        error_msg=error_msg[:1000] if error_msg else None,
    )


def mark_chunk_progress(
    con,
    business_id: int,
    sync_type: str,
    last_codigo: str,
) -> bool:
    """Atualiza só last_codigo_processed (chunk concluído) — sem mudar status nem last_sync_at."""
    if not _table_exists(con):
        return False
    with con.cursor() as cur:
        cur.execute(
            "UPDATE sync_checkpoint "
            "SET last_codigo_processed = %s, updated_at = NOW() "
            "WHERE business_id = %s AND sync_type = %s",
            (last_codigo, business_id, sync_type),
        )
    return True


def format_firebird_timestamp(dt: datetime) -> str:
    """Formata datetime pra Firebird timestamp WHERE clause.

    Firebird aceita 'YYYY-MM-DD HH:MM:SS'.
    """
    return dt.strftime("%Y-%m-%d %H:%M:%S")
