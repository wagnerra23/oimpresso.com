---
date: "2026-06-19"
time: "1154 BRT"
slug: "fechamento-midia-dns-banner"
tldr: "Fechamento mídia-WhatsApp + US-WA-309. EXECUTADO: (1) A record whatsapp-whatsmeow.oimpresso.com recriado NXDOMAIN→177.74.67.30 (W autorizou). (2) Mídia real travada = 10.134, 100% whatsapp_baileys (driver morto) → dead-lettered, pending agora 0; o '48k' era default de TODA msg; whatsmeow media pending = 0. (3) US-WA-309 já feito; banner business-wide rebaseado em #3029 (Pest verde → MERGED). Docs corrigidos via #3021. Supera 1103/1124."
decided_by: [W]
cycle: "CYCLE-08"
prs: [3018, 3019, 3021, 3029]
us: ["US-WA-309"]
next_steps:
  - "Nada urgente — DNS + dead-letter + correções + banner business-wide (#3029) já em prod/main; Pest R-WA-CAIXA-UNIF-015 verde."
  - "Opcional/produto: promover a sentinela 'daemon whatsmeow alcançável' (Camada 6 do proposal whatsapp-ingestao-perda-zero) a trabalho ativo."
  - "Validar visualmente o banner pra conta não-admin na prod (smoke) quando conveniente."
related_adrs:
  - 0045-hostinger-dns-api-endpoint-canonico
  - 0202-whatsapp-profissionalizacao-baileys-out
  - 0204-whatsmeow-driver-substituto-baileys
  - 0286-channel-health-corroborado-por-mensagem-real
  - 0093-multi-tenant-isolation-tier-0
  - 0130-handoff-append-only-mcp-first
---

# Handoff 2026-06-19 11:54 BRT — fechamento: mídia WhatsApp (DNS+dead-letter) + banner business-wide

## Estado MCP no momento do fechamento

```
cycles-active: CYCLE-08 "Receita — Onda A" · 2026-05-31→06-28 · 68% · 9 dias restantes
my-work (@wagner): 30 tasks (4 review / 8 blocked / 18 todo) — nenhuma de mídia/whatsmeow ativa (foi tudo via PR)
origin/main HEAD: ad7b40b61 (#3023)
DB prod: media_download_status pending=38.439 (texto/default) / success=659 / failed_permanent=10.149 · MÍDIA real pending=0
DNS: whatsapp-whatsmeow.oimpresso.com A=177.74.67.30 (DoH Status:0)
```

## O que aconteceu

Continuei o handoff 0730 (2 decisões frias). Diagnostiquei, **executei com autorização do [W]**, e corrigi minha própria premissa errada no meio:

1. **DNS (causa real, corrigida).** `whatsapp-whatsmeow.oimpresso.com` estava **NXDOMAIN** (A record removido da zona Hostinger). Daemon CT100 (`177.74.67.30`) `Up 3 semanas healthy` + Traefik roteando — só o DNS estava morto. **Recriei** via Hostinger DNS API (token `/root/.hostinger-api-token` no CT100, `overwrite:false`; `dig @1.1.1.1` + DoH confirmam).
2. **"48k mídia travada" era ilusão.** `media_download_status='pending'` é o **default de TODA mensagem** (texto incluso). Mídia real pending = **10.134**, **100% `whatsapp_baileys`** (driver descomissionado ADR 0202, daemon morto, `.enc` expirado = undownloadable). **whatsmeow media pending = 0** (teste síncrono). Com OK do [W], **dead-lettered** os 10.134 → `failed_permanent`; mídia pending agora **0**.
3. **US-WA-309 (banner canal-caiu)** já estava entregue (#2956/#2963/#3002). O follow-up business-wide (#2964) estava `CONFLICTING` → **rebaseei limpo em #3029** ([W] "qualquer conta pode ver"): controller perde o filtro ACL (Tier 0 por `business_id`), Pest health `014→015` (de quebra conserta colisão de `014` pré-existente no main), charter v19. Descartei a parte "probe provision_pending" do #2964 — o main já cobre melhor via ADR 0287.

## Artefatos gerados

| PR | Estado | Conteúdo |
|---|---|---|
| #3018 / #3019 | merged | handoff 1103 + proposal Camada 6 (versão inicial — nº errado) |
| #3021 | merged | **correção** dos números (10.134 Baileys) + handoff 1124 |
| #3025 | closed | tentativa de quotar date do 1124 — barrada por Append-only (catch-22) |
| #3029 | **merged** | banner business-wide (rebase de #2964); 4 suites Pest/PHPStan verdes → mergeou |
| #2964 | closed | superado por #3029 (branch preservada — worktree paralelo) |

Ações operacionais em prod (com OK [W]): A record recriado · 10.134 mídias Baileys dead-lettered.

## Persistência

- **git/main:** #3021 (correções) mergeado; este handoff via PR docs.
- **prod (Hostinger/CT100):** DNS A record + dead-letter aplicados e verificados.
- **MCP:** sem task nova (trabalho não-task; US-WA-309 já era canon via #2956).

## Próximos passos pra retomar

`gh pr view 3029` — se Pest verde, mergeou sozinho; se vermelho, o watcher avisa e corrijo. Resto: nada urgente.

## Lições catalogadas

- **`date:` em handoff TEM que ser string quotada** (`"2026-06-19"`). Bati 2× (`/date must be string`) — mesma classe do fix 6b2465a8f. Validar schema 100% ANTES do 1º push.
- **Auto-merge corre na frente da validação local.** #3018/#3019 mergearam (advisory) antes de eu verificar os números → tive que corrigir via #3021. Segurar docs de diagnóstico como draft até o dado estar confirmado na fonte.
- **Append-only + gate Handoff = catch-22.** Handoff que mergeia com erro de schema NÃO pode ser corrigido in-place (Append-only barra). Logo: acertar de primeira.
- **"pending" ≠ "mídia travada".** É o default de toda msg — sempre filtrar por `type` (mídia) + `provider` antes de afirmar backlog.
- **Vantage importa.** `curl` do CT100 (daemon) ≠ vantage do worker (Hostinger). NXDOMAIN só se confirma via DoH/resolver público.

## Pointers detalhados

- Diagnóstico + correção: handoffs [1103](2026-06-19-1103-midia-dns-rootcause.md) + [1124](2026-06-19-1124-midia-correcao-numeros.md) · proposal [whatsapp-ingestao-perda-zero.md](../decisions/proposals/whatsapp-ingestao-perda-zero.md) (Camada 6).
- Worker: [DownloadMediaJob.php](../../Modules/Whatsapp/Jobs/DownloadMediaJob.php) · DNS: [ADR 0045](../decisions/0045-hostinger-dns-api-endpoint-canonico.md) · Baileys OUT: [ADR 0202](../decisions/0202-whatsapp-profissionalizacao-baileys-out.md).
