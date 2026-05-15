# RUNBOOK — Daemon dual-sync OfficeImpresso (Fase 1 MVP)

> **Caso piloto: Martinho Caçambas LTDA — biz=164 prod Hostinger.**
> Wagner roda no PC pessoal (LAN 192.168.0.55) com SSH tunnel pra Hostinger MySQL.
> Champions: **Lara (filha · estoque)** e **Dani (financeiro)** entram canary semana **19/maio**.

## Ordem canônica de execução (FK natural)

Importers chamados pelo daemon nesta ordem. Quebrar = órfãos (FK fail) ou re-runs desperdiçados:

```
contacts                  → clientes inline VENDA (RESPONSAVEL_*)
contacts-fornecedores-nfe → fornecedores inline NOTA_FISCAL_ENTRADA (NF_*_EMITENTE) ← NOVO 2026-05-14
produtos                  → catálogo (PRODUTO → products + variations)
estoque                   → quantidades (variation_location_details · cinto-suspensório)
vendas                    → VENDA → transactions(type=sell) + transaction_sell_lines
compras                   → NOTA_FISCAL_ENTRADA → transactions(type=purchase) + purchase_lines
                           ↑ DEPENDE de contacts-fornecedores-nfe ter rodado antes
                             (contact_id NOT NULL FK em transactions)
financeiro                → FIN_TITULO + FIN_BAIXA → fin_titulos + fin_titulo_baixas
```

### Pegadinha — fornecedor pode também ser cliente

Caso real Martinho: empresa "Auto Peças X" aparece como CLIENTE em algumas VENDA E como EMITENTE em NOTA_FISCAL_ENTRADA. Os 2 importers de contacts cruzam:

| Estado prévio em `contacts` | `import-contacts-from-nfe` faz |
|---|---|
| Não existe | INSERT type=**supplier** |
| type=**customer** | UPDATE type=**both** (promote) + preenche `supplier_business_name` |
| type=**supplier** ou **both** | no-op idempotente |

Dedup via chave natural `(business_id, legacy_id=CNPJ_normalizado)`. Cada business tem sua row — mesmo CNPJ em biz=164 e biz=4 = 2 rows distintas, não vaza cross-tenant (ADR 0093). Pest cobertura em [tests/Feature/Legacy/ContactsFromNfeImporterTest.php](../../../tests/Feature/Legacy/ContactsFromNfeImporterTest.php).

## Pré-requisitos PC Wagner

### Software

| Item | Versão mínima | Como instalar |
|------|---------------|---------------|
| Python | 3.13 | `winget install Python.Python.3.13` ou python.org/downloads |
| firebird-driver | latest | `pip install firebird-driver` |
| pymysql | latest | `pip install pymysql` |
| python-dotenv | latest | `pip install python-dotenv` |
| Git (clone repo) | 2.x | `winget install Git.Git` |

Bundle: `pip install -r D:\oimpresso.com\scripts\legacy-migration\requirements.txt`

### Conectividade

- ✅ **LAN servidor Firebird Martinho** acessível em `192.168.0.55:3050` (porta default Firebird) ou via alias HKCU `MartinhoServidor`
- ✅ **SSH key Hostinger** em `~/.ssh/id_ed25519_oimpresso` (autorizada em `~/.ssh/authorized_keys` do user `u906587222`)
- ✅ **Internet estável** (daemon mantém tunnel 24/7 — interrupções breves reconectam, longas alertam)

### Acesso registry HKCU Wagner

Alias canônico `MartinhoServidor` resolve path completo do Firebird via registry. Verificar:

```powershell
reg query "HKCU\Software\Rocha\Office Comercial\Banco\Caminhos" /v MartinhoServidor
```

Se output vazio: Wagner precisa abrir Delphi WR Comercial 1× pra cadastrar alias localmente.

---

## Setup primeira vez

### 1) Clone + dependências

```powershell
cd D:\oimpresso.com
pip install -r scripts\legacy-migration\requirements.txt
```

### 2) Aplicar migration `sync_checkpoint` em PROD Hostinger

```powershell
# SSH Hostinger + php artisan migrate
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115
# (já dentro do Hostinger)
cd domains/oimpresso.com/public_html
php artisan migrate --path=database/migrations/2026_05_14_180000_create_sync_checkpoint.php --force
exit
```

