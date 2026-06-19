---
date: 2026-06-19
time: "1124 BRT"
slug: "midia-correcao-numeros"
tldr: "Corrige o handoff 1103 (#3018, mergeado): o '48k mídia whatsmeow travada' não procede. pending é default de TODA msg (texto incluso). Mídia REAL = 10.134, 100% whatsapp_baileys (driver morto ADR 0202 = dead-letter); whatsmeow media pending = 0. O A record whatsapp-whatsmeow estava NXDOMAIN e foi RECRIADO (177.74.67.30, W autorizou) — hygiene, NÃO causa de backlog. Recomendo dead-letter dos 10.134 Baileys. US-WA-309 já feito; mergear #2964."
decided_by: [W]
cycle: null
prs: [3018, 3019, 2964]
us: ["US-WA-309"]
next_steps:
  - "DECISÃO W (dados): marcar os 10.134 mídia pending (100% whatsapp_baileys, undownloadable) como failed_permanent — dead-letter pra limpar a métrica. SQL no corpo; NÃO rodei (mutação 10k linhas escala)."
  - "FEITO: A record whatsapp-whatsmeow.oimpresso.com -> 177.74.67.30 recriado (Hostinger DNS API, overwrite:false, dig+DoH ok)."
  - "US-WA-309: mergear #2964 (banner business-wide + probe provision_pending)."
related_adrs:
  - 0202-whatsapp-profissionalizacao-baileys-out
  - 0204-whatsmeow-driver-substituto-baileys
  - 0286-channel-health-corroborado-por-mensagem-real
  - 0045-hostinger-dns-api-endpoint-canonico
---

# Handoff 2026-06-19 11:24 BRT — CORREÇÃO dos números do 1103 (mídia WhatsApp)

> **Supera o handoff [1103](2026-06-19-1103-midia-dns-rootcause.md) (#3018), que foi mergeado por auto-merge ANTES desta verificação.** O 1103 está certo sobre o DNS, mas o número "48k mídia whatsmeow travada" não procede. Append-only (ADR 0130): este doc corrige; o 1103 fica como está.

## TL;DR

Dois fatos separados, não um:

1. **O A record sumiu (real, corrigido).** `whatsapp-whatsmeow.oimpresso.com` estava **NXDOMAIN** na zona Hostinger. **Recriei** (→ `177.74.67.30`, ADR 0045, `overwrite:false`; `dig @1.1.1.1` + DoH `Status:0` confirmam). Hygiene necessária (health-probe ADR 0286, admin do daemon, mídia whatsmeow futura). Daemon estava `Up 3 semanas (healthy)`; Traefik roteia — só o DNS estava morto.

2. **O "48k mídia travada" era número inflado.** Verificado na app de prod (SSH Hostinger, query builder na connection do app):

| métrica | valor |
|---|---|
| `media_download_status='pending'` (todos os tipos) | 48.574 — **mas é o DEFAULT de toda msg, texto incluso** |
| mídia REAL pending (`type` ∈ image/audio/video/document) | **10.134** |
| └ por provider | **100% `whatsapp_baileys`** (0 whatsmeow, 0 outros) |
| mídia **whatsmeow** pending | **0** (teste síncrono: nada pra baixar) |

**Conclusão:** os 10.134 são mídia do **driver Baileys descomissionado** ([ADR 0202](../decisions/0202-whatsapp-profissionalizacao-baileys-out.md), 27/mai) — daemon de decrypt morto + URLs `.enc` expiradas = **undownloadable / dead-letter**, problema mais antigo (desde ~15/mai) e **independente do DNS**. Não havia backlog de mídia whatsmeow esperando o DNS.

## Como verifiquei (anti-erro-do-1103)

O 1103 leu `pending = 48.569` da connection e assumiu "48k mídia whatsmeow travada". Errei ao não filtrar por tipo de mídia nem por provider. Aqui:
- `Message::withoutGlobalScopes()->where('media_download_status','pending')->whereIn('type', [media])->...->groupBy('provider')` → 10.134, todo `whatsapp_baileys`.
- Teste síncrono de 1 mídia whatsmeow pendente → **0 candidatas** (`whatsmeow_media_pending=0`).
- `backfill-media-download --dry-run` → **0 candidatas** (filtra `media_mime NOT NULL`; e não há mídia whatsmeow pendente).

## O que o W decide

- [ ] **(dados)** dead-letter dos 10.134 Baileys — limpa a métrica `pending`. SQL (NÃO rodei):
  ```php
  Modules\Whatsapp\Entities\Message::withoutGlobalScopes()
    ->where('media_download_status','pending')->where('provider','whatsapp_baileys')
    ->whereIn('type',['image','audio','video','document'])
    ->update(['media_download_status'=>'failed_permanent',
              'media_download_failed_reason'=>'Baileys descomissionado (ADR 0202) — mídia .enc expirada']);
  ```
- [ ] **(merge)** #2964 (banner business-wide US-WA-309).
- [ ] **(produto)** sentinela "daemon whatsmeow alcançável" (Camada 6 do proposal `ingestao-perda-zero`, corrigida no #3019/este PR) — promover a trabalho ativo quando o time tocar.

## Estado MCP no momento do fechamento

> MCP oimpresso passou a aparecer tarde na sessão (worktree órfão). Não rodei `brief-fetch` neste passo de correção; fonte de verdade = git/gh + DoH + CT100/Hostinger (read-only + 1 write DNS autorizado).

### Estado via git/gh + DB prod (substituto)
```
origin/main: handoff 1103 (#3018) e proposal Camada6 (#3019) MERGED por auto-merge (squash) ANTES da correção.
DB prod mídia real pending = 10.134 (100% whatsapp_baileys); whatsmeow media pending = 0.
DNS: whatsapp-whatsmeow.oimpresso.com A=177.74.67.30 (recriado; DoH Status:0).
US-WA-309: #2956/#2963/#3002 merged; #2964 aberto.
```

## Referências

- Handoff corrigido: [2026-06-19-1103-midia-dns-rootcause.md](2026-06-19-1103-midia-dns-rootcause.md) (#3018)
- Proposal (Camada 6 corrigida): [whatsapp-ingestao-perda-zero.md](../decisions/proposals/whatsapp-ingestao-perda-zero.md) (#3019 + este PR)
- ADR 0202: [Baileys OUT](../decisions/0202-whatsapp-profissionalizacao-baileys-out.md) · ADR 0045: [Hostinger DNS API](../decisions/0045-hostinger-dns-api-endpoint-canonico.md)
