---
name: Rede empresa — router TP-Link, DHCP, NAT, VoIP Issabel
description: Mapa completo da rede empresa em 192.168.0.0/24 — router TP-Link, reservas DHCP, port forwards (NAT), IPs livres, central VoIP Issabel/Asterisk em 192.168.0.21. Senhas no Vaultwarden.
type: reference
---

## Router TP-Link

**IP gerência:** `192.168.0.1` (HTTP, sem TLS — "Não seguro")
**IP público externo:** `177.74.67.30` (ISP ateky.net.br)
**Painéis:**
- DHCP: `Avançado → Rede → DHCP`
- NAT: `Avançado → Direcionamento NAT → Servidores Virtuais`

---

## Máquinas internas mapeadas

- **192.168.0.2** = Proxmox host (servidor `sistema`, Xeon 14C / 125 GB RAM) — ver infra-proxmox-ct100.md
- **192.168.0.4** = Windows Server 2022 (Sistema WR2 / Delphi WR Comercial — RDS, Cpanel, FireBird, FTP, Horse/THorse/socket_horce)
- **192.168.0.21** = Central VoIP Issabel/Asterisk
- **192.168.0.50** = CT 100 docker-host (Reverb, Traefik, Portainer, Meilisearch, Vaultwarden)
- **192.168.0.55** = SVN dedicado (porta 8777)

**`192.168.0.3` e `192.168.0.4` = MESMA máquina** (Windows Server 2022 com 2 NICs). Wagner esclareceu 2026-04-28: a "máquina servidor Delphi" tem **2 placas de rede**, e responde nos dois IPs. **NÃO usar `192.168.0.3` pra novas VMs/CTs** — é interface secundária do mesmo Windows Server.

