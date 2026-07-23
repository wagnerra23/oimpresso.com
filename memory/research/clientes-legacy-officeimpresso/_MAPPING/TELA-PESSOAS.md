---
id: research-clientes-legacy-officeimpresso-mapping-tela-pessoas
title: Mapping canônico — Tela "Pessoas / Contatos" Delphi → oimpresso.com
status: live
date: 2026-05-11
audience: dev migrando OfficeImpresso → oimpresso.com novo
source_files:
  - "D:/Programas/WR Comercial/app/Controller/Controller.Pessoas.pas (670 LOC)"
  - "D:/Programas/WR Comercial/app/Controller/Controller.Pessoas.Definicoes.pas (61 LOC — auto-gerado)"
  - "D:/Programas/WR Comercial/app/Controller/Controller.Pessoas.Todos.pas (95 LOC)"
  - "D:/Programas/WR Comercial/app/Controller/Controller.Pessoas.Cliente.pas (126 LOC)"
  - "D:/Programas/WR Comercial/app/Controller/Controller.Pessoas.Fornecedor.pas (246 LOC)"
  - "D:/Programas/WR Comercial/app/Controller/Controller.Pessoas.Funcionario.pas (104 LOC)"
  - "D:/Programas/WR Comercial/app/Controller/Controller.Pessoas.Representante.pas (108 LOC)"
  - "D:/Programas/WR Comercial/app/Controller/Controller.Pessoas.Agencia.pas (108 LOC)"
  - "D:/Programas/WR Comercial/app/Controller/Controller.Pessoas.OImpresso.pas (150 LOC)"
method: source-first (skill officeimpresso-source-analysis)
---

# Mapping canônico — Tela "Pessoas / Contatos" Delphi

> **Fonte autoritativa:** Controllers Delphi reais. Substitui inferências por evidência.
> **Caption raiz:** `'Contatos'` (TControllerPessoas) — subclasses redefinem.

## 1. Cadeia de herança

```
TObject
  └─ TControllerMestre               (Controller.Mestre.pas)
      └─ TControllerPessoas          (Controller.Pessoas.pas, 670 LOC) — SQL base + JOINs + agrupadores + filtros básicos
          ├─ TControllerPessoasTodos        (Pessoas.Todos.pas, TagController=0, Caption='Todos Contatos')
          ├─ TControllerPessoasCliente      (Pessoas.Cliente.pas, TagController=1, filtro P.IS_CLI='S')
          ├─ TControllerFornecedor          (Pessoas.Fornecedor.pas, TagController=2, filtro P.IS_FOR='S')
          ├─ TControllerPessoasFuncionario  (Pessoas.Funcionario.pas, TagController=3, filtro P.IS_FUN='S')
          ├─ TControllerPessoasRepresentante (Pessoas.Representante.pas, filtro P.IS_REP='S')
          ├─ TControllerPessoasAgencia       (Pessoas.Agencia.pas, filtro P.IS_AGE='S')
          └─ TControllerPessoasOImpresso     (Pessoas.OImpresso.pas)
```

`Tabela := 'PESSOAS'` em todas subclasses — **uma tabela única, multi-tipo** via flags `IS_<TIPO>='S'`.
Paths: `PathPESSOAS_TODOS`, `PathPESSOAS_CLIENTE`, `PathPESSOAS_FUNCIONARIO`, `PathPESSOAS_FORNECEDOR`, `PathPESSOAS_REPRESENTANTE`, `PathPESSOAS_AGENCIA`, `PathPESSOAS_OIMPRESSO` (constantes `wrConstantes.pas`).

## 2. SQL base (DEFINITIVO — extraído do `TControllerPessoas.Create`)

