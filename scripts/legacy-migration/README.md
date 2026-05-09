# Legacy Migration — Delphi WR Comercial → Laravel oimpresso

Pipeline de migração do legacy Delphi WR Comercial pro Laravel oimpresso.
Padrão **Strangler Fig + Anticorruption Layer** (Evans 2003 / Fowler 2004) com
**Brownfield AI** ([artigo abr/2026](https://tianpan.co/blog/2026-04-12-brownfield-ai-integrating-llm-features-into-legacy-codebases))
— Claude Opus 4.7 atua como ACL agent (mapping campo-a-campo, drift check).

## Status

🚧 **Fase 2 — POCs** (validação de pressupostos antes de investir em estrutura).

Plano completo em conversa de planejamento (sessão 2026-05-09). Visão geral:

| Fase | Entrega | Status |
|---|---|---|
| 0 | ADR `dominios/` + `clientes-legacy/` segregação | ⏳ pending |
| 1 | Estrutura de conhecimento `memory/dominios/wr-comercial/` | ⏳ pending |
| **2** | **POCs Python (parser + conexão Firebird)** | **🚧 atual** |
| 3 | Schema baseline reconstruído | ⏳ pending |
| 4 | MAPPING.md primeiro módulo (financeiro/contas bancárias) | ⏳ pending |
| 5 | Importer Python — primeira entidade | ⏳ pending |
| 6 | Smoke test no banco do Wagner (`servidor-crm:Banco` → biz=1) | ⏳ pending |
| 7 | Generalização (próxima entidade ou cliente) | ⏳ pending |

## Onde roda

**Apenas no Windows local do Wagner**. Razão:

- `servidor-crm` está na LAN do Wagner — não acessível de fora (Hostinger, CT 100)
- `HKCU\Software\Rocha\Office Comercial\Banco\Caminhos` é registry Windows local
- `pdo_firebird` raramente compila em shared hosting — Python `firebird-driver` é mais portável
- Wagner já tem Firebird client (`fbclient.dll`) instalado por usar o legacy Delphi

Hostinger/CT 100 só recebem o **resultado** (INSERTs no MySQL via Remote MySQL whitelist
ou tunnel autossh).

## Setup (uma vez)

```bash
cd scripts/legacy-migration
python -m venv .venv
.\.venv\Scripts\activate
pip install -r requirements.txt
copy .env.example .env
```

Edite `.env` se quiser sobrescrever defaults.

## POCs disponíveis

### POC 1 — Parser do `UpdateSQL.txt`

Parseia o changelog DDL incremental do Delphi (1464 blocos `UPDATE N;` no momento)
e gera um JSON com `{versao: [DDL_statements]}`.

```bash
python poc1-parser-updatesql.py
```

Output esperado:
- Total de blocos `UPDATE N;`
- Versão mínima e máxima
- Lista das primeiras 5 e últimas 5 versões com contagem de DDL por versão
- Salva `output/updatesql-parsed.json`

### POC 2 — Conexão Firebird via registry

Resolve alias do registry → path+senha → conecta no Firebird → executa queries-sonda.

```bash
python poc2-firebird-connect.py --alias ServidorWR2
```

Output esperado:
- Path resolvido do registry (deve ser `servidor-crm:Banco`)
- Versão do schema (`SELECT VALOR FROM CONFIGURACOES WHERE CONFIG='VERSAO_BANCO'`)
- Total de tabelas usuárias (`RDB$RELATIONS WHERE RDB$SYSTEM_FLAG=0`)
- Lista das 20 primeiras tabelas

## Critério de aceite Fase 2

✅ POC 1 imprime versão mín/max coerente com o `UpdateSQL.txt` (mín 6, máx ~1468)
✅ POC 2 conecta no `servidor-crm:Banco`, retorna `VERSAO_BANCO` ≥ 1308 e ≥ 1 tabela
✅ Wagner roda ambos sem erro na máquina dele

## Próximos passos após Fase 2 verde

1. Reorganizar plano com riscos validados
2. Escrever ADR de estrutura `memory/dominios/`
3. Wagner aprova ADR
4. Fase 3 — schema baseline reconstruído

## Por que aqui em `scripts/` e não em `Modules/`

Decisão arquitetural: **Python script puro primeiro, Laravel module depois quando
houver UI ou scheduling necessário**. Isolamento de risco — se o Firebird for
inacessível ou o schema variar demais, jogamos os scripts fora sem ter mexido em
módulo Laravel.
