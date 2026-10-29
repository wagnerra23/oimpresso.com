---
slug: 06-sabor-brasil
hash_id: Cliente_E360F9
status: em-migracao
date_first_analysis: 2026-10-29
date_last_update: 2026-10-29
controlador: Wagner
vertical_real: industria-temperos-atacado-supermercado
size: pequeno
tipo_relacao: cliente-pagante
banco_firebird: D:\DadosClientes\Sabor Brasil\Dados\BANCO.FDB
banco_alias_firebird: servidor-crm:D:\DadosClientes\Sabor Brasil\Dados\BANCO.FDB
alias_hkcu: Camargo
business_id_oimpresso: 199
---

# Perfil — `Cliente_E360F9` (Sabor Brasil · temperos para supermercado)

> **LGPD:** versão commitável. Sem CNPJ, sem contato, sem valores em R$ ([proibicoes.md](../../../proibicoes.md) — só contagens e estrutura). Ver [_LGPD.md](../_LGPD.md).
>
> **Fonte de cada número:** leitura **somente-leitura** do Firebird pelo apelido `Camargo` (`scripts/legacy-migration/lib/firebird_reader.py`, só `SELECT`), em 2026-10-29. Fato datado — o banco segue vivo, re-meça antes de citar.
>
> **Correção de canon:** a [matriz de clientes legacy](../../../reference/matriz-conhecimento-clientes-legacy.md) chuta *"restaurante (Sabor Brasil)?"*. É **venda de tempero para supermercado** ([W] 2026-10-29).

## 1. Identificação

| Campo | Valor |
|---|---|
| Hash ID | `Cliente_E360F9` (`sha1(razão social)[:6]`, regra do [README](../README.md)) |
| Apelido no Delphi (HKCU) | `Camargo` — o nome do registry não bate com a pasta |
| Banco | Firebird 3 no `servidor-crm`, **`VERSAO_BANCO = 1514`**, 399 tabelas |
| Empresa no oimpresso | `business_id = 199`, criada em 2026-03-23 — **vazia**: 1 loja, 1 usuário, 1 cliente, 0 produto, 0 venda (medido em produção, só leitura, 2026-10-29) |
| Uso | 4 usuários no Delphi; última venda em 2026-09-17 → cliente **ativo** |

⚠️ **Versão 1514 está fora do intervalo conhecido do adapter** (1404 → 1474, agente [`migracao-firebird-versoes`](../../../../.claude/agents/migracao-firebird-versoes.md)). O próprio agente manda escalar `VERSAO_BANCO ≥ 1500`: o schema pode ter colunas que nenhum importer conhece. É o primeiro bloqueio técnico (Etapa 0 do plano).

## 2. Tipo de negócio real

Indústria/atacado de temperos que vende **NF-e modelo 55 para supermercado** (pessoa jurídica, quase tudo em SP), com **representantes comissionados**, **tabela de preço por cliente** e **boleto com desconto por pontualidade**. Não é varejo de balcão: não há NFC-e, e o mix é pequeno (141 produtos).

## 3. O que ele usa (medido)