```sql
SELECT
  --Linha dos Fields dos tipos (gerada dinamicamente em FormCreateConsulta)
  P.IS_<TIPO1>, P.SEQUENCIA_<TIPO1>,
  P.IS_<TIPO2>, P.SEQUENCIA_<TIPO2>,
  ...   -- um par por TIPO ativo na tabela PESSOAS_TIPO

  P.CODIGO, P.TIPO, P.CNPJCPF, P.RAZAOSOCIAL, P.FANTASIA, P.ENDERECO, P.UF,
  P.TIPO_PADRAO, P.BLOQUEADO,
  P.FONE1, P.EMAIL, P.DATACADASTRO, P.LIMITE_DESCONTO, P.ATIVO, P.SITUACAO,
  P.PRIORIDADE_PRODUCAO, P.DATANASCIMENTO, P.ANIVERSARIO,
  ASS.RAZAOSOCIAL as ASSOCIADO_RAZAO, ASS.FANTASIA as ASSOCIADO_FANTASIA,
  C.DESCRICAO as CIDADE,
  REP.RAZAOSOCIAL as REPRESENTANTE_RAZAO, REP.FANTASIA as REPRESENTANTE_FANTASIA,
  PT.DESCRICAO as PESSOA_TABELA_PRECO
FROM PESSOAS P
LEFT JOIN PESSOAS ASS ON (P.PESSOA_ASSOCIADO_CODIGO = ASS.CODIGO)
LEFT JOIN PESSOAS REP ON (P.PESSOA_REPRESENTANTE_CODIGO = REP.CODIGO)
LEFT JOIN CIDADES C   ON (C.CODIGO = P.CODCIDADE)
LEFT JOIN PRODUTO_TABELA PT ON (PT.CODIGO = P.CODPRODUTO_TABELA)
```

**Total: ~24 colunas fixas + N pares dinâmicos `IS_TIPO`/`SEQUENCIA_TIPO`.** Todos LEFT JOINs.

🎯 **Descoberta crítica multi-tipo:** o `FormCreateConsulta` itera `PessoaListaTipos` e gera os campos `P.IS_<CODIGO>, P.SEQUENCIA_<CODIGO>` dinâmicamente. Linhas relevantes:

```pascal
// Controller.Pessoas.pas:263-269
for I := 0 to ALista.Count - 1 do
begin
  ASQL.Add('P.IS_' + ALista[i].Codigo + ',');
  ASQL.Add('P.SEQUENCIA_' + ALista[i].Codigo + ',');
end;
SQLInit[0] := 'Select '+ASQL.Text;
```

Cada tipo adicionado na tabela `PESSOAS_TIPO` ganha 2 colunas em `PESSOAS`: `IS_<X>` (S/N) e `SEQUENCIA_<X>` (int auto). Os tipos canônicos vistos no código: **CLI** (cliente), **FOR** (fornecedor), **FUN** (funcionário), **REP** (representante), **AGE** (agência), **OIM** (oimpresso/transportador).

## 3. Filtros por subclasse (`ConsultaGetFiltros`)

Cada subclasse adiciona um WHERE específico via `SQLWhere.AddAnd(...)`:

| Subclasse | Path | WHERE adicional | Modulo |
|-----------|------|-----------------|--------|
| Todos | `/pessoas/todos` | (nenhum) | `MODULO_CONTATOS` |
| Cliente | `/pessoas/cliente` | `P.IS_CLI = 'S'` | `MODULO_CONTATOS` |
| Fornecedor | `/pessoas/fornecedor` | `P.IS_FOR = 'S'` | `MODULO_COMPRAS` |
| Funcionário | `/pessoas/funcionario` | `P.IS_FUN = 'S'` | `MODULO_RH` |
| Representante | `/pessoas/representante` | `P.IS_REP = 'S'` | (não declarado) |
| Agencia | `/pessoas/agencia` | `P.IS_AGE = 'S'` | (não declarado) |

Filtros "Retirar filtros" / "Arquivados" vêm do `TControllerMestre` base — equivalentes a `ATIVO='S'` / `Not(ATIVO='S')`.

## 4. Agrupadores padrão (8 — `InitializeAgrupadores` linhas 472-486)

| Botão UI | Campo SQL | Tipo |
|----------|-----------|------|
| **Tabela de Preço** | `PESSOA_TABELA_PRECO` (via JOIN PT) | `kmEditavelSimples` |
| **Aniversário** | `ANIVERSARIO` | `kmEditavelSimples` |
| **Cidade** | `CIDADE` (via JOIN C) | `kmEditavelSimples` |
| **Estado** | `UF` | `kmEditavelSimples` |
| **Situação** | `SITUACAO` | `kmEditavelSimples` |
| **Prioridade** | `PRIORIDADE_PRODUCAO` (`TdxRatingControlProperties`) | `kmEditavelSimples` |
| **Bloqueado** | `BLOQUEADO` | `kmEditavelSimples` |
| **Limite de desconto** | `LIMITE_DESCONTO` | `kmEditavelSimples` |

