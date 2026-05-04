---
name: Central VoIP Issabel/Asterisk/Elastix — empresa
description: Acesso ao painel admin da central PBX em 192.168.0.21
type: reference
originSessionId: 32066199-13c2-4cc8-922b-d65034040e23
---
**IP LAN:** `192.168.0.21` (reserva DHCP por MAC `94:DE:80:F4:59:2D`)
**Painel admin:** `http://192.168.0.21/` ou `https://192.168.0.21/`
**Plataforma:** Issabel/Asterisk/Elastix (família PBX baseada em Asterisk)

**Login:**
- Usuário: `admin`
- Senha: `wscrct.000465`

**SIP / VoIP:**
- UDP 5060 (LAN — sem port forward visível)
- Range UDP 4000-5999 do TP-Link aponta pra 192.168.0.2 (Proxmox), provavelmente regra obsoleta — central real está em .21. Ajustar quando precisar.

**Quando usar acesso:**
- Adicionar/remover ramais
- Configurar trunk SIP (provedor)
- Listar chamadas, gravações
- Atualizar firmware da PBX
- Backup config

**LGPD:** chamadas gravadas (se gravação habilitada) são dados pessoais. Cuidar política de retenção.

**Migrar pra Vaultwarden** quando Wagner criar conta master no vault. Org "Infra" → item "Central VoIP Issabel".
