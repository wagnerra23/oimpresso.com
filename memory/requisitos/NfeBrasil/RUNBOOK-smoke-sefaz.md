---
title: "RUNBOOK — Smoke fiscal SEFAZ-SC homologação biz=1"
type: runbook
owner: wagner
last_validated: 2026-05-08
related_adrs: [0030, 0058, 0062, 0093]
related_us: [US-NFE-002, US-RB-044]
related_cycle_goals: [CYCLE-03 #8 — Smoke fiscal SEFAZ-SC]
status: live
charter_version: 1
---

# RUNBOOK — Smoke fiscal SEFAZ-SC homologação biz=1

> **Quando usar:** validar pipeline US-NFE-002 ponta-a-ponta com SEFAZ real (homologação), antes de habilitar emissão automática em prod ou pra qualquer cliente.
>
> **Onde rodar:** `business_id=1` (Wagner WR2 Sistemas — Tubarão/SC), ambiente SEFAZ homologação (`business.ambiente=2`).
>
> **🚨 NUNCA rodar em `business_id=4`** (RotaLivre cliente). Regra documentada em [auto-mem `feedback_test_business_id_1_nunca_4`](C:/Users/wagne/.claude/projects/D--oimpresso-com/memory/feedback_test_business_id_1_nunca_4.md) — gravidade alta porque biz=4 concentra 99% do volume real de vendas; smoke nesse tenant pode contaminar dados operacionais.

Origem: migrado de auto-mem `runbook_smoke_sefaz_biz1.md` (sessão Opus 2026-05-07/08) pra git canônico — operacionaliza Goal #8 do CYCLE-03 e obedece [ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) (zero auto-mem privada).

---

## Pré-requisitos checklist

Verificar via SSH antes de tocar qualquer flag:

```bash
# Credenciais SSH ficam em Vaultwarden (ADR 0044) — NUNCA em git.
# Comando assume SSH key local + .env Hostinger acessível por shell remoto.
ssh -4 -o ConnectTimeout=900 -i ~/.ssh/id_ed25519_oimpresso -p 65002 \
    u906587222@<HOSTINGER_IP> \
    'cd domains/oimpresso.com/public_html && \
     mysql -u u906587222_oimpresso -p"$(grep DB_PASSWORD .env | cut -d= -f2 | tr -d \"\\\"\")" \
       u906587222_oimpresso -e "
       SELECT b.id, b.name, b.cnpj, b.ncm_padrao, b.ambiente,
              c.ativo as cert_ativo, c.valido_ate as cert_valido_ate,
              n.regime, JSON_UNQUOTE(JSON_EXTRACT(n.tributacao_default, \"$.cfop\")) as cfop
       FROM business b
       LEFT JOIN nfe_certificados c ON c.business_id=b.id AND c.ativo=1
       LEFT JOIN nfe_business_configs n ON n.business_id=b.id
       WHERE b.id=1"'
```

**Resultado esperado:**

- `cnpj` ≠ NULL e ≠ `'00.000.000/0000-00'`
- `ncm_padrao` = `'49111000'` (ou outro NCM válido 8 dígitos)
- `ambiente` = `2` (homologação)
- `cert_ativo` = `1`, `cert_valido_ate` ≥ data de hoje
- `regime` = `'simples'` (ou outro vigente em biz=1)
- `cfop` = `'5102'` (varejo) ou compatível com produto-teste

Se algum item falha → consultar [SPEC NfeBrasil](SPEC.md) seção "Setup biz=1" pra restaurar.

---

## Passo 1 — Habilitar flag NFC-e auto-emission

⚠️ **IRREVERSÍVEL parcial:** ao ligar a flag, qualquer venda finalizada `paid` em biz=1 vai disparar Job. Antes de ligar:

- Garante que `nfebrasil.auto_emission_on_sell_completed` está sob seu controle (sem outras vendas em curso)
- Está em horário de baixa atividade (final de semana ou madrugada)

```bash
# Backup .env (timestamp pra histórico)
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@<HOSTINGER_IP> \
    'cd domains/oimpresso.com/public_html && cp .env .env.bak.$(date +%s)'

# Habilita flag
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@<HOSTINGER_IP> \
    'cd domains/oimpresso.com/public_html && \
     echo "NFEBRASIL_AUTO_EMISSION_NFCE=true" >> .env && \
     php artisan config:clear'
```

---

## Passo 2 — Criar venda teste via UI POS

1. Login `oimpresso.com` na biz=1
2. Navegar `/sells/create` (POS)
3. Adicionar 1 produto qualquer (CFOP 5102, valor R$ [redacted Tier 0] pra mínimo de risco)
4. Cliente: "Consumidor final" (sem CPF — NFC-e B2C aceita anônimo)
5. Pagamento: dinheiro
6. **Finalizar** (`status=final` + `payment_status=paid`)
7. Anotar `transaction_id` (recibo ou listing `/sells`)

---

## Passo 3 — Verificar status NFC-e (Page Inertia)

Navegar `/nfe-brasil/transactions/{transaction_id}/status` (Page Inertia da fase 2C, US-NFE-002).

A Page polla `/nfe-brasil/api/transactions/{tx}/nfe-status` a cada 2s. Estados terminais (`autorizada` / `rejeitada` / `denegada`) param o polling.

**Esperado em ~10–30s:**

- `cstat` = `100` (Autorizado o uso da NF-e)
- `status` = `'autorizada'`
- `chave_44` preenchida (44 dígitos)
- `numero` ≥ 1 (próximo número da série)

---

## Passo 4 — Verificar SEFAZ + DANFE no banco

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@<HOSTINGER_IP> \
    'cd domains/oimpresso.com/public_html && \
     mysql -u u906587222_oimpresso -p"$(grep DB_PASSWORD .env | cut -d= -f2 | tr -d \"\\\"\")" \
       u906587222_oimpresso -e "
       SELECT id, transaction_id, modelo, status, cstat, motivo,
              chave_44, numero, valor_total, emitido_em
       FROM nfe_emissoes
       WHERE business_id=1 AND modelo=65
       ORDER BY id DESC LIMIT 3"'
```

XML autorizado fica em `storage/app/nfe-brasil/1/notas/{serie}-{numero}.xml`.
DANFE PDF fica em `storage/app/nfe-brasil/1/danfe/{chave_44}.pdf`.

---

## Possíveis erros + diagnóstico

| Sintoma | Causa provável | Ação |
|---|---|---|
| `cstat=215` "Falha schema XML" | NCM inválido ou CFOP errado | Revisar regra NCM ou template tributário |
| `cstat=217` "NFe não consta" | Replicação SEFAZ; pode reenviar | Aguardar 30s e re-emitir |
| `cstat=225` "Falha sequencial" | Número duplicado | Resetar `business.ultimo_numero_nfe` |
| `cstat=110/205` denegada | Emitente irregular | Verificar CNPJ + cert no SEFAZ-SC |
| Job não dispara | Flag `auto_emission_on_sell_completed=false` | Re-checar `.env` no Hostinger + `config:clear` |
| `RuntimeException sem NCM padrão` | Template não setou `ncm_default` E `business.ncm_padrao` vazio | Aplicar template OU `UPDATE business SET ncm_padrao='49111000' WHERE id=1` |

Logs: `storage/logs/laravel.log` no Hostinger. Filtrar por `NFC-e` ou `NfeService`.

---

## Rollback (desabilitar emissão automática)

Pra desabilitar a flag rapidamente:

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@<HOSTINGER_IP> \
    'cd domains/oimpresso.com/public_html && \
     sed -i "s/NFEBRASIL_AUTO_EMISSION_NFCE=true/NFEBRASIL_AUTO_EMISSION_NFCE=false/" .env && \
     php artisan config:clear'
```

⚠️ NFC-e já emitidas (`cstat=100`) **NÃO devem ser deletadas** — fiscal append-only por força de lei. Pra "desfazer" emissão use **CCe (Carta de Correção)** ou **Cancelamento** dentro de 24h via SEFAZ (fora do escopo deste runbook).

---

## Vinculação com governance

- **Goal CYCLE-03 #8** (Smoke fiscal SEFAZ-SC homologação biz=1, alvo `>= 1`) — este runbook materializa o "como"
- **US-NFE-002** (Emitir NFC-e a partir de venda finalizada) — runbook valida o pipeline server-side completo
- **US-RB-044** (Listener boleto pago → NFe55) — fluxo paralelo; smoke biz=1 dá evidência operacional do listener funcionando ([Goal #9 marcado code-complete](../../sprints/s6-charter-capterra/20-postmortem-s6-baseline.md))
- **[ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)** Multi-tenant Tier 0 — runbook respeita `business_id=1` scope; NUNCA biz=4
- **[ADR 0030](../../decisions/0030-credenciais-jamais-em-git.md)** — credenciais (SSH key, DB pass, IP Hostinger) ficam em Vaultwarden + `.env` local; runbook usa `<HOSTINGER_IP>` placeholder
- **[ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)** — runbook roda APP em Hostinger; daemons (Centrifugo/Meilisearch) em CT 100

---

## Próximo passo após smoke OK

Quando `cstat=100` for confirmado em biz=1:

1. **Promover Goal #8 do CYCLE-03 pra `done`** com `cycle-goals-track goal_id:8 status:done achieved_value:"1+ NFC-e cstat 100 em biz=1 SEFAZ-SC homologação 2026-MM-DD"`
2. **Não habilitar flag em biz=4 (ROTA LIVRE)** sem aprovação Wagner explícita + comunicação Larissa
3. Criar **session log** `memory/sessions/2026-MM-DD-smoke-sefaz-biz1.md` com print do resultado + chave_44 (sem PII)
4. Considerar criar **ADR de aceitação** documentando "Pipeline NFC-e validado em homologação SEFAZ-SC" pra histórico
