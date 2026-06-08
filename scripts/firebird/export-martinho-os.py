#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
W28 G4 — Exporta Ordens de Serviço do Firebird (Martinho · WR Sistemas legacy)
para JSON consumido por `php artisan oficina:import-firebird-martinho`.

⚠️ Domínio (ADR 0194): Martinho = mecânica pesada de CAMINHÃO basculante, NÃO
locação de caçamba. O default de vehicle_type no importer é 'caminhao'. Este
script só normaliza/dumpa; a classificação canônica acontece no comando artisan.

Wagner roda LOCAL (Windows) onde tem o Firebird instalado. Output sobe pro
Hostinger via scp/git, e o importer consome com --dry-run (padrão) → --commit.

USO:
    # Instalar dep (1x)
    pip install firebird-driver

    # Descobrir os nomes REAIS das tabelas/colunas do FDB (ajustar o SCHEMA MAP abaixo)
    python scripts/firebird/export-martinho-os.py \
        --dsn "localhost/3050:C:/dados/MARTINHO.FDB" \
        --user SYSDBA --password masterkey \
        --dump-schema

    # Exportar OS + itens
    python scripts/firebird/export-martinho-os.py \
        --dsn "localhost/3050:C:/dados/MARTINHO.FDB" \
        --user SYSDBA --password masterkey \
        --output storage/app/firebird/martinho-os-2026-06-03.json

    # Limitar (debug / amostra)
    python scripts/firebird/export-martinho-os.py --dsn "..." --output ... --limit 50

VARIÁVEIS DE AMBIENTE (alternativa às flags):
    FB_DSN=...    FB_USER=SYSDBA    FB_PASSWORD=...

ESQUEMA OUTPUT (consumido por ImportFirebirdMartinhoCommand):
{
  "meta": {
    "exported_at": "2026-06-03T18:00:00-03:00",
    "source_dsn_masked": "localhost/3050:***",
    "row_count": 91,
    "script_version": "1.0.0"
  },
  "ordens": [
    {
      "ordem_id": 1234,            # CODIGO/ID da ORDEM_SERVICO (idempotência FB_LEGACY_ID)
      "placa": "ABC1D23",          # placa do caminhão (96% têm placa no Firebird Martinho)
      "veiculo_id": 55,            # CODIGO do veículo legacy (Vehicle.legacy_id)
      "vehicle_type": "caminhao",  # opcional — importer normaliza p/ enum real
      "order_type": "mecanica",    # opcional — default 'manutencao' no importer
      "status": "concluida",       # status legacy livre — importer mapeia p/ FSM
      "entered_at": "2024-03-15T08:00:00",
      "completed_at": "2024-03-18T17:00:00",
      "km": 184500,                # odômetro/hodômetro
      "notes": "Troca cilindro hidráulico basculante",
      "itens": [
        {
          "legacy_item_id": 9001,
          "tipo": "peca",          # peca | mao_obra | servico_terceiro (importer normaliza)
          "descricao": "Cilindro hidráulico 3 estágios",
          "quantidade": 1,
          "valor_unitario": 4200.00
        }
      ]
    }
  ]
}

Schema Firebird referência (genérico WR Sistemas):
- memory/requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md