## 5. Datas filtráveis (2 — `InitializeDatasNaConsulta` linhas 110-115)

| Label UI | Campo SQL |
|----------|-----------|
| **Data Cadastro** | `DATACADASTRO` |
| **Data Nascimento** | `DATANASCIMENTO` |

> Comentário do source: "Isso deveria estar apenas na compra" — desenvolvedor reconhece que `DATANASCIMENTO` aplicado a fornecedor PJ não faz sentido. Migração Laravel pode separar por subtipo.

## 6. Geração de chave primária (`GeraChavePrimaria`)

```pascal
ACadastro.FieldByname('CODIGO').Value :=
  Trunc(GetProximoCodigoGen('CR_' + Tabela + EmpresaAtiva)).ToString + '-'+ EmpresaAtiva;
```

Padrão: `<sequencial>-<codEmpresa>`. Multi-empresa via sufixo (mesmo padrão de Venda). Sequência via Firebird generator `CR_PESSOAS<EmpresaAtiva>`.

`SEQUENCIA_<TIPO>` (numérico simples) é setado em `BuscaEImportaFornecedor` linha 232: `max(SEQUENCIA_FOR) + 1`. Sequência interna por tipo, **independente** do CODIGO.

## 7. Valores padrão (`Controller.Pessoas.Definicoes.pas`)

```pascal
.AdicionarValorPadrao('ATIVO', 'S')
.AdicionarValorPadrao('TIPO_CONTRIBUINTE', '9')        // 9 = Não contribuinte
.AdicionarValorPadrao('DATACADASTRO', '@SERVIDOR')      // timestamp servidor
.AdicionarValorPadrao('ETIQUETA', 'S')                  // imprime etiqueta padrão
.AdicionarValorPadrao('CONSUMIDOR_FINAL', 'S')
.AdicionarValorPadrao('ISS_RETIDO', '2')                // 2 = Não retém ISS
.AdicionarValorPadrao('CRT', 'Simples Nacional')        // Código Regime Tributário
.AdicionarValorPadrao('LIMITE_DESCONTO', '0')
```

**Override por subclasse:**

- `TControllerPessoas.Insert` (linha 179-184): se `ComunicacaoVisual=True` então `TIPO='J'` (Jurídica), senão `TIPO='F'` (Física). Vestuário/varejo abre default PF; comunicação visual abre default PJ.
- `TControllerFornecedor.BuscaEImportaFornecedor`: `TIPO='J'`, `IS_FOR='S'`, `EMAIL='Email'` (placeholder), `ATIVO='S'`, `FONE1='0'` se vazio.

## 8. Validações (`Controller.Pessoas.Definicoes.pas` linhas 41-54)

| Campo | Regra | Mensagem |
|-------|-------|----------|
| `RAZAOSOCIAL` | obrigatorio | Informar o Nome ou Razão Social |
| `ENDERECO` | obrigatorio | Informar o Endereço |
| `BAIRRO` | obrigatorio | Informar o Bairro |
| `CODCIDADE` | obrigatorio | Informar a Cidade |
| `UF` | obrigatorio | Informar a UF |
| `FONE1` | obrigatorio | Informar o Telefone |
| `CEP` | obrigatorio | Informar o CEP |
| `NUMERO` | obrigatorio | Informar o Número do Endereço |
| `ATIVO` | valores_permitidos:S,N | Ativo deve ser S ou N |

## 9. SQLTrataNomes — alias map (`SQLTrataNomes` linhas 430-464)

Quando o usuário filtra na grid, o nome da coluna (ex: `RAZAOSOCIAL`) é transformado pra `P.RAZAOSOCIAL`. Idem todas. Mapa relevante pra construir filtros equivalentes em Laravel:

| Alias UI | Coluna SQL |
|----------|------------|
| `REPRESENTANTE_FANTASIA` | `REP.FANTASIA` |
| `REPRESENTANTE_RAZAO` | `REP.RAZAOSOCIAL` |
| `ASSOCIADO_FANTASIA` | `ASS.FANTASIA` |
| `ASSOCIADO_RAZAO` | `ASS.RAZAOSOCIAL` |
| `CIDADE` | `C.DESCRICAO` |
| `LIMITE_DESCONTO`, `PRIORIDADE_PRODUCAO`, `DATACADASTRO`, `RAZAOSOCIAL`, `FANTASIA`, `ENDERECO`, `SITUACAO`, `CNPJCPF`, `BLOQUEADO`, `CODIGO`, `FONE1`, `EMAIL`, `TIPO`, `IS_*`, `UF`, `ATIVO`, `SEQUENCIA_*` | prefixo `P.` |

