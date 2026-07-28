---
owner: W
last_validated: "2026-06-08"
title: Runbook — Snapshot financeiro de cliente OfficeImpresso
status: ativo
date: 2026-05-09
audience: Wagner / Felipe / Maiara / Eliana / IA-pair
duration: 5-15min por cliente (depende de volume de dados)
---

# Runbook — Snapshot financeiro de cliente OfficeImpresso

> Receita recuperar o "estado financeiro" de qualquer cliente OfficeImpresso (legacy Delphi WR Comercial) em 5-15 minutos. Outputs: relatório markdown 4-quadrantes (a-receber/recebida/a-pagar/paga × ativo/inativo) + alertas de churn/inadimplência.

## Quando usar

- 🎯 **Discovery pré-vendas**: prospect ainda usa OfficeImpresso, queremos saber receita/dívida/saúde antes da call
- 🎯 **Auditoria pré-migração**: vamos migrar cliente X pro oimpresso.com novo, precisamos snapshot atual
- 🎯 **Cobrança proativa**: detectar inadimplência crescente antes do cliente reclamar
- 🎯 **Análise multi-cliente mensal**: saber saúde dos 38 clientes legacy de uma vez (cron mensal)
- 🎯 **Lead magnet**: oferecer relatório grátis pro prospect → vira lead de migração

## Pré-requisitos

| Item | Como verificar |
|------|----------------|
| Estar em LAN da empresa (192.168.0.0/24) | `ping 192.168.0.55` |
| Servidor Firebird up | `Test-NetConnection 192.168.0.55 -Port 3050` |
| Python 3.11+ | `python --version` |
| firebird-driver instalado | `python -c "import firebird.driver"` |
| Alias do cliente (mapeamento) | abrir gerenciador Delphi do Wagner OU consultar [OFFICEIMPRESSO-FIREBIRD-SCHEMA.md](OFFICEIMPRESSO-FIREBIRD-SCHEMA.md) §1.2 |

## Fluxo de execução (manual)

### Passo 1 — Identificar alias do cliente
Cada cliente tem alias na lista do gerenciador. Exemplos:
- `Banco` (= ServidorWR2 = WR Sistemas próprio, IP-only)
- `Estilo` (= D:\DadosClientes\Estilo\Dados\BANCO.FDB)
- `Martinho` (= D:\DadosClientes\MartinhoCacamba\BANCO.FDB)
- `Gold` (= D:\DadosClientes\Gold\Dados\BANCO.FDB)
- ... etc

### Passo 2 — Testar conexão (sanity check 30s)
```bash
python -c "import firebird.driver as fb; con=fb.connect('192.168.0.55:Estilo', user='SYSDBA', password='masterkey'); print('OK', con); con.close()"
```

Se falhar: verificar (a) servidor up, (b) alias correto, (c) firewall.

