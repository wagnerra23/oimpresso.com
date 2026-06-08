# Análise receitas OfficeImpresso — WR Sistemas

> ⚠️ Pasta **gitignored por default**. Conteúdo é confidencial (números reais) e/ou contém credenciais hardcoded (scripts de conexão Firebird).

## O que tem aqui (local apenas, não comitado)

| Arquivo | Conteúdo | Visibilidade |
|---------|----------|--------------|
| `01-analise-receitas-COM-NOMES.md` | Relatório com razão social real dos clientes | local Wagner apenas |
| `01-analise-receitas-anonimizada.md` | Mesmo relatório, clientes hash-anonimizados | local |
| `01-analise-receitas-wr-sistemas.md` | Relatório alternativo gerado via isql.exe (R39 sub-agent) | local |
| `_test_conn.py` | Script teste conexão Firebird | local (creds hardcoded) |
| `_schema_discover.py` | Script descoberta de schema | local (creds hardcoded) |
| `_relatorio_final.py` | Script geração relatório | local (creds hardcoded) |
| `_validar_mrr.py` | Script validação MRR | local (creds hardcoded) |

## Highlights validados (sumário 1 linha cada)

- **MRR atual**: ~R$ 40.500/m (validado 3 meses consecutivos: mar/abr/mai 2026)
- **ARR projetado**: ~R$ 487k/ano
- **Receita 12m recebida**: R$ 457k–517k (depende do bucket)
- **Clientes ativos pagantes 12m**: 144
- **Concentração saudável**: top 1 = 3.9%, top 10 = 24.6%, long tail = 75.4%
- **Distribuição ticket**: 30.6% <R$ 200, 54.9% R$ 200-499, 9.0% R$ 500-799, 4.9% R$ 800-1.499, 0.7% R$ 1.500+
- **Histórico 11 anos acumulado**: ~R$ 7,15M
- **Vertical auto histórico**: 6 oficinas mecânicas churnaram entre 2009-2013 — tentativa não-sustentada (reforça STAY-FOCUSED)

## Como reproduzir (Wagner)

1. Servidor Firebird up em `192.168.0.55:3050` (alias `servidor-crm:Banco`, ServidorWR2 produção)
2. Python 3.13 + `firebird.driver` instalado (`pip install firebird-driver`)
3. Rodar scripts em ordem: `_test_conn.py` → `_schema_discover.py` → `_relatorio_final.py` → `_validar_mrr.py`
4. Senhas: SYSDBA/masterkey (default Firebird, credencial Delphi legacy)

## Por que gitignored

- Receitas reais não devem ir pra GitHub público
- Scripts contêm credenciais hardcoded (mover pra `.env` antes de commitar)
- Razão social de clientes é PII → LGPD

## Próximos passos sugeridos

1. **Investigar pico 2021** (R$ 1,07M vs ~R$ 540k anos vizinhos — playbook replicável?)
2. **Plano anti-churn**: erosão histórica 1.647→154 ativos = 90% perda em 11 anos. Recente 5-6%/ano (controlado), mas atrasos no top 20 são alerta.
3. **Migração base atual → oimpresso.com**: 144 clientes × ticket atual (R$ 200-499 mediana) ≠ R$ 599 Pro novo. Plano de upgrade gradual.
4. **Crescimento 10x até R$ 5M/ano**: migração base sozinha não chega. Aquisição net-new é mandatório.