Hack visível (linha 462): `(TIPOS =` é substituído por `('NIL' <>` pra não quebrar quando UI filtra pela coluna virtual "TIPOS". Eventualmente migrar pra checkbox multi-seleção por flag `IS_*`.

## 10. Configurações específicas (TControllerPessoasCliente.InitializeConfig)

```pascal
ConfigList.Add(TWR_Config.Create('URL_COBRANCA', 'URL Cobrança', 'text'));
ConfigList.Add(TWR_Config.Create('URL_SPC',      'URL SPC',      'text'));
```

→ Laravel: configs por business (`business_settings.cobranca_url`, `business_settings.spc_url`).

## 11. Funções auxiliares (escopo Controller.Pessoas.pas)

| Função | Linha | Propósito |
|--------|-------|-----------|
| `SQLPessoas_Endereco(ACodPessoa)` | 604-615 | Busca endereço + cidade + UF (JOIN CIDADES 2x — endereço cobrança vs correspondência) |
| `SQLPessoas_BuscaNomePorCodigo(...)` | 617-639 | Resolve nome (RAZAOSOCIAL ou FANTASIA por config) — usado em joins exibidos |
| `SQLPessoas_BoletoTeste(ACodigo)` | 641-649 | Subset de campos pra geração de boleto |
| `SQLPessoas_PercDescontoEspecialDoCliente(ACodPessoa)` | 651-666 | `BOLETO_PERC_DESCONTO_PADRAO` por cliente |
| `MigraEmpresaContador(ADataSet)` | 191-241 | Cria fornecedor contador a partir do cadastro de Empresa |
| `BuscaEImportaFornecedor(ANFe)` | 96-177 (Fornecedor) | Importa fornecedor a partir de NFe XML — preenche CNPJCPF, INSCIDENT, RAZAOSOCIAL, etc |
| `BuscaEImportaFornecedorCTe(ACTe)` | 179-240 (Fornecedor) | Idem mas a partir de CTe XML |

## 12. Mapping de campos Delphi → Laravel/oimpresso (`PESSOAS` → `contacts`)

### 12.1 Core (sempre migrar)

| Delphi `PESSOAS.*` | Laravel `contacts.*` | Notas |
|--------------------|-----------------------|-------|
| `CODIGO` (varchar `N-empresa`) | `id` (bigint AI) | preservar em `legacy_id`; sufixo empresa migra pra `business_id` |
| `TIPO` (`F`/`J`) | `tipo_pessoa` enum | F=Física, J=Jurídica |
| `CNPJCPF` | `cnpj` ou `cpf` (split por `TIPO`) | UltimatePOS já tem `tax_number` único |
| `RAZAOSOCIAL` | `name` | obrigatório |
| `FANTASIA` | `fantasy_name` ou `supplier_business_name` | UltimatePOS já tem `supplier_business_name` |
| `ENDERECO` | `address_line_1` | obrigatório |
| `NUMERO` | `address_number` (novo — UltimatePOS não tem) | obrigatório |
| `COMPLEMENTO` | `address_line_2` | nullable |
| `BAIRRO` | `neighborhood` (novo) | obrigatório |
| `CEP` | `zip_code` | obrigatório |
| `CODCIDADE` | `city_id` (FK `cities`) | obrigatório |
| `UF` | `state` | obrigatório (denormalizado — cidade já traz) |
| `FONE1`, `FAX` | `mobile`, `landline`, `fax` | obrigatório FONE1 |
| `EMAIL` | `email` | nullable |
| `DATACADASTRO` | `created_at` | nativo Laravel |
| `DATANASCIMENTO`, `ANIVERSARIO` | `dob`, `birthday_mmdd` | UltimatePOS tem `dob` |
| `ATIVO` (`S`/`N`) | `deleted_at` (soft delete) ou `is_active` bool | semântica dual — preferir bool + scope |
| `SITUACAO` | `situation` (string livre) | "BLACKLIST", "VIP", etc — variado por cliente |
| `BLOQUEADO` | `is_blocked` (bool) | bloqueio cobranças/vendas |
| `PRIORIDADE_PRODUCAO` | `production_priority` (smallint 0-5) | rating control 0-5 estrelas |
| `LIMITE_DESCONTO` | `discount_limit_percent` (decimal) | 0-100 |
| `OBSERVACAO` | `notes` (text) | livre |

