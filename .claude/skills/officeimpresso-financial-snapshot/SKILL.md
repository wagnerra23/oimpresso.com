---
name: officeimpresso-financial-snapshot
description: ATIVAR quando user pedir "analisar receita do cliente X", "snapshot financeiro de {cliente OfficeImpresso}", "comparar 2 clientes legacy", "/financial-snapshot {cliente}", "rodar análise multi-cliente OfficeImpresso", OU sempre que precisar conectar em banco Firebird de qualquer cliente WR Sistemas legacy pra extrair receita/despesa/inadimplência. Substitui consulta manual em isql + queries ad-hoc. Carrega o schema canônico OFFICEIMPRESSO-FIREBIRD-SCHEMA.md, padrão de conexão Python firebird-driver, queries-template validadas, e padrão de output anonimizado/com-nomes. Usado em (a) discovery pré-vendas (Wagner sabe receita do prospect antes da call), (b) auditoria pré-migração, (c) feature comercial paga "dashboard financeiro automático" que será serviço/produto.
type: tier-b-auto-trigger
status: draft
date: 2026-05-09
tier: B
---

# officeimpresso-financial-snapshot — Skill

> Conecta em banco Firebird OfficeImpresso (legacy Delphi WR Comercial), gera snapshot financeiro 4-quadrantes (a-receber/recebida/a-pagar/paga × ativo/inativo), exporta relatório markdown anonimizado/com-nomes.

## Quando usar

✅ ATIVAR:
- User pede análise financeira de cliente OfficeImpresso por nome/alias
- Discovery pré-vendas: prospect ainda usa OfficeImpresso, queremos saber receita pra calibrar pitch
- Auditoria pré-migração: vamos migrar cliente X, precisamos snapshot atual
- Comparação multi-cliente: relatório consolidado de N clientes
- Slash `/financial-snapshot {cliente}` ou `/officeimpresso-analyze`

❌ NÃO ATIVAR:
- Análise financeira no oimpresso.com novo (Laravel/MySQL) — usa skill diferente
- Migração de schema (essa é específica de análise read-only)
- Edição/atualização de dados legacy (sempre read-only)

## Quando NÃO ativar (proteção)

- ❌ Banco fora da rede 192.168.0.x (verificar IP antes de tentar)
- ❌ Cliente que pediu pra NÃO ser analisado (compliance LGPD)
- ❌ Período fora do horário comercial sem aviso (carga no servidor)

## Pré-requisitos

1. Acesso à rede LAN 192.168.0.0/24 (servidor `192.168.0.55:3050`)
2. Python 3.11+ instalado
3. `pip install firebird-driver` (mas verificar primeiro: `python -c "import firebird.driver"`)
4. Conhecimento do alias do cliente (consultar gerenciador Delphi do Wagner ou auto-mem `reference_bancos_firebird_wr2_office_comercial.md`)

## Referência canônica

- Schema completo: [memory/requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md](../../memory/requisitos/Officeimpresso/OFFICEIMPRESSO-FIREBIRD-SCHEMA.md)
- Runbook operacional manual: [memory/requisitos/Officeimpresso/RUNBOOK-financial-snapshot-cliente.md](../../memory/requisitos/Officeimpresso/RUNBOOK-financial-snapshot-cliente.md)

## Fluxo da skill (10 passos)

### 1. Identificar alias do cliente
- User fala "rodar análise pro Martinho" → mapear pra alias real (ex: `Martinho` ou `MartinhoServidor` em D:\DadosClientes\MartinhoCacamba\BANCO.FDB)
- Se alias ambíguo, perguntar antes de conectar

### 2. Validar conectividade
- `Test-NetConnection 192.168.0.55 -Port 3050` (ou ping + manual)
- Se off, parar e avisar Wagner

### 3. Conectar (Python)
```python
import firebird.driver as fb
con = fb.connect(f'192.168.0.55:{ALIAS}', user='SYSDBA', password='masterkey')
```