### Passo 3 — Rodar snapshot script
```bash
python <<'EOF'
import firebird.driver as fb
import datetime as dt
ALIAS = 'Estilo'  # ALTERAR pra cliente desejado
con = fb.connect(f'192.168.0.55:{ALIAS}', user='SYSDBA', password='masterkey')
cur = con.cursor()

cur.execute("""
SELECT
  (SELECT COALESCE(SUM(VALOR),0) FROM FINANCEIRO WHERE TIPO='RECEBIDA' AND STATUS='ATIVO'
     AND DATAPAGTO BETWEEN DATEADD(YEAR,-1,CURRENT_DATE) AND CURRENT_DATE) AS REC_12M,
  (SELECT COUNT(DISTINCT RAZAOSOCIAL) FROM FINANCEIRO WHERE TIPO='RECEBIDA' AND STATUS='ATIVO'
     AND DATAPAGTO BETWEEN DATEADD(YEAR,-1,CURRENT_DATE) AND CURRENT_DATE
     AND RAZAOSOCIAL IS NOT NULL) AS N_CLI_12M,
  (SELECT COALESCE(SUM(VALOR),0) FROM FINANCEIRO WHERE TIPO='A RECEBER' AND STATUS='ATIVO'
     AND VENCTO < CURRENT_DATE AND DATAPAGTO IS NULL) AS A_RECEBER_VENCIDAS,
  (SELECT COALESCE(SUM(VALOR),0) FROM FINANCEIRO WHERE TIPO='A PAGAR' AND STATUS='ATIVO'
     AND VENCTO < CURRENT_DATE AND DATAPAGTO IS NULL) AS A_PAGAR_VENCIDAS,
  (SELECT COALESCE(SUM(VALOR),0) FROM FINANCEIRO WHERE TIPO='PAGA' AND STATUS='ATIVO'
     AND DATAPAGTO BETWEEN DATEADD(YEAR,-1,CURRENT_DATE) AND CURRENT_DATE) AS PAG_12M,
  (SELECT COALESCE(SUM(VALOR),0) FROM MENSALIDADE_FINANCEIRO WHERE STATUS='ATIVO'
     AND DT_VENCTO BETWEEN
        DATEADD(DAY, 1-EXTRACT(DAY FROM CURRENT_DATE), CURRENT_DATE) AND
        LAST_DAY(CURRENT_DATE)) AS MRR
FROM RDB$DATABASE
""")
r = cur.fetchone()
print(f'\n=== {ALIAS} ===')
print(f'Receita 12m: R$ {float(r[0]):,.2f}')
print(f'Clientes pagantes 12m: {r[1]}')
print(f'A receber vencidas: R$ {float(r[2]):,.2f}')
print(f'A pagar vencidas: R$ {float(r[3]):,.2f}')
print(f'Despesas pagas 12m: R$ {float(r[4]):,.2f}')
print(f'MRR atual: R$ {float(r[5]):,.2f}')
print(f'Resultado 12m: R$ {float(r[0])-float(r[4]):,.2f}')
con.close()
EOF
```

### Passo 4 — Análise estendida (relatório completo)
Usar template `_analise_completa.py` (em `memory/research/2026-05-receitas-officeimpresso/`) — ajustar a variável ALIAS no topo.

Gera:
- `01-{cliente}-COM-NOMES.md` (uso interno)
- `01-{cliente}-anonimizada.md` (committable se aprovado)

### Passo 5 — Detectar alertas
Critérios automáticos:
- 🔴 **Vermelho**: Resultado 12m < 0 (déficit)
- 🟠 **Laranja**: A pagar vencidas > A receber vencidas (passivo > ativo)
- 🟡 **Amarelo**: Top 1 cliente > 30% concentração (vulnerabilidade)
- 🟢 **Verde**: Todos números positivos + crescimento M/M ≥ 3%

### Passo 6 — Reportar (≤300 palavras pra dono)
Template:
```
[Cliente X] — Snapshot financeiro 2026-05

Receita 12m: R$ XXX | MRR: R$ YY
A receber vencidas: R$ ZZ (oportunidade cobrar)
A pagar vencidas: R$ WW (atenção)
Resultado 12m: R$ ±KK

Top 3 alertas:
1. ...
2. ...
3. ...

Top 3 ações sugeridas:
1. Cobrar prospect/cliente X (R$ Z em atraso)
2. Renegociar fornecedor Y
3. Focar aquisição de tipo Z
```

### Passo 7 — Salvar arquivo (.gitignore proteção)
```
memory/research/2026-05-receitas-officeimpresso/
├── .gitignore  (protege *.md de dados reais)
├── README.md   (committable)
└── {cliente}/
    ├── 01-{cliente}-COM-NOMES.md  (gitignored)
    └── 01-{cliente}-anonimizada.md (committable se aprovar)
```

## Multi-cliente (rotina mensal)

Para varrer TODOS os ~38 bancos clientes WR Sistemas:

```python
# Lista de aliases extraída do registry HKCU\Software\Rocha\Office Comercial\Banco\Caminhos
CLIENTES = [
    'Estilo', 'Extreme', 'Fixar', 'Fluxo', 'GPSinalizacao', 'GSX', 'Golbal',
    'Gold', 'GoldenPrint', 'Guia Decor', 'HexiPrint', 'Lebrinha', 'Martinho',
    'Max', 'Mecanica Lebrinha', 'Medeiros Produtos Limpeza', 'Metalurgica SF',
    'Mhundo', 'Midia OFF', 'Midia e CIA', 'MilLetras', 'Movessul', 'Multimage',
    'NewPrintFoz', 'Personalise', 'Produart', 'RG Comunicacao', 'SCMola', 'Safety',
    'ServidorWR2',  # produção própria
    'Studium Vinil', 'TechPress', 'Vargas', 'Vargas Acessorios', 'Wow', 'Zoom',
]

for alias in CLIENTES:
    try:
        snapshot(alias)  # função que rola o passo 3+4
    except Exception as e:
        print(f'FAIL {alias}: {e}')
```

Output: 1 relatório consolidado `memory/research/2026-05-receitas-officeimpresso/CONSOLIDADO-2026-05.md` com:
- Tabela ranking 38 clientes por MRR
- Health score por cliente (verde/amarelo/laranja/vermelho)
- Top 5 oportunidades (clientes com maior potencial migração)
- Top 5 alertas (clientes em crise)

## Restrições duras

- ❌ NUNCA INSERT/UPDATE/DELETE — somente SELECT
- ❌ NUNCA exportar dados reais sem anonimização pra git público
- ❌ NUNCA rodar em horário comercial cliente sem aviso (carga no servidor)
- ❌ NUNCA commitar credenciais hardcoded (mover pra `.env` na produção)
- ✅ Sempre criar `.gitignore` protegendo arquivos sensíveis
- ✅ Sempre validar schema antes de queries customizadas (heurística: confirmar tabelas FINANCEIRO + MENSALIDADE_FINANCEIRO + CONTRATO + PESSOAS + BOLETOS)
- ✅ LGPD: dados pessoais (CPF/CNPJ/email) só no relatório COM-NOMES, gitignored

## Tempo esperado por etapa

| Etapa | Manual | Skill `officeimpresso-financial-snapshot` |
|-------|--------|--------------------------------------------|
| Identificar alias | 1min | automatizado |
| Conectar | 30s | 30s |
| Rodar 8 queries | 2-3min | 1min (paralelo) |
| Anonimizar + salvar | 5min | 30s |
| Reportar achados | 5-10min | 1min |
| **Total** | **15-20min** | **3-5min** |

## Troubleshooting

| Sintoma | Causa provável | Fix |
|---------|----------------|-----|
| `Connection refused` | servidor down ou firewall | verificar `Test-NetConnection 192.168.0.55 -Port 3050` |
| `Login failed` | senha trocada | atualizar `.env` ou perguntar Wagner |
| `Database is shutdown` | banco em manutenção | aguardar ou usar backup .fdb local |
| Schema mismatch | versão Delphi diferente | comparar com OFFICEIMPRESSO-FIREBIRD-SCHEMA.md §2 |
| Alias não resolve | nome mudou no gerenciador | listar `HKCU\Software\Rocha\Office Comercial\Banco\Caminhos` no registry |
| Encoding ISO-8859-1 → UTF-8 | charset Firebird antigo | adicionar `charset='ISO8859_1'` na conexão |

## Histórico de versões

- **2026-05-09**: V1 — runbook criado após análise piloto ServidorWR2 revelar déficit -R$ [redacted Tier 0]k 12m + R$ [redacted Tier 0]k em atrasos. Wagner pediu rotina multi-cliente.

## Referências

- Schema canônico: [OFFICEIMPRESSO-FIREBIRD-SCHEMA.md](OFFICEIMPRESSO-FIREBIRD-SCHEMA.md)
- Skill que automatiza: [.claude/skills/officeimpresso-financial-snapshot/SKILL.md](../../../.claude/skills/officeimpresso-financial-snapshot/SKILL.md)
- Feature comercial proposta: [feature-financial-snapshot-multi-cliente.md](../../decisions/proposals/feature-financial-snapshot-multi-cliente.md)
