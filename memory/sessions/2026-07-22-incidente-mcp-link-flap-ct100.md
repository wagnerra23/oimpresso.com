---
date: "2026-07-22"
topic: "Incidente MCP fora do ar — link flap do host Proxmox + container zumbi (recovery automatizado)"
authors: [C, W]
outcomes:
  - "MCP restaurado e validado (brief-fetch + decisions-search)"
  - "Causa-raiz provada: cabo/link do servidor HP flapando desde 10:40 BRT"
  - "Recovery via vigia+autofix em janela de link (docker restart oimpresso-mcp)"
---

# Incidente 2026-07-22 — MCP server fora do ar (link flap + container zumbi)

## Sintoma
`brief-fetch` timeout no SessionStart (~14h BRT); `mcp.oimpresso.com:443` sem resposta de dentro e de fora; Tailscale mostrando `ct100-mcp` + `pve-empresa` + `recorder` offline **desde exatamente 2026-07-22T13:40:00Z (10:40 BRT)** — queda simultânea dos 3 nós da mesma máquina física.

## Diagnóstico (medido, não inferido)
| Hipótese | Veredito | Prova |
|---|---|---|
| Servidor desligou | ❌ | `uptime` no CT 100 = 6 dias; containers "Up 6 days" |
| Queda de energia/WoL necessário | ❌ | WoL enviado (MAC `00:E0:1C:0E:70:4B` do canon) mas a máquina nunca esteve off |
| Roteador/WAN do escritório | ❌ | PC do [W] (mesma rede, mesmo gateway 192.168.0.1) estável o dia todo; IP público inalterado (177.74.67.30) |
| **Link físico do host Proxmox flapando** | ✅ | 3 quedas/retornos síncronos dos nós Tailscale (10:40 → ~14:47 → ~14:55); ARP PC→192.168.0.50 falhando; de dentro do CT 100 gateway/inet OK nas janelas |
| **Container `oimpresso-mcp` zumbi** | ✅ | Reiniciou sozinho às ~10:40 (hora do incidente), ficou "healthy" no healthcheck mas Traefik respondia **502** e o app não atendia |

## Recovery executado
1. Script vigia em background (PC [W], mesma LAN): esperou janela de link → `docker restart oimpresso-mcp` às 17:57 UTC (14:57 BRT) → app respondendo em 30s.
2. Validação: rota pública `https://mcp.oimpresso.com` respondendo em 0,12s; `brief-fetch` devolvendo Brief #400 normal.
3. Traefik NÃO precisou de restart.

## Pendência física (única vacina real)
Reencaixar/trocar o **cabo de rede do servidor HP** (Proxmox `sistema`, 192.168.0.2) ou mudar de porta no switch. Enquanto o link flapar, o MCP volta a sumir junto com Vaultwarden/Meilisearch/Centrifugo/staging/Langfuse.

## Lições
- **Tailscale "offline" ≠ máquina desligada.** Os 3 nós offline no mesmo segundo apontavam pro denominador comum (host físico), mas o `uptime` de 6 dias refutou "desligou" — perguntar ao runtime antes de concluir (família LC-08: a consequência, não a declaração).
- **Healthcheck "healthy" não prova serviço servível** — o `oimpresso-mcp` ficou 4h healthy respondendo 502 ao proxy. Mesma família da R1 (CI verde não prova render). **Correção medida (18:30):** o healthcheck JÁ testa a rota HTTP real (`curl localhost/api/mcp/health` no container + `loadbalancer.healthcheck.path=/api/mcp/health` no Traefik — [docker-compose.yml:61-85](../../docker/oimpresso-mcp/docker-compose.yml)). O ponto cego é estrutural: healthcheck interno não enxerga o caminho Traefik→container (rede/registro), que foi o que quebrou. Detectar isso exige probe de FORA (o smoke `https://mcp.oimpresso.com/api/mcp/health` externo), não mais healthcheck interno.
- **Recovery automatizável em janela de link**: o padrão "vigia + autofix na próxima janela" funcionou de primeira; receita reutilizável pra links intermitentes.

## Desfecho (18:15 BRT)
[W] reencaixou o cabo do servidor → LAN voltou (pve+CT100 pingam), Tailscale saiu de relay pra **conexão direta 5ms**. Na volta do link, o cron `*/15 self-update.sh` fez o primeiro `git pull` desde 10:40, viu ~7h de commits e **recriou o container `oimpresso-mcp` às 21:00:03 UTC** — que passou minutos saturado re-sincronizando o backlog no Meilisearch (POSTs a cada 2s nos logs do Traefik), deixando hits no `/` lentos/travados enquanto os endpoints reais do MCP respondiam em 6ms. Estado final validado: `brief-fetch` e `decisions-search` funcionais pelo bridge. Rota `/` ainda flaky durante a digestão do backlog — cosmético, não bloqueia a memória.

## Follow-ups sugeridos (decisão [W])
1. ✅ Cabo reencaixado por [W] 2026-07-22 ~18:10 BRT — link voltou direto (5ms).
2. ~~Healthcheck apontar pra rota real~~ — **já aponta** (ver correção na lição acima). O follow-up honesto seria um probe EXTERNO de `https://mcp.oimpresso.com/api/mcp/health` com alerta (pega o ponto cego Traefik→container que o healthcheck interno não vê). Avaliar se `jana:health-check`/sentinela existente já cobre antes de criar régua nova (§5: estender o dono do tema, não duplicar).
3. Habilitar WoL na BIOS do HP — o MAC já está catalogado no canon; religa remoto vira comando (hoje o WoL foi inócuo porque a máquina estava ligada, mas o caminho ficou provado até o broadcast).
