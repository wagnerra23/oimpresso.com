---
id: dominios-patterns-04-metadata-json-denormalized
---

# Pattern 04 — Metadata JSON denormalized

**Status**: canônico desde 2026-05-09

## Contexto

Schemas legacy frequentemente têm tabelas-monstro: WR Comercial `CONTAS` tem 57 colunas; Bling provavelmente tem objeto JSON com 30+ campos por cliente. Mapear tudo pra colunas tipadas no oimpresso é insustentável e gera ratchet de migrations.

## Problema

- Mapear 57 colunas Delphi pra 57 colunas Laravel = duplicação massiva, schema oimpresso virou prima do legacy (anti-pattern: SoC quebrado)
- Mapear só 10 colunas chave = perde 47 colunas de contexto (CNAB config, PIX, WS auth, mensagens de boleto, etc) pra futura auditoria
- Schema oimpresso evoluindo sob constraint de "legacy backwards-compat" = paralisia

## Solução

**Split em 3 destinos**:

1. **Colunas tipadas Laravel** — 5-10 campos *core do domínio Laravel* (ex: `agencia`, `conta`, `carteira` em `fin_contas_bancarias`). Schema oimpresso decide independentemente do legacy.

2. **Coluna `metadata` JSON** — captura **todo o resto** do contexto legacy. Estrutura denormalized livre, sub-objetos por categoria semântica. Acessível via `JSON_EXTRACT` ou cast `'array'` no Eloquent.

3. **Coluna `legacy_metadata` em bridge table** — snapshot completo do registro original pra audit/debug. Read-only após import.

### Estrutura JSON canônica

```json
{
  "<sistema>_legacy": {
    "_raw_codes": {...},                  // FKs Delphi (CODBANCO, CODEMPRESA)
    "config_<area>": {...},                // Sub-objetos por categoria
    "...": "..."
  },
  "boleto_email": {...},                   // Categoria genérica
  "pix": {...},
  "ws_bancario": {...},
  "credenciais_warning": "..."             // Alertas pendentes (segredos)
}
```

## Exemplo validado

`fin_contas_bancarias.metadata` no smoke (Wagner biz=1, [PR #354](https://github.com/wagnerra23/oimpresso.com/pull/354)):

```json
{
  "delphi_legacy": {
    "codbanco_delphi": 104,
    "carteira_gera_remessa": "N",
    "layout_arquivo": "240",
    "especie": "DS",
    "tolerancia": null
  },
  "cooperativa": null,
  "boleto_email": {"assunto": null, "exibir_nota": null, ...},
  "boleto_mensagens": {
    "protesto": "PROTESTAR $Protesto$ APÓS $ProtestoAPartir$.",
    "multa": "MULTA DE R$ $Multa$ APÓS $MultaAPartir$.",
    "juros": "JUROS DE R$ $Juros$ AO DIA."
  },
  "pix": null,
  "ws_bancario": null,
  "credenciais_warning": null
}
```

## Trade-offs

| Aspecto | Tipadas | Metadata JSON |
|---|---|---|
| Query | `WHERE banco_codigo='104'` simples | `WHERE JSON_EXTRACT(metadata,'$.delphi_legacy.codbanco_delphi')=104` |
| Index | Possível | Possível mas custoso (functional index) |
| Refactor | Migration nova | Zero — JSON é flexível |
| Type safety | Forte | Fraca (parsing manual) |
| Capturar contexto desconhecido | Impossível | Trivial |

## Regra de decisão

Coloca em **coluna tipada** se atende **todos**:
1. Field é **core do domínio Laravel** (não-derivado de legacy)
2. Será **filtrado/ordenado** em queries comuns
3. Tem **type semantics** clara (não config opaco)

Senão → `metadata` JSON.

## Quando NÃO usar JSON

- Field é **chave estrangeira** — sempre tipada
- Field é **business critical pra cálculos** (saldo, total) — sempre tipada com type
- Field tem **ENUM curto fechado** — pode ser tipada
- DB não suporta JSON nativo (raro hoje — MySQL 5.7+, Postgres, SQL Server moderno suportam)

## Segredos sensíveis em JSON

⚠️ JSON de metadata pode capturar credenciais (CLIENTSECRET, KEYFILE, CERTFILE). **Em produção, segredos vão pra Vaultwarden** — JSON guarda só `vault_ref="acc-X-credentials"`. Em dev, pode ficar em JSON com flag `is_dev_only=true`.

Pendência ainda aberta no MAPPING.md WR Comercial — Vaultwarden integration timing.
