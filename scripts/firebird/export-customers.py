#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
US-WA-VOZ-002 — Exporta clientes do Firebird (OfficeImpresso WR Sistemas legacy)
para JSON consumido por `php artisan customer-memory:enrich-firebird`.

Wagner roda LOCAL (Windows) onde tem o Firebird instalado. Output sobe pra
Hostinger via scp/git.

USO:
    # Instalar dep (1x)
    pip install firebird-driver

    # Exportar
    python scripts/firebird/export-customers.py \
        --dsn "localhost/3050:C:/dados/EMPRESA.FDB" \
        --user SYSDBA --password masterkey \
        --output storage/app/firebird/customers-2026-05-15.json

    # Cliente específico
    python scripts/firebird/export-customers.py \
        --dsn "..." --output ... --client "Martinho Caçambas"

VARIÁVEIS DE AMBIENTE (alternativa às flags):
    FB_DSN=...
    FB_USER=SYSDBA
    FB_PASSWORD=...

ESQUEMA OUTPUT (alinhado com FirebirdLookupSourceContract.php):
{
  "meta": {
    "exported_at": "2026-05-15T18:00:00-03:00",
    "source_dsn_masked": "localhost/3050:***",
    "row_count": 1234,
    "script_version": "1.0.0"
  },
  "customers": [
    {
      "cliente_id": 1234,
      "nome": "ACME LTDA",
      "fone1": "554899872822",
      "fone2": null,
      "email": "contato@acme.com.br",
      "bloqueado": false,
      "cpf_cnpj": "12345678000100",
      "cidade": "Florianópolis",
      "data_cadastro": "2024-03-15T00:00:00"
    },
    ...
  ]
}

Schema Firebird referência (CLIENTES):
- memory/requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md §2.5

LGPD: redacted DSN no log. Customers JSON tem PII real — armazenar com cuidado,
NÃO commitar pro git (gitignore storage/app/firebird/ apropriado).
"""

import argparse
import datetime
import json
import os
import re
import sys
from pathlib import Path


def normalize_phone(raw):
    """Strip não-dígitos. Retorna string com só dígitos (ou None se vazio)."""
    if raw is None:
        return None
    s = str(raw).strip()
    if not s:
        return None
    digits = re.sub(r'\D+', '', s)
    return digits if digits else None


def normalize_str(raw):
    """Trim + None se vazio."""
    if raw is None:
        return None
    s = str(raw).strip()
    return s if s else None


def normalize_bool_sn(raw):
    """Firebird usa 'S'/'N' pra booleano. Mapeia."""
    if raw is None:
        return False
    s = str(raw).strip().upper()
    return s == 'S' or s == '1' or s.lower() == 'true'


def normalize_date(raw):
    """ISO 8601 ou None."""
    if raw is None:
        return None
    if isinstance(raw, (datetime.date, datetime.datetime)):
        return raw.isoformat()
    return str(raw)


def mask_dsn(dsn):
    """Mascara DSN pra log — remove credencial."""
    return re.sub(r'(://)[^/]+', r'\1***', dsn) if dsn else 'unknown'


def export_customers(args):
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

    output_path = Path(args.output)
    output_path.parent.mkdir(parents=True, exist_ok=True)

    print(f"[fb-export] DSN: {mask_dsn(dsn)}")
    print(f"[fb-export] Output: {output_path}")

    # Query CLIENTES — campos do schema documentado.
    # WHERE opcional pra filtrar cliente específico (debug).
    where_clause = ''
    params = ()
    if args.client:
        where_clause = "WHERE UPPER(RAZAO_SOCIAL) LIKE UPPER(?)"
        params = (f'%{args.client}%',)

    sql = f"""
        SELECT
            CODIGO,
            RAZAO_SOCIAL,
            FONE1,
            FONE2,
            EMAIL,
            BLOQUEADO,
            CPF,
            CNPJ,
            CIDADE,
            DATACADASTRO
        FROM CLIENTES
        {where_clause}
        ORDER BY CODIGO
    """

    customers = []
    try:
        with fdb.connect(dsn, user=user, password=password) as con:
            cur = con.cursor()
            cur.execute(sql, params)
            for row in cur:
                codigo, razao, fone1, fone2, email, bloq, cpf, cnpj, cidade, dt_cad = row
                cpf_cnpj = normalize_str(cnpj) or normalize_str(cpf)
                customers.append({
                    'cliente_id': int(codigo) if codigo is not None else 0,
                    'nome': normalize_str(razao) or '',
                    'fone1': normalize_phone(fone1),
                    'fone2': normalize_phone(fone2),
                    'email': normalize_str(email),
                    'bloqueado': normalize_bool_sn(bloq),
                    'cpf_cnpj': cpf_cnpj,
                    'cidade': normalize_str(cidade),
                    'data_cadastro': normalize_date(dt_cad),
                })
    except Exception as e:
        print(f"ERRO conectando/lendo Firebird: {e}", file=sys.stderr)
        sys.exit(3)

    # Escreve JSON
    payload = {
        'meta': {
            'exported_at': datetime.datetime.now().astimezone().isoformat(),
            'source_dsn_masked': mask_dsn(dsn),
            'row_count': len(customers),
            'script_version': '1.0.0',
        },
        'customers': customers,
    }

    with open(output_path, 'w', encoding='utf-8') as f:
        json.dump(payload, f, ensure_ascii=False, indent=2)

    print(f"[fb-export] OK · {len(customers)} clientes exportados → {output_path}")
    return 0


def main():
    parser = argparse.ArgumentParser(
        description='Exporta clientes do Firebird OfficeImpresso pra JSON.',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=__doc__,
    )
    parser.add_argument('--dsn', help='Firebird DSN (ex: localhost/3050:C:/dados/EMPRESA.FDB)')
    parser.add_argument('--user', default=None, help='Firebird user (default: env FB_USER ou SYSDBA)')
    parser.add_argument('--password', default=None, help='Firebird password (default: env FB_PASSWORD)')
    parser.add_argument('--output', required=True, help='Arquivo JSON de saída')
    parser.add_argument('--client', default=None, help='Filtra por nome cliente (LIKE)')
    args = parser.parse_args()

    return export_customers(args)


if __name__ == '__main__':
    sys.exit(main())
