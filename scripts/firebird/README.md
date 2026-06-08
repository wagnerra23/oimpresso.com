# Firebird OfficeImpresso → JSON exporter

US-WA-VOZ-002 — Pipeline cross-DB pra enriquecer `customer_memory` com clientes do Firebird legacy WR Sistemas.

## Por que existe

Hostinger shared hosting NÃO tem driver Firebird (PHP `ibase`/`firebird` PECL não disponível). Solução: exportar JSON local via Python e importar via comando artisan.

Não-trivial alternativas consideradas e rejeitadas:
- ❌ Tunnel HTTP do Hostinger → CT 100 → Firebird remoto (complexo + lento + LGPD)
- ❌ Driver Firebird-PHP custom (manutenção alta)
- ✅ Export JSON local (idempotente, versionado, fácil debug)

## Pre-requisitos

- Python 3.10+
- Firebird Client instalado local (Windows: `gds32.dll`/`fbclient.dll` no PATH)
- Acesso ao .FDB do cliente (servidor WR Sistemas)

## Setup (1×)

```bash
pip install firebird-driver
```

## Uso

```powershell
# Wagner local Windows — exporta TODOS os clientes
python scripts/firebird/export-customers.py `
    --dsn "localhost/3050:C:/dados/EMPRESA.FDB" `
    --user SYSDBA --password masterkey `
    --output storage/app/firebird/customers-2026-05-15.json

# Sobe pro Hostinger via git OU scp
scp storage/app/firebird/customers-2026-05-15.json \
    u906587222@148.135.133.115:domains/oimpresso.com/public_html/storage/app/firebird/

# No Hostinger — enriquece customer_memory biz=1
php artisan customer-memory:enrich-firebird \
    --business=1 \
    --json=storage/app/firebird/customers-2026-05-15.json
```

## Output JSON

```json
{
  "meta": {
    "exported_at": "2026-05-15T18:00:00-03:00",
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
    }
  ]
}
```

## LGPD

JSON contém **PII real** (nome, fone, CPF, email). NÃO commitar pro git.

`storage/app/firebird/` deve estar no `.gitignore` (verificar).

Compartilhar apenas via canal seguro (scp + chave SSH).

## Cron sugerido (futuro)

Quando estabilizar, rodar 1×/semana via cron Windows local:
```
schtasks /create /tn "fb-export-weekly" /tr "python C:\path\export-customers.py --output ..." /sc weekly
```

## Troubleshooting

| Erro | Causa | Fix |
|---|---|---|
| `ImportError: firebird-driver` | dep não instalada | `pip install firebird-driver` |
| `Connection refused` | Firebird server offline | Verificar serviço Firebird Server |
| `User name and password are not defined` | credencial faltando | Passar `--user --password` ou env `FB_USER FB_PASSWORD` |
| `I/O error during "CreateFile (open)" operation` | .FDB inacessível | Verificar path absoluto, permissões Windows |

## Schema referência

[memory/requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md §2.5 (CLIENTES)](../../memory/requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md)