**Verificar:** tabela criada
```bash
ssh -4 ... 'cd domains/oimpresso.com/public_html && php artisan tinker --execute="echo Schema::hasTable(\"sync_checkpoint\") ? \"OK\" : \"AUSENTE\";"'
```

### 3) Smoke dry-run (sem tocar Hostinger)

```powershell
cd D:\oimpresso.com\scripts\legacy-migration
python daemon-sync-martinho.py --once --target dry-run --types contacts
```

**Esperado:**
- abre Firebird local (`MartinhoServidor` alias)
- NÃO abre SSH tunnel (`--target dry-run` pula)
- gera log em `output/daemon-contacts-biz164-*.log`
- heartbeat em `output/daemon-heartbeat.json`
- exit code 0

---

## Comando rodar (Wagner deixa em janela cmd 24/7)

```powershell
cd D:\oimpresso.com\scripts\legacy-migration
python daemon-sync-martinho.py
```

**Output esperado nos primeiros 60s:**

```
======================================================================
== Daemon dual-sync — biz=164 alias=MartinhoServidor ==
   target=prod types=['contacts', 'financeiro', 'vendas', 'produtos', 'estoque', 'compras']
   once=False no_tunnel=False
   PID=12345 heartbeat=D:\oimpresso.com\scripts\legacy-migration\output\daemon-heartbeat.json
======================================================================
[ssh] Lendo DB_PASSWORD via SSH .env Hostinger (sem ecoar)...
[ssh] Abrindo tunnel localhost:33069 -> Hostinger MySQL...
   [ok] Tunnel ready
[run] contacts (import-contacts-from-venda.py) target=prod
[ok] contacts concluído rc=0 | output/daemon-contacts-biz164-20260514-180521.log
[run] financeiro (import-financeiro.py) target=prod
...
```

Deixar **janela cmd aberta** — Ctrl+C pausa gracioso.

---

## Como saber se está rodando

### Heartbeat (atualizado a cada ciclo, ~60s)

```powershell
Get-Content D:\oimpresso.com\scripts\legacy-migration\output\daemon-heartbeat.json
```

**Saúde OK** se `last_alive` é nos últimos 2 minutos:
```json
{
  "last_alive": "2026-05-14T19:23:45.123456+00:00",
  "pid": 12345,
  "current_sync_type": "vendas",
  "rows_processed_total": 248,
  "consecutive_failures": 0,
  "last_status": "running"
}
```

**Problema** se `last_alive` > 5min atrás → daemon morreu ou travou. Wagner mata janela cmd + re-roda.

### sync_checkpoint table (estado vivo no MySQL)

```powershell
# Via tunnel já aberto pelo daemon, ou re-tunnel manual
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 `
  'cd domains/oimpresso.com/public_html && php artisan tinker --execute="DB::table(\"sync_checkpoint\")->where(\"business_id\", 164)->get()->each(fn(\$r) => print json_encode(\$r) . \"\n\");"'
```

Saída esperada: 6 linhas (uma per sync_type), todas com `last_status: success` ou `running`.

---

## Troubleshoot

### Firebird conexão caiu

**Sintoma:** log mostra `DatabaseError: connection failed` repetidamente

**Recuperação automática:** daemon tenta 3× com backoff (5s, 15s, 45s) — Wagner não precisa fazer nada se servidor Martinho voltar em < 1min

**Wagner manual** se persistir:
1. Verificar servidor Martinho ligado (alguém pode ter desligado o PC do escritório dele)
2. `ping 192.168.0.55` no PC Wagner → se fail, LAN problema
3. Após servidor voltar, daemon retoma sozinho via `sync_checkpoint.last_codigo_processed`

### SSH tunnel caiu

**Sintoma:** log mostra `socket.timeout` + `[ssh] Tunnel morreu — reconectando...`

**Recuperação automática:** daemon detecta tunnel dead via socket ping cada ciclo (60s), kill + reopen

**Wagner manual** se reconnect FALHA 3× consecutivo (`output/daemon-alerts.log` registra):
1. Ctrl+C daemon
2. Verificar SSH funciona: `ssh -4 -p 65002 u906587222@148.135.133.115 'whoami'`
3. Se SSH falha: Wagner verifica internet PC pessoal + status Hostinger
4. Re-rodar `python daemon-sync-martinho.py`

### Pause / Resume

**Pausa graciosa:** Ctrl+C na janela cmd

**Resume:** re-rodar `python daemon-sync-martinho.py`

**Estado preservado:**
- `sync_checkpoint` no MySQL guarda último sucesso per type
- Próxima rodada já é delta `WHERE DT_ALTERACAO > last_sync_at`
- ZERO retrabalho duplicado (idempotente)

### Wagner trabalha em outra coisa no PC

OK rodar daemon em background — overhead é baixo (60s sleep entre verificações + 5-15min per type). Janela cmd pode ficar minimizada.

---

## Monitor alertas

### Logs por sync_type

```powershell
# Último log de cada importer:
Get-ChildItem D:\oimpresso.com\scripts\legacy-migration\output\daemon-*-biz164-*.log `
  | Sort-Object LastWriteTime -Descending `
  | Select-Object -First 12 `
  | Format-Table Name, LastWriteTime
```