LGPD: DSN redacted no log. JSON tem dado operacional (placas) — armazenar com
cuidado, NÃO commitar pro git (storage/app/firebird/ deve estar no .gitignore).
"""

import argparse
import datetime
import json
import os
import re
import sys
from pathlib import Path

# ---------------------------------------------------------------------------
# SCHEMA MAP — AJUSTAR AOS NOMES REAIS DO FDB DO MARTINHO.
# Use `--dump-schema` pra listar tabelas/colunas e preencher abaixo.
# Os nomes default são convenção WR Sistemas; cada cliente pode variar.
# ---------------------------------------------------------------------------
OS_TABLE = "ORDEM_SERVICO"
OS_ITENS_TABLE = "ORDEM_ITENS"

# Colunas da ORDEM_SERVICO  → chave do JSON de saída.
OS_COLS = {
    "ordem_id":     "CODIGO",
    "placa":        "PLACA",
    "veiculo_id":   "VEICULO_ID",
    "vehicle_type": "TIPO_VEICULO",
    "order_type":   "TIPO_OS",
    "status":       "SITUACAO",
    "entered_at":   "DATA_ENTRADA",
    "completed_at": "DATA_SAIDA",
    "km":           "KM",
    "notes":        "OBSERVACAO",
}

# Colunas da ORDEM_ITENS → chave do JSON. FK pro ORDEM_SERVICO em OS_ITENS_FK.
OS_ITENS_FK = "ORDEM_ID"
OS_ITENS_COLS = {
    "legacy_item_id": "CODIGO",
    "tipo":           "TIPO",
    "descricao":      "DESCRICAO",
    "quantidade":     "QUANTIDADE",
    "valor_unitario": "VALOR_UNITARIO",
}


def normalize_str(raw):
    if raw is None:
        return None
    s = str(raw).strip()
    return s if s else None


def normalize_num(raw):
    if raw is None:
        return None
    try:
        return float(raw)
    except (TypeError, ValueError):
        return None


def normalize_date(raw):
    if raw is None:
        return None
    if isinstance(raw, (datetime.date, datetime.datetime)):
        return raw.isoformat()
    return str(raw)


def mask_dsn(dsn):
    return re.sub(r'(://)[^/]+', r'\1***', dsn) if dsn else 'unknown'


def _connect(args):
    try:
        import firebird.driver as fdb
    except ImportError:
        print("ERRO: dependência 'firebird-driver' não instalada.", file=sys.stderr)
        print("Rode: pip install firebird-driver", file=sys.stderr)
        sys.exit(2)

    dsn = args.dsn or os.environ.get('FB_DSN')
    user = args.user or os.environ.get('FB_USER', 'SYSDBA')
    password = args.password or os.environ.get('FB_PASSWORD', 'masterkey')
    if not dsn:
        print("ERRO: --dsn ou FB_DSN obrigatório.", file=sys.stderr)
        sys.exit(2)

    print(f"[fb-export] DSN: {mask_dsn(dsn)}")
    return fdb.connect(dsn, user=user, password=password)


def dump_schema(args):
    """Lista tabelas + colunas pra ajudar a preencher o SCHEMA MAP."""
    with _connect(args) as con:
        cur = con.cursor()
        cur.execute("""
            SELECT TRIM(RDB$RELATION_NAME)
            FROM RDB$RELATIONS
            WHERE RDB$SYSTEM_FLAG = 0 AND RDB$VIEW_BLR IS NULL
            ORDER BY RDB$RELATION_NAME
        """)
        tabelas = [r[0] for r in cur]
        alvo = {OS_TABLE.upper(), OS_ITENS_TABLE.upper()}
        for t in tabelas:
            destaque = '  <<< OS' if t.upper() in alvo else ''
            print(f"TABLE {t}{destaque}")
            if t.upper() in alvo or args.all_columns:
                cur.execute("""
                    SELECT TRIM(RDB$FIELD_NAME)
                    FROM RDB$RELATION_FIELDS
                    WHERE RDB$RELATION_NAME = ?
                    ORDER BY RDB$FIELD_POSITION
                """, (t,))
                for (col,) in cur:
                    print(f"    - {col}")
    return 0


def _select(cur, table, cols_map, where='', params=()):
    """SELECT só das colunas mapeadas; tolera coluna ausente caindo pra NULL."""
    select_cols = ', '.join(cols_map.values())
    sql = f"SELECT {select_cols} FROM {table} {where}"
    cur.execute(sql, params)
    keys = list(cols_map.keys())
    out = []
    for row in cur:
        out.append({keys[i]: row[i] for i in range(len(keys))})
    return out


def export_os(args):
    with _connect(args) as con:
        cur = con.cursor()

        first = f"FIRST {int(args.limit)}" if args.limit else ""
        os_where = f"ORDER BY {OS_COLS['ordem_id']}"
        if first:
            # Firebird: FIRST vai logo após SELECT — refaz a query manualmente.
            select_cols = ', '.join(OS_COLS.values())
            cur.execute(f"SELECT {first} {select_cols} FROM {OS_TABLE} {os_where}")
            keys = list(OS_COLS.keys())
            os_rows = [{keys[i]: r[i] for i in range(len(keys))} for r in cur]
        else:
            os_rows = _select(cur, OS_TABLE, OS_COLS, os_where)

        # Itens indexados por FK pra evitar N+1.
        itens_por_os = {}
        for it in _select(cur, OS_ITENS_TABLE, {**{'_fk': OS_ITENS_FK}, **OS_ITENS_COLS}):
            fk = it.pop('_fk')
            itens_por_os.setdefault(int(fk) if fk is not None else fk, []).append(it)

    ordens = []
    for r in os_rows:
        oid = r.get('ordem_id')
        oid_int = int(oid) if oid is not None else None
        itens = []
        for it in itens_por_os.get(oid_int, []):
            itens.append({
                'legacy_item_id': it.get('legacy_item_id'),
                'tipo': normalize_str(it.get('tipo')),
                'descricao': normalize_str(it.get('descricao')) or 'Sem descrição',
                'quantidade': normalize_num(it.get('quantidade')) or 1,
                'valor_unitario': normalize_num(it.get('valor_unitario')) or 0,
            })
        ordens.append({
            'ordem_id': oid_int,
            'placa': normalize_str(r.get('placa')),
            'veiculo_id': r.get('veiculo_id'),
            'vehicle_type': normalize_str(r.get('vehicle_type')),
            'order_type': normalize_str(r.get('order_type')),
            'status': normalize_str(r.get('status')),
            'entered_at': normalize_date(r.get('entered_at')),
            'completed_at': normalize_date(r.get('completed_at')),
            'km': normalize_num(r.get('km')),
            'notes': normalize_str(r.get('notes')),
            'itens': itens,
        })

    output_path = Path(args.output)
    output_path.parent.mkdir(parents=True, exist_ok=True)
    payload = {
        'meta': {
            'exported_at': datetime.datetime.now().astimezone().isoformat(),
            'source_dsn_masked': mask_dsn(args.dsn or os.environ.get('FB_DSN')),
            'row_count': len(ordens),
            'script_version': '1.0.0',
        },
        'ordens': ordens,
    }
    with open(output_path, 'w', encoding='utf-8') as f:
        json.dump(payload, f, ensure_ascii=False, indent=2)

    print(f"[fb-export] OK · {len(ordens)} OS exportadas → {output_path}")
    return 0


def main():
    parser = argparse.ArgumentParser(
        description='Exporta Ordens de Serviço do Firebird Martinho pra JSON (W28 G4).',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=__doc__,
    )
    parser.add_argument('--dsn', help='Firebird DSN (ex: localhost/3050:C:/dados/MARTINHO.FDB)')
    parser.add_argument('--user', default=None, help='Firebird user (default: env FB_USER ou SYSDBA)')
    parser.add_argument('--password', default=None, help='Firebird password (default: env FB_PASSWORD)')
    parser.add_argument('--output', default=None, help='Arquivo JSON de saída')
    parser.add_argument('--limit', type=int, default=0, help='Máx. OS exportadas (0=todas)')
    parser.add_argument('--dump-schema', action='store_true', help='Lista tabelas/colunas e sai (ajustar SCHEMA MAP)')
    parser.add_argument('--all-columns', action='store_true', help='Com --dump-schema: lista colunas de TODAS as tabelas')
    args = parser.parse_args()

    if args.dump_schema:
        return dump_schema(args)

    if not args.output:
        print("ERRO: --output obrigatório (ou use --dump-schema).", file=sys.stderr)
        sys.exit(2)

    return export_os(args)


if __name__ == '__main__':
    sys.exit(main())