| Área | Prova no banco | O que significa |
|---|---|---|
| **Vendas** | `VENDA` 4.089 no total · 550 desde 2024 (≈ 200/ano) · tipos: venda, venda sem ST, venda simples, bonificação, orçamento, devolução, troca | Volume baixo, operação B2B. Tela mais usada nos últimos 12 meses: `TFrmVenda_Base` (6.854 eventos no log `HISTORICO`) |
| **Duas empresas, dois regimes** | Desde 2024: empresa 4 (**Regime Normal**) 372 vendas · empresa 5 (**Simples Nacional**) 174 vendas | Emite por **dois CNPJs com regimes diferentes**. CST na empresa normal (10/20/60), CSOSN na do Simples (500/400/101) |
| **Tributação** | 22 regras em `REGRA_TRIBUTARIA` (por regime, UF destino, CFOP, CST/CSOSN) · itens desde 2024: CST 10/CFOP 5405 (1.754), CSOSN 500/5405 (1.771), CST 20/5102 (775) · ICMS-ST calculado em 215 itens com **MVA 62,3% e alíquota 18%** · CFOP 5910 (bonificação) | ICMS-ST com MVA, redução de base (CST 20), ST já retido (500/60). Sem IPI, sem DIFAL/FCP (cliente é quase todo intraestadual) |
| **Tabela de preço por cliente** | 74 tabelas ativas · 707 preços (706 com valor fixo, 453 com % desconto, 213 com % acréscimo) · **192 clientes** ligados a **61 tabelas** | Cada rede de supermercado tem a sua tabela |
| **Comissão** | 1.643 lançamentos em `COMISSAO_FINANCEIRO` (por **parcela**: aberta / recebida / vencida) · % por cliente em `PESSOAS.COMISSAO_REPRESENTANTE` (2% a 15%, maioria 10%) · 3 representantes ativos desde 2024 · fechamento em `COMISSAO` (último em 2022-09) | Comissão é **por cliente** e paga **conforme o cliente paga a parcela** |
| **Boleto** | 1.650 boletos · banco por ano: Caixa (104) 2017–18, Santander (033) 2019–23, **Inter (077) desde 2023**, **Banco do Brasil (001) em 2026** · desconto em 606 boletos · % padrão por cliente (3%, 5%, 7% — 52 clientes) · juros e multa por boleto | Hoje imprime boleto **Inter e BB**. Desconto vale **até o vencimento** (`DIADESCONTO = 0`) |
| **Financeiro** | `FINANCEIRO` 3.226 · 12 meses: 188 a receber por boleto, 18 recebidas · a pagar pouco usado (121 abertas no total) · nada conciliado · plano de contas presente (`CODPLANOCONTAS`) | Uso concentrado em **contas a receber por boleto**. A diferença 188 × 18 precisa de explicação (baixa fora do sistema?) — pergunta para a Maiara |
| **Importação fiscal** | `NF_ENTRADA_MANIFESTO` 453 notas (2019–2023), com ciência e confirmação · 20 XMLs guardados (2023) · `NF_ENTRADA` só 23 | Usa **manifesto do destinatário** (baixa as NF-e emitidas contra ele na SEFAZ). Lança pouca entrada no sistema. Uso parou em 2023 — confirmar se ainda importa |
| **Duplicar venda** | 106 edições com "duplic" no log de `TFrmVenda_Base`, a mais recente em 2026-03-06 | Usa a cópia de venda (recompra do mesmo pedido) |
| **Cadastro** | `PESSOAS` 434 (265 PJ, 163 tipo "O", 6 PF) · `PRODUTO` 141, sem grade | Base pequena, migração rápida |

## 4. Sinal qualificado ([ADR 0105](../../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))

- [x] Pedido do [W]: *"vamos colocar em prática a migração do cliente Ricardo (Sabor Brasil)"* (2026-10-29) — pedido do dono é decisão ([ADR 0382](../../../decisions/0382-remove-trava-de-sinal-para-trabalho-dirigido-por-w.md))
- [x] Empresa 199 já criada em produção (2026-03-23)

## 5. Decisor

- **Ricardo** — dono/operador. Contato fora do git.
- Levantamento dos casos de uso: **Maiara [M]**.

## 6. Plano

Etapas do início à conclusão em [02-plano-migracao.md](02-plano-migracao.md).

## 7. Medições de 2026-10-29 (lote F — perguntas abertas 2 e 3)

Leitura somente-leitura (`SELECT`) no apelido `Camargo`, `VERSAO_BANCO = 1514`, via `firebird_reader.firebird_connect`. Só contagens e datas. "12 meses" = `VENCTO` entre 2025-10-29 e 2026-10-29, `DT_EXCLUSAO IS NULL`. Fato datado: o banco segue vivo, re-meça antes de citar.

### 7.1 Manifesto de NF-e de entrada ainda é usado? (pergunta aberta 3)

**Resposta: não.** O manifesto parou em **2023-06-15**. Nenhuma NF-e de entrada foi manifestada ou importada por XML depois disso, e a tela não aparece no log de 12 meses.

Três tabelas, três momentos do fluxo. Por ano de emissão da nota:

| Ano | Baixadas da SEFAZ (`NOTA_FISCAL_ENTRADA`) | No manifesto (`NF_ENTRADA_MANIFESTO`) | Com ciência | Com confirmação | Lançadas como entrada, por XML (`NF_ENTRADA`, por `DT_NOTA`) |
|---|---|---|---|---|---|
| 2016 | 0 | 0 | 0 | 0 | 1 |
| 2017 | 0 | 0 | 0 | 0 | 4 |
| 2018 | 0 | 0 | 0 | 0 | 2 |
| 2019 | 15 | 14 | 2 | 1 | 0 |
| 2020 | 474 | 273 | 233 | 211 | 11 |
| 2021 | 448 | 146 | 106 | 86 | 0 |
| 2022 | 0 | 0 | 0 | 0 | 1 |
| 2023 | 20 | 20 | 0 | 0 | 4 |
| 2024 a 2026 | 0 | 0 | 0 | 0 | 0 |

Última de cada tipo:

| O quê | Data |
|---|---|
| Última nota baixada da SEFAZ (emissão) | 2023-05-31 |
| Último recebimento (`DT_RECEBIMENTO` / `NF_DT_RECEBIMENTO`) | 2023-06-15 |
| Última nota lançada como entrada por XML (`NF_ENTRADA.DT_NOTA`) | 2023-06-22 |
| Última ciência (a tabela não tem data de ciência; uso o último ano com `TEM_CIENCIA = 'S'`) | 2021 (emissão até 2021-07-13) |
| Última linha de log de tela de manifesto (`HISTORICO`) | 2023-06-15 |
| Telas de manifesto no log desde 2025-10-29 | 0 |

Outras leituras:

- As 23 notas de `NF_ENTRADA` têm `IMPORTACAO_XML = 'S'` (todas por XML). Nenhuma aponta para o manifesto (`CODNF_ENTRADA_MANIFESTO`) nem para `NOTA_FISCAL_ENTRADA` (`CODNOTA_FISCAL_ENTRADA`): a importação por XML é um caminho **separado** do manifesto.
- `TEM_COMPRA = 'S'` em **0** das 453 do manifesto e em **0** das 957 de `NOTA_FISCAL_ENTRADA`: nenhuma nota baixada virou compra pelo manifesto.
- Das 453 do manifesto: 269 com ciência e confirmação, 72 só ciência, 29 só confirmação, 83 sem nenhuma. Ciência e confirmação são de 2019 a 2021; as 20 de 2023 chegaram sem ciência.
- `NSU` máximo 1.732 nas duas tabelas (o último NSU baixado da SEFAZ).

**Para o plano:** a tela `ManifestacaoController` e o `PuxarDfesRecebidosCommand` não são caminho crítico da virada para este cliente. O que ele usou até 2023 foi lançar entrada por XML, e isso é a `ImportarXmlEntradaService`. Se o Ricardo pedir o manifesto de volta, é pedido novo, não paridade.

Consultas (cada uma devolveu as linhas das tabelas acima):

```sql
-- notas lançadas por XML, por ano
SELECT EXTRACT(YEAR FROM DT_NOTA), COUNT(*), MAX(DT_NOTA) FROM NF_ENTRADA GROUP BY 1;
SELECT IMPORTACAO_XML, COUNT(*) FROM NF_ENTRADA GROUP BY 1;
-- manifesto, por ano de emissão
SELECT EXTRACT(YEAR FROM NF_DT_EMISSAO), COUNT(*),
       SUM(CASE WHEN TEM_CIENCIA='S' THEN 1 ELSE 0 END),
       SUM(CASE WHEN TEM_CONFIRMADA='S' THEN 1 ELSE 0 END),
       SUM(CASE WHEN TEM_COMPRA='S' THEN 1 ELSE 0 END),
       MAX(NF_DT_EMISSAO), MAX(NF_DT_RECEBIMENTO)
FROM NF_ENTRADA_MANIFESTO GROUP BY 1;
SELECT TEM_CIENCIA, TEM_CONFIRMADA, TEM_COMPRA, COUNT(*) FROM NF_ENTRADA_MANIFESTO GROUP BY 1,2,3;
-- baixadas da SEFAZ, por ano de emissão
SELECT EXTRACT(YEAR FROM NF_DT_EMISSAO), COUNT(*), MAX(NF_DT_EMISSAO), MAX(DT_RECEBIMENTO)
FROM NOTA_FISCAL_ENTRADA GROUP BY 1;
-- log de tela
SELECT MAX(DATA) FROM HISTORICO WHERE UPPER(TELA) LIKE '%MANIFEST%' OR UPPER(FORMULARIO) LIKE '%MANIFEST%';
SELECT COUNT(*) FROM HISTORICO WHERE DATA>=DATE '2025-10-29'
  AND (UPPER(TELA) LIKE '%MANIFEST%' OR UPPER(FORMULARIO) LIKE '%MANIFEST%');
```

