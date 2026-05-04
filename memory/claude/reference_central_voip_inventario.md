---
name: Central VoIP Issabel — inventário detalhado
description: Snapshot 2026-04-28 do estado da central PBX em 192.168.0.21 — extensões, trunks, OS, serviços, riscos
type: reference
originSessionId: 32066199-13c2-4cc8-922b-d65034040e23
---
**Captura:** 2026-04-28 via SSH `root@192.168.0.21` (senha `wscrct.000465` mesma do painel)

## Hardware / OS

```
Hostname:    sip.wr2.com.br
SO:          CentOS Linux 7.7.1908 (Core)  ⚠️ EOL desde junho/2024
Kernel:      3.10.0-862.6.3.el7 (jun/2018)
Uptime:      40 dias
RAM:         1.9 GB (597 MB usados)
Swap:        3.8 GB (15 MB usado)
Plataforma:  Issabel + Asterisk 13.30.0  ⚠️ versão antiga (atual 22.x)
Interface:   eth0 192.168.0.21/24
```

## Asterisk — estado

- 0 chamadas ativas no momento da captura
- **326 chamadas processadas** total (histórico)
- 16 SIP peers cadastrados
- **0 trunks SIP registradas** ← central não tem provedor VoIP externo

## Extensões (ramais SIP)

| Ramal | Status | IP | Notas |
|---|---|---|---|
| 1060 | offline | — | (não autenticou) |
| 1203 | offline | — | |
| **1220** | ✅ ONLINE (129ms) | 192.168.0.108 | é o cliente "WR2" da tabela DHCP — provável celular/softphone do Wagner |
| 1221 | offline | — | |
| 1224, 1225, 1226, 1227, 1228, 1229 | offline | — | |
| **1230** | ✅ ONLINE (126ms) | 192.168.0.103 | softphone/celular ativo |
| 1231, 1232, 1233 | offline | — | |
| 3000 | offline | — | (provável rota/voicemail) |
| 333 | offline | — | (provável diagnóstico) |

**Resumo:** 16 cadastrados, 2 online, 14 offline. Sem trunk SIP → **só intercom interno**, não disca pra rua via VoIP.

## Serviços rodando (portas listening)

| Porta | Serviço | Notas |
|---|---|---|
| 22 | SSH | acesso administrativo |
| 80 / 443 | Apache (Issabel painel) | painel admin web |
| 110 / 143 / 993 / 995 | **Cyrus IMAP/POP3 + IMAP-S/POP3-S** | servidor de e-mail rodando aqui também! |
| 3306 | MySQL | DB Asterisk + Issabel |
| 4190 | Cyrus Sieve | filtros e-mail |
| 4445 | fop2_server | Flash Operator Panel 2 (UI ramais) |
| 4559 | hfaxd | servidor de fax |
| 5038 | Asterisk Manager Interface (AMI) | API Asterisk |
| 10000 | Webmin | painel admin alternativo Linux |
| **14101, 14102, 14110, 14123, 14130** | k3lserver, klogserver, kqueryserver | **Khomp K3L** — placa de telefonia analógica/GSM. Sugere que a central tem linhas físicas (carrossel telefônico tradicional) |
| 20004, 20005 | PHP listeners | partes da Issabel |

## ⚠️ Riscos identificados

1. **CentOS 7 EOL** (junho/2024) — não recebe security updates. Vulnerável a CVEs novos. Migração pra distro nova ou Issabel em container Debian 12 dentro do `docker-host` é estratégia recomendada (sprint futura).
2. **Asterisk 13** — fim de suporte oficial em 2021. Atual é Asterisk 22 LTS. Quebra de compat alta entre 13→18→20→22, migração precisa cuidado.
3. **Sem trunk SIP** — se Wagner achava que central faz ligações pra rua via VoIP, está enganado. Configurar trunk com provedor (ex.: Telecall, Twilio, Voxbeam) ou confirmar que linhas Khomp atendem.
4. **Painel Issabel publicamente exposto?** Verificar regra TP-Link #7 "Telefone 4000-5999 → 192.168.0.2" se não inclui IP da central.
5. **MySQL root password desconhecida** — comando padrão `eLaStIc.2oo7` falhou. Pode ser senha customizada ou disabled. Não bloqueia operação mas dificulta backup/manutenção.
6. **Servidor de e-mail no mesmo host** — Cyrus IMAP rodando junto. Aumenta superfície de ataque. Avaliar se ainda usa.

## Acesso administrativo

- **SSH:** `ssh root@192.168.0.21` (senha `wscrct.000465`)
- **Painel web:** `https://192.168.0.21/` admin/wscrct.000465
- **Webmin:** `https://192.168.0.21:10000/` (provável user `root` mesma senha — não testei)
- **fop2 (UI ramais ao vivo):** `http://192.168.0.21:4445/` (login separado)

## Próximas tarefas sugeridas (não bloqueia Cycle 01)

- [ ] Decidir: manter como está vs migrar pra Issabel container moderno
- [ ] Configurar trunk SIP com provedor (se Wagner usa ou pretende usar VoIP outbound)
- [ ] Backup completo via Issabel `Sistema > Backup/Restore` antes de qualquer mudança
- [ ] Migrar credencial pra Vaultwarden organization "Infra"
- [ ] Apertar regra TP-Link #7 (Telefone) — central real está em .21, não .2