### 4. Validar schema-fingerprint
Rodar query de schema fingerprint pra confirmar que é OfficeImpresso (e não outro Firebird):
```sql
SELECT COUNT(*) FROM RDB$RELATIONS WHERE RDB$RELATION_NAME IN
  ('FINANCEIRO', 'MENSALIDADE_FINANCEIRO', 'CONTRATO', 'PESSOAS', 'BOLETOS')
```
Se != 5, parar com aviso "schema desconhecido".

### 5. Rodar 8 queries canônicas (do schema reference)
- 5.1 Sumário financeiro 12m (1 row)
- 5.2 Receita mensal recebida 24m (mensal)
- 5.3 Top 30 clientes 12m
- 5.4 A receber vencidas (top 20)
- 5.5 A pagar vencidas (top 20)
- 5.6 Despesas pagas 12m mensal
- 5.7 MRR atual + ARR projetado
- 5.8 Distribuição ticket mensal

### 6. Identificar "melhor cliente"
Cruzar receita 12m × recência (último pgto <30d) × ticket médio. Best fit = "Cliente Alpha".

### 7. Detectar alertas
- Resultado 12m negativo? → flag vermelho
- A pagar vencidas > A receber vencidas? → flag amarelo
- Top cliente >50% concentração? → flag concentração
- Churn estimado (clientes pagantes 24m - pagantes 12m) > 20%? → flag churn alto

### 8. Gerar 2 relatórios markdown
- `01-{cliente}-COM-NOMES.md` (gitignored — uso interno)
- `01-{cliente}-anonimizada.md` (committable se Wagner aprovar)

Anonimização: sha1 hash 6 chars de RAZAOSOCIAL → `Cliente_ABC123`.

### 9. Salvar em pasta padrão
`memory/research/2026-05-receitas-officeimpresso/{cliente-slug}/`

Sempre criar `.gitignore` na pasta protegendo arquivos sensíveis.

### 10. Reportar achados (≤300 palavras)
- MRR · ARR · Receita 12m · Resultado 12m · Top 1 cliente
- Top 3 alertas detectados
- Recomendação acionável (cobrar X, renegociar Y, focar Z)

## Restrições duras

- ❌ NUNCA fazer INSERT/UPDATE/DELETE
- ❌ NUNCA exportar dados sem anonimização pra git público
- ❌ NUNCA commitar credenciais (.env obrigatório se virar produção)
- ❌ NUNCA rodar em horário comercial cliente sem aviso (carga no servidor)
- ✅ Sempre ler schema reference antes de fazer queries customizadas
- ✅ Sempre criar .gitignore protegendo dados reais
- ✅ Sempre anonimizar antes de mostrar relatório a 3º (deck investidor, blog, parceiro)

## ROI esperado da skill

- **Discovery pré-vendas**: economia de 1-2h/prospect (Wagner sabe receita antes da call)
- **Auditoria pré-migração**: padroniza checklist (zero esquecimento)
- **Feature comercial** (`feature-financial-snapshot-multi-cliente.md`): vira produto pago R$ [redacted Tier 0]/m por cliente analisado mensalmente

## Próximos passos quando virar produção

1. Mover credenciais pra `.env` Laravel (não hardcoded em scripts)
2. Implementar via Modules/OfficeImpressoFinancialSnapshot
3. Schedulable: cron mensal varre todos clientes da lista
4. Dashboard React no oimpresso.com com chart.js + filtros por cliente
5. Alertas push: déficit detectado → notifica Wagner via WhatsApp
6. Tier comercial: oferecer pra clientes legacy "vamos te dar este dashboard de graça por 30d, depois R$ [redacted Tier 0]/m" (lead magnet pra migração)

## Histórico

- 2026-05-09: skill draft criada após análise piloto em ServidorWR2 revelar déficit -R$ [redacted Tier 0]k 12m + R$ [redacted Tier 0]k em atrasos. Wagner pediu rotina multi-cliente.