### 12.2 Multi-tipo (flags `IS_<TIPO>`)

UltimatePOS tem **`contacts.type` enum** com `customer`, `supplier`, `both` — granularidade insuficiente. Migração:

| Delphi flag | UltimatePOS `contacts.type` | Tabela auxiliar (novo) |
|-------------|------------------------------|------------------------|
| `IS_CLI = 'S'` | `customer` | — |
| `IS_FOR = 'S'` | `supplier` | — |
| `IS_FUN = 'S'` | — (não é customer/supplier) | `contact_roles` (1-N) |
| `IS_REP = 'S'` | — | `contact_roles.role = 'sales_rep'` |
| `IS_AGE = 'S'` | — | `contact_roles.role = 'agency'` |
| `IS_OIM = 'S'` | — | `contact_roles.role = 'carrier'` |
| `IS_CLI + IS_FOR` ambos `'S'` | `both` | — |

→ Recomendado: **manter `contacts.type` UltimatePOS** + tabela nova `contact_roles(contact_id, role, sequence_number, business_id)` pra cobrir multi-papel. Funcionário **vira `users` se for usuário do sistema** ou `employees` (UltimatePOS) se for só folha de pagamento.

### 12.3 Sequências por tipo

| Delphi | Laravel |
|--------|---------|
| `SEQUENCIA_CLI`, `SEQUENCIA_FOR`, `SEQUENCIA_FUN`, etc | `contact_roles.sequence_number` (auto-incremento por business+role) |
| `TIPO_PADRAO` (qual papel principal exibir) | `contacts.primary_role` (string) |

### 12.4 Relacionamentos

| Delphi | Laravel |
|--------|---------|
| `PESSOA_ASSOCIADO_CODIGO` (FK pra PESSOAS) | `parent_contact_id` ou `associated_contact_id` | usado em rede de filiais/sub-cadastros |
| `PESSOA_REPRESENTANTE_CODIGO` | `sales_rep_id` (FK contacts) | rep responsável |
| `CODPRODUTO_TABELA` | `price_list_id` (FK `pricing_groups` ou `selling_price_groups` UltimatePOS) | tabela de preço |
| `CODCIDADE` | `city_id` (FK `cities`) | tabela cidades IBGE |
| `CODEMPRESA` | `business_id` (multi-tenant) | escopo global obrigatório |

### 12.5 Fiscal

| Delphi | Laravel |
|--------|---------|
| `INSCIDENT` (IE) | `state_registration` ou `ie` | UltimatePOS tem |
| `INSC_MUNICIPAL` | `municipal_registration` | adicionar |
| `TIPO_CONTRIBUINTE` (1/2/9) | `contributor_type` (1/2/9) | 1=ICMS, 2=isento, 9=não contribuinte |
| `CRT` (Simples Nacional/Normal) | `tax_regime` | string |
| `CONSUMIDOR_FINAL` (`S`/`N`) | `is_final_consumer` (bool) | obrigatório fiscal |
| `ISS_RETIDO` (1/2) | `iss_retained` | enum |

### 12.6 Crediário / cobrança

| Delphi | Laravel | Notas |
|--------|---------|-------|
| `LIMITE_DESCONTO` | `discount_limit_percent` | 0-100 |
| `BOLETO_PERC_DESCONTO_PADRAO` | `default_boleto_discount_percent` | desconto pontualidade |
| `COBRAR_CUSTO_BOLETO` (`S`/`N`) | `charge_boleto_cost` | bool |
| `FATURA_PREVISAO` | `forecast_invoice_date` | data |
| `URL_COBRANCA` (config) | `business_settings.collection_url` | URL externa |
| `URL_SPC` (config) | `business_settings.spc_url` | URL externa |

## 13. Funcionário — pegadinhas

`TControllerPessoasFuncionario` tem `Modulo := MODULO_RH` mas a tabela continua sendo `PESSOAS` — funcionário é **um contato** com flag `IS_FUN='S'`, não entidade separada. Em UltimatePOS, **users** + **employees** são separados; recomendado:

- Funcionário interno (operacional) → `users` UltimatePOS (login, permissions)
- Funcionário CLT (folha/contabilidade) → `employees` (CPF, CTPS, salário) — UltimatePOS tem
- "Funcionário" Delphi = mistura dos dois. Migration script precisa decidir destino por sinal (tem login? tem RG? tem salário?).

## 14. F3 (Cadastro F3 no Grupo de Tabela de Preços)

```pascal
class procedure TControllerPessoas.AbreCadastroF3NoGrupoTabelaDePrecos(Sender, AKeys);
```

Atalho F3 abre cadastro de Pessoa direto na aba "Grupo de Tabela de Preços" (`GrupoTabelaDePrecos.MakeVisible`). UI Delphi com `inplace edit` por aba — replicar em React via tab inicial deep-link `?tab=pricing`.

## 15. Bridge oimpresso.com (Controller.Pessoas.OImpresso.pas)

Subclasse `TControllerPessoasOImpresso` (150 LOC) — filtra `P.IS_OIM='S'` e exibe **transportadoras** (oimpresso = nomenclatura WR pra terceiros que transportam). Não é integração cloud — é só uma view filtrada da tabela mestre. (Cuidado nome confuso: produto novo "oimpresso.com" ≠ flag `IS_OIM` do Delphi.)

## 16. Recomendações de implementação na ordem

| Ordem | Etapa | O que mudar |
|-------|-------|-------------|
| 1 | Schema | Adicionar colunas faltantes em `contacts`: `address_number`, `neighborhood`, `production_priority`, `discount_limit_percent`, `is_blocked`, `is_final_consumer`, `iss_retained`, `tax_regime`, `municipal_registration`, `default_boleto_discount_percent` |
| 2 | `contact_roles` | Tabela nova multi-papel — sobrepõe `contacts.type` UltimatePOS quando precisa granularidade |
| 3 | Subtipos UI | Index page com filtro por role (Todos / Clientes / Fornecedores / Funcionários / Representantes / Agências) — equivalentes às 6+ BaseItens Delphi |
| 4 | Validações | Pest tests pras 9 regras obrigatórias (`RAZAOSOCIAL` ... `NUMERO` + `ATIVO IN (S,N)`) |
| 5 | Importação NFe/CTe | Service `ImportContactFromNFeXml` espelhando `BuscaEImportaFornecedor` — mesmas regras de fallback (`FONE1='0'`, `EMAIL='Email'` → `fornecedor@semcadastro.local`) |
| 6 | Auto-prio | Campo `production_priority` 0-5 com componente rating React (`react-rating-stars`) |
| 7 | F3 deep-link | Suporte `?tab=<aba>` no Show page pra abrir aba específica via atalho teclado |

## 17. Erros corrigidos por este mapping

| Erro anterior | Causa | Correção |
|---------------|-------|----------|
| Pessoas = só clientes | Inferi por "Contatos" | É **multi-tipo** com 7+ subclasses (CLI/FOR/FUN/REP/AGE/OIM) compartilhando tabela única `PESSOAS` |
| Funcionário é tabela separada | Suposição em UltimatePOS (employees vs users) | É **mesma tabela `PESSOAS`** + flag `IS_FUN='S'` no Delphi |
| `contacts.type` UltimatePOS é suficiente | enum `customer`/`supplier`/`both` | Não cobre representante/agência/funcionário — precisa **`contact_roles` 1-N** |
| `IS_OIM` é flag oimpresso cloud sync | Nome confuso | É **transportadora** ("oimpresso" = nomenclatura WR antiga pra transp) — nada a ver com cloud |
| Default `TIPO=F` (Física) | UltimatePOS B2B vibe | Default **depende do segmento** (`ComunicacaoVisual` → J; vestuário/varejo → F) — config por business |

## 18. Refs

- [TELA-LISTA-VENDAS.md](TELA-LISTA-VENDAS.md) — referência canônica de formato
- [.claude/skills/officeimpresso-source-analysis/SKILL.md](../../../../.claude/skills/officeimpresso-source-analysis/SKILL.md) — método
- UltimatePOS schema: `contacts`, `users`, `employees`, `cities`, `selling_price_groups`

---
**Última atualização:** 2026-05-11 — mapping canônico via Controllers Delphi (source-first). Substitui inferências anteriores sobre estrutura multi-papel.
