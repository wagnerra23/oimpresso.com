---
title: Glossário — termos do domínio OfficeImpresso/Delphi/Firebird/Verticais
status: live
date: 2026-05-11
audience: time interno + IA-pair (sobretudo IA nova que não viveu o legacy)
---

# Glossário

## Sistema legacy

| Termo | Definição |
|-------|-----------|
| **OfficeImpresso** | Sistema ERP/comercial Delphi feito pela WR Sistemas (Wagner) há 20+ anos. Roda em PC do cliente conectando em Firebird local/LAN. Pacote desktop, não SaaS. |
| **WR Comercial** | Nome interno do executável Delphi (`WR Comercial.exe`). Mesmo sistema = OfficeImpresso (rebranding). |
| **Firebird** | Banco SQL embarcado (similar a SQL Server/PostgreSQL mas leve). Versão 3.0.12 no servidor produção WR. |
| **alias Firebird** | Em produção Wagner usa só `Banco` como alias (= `C:\WR Sistema\Dados\BANCO.FDB`). Pra clientes, usar **path completo**: `192.168.0.55:D:\DadosClientes\<Cliente>\Dados\BANCO.FDB` |
| **SYSDBA/masterkey** | Credencial default Firebird hardcoded em todos OS bancos (legado Delphi). Boa pra read-only análise, **terrível** pra produção exposta (mitigado pq LAN-only) |
| **servidor-crm** | Hostname interno (resolução DNS LAN) → 192.168.0.55. Mesmo servidor onde Wagner roda o Firebird. |
| **DadosClientes** | Pasta `D:\DadosClientes\` no servidor onde ficam os bancos individuais de cada cliente legacy (1 pasta por cliente) |
| **Gerenciador Delphi** | Aplicação custom do Wagner pra listar/conectar bancos de cliente. Lista os 38 aliases conhecidos. |

## Tabelas-chave Firebird

| Tabela | Conteúdo |
|--------|----------|
| `VENDA` | OS / vendas comerciais. Tabela master de operação (~85k linhas em cliente grande). 359-361 colunas. |
| `VENDA_PRODUTO` | Itens da venda (1:N). Cliente que recapagem tem média 3 itens/venda |
| `VENDA_PRODUTO_CENTRO_TRABALHO` | PCP — rastreio de cada item por centro/máquina. **Só Extreme usa** no sample. |
| `VENDA_PRODUTO_ETAPA` | Etapas de produção do item. Vazio no sample (mas existe no schema). |
| `VENDA_SITUACAO` | Lookup catalog de situações da venda (1 row por estado). |
| `VENDA_ESTAGIO` | FSM/funil da venda (estados + transições). |
| `VENDA_OBRA` | **Não existe em nenhum banco do sample** — descartar como conceito |
| `EQUIPAMENTO` | Cadastro mestre de equipamento (master polimórfica). |
| `EQUIPAMENTO_VEICULO` | Especialização 1:1 com EQUIPAMENTO — só campos de veículo (PLACA, CHASSI, etc) |
| `EQUIPAMENTO_VEICULO.PLACA` | Placa principal |
| `EQUIPAMENTO_VEICULO.PLACA2` | Placa secundária (cavalo+reboque) |
| `EQUIPAMENTO_VEICULO.CHASSI` | Chassi principal |
| `EQUIPAMENTO_VEICULO.CHASSI2` | Chassi secundário (cavalo+reboque) |
| `FINANCEIRO` | Master de lançamentos (receita + despesa). 59k linhas em cliente grande. |
| `MENSALIDADE_FINANCEIRO` | Lançamentos recorrentes (SaaS / contratos). |
| `CONTRATO` | Contratos vivos com cliente — MRR contratado. |
| `PESSOAS` | Cadastro mestre (clientes, fornecedores, funcionários, transportadores). 329 colunas. |
| `BOLETOS` | Boletos bancários emitidos. |
| `CODFINANCEIRO_GRUPO` | Coluna em FINANCEIRO que agrupa linhas — base do "agrupamento" no Delphi |
| `RDB$RELATIONS` | Meta-tabela Firebird — lista todas tabelas (sistema + user) |
| `RDB$RELATION_FIELDS` | Meta-tabela — lista colunas de cada tabela |

## Datas no Delphi

| Campo | Significado |
|-------|-------------|
| `DT_EMISSAO` ou `EMISSAO` | Data em que a venda foi criada |
| `DT_FATURAMENTO` | Data em que a NF foi emitida |
| `DT_COMPETENCIA` | Data de competência fiscal (regime competência) |
| `DT_PROMETIDO` | Data prometida de entrega — **só Gold (comvis) usa no sample** |
| `DT_ENVIO_FATURAMENTO` | Data em que a NF foi enviada |
| `DT_ALTERACAO` | Última modificação da venda |
| `DATAPAGTO` (em FINANCEIRO) | Data do pagamento (quando recebido/pago) |
| `VENCTO` (em FINANCEIRO) | Data de vencimento |

## Verticais identificadas no sample

| Vertical | CNAE típico | Marcadores no Firebird |
|----------|-------------|------------------------|
| **Gráfica industrial PCP** | 1813-0/01 (impressão) | `VENDA_PRODUTO_CENTRO_TRABALHO` > 1k linhas + zero veículos |
| **Comunicação visual** | 7320-5/00 ou 1813-0/01 | `DT_PROMETIDO` > 50% preenchido + `VENDA.SITUACAO` distinct >= 5 + zero PCP + zero veículos |
| **Oficina auto** (genérica) | 4520-0/01 | `EQUIPAMENTO_VEICULO.PLACA` > 30% + sem multi-placa |
| **Oficina recapagem caminhão** | 2212-9/00 (recapagem) | `EQUIPAMENTO_VEICULO` com PLACA2/CHASSI2 > 5% (cavalo+reboque) + média itens/venda > 2 |
| **Mecânica pesada caminhão basculante** (Martinho · ADR 0194) | 4520-0/01 (manutenção/reparação) | `EQUIPAMENTO_VEICULO.PLACA` > 90% E PLACA2 = 0% + média itens/venda ~1 + caminhões DE CLIENTES (não frota própria) |
| ~~Oficina caçamba avulsa~~ (hipótese sub-vertical 3 sem cliente real) | 4581-4/00 (locação) | container estacionária sem placa — incompatível com PLACA Firebird 96% Martinho |

## ROTA LIVRE — separação importante

| Sistema | Cliente |
|---------|---------|
| **OfficeImpresso (Firebird/Delphi)** | 38 clientes legacy (vivem nessa pasta de pesquisa) |
| **oimpresso.com (Laravel/MySQL)** | ROTA LIVRE (`business_id=4`, Larissa, vestuário em Termas do Gravatal/SC) + WR Sistemas (biz=1, Wagner) |

ROTA LIVRE **não tem perfil aqui** — ela é cliente do oimpresso.com novo, não OfficeImpresso legacy.

## Arquitetura Delphi (descobertas 2026-05-11)

| Termo | Significado |
|-------|-------------|
| `TControllerMestre` | Classe base abstrata (3.444 LOC) em `Controller.Mestre.pas`. **Todos** Controllers herdam dela. Tem SQL/filtros/grid/permissões/validação/bridge OImpresso. |
| `TControllerVenda` | Controller específico da Lista de Vendas (4.010 LOC), herda TControllerMestre. |
| `Controller.<X>.Definicoes.pas` | Arquivo **gerado automaticamente** com valores default + validações da tabela X (ex: `Controller.Venda.Definicoes.pas`). |
| `SQLInit` (em TControllerMestre) | TStringList com o SQL base da consulta. Ex: `select V.* from VENDA V` |
| `SQLWhere` | WHERE adicional aplicado dinamicamente conforme filtros do usuário |
| `SQLOrderBy` | ORDER BY default da tela |
| `FormCreateConsulta` | Hook chamado quando tela abre — define filtros default, ordenação, agrupamento |
| `TConsultaFiltros` | Coleção de filtros nomeados (`GetFiltroProNome('Retirar filtros')`) |
| `TWR_GridDBColumn` | Coluna do grid configurada — `FGridDBColumnList: TList<TWR_GridDBColumn>` |
| `CONFIGURACOES_GRID` (tabela Firebird) | Persiste config de colunas/filtros que o **usuário** configurou na tela. Custom por cliente. |
| `TMestreOImpresso` | Bridge pro oimpresso.com novo — métodos `OImpressoPrepareFieldsForSet/Get`, `GeraFDQueryOImpressoPost/Get` |
| `Controller.OImpresso.pas` | Tela "API OImpresso.com" — métodos `LoginDaAPI`, `SincronizarContatos/Vendas/Financeiro/Produto/Tudo` |
| `OIMPRESSO` (tabela Firebird) | Master de registros sincronizados — uma linha por entidade no oimpresso.com novo |
| `OIMPRESSO_LOG` | Log de cada operação de sync (timestamp, status, erro) |
| `Controller.Pessoas.OImpresso.pas` | Sync específica de Pessoas (clientes/fornecedores) Delphi → oimpresso |
| `TWR_APP` (em Classes.APP.pas) | Registro do app no framework (Caption, módulo, ícone) |
| `RegisterController(Path, TControllerXXX.Create)` | Auto-registro no rodapé do `.pas` — Path do menu |
| `TKanbanManager` | Suporte a Kanban built-in no framework (Wagner já tem!) |
| `TValidationManager` | Sistema de validação dinâmica com regras e contextos condicionais |
| `Path<X>` constants (wrConstantes) | Constantes que mapeiam path do menu → controller (`PathCONFIGURACAO_GRID`, `PathOIMPRESSO`, etc) |

## Acrônimos verticais oimpresso.com

| Sigla | Módulo |
|-------|--------|
| `Modules/ComVis` ou `Modules/ComunicacaoVisual` | comunicação visual |
| `Modules/OficinaAuto` | oficinas automotivas em geral (atende auto + caçamba + recapagem) |
| `Modules/Vestuario` | vestuário (atende ROTA LIVRE) |
| `Modules/Pcp` | (proposto, ainda não existe) PCP industrial — caso Extreme |

## PII vs não-PII

| Tipo | É PII? | Comitar em git público? |
|------|--------|-------------------------|
| Razão social | sim | não — hash |
| CNPJ | sim | não — mascarar |
| Endereço (rua/número/CEP) | sim | não — cidade/UF OK |
| Telefone/email | sim | não |
| Placa de veículo | sim (LGPD inclui) | não — ofuscar |
| Status/Situação custom (texto livre) | depende — pode ter nome de cliente embutido | ofuscar por segurança |
| Códigos internos (CODIGO, IDs) | não | OK |
| Agregados (count, sum, %) | não | OK |
| Hash anonimizado | não | OK (é o ponto) |

---

**Última atualização:** 2026-05-11
