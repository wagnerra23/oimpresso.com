---
name: Firebird LAN instável em batch grande — chunks paginados obrigatório
description: Conexão Firebird via LAN (192.168.0.55:3050 Martinho) cai durante leitura de cursor único 100k+ rows. Erro DatabaseError "Error writing data to the connection · connection shutdown". Solução pattern chunks paginados + retry exponencial + checkpoint per chunk. Aplicado no daemon Fase 1 dual-sync.
type: feedback
---
# Firebird LAN cai em batch grande — chunks obrigatório

## Incidente real 2026-05-14

Durante `import-financeiro.py --target prod --confirm` rodando em background (`bp98b0i4a`) pra Martinho biz=164, tentativa de ler 103.997 rows da tabela `FINANCEIRO` num cursor único Firebird (192.168.0.55:3050 LAN). **Conexão caiu após ~5.000 rows.**

### Stack trace canônico

```
firebird.driver.types.DatabaseError: Error writing data to the connection.
Exception ignored in: <function Cursor.__del__ at 0x...>
firebird.driver.types.DatabaseError: connection shutdown
Exception ignored in: <function Statement.__del__ at 0x...>
firebird.driver.types.DatabaseError: connection shutdown
[err] financeiro falhou rc=1
[ssh] Fechando SSH tunnel...
```

### Impacto

- `fin_titulos biz=164` ficou em **5.546 / 98.533** (6%)
- Sem ele, Dani vê apenas amostra pequena ao entrar `/financeiro/boletos`
- Re-rodar importer **sobrescreveu** `metadata.is_write_off_candidate` (lição 2 separada — ver [proposta dual-sync §6.1 Lição 2](../decisions/proposals/dual-system-delphi-oimpresso-sync-realtime.md))

## Por que cai

LAN doméstica/escritório (192.168.0.55) tem fragilidades:
- Rede pisca breve (Wi-Fi switching · roteador buffering · servidor swap)
- Firebird 2.5/3 server timeout default ~60s sem traffic
- Cursor open longo segura recursos · servidor pode reaplicar load → close cursor
- Python `firebird-driver` não tem auto-reconnect transparente

## Pattern correto — chunks paginados

```python
def read_chunk_with_retry(con_factory, sync_type, chunk_size=5000, max_retries=3):
    """Lê chunks paginados de FINANCEIRO/VENDA/PRODUTO etc com retry exponencial.

    con_factory: callable que retorna nova conexão Firebird (pra reconnect)
    sync_type: identificador pra sync_checkpoint
    """
    last_codigo = get_last_codigo_processed(sync_type)
    while True:
        for attempt in range(max_retries):
            try:
                with con_factory() as con:
                    chunk = query(
                        con,
                        f"SELECT FIRST {chunk_size} CODIGO, ... FROM FINANCEIRO "
                        f"WHERE CODIGO > {last_codigo} ORDER BY CODIGO"
                    )
                    if not chunk:
                        return  # done
                    process_chunk(chunk)
                    last_codigo = chunk[-1]["CODIGO"]
                    update_checkpoint(sync_type, last_codigo)
                    break  # success — proxima iteration
            except DatabaseError as e:
                wait = [5, 15, 45][attempt]
                log_warn(f"Firebird shutdown chunk {last_codigo}+{chunk_size}: {e} · retry em {wait}s ({attempt+1}/{max_retries})")
                time.sleep(wait)
                if attempt == max_retries - 1:
                    set_checkpoint_status(sync_type, "failed", error_msg=str(e))
                    raise
```

### 3 pilares do pattern

1. **Chunks de 5.000 rows** (Firebird `SELECT FIRST N` semantics) — cada cursor curto, fecha rápido, libera recursos servidor
2. **`WHERE CODIGO > last_codigo ORDER BY CODIGO`** — paginação determinística, idempotente, resume-friendly
3. **Checkpoint per chunk** em `sync_checkpoint` table — re-rodar pega de onde parou, NUNCA reprocessa

### 3 pilares retry

1. **Backoff exponencial 5s → 15s → 45s** (não constant 5s · 30s não tem ganho médio)
2. **Reconnect Firebird** entre tentativas (not reuse cursor morto)
3. **Set `last_status='failed'` + error_msg em sync_checkpoint** após 3 retries · alert Wagner WhatsApp

## Pré-flight check antes de batch

```python
def firebird_health_check(con):
    """SELECT 1 ping antes de iniciar batch grande. Aborta se falha."""
    try:
        query(con, "SELECT 1 AS heartbeat FROM RDB$DATABASE")
        return True
    except DatabaseError:
        return False
```

Daemon dual-sync deve rodar health-check **antes** de cada chunk batch grande. Se falha 3× consecutivas, marca tipo como `failed` + pula pra próximo ciclo (não bloqueia outras syncs).

## Tabelas com risco alto (Martinho v1404)

| Tabela Firebird | Rows típicos | Chunks recomendados |
|---|---:|---|
| FINANCEIRO | 103.997 | 5.000/chunk · ~21 chunks |
| VENDA | 44.709 | 5.000/chunk · ~9 chunks |
| NF_ENTRADA_PRODUTOS | 100k+ | 5.000/chunk · variável |
| NF_ENTRADA_PRODUTOS_COMPOSICAO | 50k+ | 3.000/chunk |
| PRODUTO | 4.378 | single batch OK |
| PRODUTO_ESTOQUE | 4.581 | single batch OK |
| EQUIPAMENTO_VEICULO | 91 | single batch OK |
| PESSOAS (via DISTINCT VENDA) | 11k+ | 3.000/chunk |

## SSH tunnel também precisa robustez

Mesmo cenário acontece com SSH tunnel Hostinger:
- Tunnel `subprocess.Popen` no Windows pode pendurar em socket buffering
- Output `tee` em background NÃO flusha em tempo real (size=0 enquanto processa)
- Conexão SSH em si pode pisar (Hostinger rate-limit · rede pisca)

Daemon Fase 1 implementa:
- `python -u` (unbuffered) + redirect `> log 2>&1` (sem `tee` pipe)
- Tunnel reconnect supervisord-style: se ping `127.0.0.1:33069` falha, kill + reopen
- Heartbeat file `daemon-heartbeat.json` a cada 60s · externo detecta zombie

## Status fix

| Mitigação | Status |
|---|---|
| Pattern chunks + retry em importers | ⚠️ pendente daemon Fase 1 (`a13a132de0c4217f1` rodando) |
| SSH tunnel resiliente | ⚠️ pendente daemon Fase 1 |
| Pre-flight Firebird health check | ⚠️ pendente daemon Fase 1 |
| `sync_checkpoint` table | ⚠️ pendente daemon Fase 1 (migration nova) |

Quando daemon completar, todos importers automaticamente herdam pattern via flag `--delta-since-last-sync`.

## Refs

- [Proposta arquitetural dual-sync §6.1 Lição 1](../decisions/proposals/dual-system-delphi-oimpresso-sync-realtime.md)
- [Session log 2026-05-14](../sessions/2026-05-14-martinho-canary-prep-massive.md) — incidente narrativo
- [Cliente Martinho](cliente-martinho.md) — perfil canary
- `scripts/legacy-migration/import-financeiro.py` — importer atual (sem chunks · pendente daemon Fase 1)
