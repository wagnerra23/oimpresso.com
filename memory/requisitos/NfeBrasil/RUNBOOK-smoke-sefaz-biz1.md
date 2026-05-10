# RUNBOOK — Smoke fiscal SEFAZ NFC-e (homologação, biz=1)

> **Quando usar:** validar pipeline US-NFE-002 ponta-a-ponta com SEFAZ real (homologação), antes de habilitar emissão automática em prod ou pra qualquer cliente.
>
> **Onde rodar:** `business_id=1` (Wagner WR2 Sistemas — Tubarão/SC), ambiente SEFAZ homologação (`business.ambiente=2`).
>
> 🚨 **NUNCA rodar em `business_id=4` (RotaLivre cliente)** — ver [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md).

> **Estado em 2026-05-10** (verificado via SSH+MySQL Hostinger):
> - ✅ flag `NFEBRASIL_AUTO_EMISSION_NFCE=true` **já está ON** no `.env` (Passo 1 abaixo já feito)
> - ✅ biz=1 CNPJ `36.613.150/0001-18`, NCM `49111090`, ambiente `2`, cert válido até `2026-08-06`
> - ⚠️ **40 vendas paid+final em biz=1 mas 0 emissões NFC-e** — Listener só pega evento NOVO (não retroage). Significa que falta **criar 1 venda nova** pra disparar pipeline.
>
> Próxima ação: pular Passo 1, executar **Passo 2 (criar venda) → Passo 3 (verificar status) → Passo 4 (verificar SEFAZ + DANFE)**.

## Pré-requisitos checklist (verificar via SSH antes de tocar)

```bash
ssh -4 -o ConnectTimeout=900 -i ~/.ssh/id_ed25519_oimpresso -p 65002 \
    u906587222@148.135.133.115 \
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

Resultado **esperado**:
- `cnpj` ≠ NULL e ≠ '00.000.000/0000-00'
- `ncm_padrao` = '49111000' (ou outro NCM válido 8 dígitos)
- `ambiente` = 2 (homologação)
- `cert_ativo` = 1, `cert_valido_ate` ≥ data de hoje
- `regime` = 'simples' (ou outro)
- `cfop` = '5102' (varejo)

## Passo 1 — Habilitar flag NFC-e auto-emission

⚠️ **IRREVERSÍVEL parcial:** ao ligar a flag, qualquer venda finalizada `paid` em biz=1 vai disparar o Job. Antes de ligar, garanta que:
- `nfebrasil.auto_emission_on_sell_completed` está sob seu controle (sem outras vendas em curso)
- Está em horário de baixa atividade

```bash
# Backup .env
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
    'cd domains/oimpresso.com/public_html && cp .env .env.bak.$(date +%s)'

# Habilita flag
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
    'cd domains/oimpresso.com/public_html && \
     echo "NFEBRASIL_AUTO_EMISSION_NFCE=true" >> .env && \
     php artisan config:clear'
```

## Passo 2 — Criar venda teste via UI POS

1. Login `oimpresso.com` na biz=1
2. `/sells/create` — POS
3. Adicionar 1 produto qualquer (CFOP 5102, valor R$ [redacted Tier 0] pra mínimo de risco)
4. Cliente: "Consumidor final" (sem CPF — NFC-e B2C aceita anônimo)
5. Pagamento: dinheiro
6. **Finalizar** (status=final + payment_status=paid)
7. Anotar `transaction_id` (vai aparecer no recibo ou /sells listing)

## Passo 3 — Verificar status NFC-e

Navegar `/nfe-brasil/transactions/{transaction_id}/status` (Page Inertia da fase 2C).

A Page polla `/nfe-brasil/api/transactions/{tx}/nfe-status` a cada 2s. Estados terminais (autorizada/rejeitada/denegada) param o polling.

**Esperado em ~10-30s:**
- `cstat = 100` (Autorizado o uso da NF-e)
- `status = 'autorizada'`
- `chave_44` preenchida (44 dígitos)
- `numero` ≥ 1 (próximo número da série)

## Passo 4 — Verificar SEFAZ + DANFE

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
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

## Possíveis erros + diagnóstico

| Sintoma | Causa provável | Ação |
|---|---|---|
| `cstat=215` "Falha schema XML" | NCM inválido ou CFOP errado | revisar regra NCM ou template |
| `cstat=217` "NFe não consta" | replicação SEFAZ; pode reenviar | aguardar e re-emitir |
| `cstat=225` "Falha sequencial" | número duplicado | resetar `business.ultimo_numero_nfe` |
| `cstat=110/205` denegada | emitente irregular | verificar CNPJ + cert no SEFAZ |
| Job não dispara | flag `auto_emission_on_sell_completed=false` | re-checar `.env` no Hostinger |
| `RuntimeException sem NCM padrão` | template não setou `ncm_default` E `business.ncm_padrao` vazio | aplicar template OU `UPDATE business SET ncm_padrao='49111000' WHERE id=1` |

Logs: `storage/logs/laravel.log` no Hostinger. Filtrar por `NFC-e` ou `NfeService`.

## Rollback

Pra desabilitar emissão automática rápido:

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
    'cd domains/oimpresso.com/public_html && \
     sed -i "s/NFEBRASIL_AUTO_EMISSION_NFCE=true/NFEBRASIL_AUTO_EMISSION_NFCE=false/" .env && \
     php artisan config:clear'
```

NFC-e já emitidas (cstat 100) **NÃO devem ser deletadas** — fiscal append-only. Pra "desfazer" emissão use **CCe (Carta de Correção)** ou **Cancelamento** dentro de 24h via SEFAZ (fora do escopo desse runbook).

## Refs

- [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) — biz=1 nunca cliente
- [ADR 0058](../../decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md) — Centrifugo (eventos NFCeAutorizada)
- [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md) — runtime split
- ADR ARQ-0006 (cascade tributário)
- [RUNBOOK-hostinger-ssh-flaky.md](../Infra/RUNBOOK-hostinger-ssh-flaky.md) — receita SSH+MySQL Hostinger (se existir; senão ver `memory/proibicoes.md` sobre warm-up)