### Alertas (3+ falhas consecutivas)

```powershell
Get-Content D:\oimpresso.com\scripts\legacy-migration\output\daemon-alerts.log -Tail 20
```

Quando aparece linha: **Wagner pega celular** (futuro: webhook WhatsApp; V0 = local log).

### Failed status no checkpoint

```sql
-- SQL via tinker remoto
SELECT business_id, sync_type, last_status, last_sync_at, error_msg
FROM sync_checkpoint
WHERE last_status = 'failed'
ORDER BY updated_at DESC;
```

---

## Quando adicionar novo cliente (Vargas etc)

1. **Alias Firebird** novo em registry HKCU Wagner (abrir Delphi WR Comercial do cliente 1×)
2. **Conexão LAN** ao servidor do cliente confirmada (`ping <ip>:3050`)
3. **Migration ROTA LIVRE-style**: `sync_checkpoint` já criada (compartilhada — só precisa INSERT INTO business)
4. **business novo no oimpresso** (Wagner cria via UI superadmin com `business_id` próximo)
5. **Daemon CLI:**

```powershell
# Em nova janela cmd Wagner (segundo daemon paralelo)
cd D:\oimpresso.com\scripts\legacy-migration
python daemon-sync-martinho.py --target-business 200 --alias VargasServidor
```

**Cuidado:** dois daemons paralelos significam 2 tunnels SSH simultâneos pra Hostinger — limite Hostinger é ~10 connections.

**Recomendação V0:** rotação manual (Martinho 24h, depois Vargas 24h). V1 = daemon multi-cliente single-process (sprint depois).

---

## Bottleneck conhecido / próximos passos

### V0 (Fase 1) limitações

- **Daemon roda só no PC Wagner** — se Wagner desligar PC, sync para. Champions Lara/Dani veem dados velhos
- **1 daemon = 1 cliente** — escala via N janelas (manual; multi-tenant single-process vem em V1)
- **DT_ALTERACAO assumido em todas tabelas** — versões Firebird antigas (v < 1300?) podem não ter; daemon faz fallback FULL SYNC + warn
- **Alertas só log local** — POST webhook `/api/internal/daemon-alert` é TODO V1

### Fase 2 (planejado — pós-canary Martinho)

- Daemon em CT 100 Proxmox (sempre on)
- Multi-cliente single-process (1 daemon orchestra N clientes)
- Webhook alert → WhatsApp Wagner via Baileys

### Fase 4 (planejado — bidirectional)

- oimpresso → Delphi sync (cliente edita venda no oimpresso, daemon escreve de volta no Firebird)
- Lock per-record pra evitar split-brain
- ADR mãe aprovação Wagner ANTES de implementar

---

## Refs

- [memory/decisions/proposals/dual-system-delphi-oimpresso-sync-realtime.md](../../decisions/proposals/dual-system-delphi-oimpresso-sync-realtime.md) — ADR proposal (não aceita ainda)
- [scripts/legacy-migration/daemon-sync-martinho.py](../../../scripts/legacy-migration/daemon-sync-martinho.py) — código daemon
- [database/migrations/2026_05_14_180000_create_sync_checkpoint.php](../../../database/migrations/2026_05_14_180000_create_sync_checkpoint.php) — schema
- [scripts/legacy-migration/migrar-martinho.py](../../../scripts/legacy-migration/migrar-martinho.py) — wrapper one-shot canônico (base do daemon)
- [tests/Feature/Daemon/DaemonSyncDualBusinessTest.php](../../../tests/Feature/Daemon/DaemonSyncDualBusinessTest.php) — Pest cross-tenant
- [ADR 0093 multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0101 testes business_id=1 nunca cliente](../../decisions/0101-tests-business-id-1-nunca-cliente.md)
