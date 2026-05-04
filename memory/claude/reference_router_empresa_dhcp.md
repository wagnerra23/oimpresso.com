---
name: Router empresa TP-Link — DHCP / clientes / reservas IP
description: Mapa completo de DHCP leases ativas + reservas estáticas do router 192.168.0.1. Snapshot 2026-04-28. Atualizar quando adicionar/remover dispositivo
type: reference
originSessionId: 32066199-13c2-4cc8-922b-d65034040e23
---
**Router:** TP-Link em `192.168.0.1`
**Painel DHCP:** Avançado → Rede → DHCP

## ⚠️ `192.168.0.3` e `192.168.0.4` = MESMA máquina (Windows Server 2022 com 2 NICs)

Wagner esclareceu em 2026-04-28 (depois do alerta inicial): a "máquina servidor Delphi" tem **2 placas de rede**, e responde nos dois IPs. **Não usar `192.168.0.3` pra novas VMs/CTs** — é interface secundária do mesmo Windows Server.

Specs do Windows Server 2022:
- 2× Intel Xeon (multi-socket)
- 128 GB RAM
- Placa de vídeo dedicada
- 2 NICs (192.168.0.3 + 192.168.0.4)
- **Roda sistemas Delphi** (WR Comercial / Sistema WR2 — ver `reference_delphi_wr_comercial.md`)
- Acesso: RDS 3389 (público via TP-Link regra #4 → 192.168.0.4)
- DB: FireBird 3050 (público via regra #8)
- Outros services: Cpanel:21, FTP 50000-51000, Horse:19000, THorse:8050, socket_horce:55666

Credenciais Windows RDS: **pendente Wagner passar** (login + senha) pra salvar em Vaultwarden.

## Reservas estáticas (Permanentes)

| IP | MAC | Cliente | Função |
|---|---|---|---|
| **192.168.0.2** | `00:E0:1C:0E:70:4B` (HP NIC) | (Proxmox host) | **Servidor Proxmox `sistema`** |
| 192.168.0.3 | (sem reserva visível) | (outro servidor) | ⚠️ NÃO USAR |
| 192.168.0.4 | `D0:00:06:12:0C:DB` | Sistema | Servidor Windows / Delphi (RDS:3389, FireBird:3050, Cpanel:21, FTP, Horse, THorse, socket_horce) |
| 192.168.0.10 | `E4:FD:45:AB:5C:F2` | Wagner_Lenovo | Notebook do Wagner |
| 192.168.0.20 | `F8:A9:63:5A:55:0E` | (sem nome) | ? |
| 192.168.0.21 | `94:DE:80:F4:59:2D` | (sem nome) | ? |
| 192.168.0.55 | `64:66:B3:02:4B:1B` | (sem nome) | SVN dedicado (porta 8777) |
| 192.168.0.60 | `08:98:46:96:D8:27` | Relojo Ponto | Relógio de ponto físico (Henry/equivalente — input do PontoWr2) |
| 192.168.0.105 | `3C:77:E6:76:15:3A` | Impressora HP1102w | Impressora |

## DHCP dinâmico (snapshot momentânea — vai mudar)

| IP | MAC | Cliente |
|---|---|---|
| 192.168.0.101 | `36:51:88:58:48:E0` | Unknown |
| 192.168.0.106 | `90:09:DF:9A:BC:E9` | BOOK-GV80BF5507 |
| 192.168.0.107 | `D0:00:06:23:07:C8` | Sistema (NOTA: outro `D0:00:06:*` igual ao 192.168.0.4 — pode ser segunda interface ou hardware semelhante) |
| 192.168.0.108 | `F8:A9:63:5A:56:AE` | WR2 (PontoWr2 device? Tecno mobile?) |
| 192.168.0.114 | `96:74:10:9C:2D:8B` | Unknown |
| 192.168.0.125 | `50:A1:32:22:AF:FA` | Eliana (notebook esposa) |

## IPs livres confirmados (probe 2026-04-28: ping=False, arp=vazio)

- 192.168.0.30, 50, 51, 99, 150 — todos livres

## Recomendação pra novas VMs/CTs

Reservar (permanente) por MAC. Sugestão de range fora do DHCP rotativo:

- **192.168.0.50** → docker-host (Reverb, Traefik, Portainer, Meilisearch futuramente)
- 192.168.0.51-59 → outras VMs (staging, workers, etc.)

Adicionar reserva DHCP pelo MAC do CT/VM antes de subir, pra IP não trocar em reboot.

## Observações

- **2 MACs `D0:00:06:*`** — `192.168.0.4` (permanente "Sistema") e `192.168.0.107` (dinâmico "Sistema"). Investigar se é a mesma máquina com 2 NICs ou 2 servidores parecidos.
- **Cliente "WR2"** com MAC mobile-like (Tecno) em 192.168.0.108 — pode ser o telefone com app PontoWr2 ou device de teste do PontoWr2.
- **Range DHCP rotativo:** parece estar entre `.100-.150`. Manter VMs/CTs fora dessa faixa pra evitar conflito.