### 7.2 Por que 188 boletos a receber e só 18 baixados? (pergunta aberta 2)

**Resposta: a premissa não se confirma, e o que sobra é baixa manual que parou.**

1. **"188 por boleto" não bate.** Em 12 meses há **174 títulos a receber ativos** (mais 19 cancelados e 1 excluído, 194 a receber no total) e **18 recebidos**. O 18 reproduz; o 188 não (não achei filtro que dê 188). Dos 174 ativos, só **33 têm nosso número** e **38 têm linha em `BOLETOS`**. Os outros cerca de 136 são título **sem boleto**: a maior parte do contas a receber não é boleto.
2. **Baixa por retorno bancário: não existe.** Nenhuma linha de `BOLETOS` tem `RETORNO` (0 de 1.650) e nenhuma de `FINANCEIRO_BOLETO_HISTORICO` (0 de 3.714). O crédito do banco (`DT_CREDITO`) vai de 2017 a **2023-02-22** e acaba aí; desde 2025-10-29 não entrou nenhuma linha. O último uso da tela de retorno no log é de **2023-03-01**. As colunas novas de cobrança em `FINANCEIRO` (`COBRANCA_STATUS`, `BAIXA_ORIGEM`, `RETORNO`) estão vazias nas 18 recebidas: ninguém baixou por integração.
3. **Baixa manual, e ela parou.** As 18 recebidas têm `DATAPAGTO`, a mais nova em **2026-02-09**. Nada foi baixado desde então, embora títulos tenham sido emitidos até **2026-08-13**. Por ano de vencimento, as abertas somam 46 (2019), 47 (2020), 10 (2021), 39 (2022), 74 (2023), 65 (2024), 110 (2025) e 150 (2026), e em 2026 só 3 foram baixadas.
4. **Não há segunda fonte de "pagou".** `CONCILIACAO_BANCARIA` e `CONCILIACAO_BANCARIA_FINANCEIRO` têm 0 linhas; `CONCILIADO = 'S'` em 0 títulos do período; `CAIXA` tem 19 linhas, a última aberta em **2018-08-21**.
5. **Banco dos 38 boletos em aberto:** 31 Inter (077) e 7 Banco do Brasil (001).

**Leitura para o "cleanup-first" da fase 7 de dados:** o Delphi **não tem prova de pagamento fora da coluna `DATAPAGTO`**. Os 174 ativos são "aberto no sistema", não "não pago pelo cliente": desde fevereiro de 2026 ninguém baixa, então parte deles provavelmente já foi paga no banco (o extrato do Inter e do BB tem a resposta, o Delphi não). Importar essa pilha como contas a receber vivas pode gerar cobrança de título já pago. Duas saídas, a escolher com o Ricardo e a [M]: (a) pegar o extrato do Inter e do BB do período, cruzar pelo nosso número e baixar antes de migrar; (b) migrar só as recebidas e mandar as abertas para um estado "a conferir". A (a) depende de o cliente entregar o extrato.

Consultas:

