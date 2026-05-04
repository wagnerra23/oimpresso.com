---
name: Router empresa TP-Link — port forwards (NAT)
description: Mapa completo de Servidores Virtuais (port forwards) do router TP-Link em 192.168.0.1. Snapshot 2026-04-28 enviado por Wagner. Atualizar quando mudar regra
type: reference
originSessionId: 32066199-13c2-4cc8-922b-d65034040e23
---
**Router:** TP-Link em `192.168.0.1` (HTTP, sem TLS — "Não seguro")
**IP público externo:** `177.74.67.30` (ISP ateky.net.br)
**Painel:** `Avançado → Direcionamento NAT → Servidores Virtuais`

## Máquinas internas mapeadas

- **192.168.0.2** = Proxmox host (servidor `sistema`, Xeon 14C / 125 GB RAM)
- **192.168.0.4** = outra máquina LAN (parece servidor Windows/Delphi — RDS, Cpanel, FireBird, FTP, Horse/THorse/socket_horce, SVN-related)
- **192.168.0.55** = SVN dedicado (porta 8777)

## Tabela de port forwards (snapshot 2026-04-28 — pós-edição Wagner)

| ID | Serviço | Porta Ext | → IP Interno | Porta Int | Proto |
|----|---------|-----------|--------------|-----------|-------|
| 1 | Sistema WR2 | 211-212 | 192.168.0.4 | 211-212 | TCP |
| 2 | API | 80 | **192.168.0.50** ✅ (era .2) | 80 | TCP+UDP |
| 3 | **https** | **443** | **192.168.0.50** ✅ (era .2) | **443** | TCP+UDP |
| 4 | RDS | 3389 | 192.168.0.4 | 3389 | TCP+UDP |
| 5 | Svn | 8777 | 192.168.0.55 | 8777 | TCP+UDP |
| 6 | Cpanel | 21 | 192.168.0.4 | 21 | TCP |
| 7 | Telefone | 4000-5999 | 192.168.0.2 | 4000-5999 | TCP+UDP |
| 8 | FireBird | 3050 | 192.168.0.4 | 3050 | TCP |
| 9 | FTP | 50000-51000 | 192.168.0.4 | 50000-51000 | TCP |
| 10 | **servidores** | **7000-8049** | **192.168.0.2** | 7000-8049 | TCP |
| 11 | Portainer | 9000 | 192.168.0.2 | 9000 | TCP |
| 12 | FtpLinux | 20 | 192.168.0.2 | 20 | TCP+UDP |
| 13 | Rat | 214 | 192.168.0.4 | 214 | TCP |
| 14 | Horse19000 | 19000 | 192.168.0.4 | 19000 | TCP |
| 15 | THorse | 8050 | 192.168.0.4 | 8050 | TCP |
| 16 | socket_horce | 55666 | 192.168.0.4 | 55666 | TCP |

## ⚠️ Riscos de segurança visíveis

1. **`7000-8049` em 192.168.0.2 abrange a porta 8006 do painel Proxmox** — o painel está exposto publicamente em `https://177.74.67.30:8006/`. Risco alto. Recomendação: estreitar a regra "servidores" pra excluir 8006, ou bloquear 8006 via firewall do Proxmox host.
2. **Portainer 9000 público** — Portainer expôs gerenciamento Docker. Verificar se ainda está em uso e se tem auth forte.
3. **Range largo `4000-5999` (Telefone)** — qualquer serviço user-installed escutando aí fica exposto.
4. Painel TP-Link em HTTP sem TLS na LAN — se algum endpoint LAN for comprometido, sniffer pega senha do router.

## Pendente pra Reverb

Cliente vai conectar em `wss://reverb.wr2.com.br:443` (porta padrão TLS). Hoje regra ID #3 já direciona `443 → 192.168.0.2:443`. Wagner precisa decidir:

- **Opção A:** trocar **IP interno** da regra #3 de `192.168.0.2` pra **`192.168.0.50`** (CT Reverb que vou criar). Mais simples, mas quebra o que quer que esteja servindo HTTPS no host Proxmox hoje (ver `reference_proxmox_443_listener.md` se existir).
- **Opção B:** criar nova regra `8443 → 192.168.0.50:443` e cliente usa `wss://reverb.wr2.com.br:8443`. Não quebra nada existente, porta não-padrão.
- **Opção C:** instalar nginx no Proxmox host (192.168.0.2) que faz reverse-proxy 443 → CT 192.168.0.50:8080 baseado em `Host: reverb.wr2.com.br`. Mantém forward atual, isola Reverb no CT, permite outros serviços HTTPS no host. **Mais elegante mas mais peças.**

Decisão de Wagner: pendente.
