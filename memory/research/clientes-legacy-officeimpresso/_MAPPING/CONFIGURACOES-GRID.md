---
id: research-clientes-legacy-officeimpresso-mapping-configuracoes-grid
title: Mapping canonico — CONFIGURACOES_GRID (descoberta de uso real por cliente)
status: live
date: 2026-05-11
audience: dev implementando US-SELL-027 (schema discovery dinamico — PR #534)
source_scripts:
  - "scripts/probe_configuracoes_grid.py (schema + tabela alvo)"
  - "scripts/probe_configuracoes_grid_blob.py (analise GRID BLOB DFM)"
source_files:
  - "D:/Programas/WR Comercial/app/Controller/Controller.Configuracoes_Grid.pas"
method: firebird-probe (5 bancos OfficeImpresso) — READ-ONLY
banks_probed: 5
---

# Mapping canonico — `CONFIGURACOES_GRID` (descoberta de uso real)

> **Sinal OURO para US-SELL-027 (PR #534 Grade Avancada).**
> Em vez de assumir que cliente X usa colunas A, B, C — leio o que ele **configurou de fato** no Firebird. Cada usuario do Delphi salva seu grid personalizado nessa tabela; somando todos os usuarios de uma empresa, descobrimos o perfil real de uso.

## 1. Existencia e tamanho

Tabela `CONFIGURACOES_GRID` existe nos **5 bancos**, todos com schema identico:

| Cliente (hash) | Total linhas | ATIVO=S |
|----------------|-------------:|--------:|
| Cliente_BC9F35 (01) | 283 | 283 (100%) |
| Cliente_661A7C (02) | 548 | 548 (100%) |
| Cliente_F8E47B (03) | 253 | 253 (100%) |
| Cliente_5D8A6C (04) | 469 | 469 (100%) |
| Cliente_3A1E70 (05) | 690 | 690 (100%) |

> Universal — todo cliente OfficeImpresso tem essa tabela populada. Nao precisa fallback.

## 2. Schema da tabela (8 colunas — todos clientes identicos)

| # | Coluna | Tipo Firebird | Largura | Funcao |
|--:|--------|---------------|--------:|--------|
| 1 | `CODIGO` | LONG | 4 | PK auto-increment |
| 2 | **`FORM`** | VARYING | 100 | Nome da classe Delphi da tela (ex `TFrame_ConsuVenda_Venda`) |
| 3 | `DESCRICAO` | VARYING | 255 | Label do grid dentro da tela (ex `GridConsultaFD`) |
| 4 | **`CODUSUARIO`** | LONG | 4 | FK pro usuario que salvou |
| 5 | **`GRID`** | BLOB | 8 | Stream DFM binario com layout do grid (~12-16 KB) |
| 6 | `DT_ALTERACAO` | TIMESTAMP | 8 | Quando foi salvo |
| 7 | `ARQUIVO_INI` | BLOB | 8 | Filtros salvos (alternativa antiga) — sempre vazio nos 5 |
| 8 | `ATIVO` | VARYING | 1 | 'S' / 'N' (so 'S' em uso) |

**Descobertas vs hipotese inicial:**

| Hipotese inicial | Realidade |
|------------------|-----------|
| Tabela tem `NOME_CAMPO`, `LARGURA`, `VISIVEL`, `ORDEM` (1 linha por coluna) | ❌ — tem **`GRID` BLOB unico** (1 linha = 1 grid inteiro, com todas as colunas dentro) |
| Campo `TABELA_REFERENCIA` ou `MODULO` indica tela alvo | ❌ — campo se chama **`FORM`** e guarda classe Delphi (`TFrame_*` / `TConsu*` / `TFrm*`) |
| Configs por coluna soltas | ✅ existem **dentro do BLOB DFM** — extraidas via parsing binario (script #2) |

> **Implicacao critica:** o granular (largura/visivel/ordem por coluna) so eh acessivel parseando o BLOB DFM, que eh o stream serializado de `TcxGridDBTableView` da DevExpress cxGrid. Estrutura registrada na §5 abaixo.

## 3. Top 10 tabelas alvo (formularios mais customizados) por cliente

Quanto mais grids salvos por form, mais o cliente USA aquela tela diariamente — equivale a heatmap de uso.

| Form Delphi (tela) | C_BC9F35 (01) | C_661A7C (02) | C_F8E47B (03) | C_5D8A6C (04) | C_3A1E70 (05) |
|--------------------|--------------:|--------------:|--------------:|--------------:|--------------:|
| **TFrame_Venda_Venda** (Cadastro Venda) | 9 | 45 | 39 | 69 | 45 |
| **TFrame_ConsuVenda_Venda** (Lista Vendas) | 8 | 32 | 24 | 46 | 30 |
| TFrame_Venda_Pedido | 6 | 21 | 9 | — | 39 |
| TFrame_ConsuVenda_Pedido | 6 | 16 | — | — | 26 |
| TFrame_ConsuPessoas_Todos | 6 | 14 | 13 | 24 | 16 |
| TConsuProduto_ProdutoSimples | 6 | 25 | 11 | 22 | 17 |
| TFrame_ConsuProduto_Venda | 4 | 16 | 12 | 19 | 15 |
| TFrame_Venda_Orcamento | — | 45 | — | — | 18 |
| TFrame_Venda_NotaFiscal | — | 18 | — | 21 | 12 |
| TFrame_ConsuProducao_Tarefas | — | 14 | 12 | 20 | — |

**Leitura cross-cliente:**

1. **Lista de Vendas (`TFrame_ConsuVenda_Venda`)** eh a 1ª ou 2ª tela mais customizada em todos os 5 clientes — confirma criticidade de US-SELL-015..028 (escopo PR #534).
2. **Cadastro de Venda (`TFrame_Venda_Venda`)** lidera quase sempre — proximo PR depois da Lista deve ser esse.
3. **Producao Tarefas** so aparece em 3/5 (02, 03, 04) — sinal de uso PCP estruturado (corrobora Q8 PCP no `sells_grade_heatmap.py`).
4. **Orcamento** so eh customizado pesado em 02 (Vargas — 45 grids) — sinal de fluxo orçamento → conversao em venda. Em 03 e 04 nem aparece no top 15 → fluxo direto venda.

## 4. Analise especifica `TFrame_ConsuVenda_Venda` (Lista de Vendas — alvo US-SELL-027)

Cada linha desta secao = N grids salvos pelos usuarios da empresa para essa tela. Parsing do BLOB DFM extrai contagem de colunas (visiveis vs ocultas vs total declarado).

| Cliente | N grids salvos | avg colunas total | avg **visiveis** | avg ocultas | % grids c/ AGRUPAMENTO | % grids c/ SORT |
|---------|--------------:|------------------:|----------------:|------------:|----------------------:|----------------:|
| Cliente_BC9F35 (01 / poucos users) | 8 | 42.0 | **16.5** | 25.5 | 0% | 0% |
| Cliente_661A7C (02 / oficina recapagem) | 32 | 42.2 | **15.8** | 26.4 | 0% | 0% |
| Cliente_F8E47B (03 / grafica industrial) | 24 | 42.1 | **13.0** | 29.2 | **12.5%** | 0% |
| Cliente_5D8A6C (04 / com.visual) | 46 | 42.7 | **16.4** | 26.3 | 0% | 0% |
| Cliente_3A1E70 (05 / cacambas) | 30 | 43.2 | **18.2** | 25.1 | **33.3%** | 0% |

### Implicacoes sinalizadas pra US-SELL-027

1. **Padrao universal: ~42 colunas declaradas, ~13-18 visiveis (avg).** O Delphi oferece um grid riquissimo (42+ colunas) mas cada cliente **filtra agressivamente** mostrando so 30-40% delas. O oimpresso.com novo deve adotar a mesma logica de **default-hide** com toggle persistido.
2. **Cliente_3A1E70 (cacambas) eh o mais "show everything"** com 18.2 colunas visiveis vs media ~15.5 — perfil de operacao que precisa de visao ampla (controle de entrega/locacao de cacambas).
3. **Cliente_F8E47B (Extreme grafica industrial) eh o mais "filtrado"** com so 13.0 visiveis — empresa madura sabe exatamente quais colunas importam pra ela.
4. **Agrupamento (grouping)**: so 03 (12.5%) e 05 (33.3%) usam. Empresas com producao complexa agrupam por situacao/funcionario. Sinaliza US-SELL-019 (agrupamento) **P1 condicional**: clientes 03 e 05 ja USAM, 01/02/04 ainda nao mas tem 42 colunas pra agrupar — feature vale.
5. **Sort persistido = 0%** em todos. Surpresa — usuarios fazem sort ad-hoc na sessao mas nao salvam preferencia. Logico — Delphi cxGrid permite sort por click sem precisar configurar. **Nao priorizar persistencia de sort em US-SELL-015** (low impact).

### Numero de grids salvos = numero de usuarios ativos

| Cliente | Grids `TFrame_ConsuVenda_Venda` | Grids `TFrame_Venda_Venda` | Sinal |
|---------|--------------------------------:|---------------------------:|-------|
| 01 | 8 | 9 | empresa **pequena/teste** (1 user + variantes) |
| 02 | 32 | 45 | media (5-8 users persistiram preferencias) |
| 03 | 24 | 39 | media (similar a 02) |
| 04 | 46 | 69 | **grande** — 8-10 users diferentes |
| 05 | 30 | 45 | media-grande (5-8 users) |

→ Util como **sinal de "company size"** alternativo a contar vendas. Pre-vendas pode usar: cliente com >40 grids = empresa com mais de 5 operadoras = lead qualificado.

## 5. Estrutura interna do BLOB `GRID` (DFM binario DevExpress cxGrid)

Cada BLOB tem ~12-16 KB serializados em formato Delphi DFM stream:

```
06 <len> Frame_ConsuVenda_Venda.GridConsultaDBTableView1     // root component name
06 12 TcxGridDBTableView                                      // class
  02 09 06 09 SourceDPI 02 06 02 60                           // properties do view
  06 06 Footer 02 09 06 05 False
  06 0a GroupByBox 02 09 06 04 True
  06 0c GroupFooters 02 02 06 01 00
  ...

02 3b 06 02 32 36                                             // marker "26" (cxGrid format version)
06 0f TcxGridDBColumn                                          // <-- inicio coluna #1
  02 0c 06 09 SourceDPI 02 06 02 60
  06 11 FilterRowOperator 02 02 06 01 00
  06 0a GroupIndex 02 06 02 ff                                // ff = nao agrupada
  06 14 IsChildInMergedGroup ... False
  06 05 Width 02 06 02 74                                     // largura em px (0x74 = 116)
  06 0d AlignmentHorz 02 02 06 01 00
  06 05 Index 02 06 02 00                                     // posicao 0 (1ª coluna)
  06 07 Visible 02 09 06 04 True                              // VISIVEL
  06 09 SortOrder 02 09 06 06 soNone
  06 09 SortIndex 02 06 02 ff
  06 18 WasVisibleBeforeGrouping 02 09 06 05 False
  ...

06 0f TcxGridDBColumn                                          // <-- coluna #2
  ...
  06 07 Visible 02 09 06 05 False                             // OCULTA
  ...
```

**Markers extraidos pelo `scripts/probe_configuracoes_grid_blob.py`:**

| Marker bytes | Significado | Como conta |
|--------------|-------------|------------|
| `TcxGridDBColumn` | Cada coluna declarada | `data.count(b"TcxGridDBColumn")` |
| `Visible` + `0x02 0x09 0x06 0x04 True` | Visivel | regex exato 12 bytes |
| `Visible` + `0x02 0x09 0x06 0x05 False` | Oculta | regex exato 13 bytes |
| `GroupIndex` + `0x02 0x06 0x02 0xff` | NAO agrupada (default) | total — count(NONE) = agrupadas |
| `SortOrder` + `0x02 0x09 0x06 0x05 soAsc` | Ascendente persistido | count direto |
| `SortOrder` + `0x02 0x09 0x06 0x06 soDesc` | Descendente persistido | count direto |

**O que NAO esta no BLOB (descoberta):**

- ❌ Nome do `FieldName` da coluna inline (so 1 ocorrencia de "FieldName" em todo blob, na sumarizacao Total no rodape). cxGrid amarra coluna ao DataController por **indice posicional**, nao por nome → migrar requer ler a definicao da tela Delphi tambem (script `officeimpresso-source-analysis` ja faz).
- ❌ Caption customizado por user (vem da unidade Delphi compilada).

→ **Implicacao pra US-SELL-027:** o BLOB sozinho diz "essa tela tem 42 cols mas user ve 16" — o **mapping cols→FieldName** vem do controller Delphi (`Controller.Venda.pas` ja lido — 30+ colunas mapeadas em `TELA-LISTA-VENDAS.md` §10). Combinar os dois = saber **exatamente** quais colunas o cliente mostra.

## 6. Implicacao pra US-SELL-027 (Schema Discovery — PR #534)

US-SELL-027 ([memory/requisitos/Sells/SPEC.md](../../../requisitos/Sells/SPEC.md)) prevê popular `business.legacy_origin_features` no oimpresso.com novo via discovery do banco legacy. Esta tabela fornece o **sinal mais direto possivel**:

### Pipeline proposto

```
1. import-job le CONFIGURACOES_GRID do banco Firebird do cliente (read-only via ODBC bridge)
2. filtra WHERE FORM = 'TFrame_ConsuVenda_Venda' AND ATIVO = 'S'
3. agrega por usuario:
   - quantos columns visible (avg)
   - quantos hidden (avg)
   - tem agrupamento? (count > 0)
   - tem sort persistido? (count > 0)
4. cruza com mapping Controller.Venda.pas (`TELA-LISTA-VENDAS.md` §10 — 30 colunas mapeadas)
5. popula business.legacy_origin_features:
   {
     "lista_vendas": {
       "default_columns_visible": ["codigo", "razao_social", "dt_emissao", "total", ...],  // top N visiveis cross-users
       "default_columns_hidden":  ["pedido_compra", "chassi2", ...],
       "uses_grouping": true|false,
       "n_users_with_custom_grid": 8,
       "company_size_signal": "media|grande"
     }
   }
6. oimpresso.com novo, ao logar primeiro user da business migrada, le legacy_origin_features
   e applica defaults da Grade Avancada (US-SELL-015 toggle Lista/Grade)
   → user ve o grid "como ja estava acostumado" — zero re-configuracao
```

### Sinais quantitativos prontos pra usar

| Sinal | Como extrair | Onde popular no oimpresso.com |
|-------|--------------|-------------------------------|
| **Numero de users ativos** | `COUNT(DISTINCT CODUSUARIO)` em CONFIGURACOES_GRID | `business.legacy_active_users_count` |
| **Frequencia de uso da tela X** | `COUNT(*) WHERE FORM=X` (proxy: + grids salvos = + uso) | ranking de telas a migrar |
| **Avg colunas visiveis na Lista** | parse BLOB | default de quais colunas mostrar na Grade Avancada |
| **Usa agrupamento?** | parse BLOB `GroupIndex != ff` | habilita botao "Agrupar por" P1 |
| **Usa orcamento (TFrame_Venda_Orcamento)?** | total > 0 | habilita feature Orcamento (modulo opcional) |

### Casos de uso vendaveis

1. **Pre-vendas: "company size signal"** — Wagner ver "Cliente_X tem 46 grids = empresa media com 8 users diferentes" ANTES de marcar demo. Refina lead score.
2. **Onboarding zero-config** — Migrar cliente OfficeImpresso pro oimpresso.com novo e ele ja ver a Lista de Vendas configurada exatamente como estava acostumado.
3. **Diferenca de uso real vs vendido** — `TFrame_ConsuProducao_Tarefas` so aparece em 3/5 = PCP estruturado NAO eh universal entre OfficeImpresso. Decidir se vale construir feature PCP completa no oimpresso.com (vs aproveitar apenas em clientes que ja usam).

## 7. Scripts canonicos

| Script | O que faz |
|--------|-----------|
| [`scripts/probe_configuracoes_grid.py`](../../../../scripts/probe_configuracoes_grid.py) | Schema da tabela + top tabelas alvo + sample por cliente |
| [`scripts/probe_configuracoes_grid_blob.py`](../../../../scripts/probe_configuracoes_grid_blob.py) | Parsing binario do BLOB GRID DFM — extrai n_columns/visible/hidden/grouped |

Ambos READ-ONLY ESTRITO (so SELECT). Output JSON em `_MAPPING/raw-configuracoes-grid/` (gitignored — LGPD).

Rodar:
```powershell
cd D:\oimpresso.com
python scripts/probe_configuracoes_grid.py
python scripts/probe_configuracoes_grid_blob.py
```

## 8. Refs

- [TELA-LISTA-VENDAS.md](TELA-LISTA-VENDAS.md) — mapping canonico Delphi → Laravel (30 colunas)
- [memory/requisitos/Sells/SPEC.md](../../../requisitos/Sells/SPEC.md) — US-SELL-027 schema discovery
- [memory/decisions/0136-sells-grade-avancada-modo-toggle.md](../../../decisions/0136-sells-grade-avancada-modo-toggle.md) — toggle Lista/Grade
- PR #534 (merged) — Grade Avancada Sells
- [_LGPD.md](../_LGPD.md) — protocolo anonimizacao
- Fonte Delphi: `D:/Programas/WR Comercial/app/Controller/Controller.Configuracoes_Grid.pas`

## 9. Proximos passos

1. **Validar com cliente piloto:** rodar parser BLOB num grid `TFrame_ConsuVenda_Venda` de Cliente_5D8A6C (04 / com.visual — 46 grids) e cruzar com lista de colunas que o usuario realmente ve no Delphi (screenshot).
2. **Construir job migrator:** Modules/Officeimpresso (ja existe modulo restaurado) pode receber comando `php artisan officeimpresso:import-legacy-grids {firebird_dsn} {business_id}` que faz o pipeline §6.
3. **Documentar contrato `business.legacy_origin_features`** em SPEC.md US-SELL-027 — schema JSON canonico.
4. **Ler `ARQUIVO_INI` BLOB (sempre vazio nos 5)** — possivel feature antiga removida; nao priorizar.

---

**Ultima atualizacao:** 2026-05-11 — primeira passada nos 5 bancos. Schema confirmado universal. Parsing BLOB validado (avg 42 cols / 16 visiveis). Pronto pra integrar pipeline US-SELL-027.