```sql
-- janela de 12 meses por tipo/status e pagamento
SELECT TIPO, STATUS, COUNT(*), SUM(CASE WHEN DATAPAGTO IS NOT NULL THEN 1 ELSE 0 END),
       SUM(CASE WHEN CONCILIADO='S' THEN 1 ELSE 0 END)
FROM FINANCEIRO WHERE VENCTO>=DATE '2025-10-29' AND VENCTO<=DATE '2026-10-29' AND DT_EXCLUSAO IS NULL GROUP BY 1,2;
-- das 174 ativas: com boleto
SELECT COUNT(*), SUM(CASE WHEN BOLETO_NOSSO_NR IS NOT NULL AND BOLETO_NOSSO_NR<>'' THEN 1 ELSE 0 END),
       SUM(CASE WHEN EXISTS(SELECT 1 FROM BOLETOS B WHERE B.CODFINANCEIRO=F.CODIGO) THEN 1 ELSE 0 END)
FROM FINANCEIRO F WHERE VENCTO>=DATE '2025-10-29' AND VENCTO<=DATE '2026-10-29'
  AND DT_EXCLUSAO IS NULL AND TIPO='A RECEBER' AND STATUS='ATIVO';
-- retorno bancário
SELECT COUNT(*), SUM(CASE WHEN RETORNO IS NOT NULL THEN 1 ELSE 0 END) FROM BOLETOS;
SELECT EXTRACT(YEAR FROM DT_OCORRENCIA), COUNT(*), SUM(CASE WHEN RETORNO IS NOT NULL THEN 1 ELSE 0 END),
       SUM(CASE WHEN DT_CREDITO IS NOT NULL THEN 1 ELSE 0 END), MAX(DT_OCORRENCIA)
FROM FINANCEIRO_BOLETO_HISTORICO GROUP BY 1;
SELECT COUNT(*) FROM FINANCEIRO_BOLETO_HISTORICO WHERE DT_OCORRENCIA>=DATE '2025-10-29';
-- origem da baixa nas 18 recebidas
SELECT BAIXA_ORIGEM, COBRANCA_STATUS, RETORNO, COUNT(*), MIN(DATAPAGTO), MAX(DATAPAGTO)
FROM FINANCEIRO WHERE VENCTO>=DATE '2025-10-29' AND VENCTO<=DATE '2026-10-29'
  AND DT_EXCLUSAO IS NULL AND TIPO='RECEBIDA' GROUP BY 1,2,3;
-- evolução por ano de vencimento
SELECT EXTRACT(YEAR FROM VENCTO), COUNT(*),
       SUM(CASE WHEN TIPO='RECEBIDA' THEN 1 ELSE 0 END),
       SUM(CASE WHEN TIPO='A RECEBER' AND STATUS='ATIVO' THEN 1 ELSE 0 END), MAX(DATAPAGTO)
FROM FINANCEIRO WHERE DT_EXCLUSAO IS NULL AND TIPO IN ('RECEBIDA','A RECEBER')
  AND VENCTO>=DATE '2019-01-01' GROUP BY 1;
-- outras fontes de pagamento
SELECT (SELECT COUNT(*) FROM CONCILIACAO_BANCARIA), (SELECT COUNT(*) FROM CONCILIACAO_BANCARIA_FINANCEIRO),
       (SELECT COUNT(*) FROM CAIXA), (SELECT MAX(DATA_ABERTURA) FROM CAIXA) FROM RDB$DATABASE;
-- banco dos boletos ainda em aberto
SELECT CODBANCO, COUNT(*) FROM BOLETOS WHERE CODFINANCEIRO IN (SELECT CODIGO FROM FINANCEIRO
  WHERE VENCTO>=DATE '2025-10-29' AND TIPO='A RECEBER' AND STATUS='ATIVO' AND DT_EXCLUSAO IS NULL) GROUP BY 1;
-- uso da tela de retorno
SELECT MAX(DATA) FROM HISTORICO WHERE UPPER(TELA) LIKE '%RETORNO%' OR UPPER(FORMULARIO) LIKE '%RETORNO%';
```

**Limites da medição.** O 188 do levantamento não foi reproduzido. A data de ciência do manifesto não existe como coluna (usei o ano). "Baixa fora do sistema" é inferência pelas fontes vazias, não prova: só o extrato bancário confirma. A tela de retorno e a de manifesto foram achadas por nome em `HISTORICO.TELA`/`FORMULARIO` (`LIKE`); tela com outro nome não entra.