Specs do Windows Server 2022 (.4):
- 2× Intel Xeon (multi-socket) · 128 GB RAM · placa de vídeo dedicada · 2 NICs
- Roda sistemas Delphi (WR Comercial / Sistema WR2 — ver legacy-delphi-firebird.md)
- Acesso: RDS 3389 (público via TP-Link regra #4)
- DB: FireBird 3050 (público via regra #8)
- Outros services: Cpanel:21, FTP 50000-51000, Horse:19000, THorse:8050, socket_horce:55666
- Credenciais Windows RDS: **no Vaultwarden** quando Wagner passar (pendente)

---

## Reservas DHCP estáticas (Permanentes)

| IP | MAC | Cliente | Função |
|---|---|---|---|
| **192.168.0.2** | `00:E0:1C:0E:70:4B` (HP NIC) | (Proxmox host) | **Servidor Proxmox `sistema`** |
| 192.168.0.3 | (sem reserva visível) | (segunda NIC do .4) | NÃO USAR |
| 192.168.0.4 | `D0:00:06:12:0C:DB` | Sistema | Servidor Windows / Delphi |
| 192.168.0.10 | `E4:FD:45:AB:5C:F2` | Wagner_Lenovo | Notebook do Wagner |
| 192.168.0.20 | `F8:A9:63:5A:55:0E` | (sem nome) | ? |
| **192.168.0.21** | `94:DE:80:F4:59:2D` | (Central VoIP) | **Issabel/Asterisk PBX** |
| 192.168.0.55 | `64:66:B3:02:4B:1B` | (sem nome) | SVN dedicado (porta 8777) |
| 192.168.0.60 | `08:98:46:96:D8:27` | Relojo Ponto | Relógio de ponto físico (Henry/equivalente — input do PontoWr2) |
| 192.168.0.105 | `3C:77:E6:76:15:3A` | Impressora HP1102w | Impressora |

## DHCP dinâmico (snapshot momentânea — vai mudar)

| IP | MAC | Cliente |
|---|---|---|
| 192.168.0.101 | `36:51:88:58:48:E0` | Unknown |
| 192.168.0.106 | `90:09:DF:9A:BC:E9` | BOOK-GV80BF5507 |
| 192.168.0.107 | `D0:00:06:23:07:C8` | Sistema (NOTA: outro `D0:00:06:*` igual ao 192.168.0.4 — pode ser segunda interface) |
| 192.168.0.108 | `F8:A9:63:5A:56:AE` | WR2 (PontoWr2 device? Tecno mobile? — também é o softphone SIP ramal 1220) |
| 192.168.0.114 | `96:74:10:9C:2D:8B` | Unknown |
| 192.168.0.125 | `50:A1:32:22:AF:FA` | Eliana (notebook esposa) |

## IPs livres confirmados (probe 2026-04-28: ping=False, arp=vazio)

- **192.168.0.30, 50, 51, 99, 150** — todos livres
- (`.50` agora ocupado pelo CT 100 docker-host — novas VMs em `.51-.59`)

## Recomendação pra novas VMs/CTs

Reservar (permanente) por MAC. Sugestão de range fora do DHCP rotativo:
- **192.168.0.50** → docker-host (já em uso — Reverb, Traefik, Portainer, Meilisearch, Vaultwarden)
- 192.168.0.51-59 → outras VMs (staging, workers, etc.)

Adicionar reserva DHCP pelo MAC do CT/VM antes de subir, pra IP não trocar em reboot.

**Range DHCP rotativo:** parece estar entre `.100-.150`. Manter VMs/CTs fora dessa faixa pra evitar conflito.

---

## Tabela de port forwards (NAT — snapshot 2026-04-28 pós-edição Wagner)

| ID | Serviço | Porta Ext | → IP Interno | Porta Int | Proto |
|----|---------|-----------|--------------|-----------|-------|
| 1 | Sistema WR2 | 211-212 | 192.168.0.4 | 211-212 | TCP |
| 2 | API | 80 | **192.168.0.50** (era .2) | 80 | TCP+UDP |
| 3 | **https** | **443** | **192.168.0.50** (era .2) | **443** | TCP+UDP |
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

### Riscos de segurança visíveis

1. **`7000-8049` em 192.168.0.2 abrange a porta 8006 do painel Proxmox** — o painel está exposto publicamente em `https://177.74.67.30:8006/`. **Risco alto.** Recomendação: estreitar a regra "servidores" pra excluir 8006, ou bloquear 8006 via firewall do Proxmox host.
2. **Portainer 9000 público** — Portainer expôs gerenciamento Docker. Verificar se ainda está em uso e se tem auth forte.
3. **Range largo `4000-5999` (Telefone)** — qualquer serviço user-installed escutando aí fica exposto. Regra parece obsoleta — central real está em .21, não .2. Apertar.
4. Painel TP-Link em HTTP sem TLS na LAN — se algum endpoint LAN for comprometido, sniffer pega senha do router.

### Pendente pra Reverb/Centrifugo (ADR 0058 substituiu Reverb)

Cliente vai conectar em `wss://reverb.wr2.com.br:443` (porta padrão TLS). Hoje regra ID #3 já direciona `443 → 192.168.0.50`. 3 opções avaliadas:
- **A:** trocar IP interno regra #3 → `.50` (já feito)
- **B:** nova regra `8443 → .50:443` (porta não-padrão)
- **C:** nginx no Proxmox host fazendo reverse-proxy 443 → CT por Host header (mais elegante mas mais peças)

---

## Central VoIP Issabel — `192.168.0.21`

**Plataforma:** Issabel/Asterisk/Elastix (família PBX baseada em Asterisk)
**Painel admin:** `http://192.168.0.21/` ou `https://192.168.0.21/`

**Login:** user `admin`, senha **no Vaultwarden** (item `issabel-admin`)

**SSH:** `ssh root@192.168.0.21` (mesma senha do Vaultwarden)
**Webmin:** `https://192.168.0.21:10000/`
**fop2 (UI ramais ao vivo):** `http://192.168.0.21:4445/` (login separado)

### Hardware / OS (captura 2026-04-28)

```
Hostname:    sip.wr2.com.br
SO:          CentOS Linux 7.7.1908 (Core)  -- EOL desde junho/2024
Kernel:      3.10.0-862.6.3.el7 (jun/2018)
Uptime:      40 dias
RAM:         1.9 GB (597 MB usados)
Swap:        3.8 GB (15 MB usado)
Plataforma:  Issabel + Asterisk 13.30.0  -- versão antiga (atual 22.x)
Interface:   eth0 192.168.0.21/24
```

### Asterisk — estado

- 0 chamadas ativas no momento da captura
- **326 chamadas processadas** total (histórico)
- 16 SIP peers cadastrados
- **0 trunks SIP registradas** ← central não tem provedor VoIP externo (só intercom interno)

### Extensões (ramais SIP)

| Ramal | Status | IP | Notas |
|---|---|---|---|
| 1060 | offline | — | (não autenticou) |
| 1203 | offline | — | |
| **1220** | ONLINE (129ms) | 192.168.0.108 | é o cliente "WR2" da tabela DHCP — provável celular/softphone do Wagner |
| 1221 | offline | — | |
| 1224, 1225, 1226, 1227, 1228, 1229 | offline | — | |
| **1230** | ONLINE (126ms) | 192.168.0.103 | softphone/celular ativo |
| 1231, 1232, 1233 | offline | — | |
| 3000 | offline | — | (provável rota/voicemail) |
| 333 | offline | — | (provável diagnóstico) |

**Resumo:** 16 cadastrados, 2 online, 14 offline. Sem trunk SIP → **só intercom interno**, não disca pra rua via VoIP.

### Serviços rodando (portas listening)

| Porta | Serviço | Notas |
|---|---|---|
| 22 | SSH | acesso administrativo |
| 80 / 443 | Apache (Issabel painel) | painel admin web |
| 110 / 143 / 993 / 995 | **Cyrus IMAP/POP3 + IMAP-S/POP3-S** | servidor de e-mail rodando aqui também |
| 3306 | MySQL | DB Asterisk + Issabel |
| 4190 | Cyrus Sieve | filtros e-mail |
| 4445 | fop2_server | Flash Operator Panel 2 (UI ramais) |
| 4559 | hfaxd | servidor de fax |
| 5038 | Asterisk Manager Interface (AMI) | API Asterisk |
| 10000 | Webmin | painel admin alternativo Linux |
| **14101, 14102, 14110, 14123, 14130** | k3lserver, klogserver, kqueryserver | **Khomp K3L** — placa de telefonia analógica/GSM. Sugere que a central tem linhas físicas (carrossel telefônico tradicional) |
| 20004, 20005 | PHP listeners | partes da Issabel |

### SIP / VoIP (porta UDP)
- UDP 5060 (LAN — sem port forward visível)
- Range UDP 4000-5999 do TP-Link aponta pra 192.168.0.2 (Proxmox), provavelmente regra obsoleta — central real está em .21. Ajustar quando precisar.

### Riscos identificados

1. **CentOS 7 EOL** (junho/2024) — não recebe security updates. Vulnerável a CVEs novos. Migração pra distro nova ou Issabel em container Debian 12 dentro do `docker-host` é estratégia recomendada (sprint futura).
2. **Asterisk 13** — fim de suporte oficial em 2021. Atual é Asterisk 22 LTS. Quebra de compat alta entre 13→18→20→22, migração precisa cuidado.
3. **Sem trunk SIP** — se Wagner achava que central faz ligações pra rua via VoIP, está enganado. Configurar trunk com provedor (ex.: Telecall, Twilio, Voxbeam) ou confirmar que linhas Khomp atendem.
4. **Painel Issabel publicamente exposto?** Verificar regra TP-Link #7 "Telefone 4000-5999 → 192.168.0.2" se não inclui IP da central.
5. **MySQL root password desconhecida** — comando padrão `eLaStIc.2oo7` falhou. Pode ser senha customizada ou disabled. Não bloqueia operação mas dificulta backup/manutenção.
6. **Servidor de e-mail no mesmo host** — Cyrus IMAP rodando junto. Aumenta superfície de ataque. Avaliar se ainda usa.

### Quando usar acesso

- Adicionar/remover ramais
- Configurar trunk SIP (provedor)
- Listar chamadas, gravações
- Atualizar firmware da PBX
- Backup config

**LGPD:** chamadas gravadas (se gravação habilitada) são dados pessoais. Cuidar política de retenção.

### Próximas tarefas sugeridas (não bloqueia Cycle 01)

- [ ] Decidir: manter como está vs migrar pra Issabel container moderno
- [ ] Configurar trunk SIP com provedor (se Wagner usa ou pretende usar VoIP outbound)
- [ ] Backup completo via Issabel `Sistema > Backup/Restore` antes de qualquer mudança
- [ ] Migrar credencial pra Vaultwarden organization "Infra"
- [ ] Apertar regra TP-Link #7 (Telefone) — central real está em .21, não .2

### Observações DHCP — referência cruzada

- **2 MACs `D0:00:06:*`** — `192.168.0.4` (permanente "Sistema") e `192.168.0.107` (dinâmico "Sistema"). Investigar se é a mesma máquina com 2 NICs ou 2 servidores parecidos.
- **Cliente "WR2"** com MAC mobile-like (Tecno) em 192.168.0.108 — é o softphone SIP ramal 1220 (Wagner mobile).

---

## Refs cruzadas

- infra-proxmox-ct100.md — Proxmox host + CT 100 docker-host detalhado
- legacy-delphi-firebird.md — Servidor Windows .4 + WR Comercial + Firebird
- vaultwarden-credenciais.md — credenciais migradas pra cofre
