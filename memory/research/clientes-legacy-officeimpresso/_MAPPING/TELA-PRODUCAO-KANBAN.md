---
id: research-clientes-legacy-officeimpresso-mapping-tela-producao-kanban
title: Mapping canônico — Tela "Produção Kanban" Delphi → oimpresso.com
status: live
date: 2026-05-11
audience: dev migrando OfficeImpresso → oimpresso.com novo (relevante pra Modules/OficinaAuto e Modules/ComunicacaoVisual)
source_files:
  - "D:/Programas/WR Comercial/app/Controller/Controller.Producao.Kanban.pas (251 LOC)"
  - "D:/Programas/WR Comercial/app/Controller/Controller.Producao.pas (302 LOC — parent)"
  - "D:/Programas/WR Comercial/app/Controller/Controller.Producao.Tarefas.pas"
  - "D:/Programas/WR Comercial/app/Controller/Controller.Producao.Centro_Trabalho.pas"
  - "D:/Programas/WR Comercial/app/Controller/Controller.Producao.Acabamento.pas"
  - "D:/Programas/WR Comercial/app/Controller/Controller.ProducaoSituacao.Definicoes.pas"
  - "D:/Programas/WR Comercial/app/Controller/Controller.ProducaoEstagio.Definicoes.pas"
  - "D:/Programas/WR Comercial/app/Controller/Controller.ProducaoStatus.Definicoes.pas"
method: source-first (skill officeimpresso-source-analysis)
---

# Mapping canônico — Tela "Produção Kanban" Delphi

> **Fonte autoritativa:** Controllers Delphi reais.
> **🎯 Descoberta crítica (Wagner 2026-05-11):** Delphi já tem **Kanban built-in** com sub-grids dinâmicos, agrupadores configuráveis, e tabela `WR_KANBAN` que persiste posições/colunas. Relevante pra Modules/OficinaAuto + ComunicacaoVisual.

## 1. Cadeia de herança

```
TObject
  └─ TControllerMestre               (Controller.Mestre.pas)
      └─ TControllerProducao         (Controller.Producao.pas, 302 LOC) — SQL base + filtros + duplicar OP
          ├─ TControllerProducaoKanban       (Producao.Kanban.pas, 251 LOC) ← TELA ALVO
          ├─ TControllerProducaoTarefas       (vista lista flat de tarefas)
          └─ Controller.Producao.Centro_Trabalho / Producao.Acabamento (subviews)

  Auxiliares (não herdam):
  └─ Controller.Producao_Estagio / _Marcador / _Motivo / _Prioridade / _Situacao /
      _Status / _Roteiro / _Template / _Produto / _Projeto / _Acao (cadastros suporte)
```

`Caption := 'Kanban'`, `Tabela := 'PRODUCAO'`, `Path := PathPRODUCAO_KANBAN`, `Modulo := MODULO_PRODUCAO`, `TagController := 1`.

## 2. SQL base (DEFINITIVO — `TControllerProducaoKanban.Create` linhas 59-65)

```sql
SELECT
  P.CODIGO, P.DESCRICAO, P.RAZAOSOCIAL, P.PRODUTO, P.CODPRODUTO,
  P.QTDADEPECA, P.PRIORIDADE_PRODUCAO,
  P.CALENDARIO_DT_PREVISAO_FIM, P.SITUACAO,
  P.PRODUCAO_ESTAGIO, P.CODCENTRO_TRABALHO,
  P.PESSOA_FUNCIONARIO_CODIGO, P.CODVENDA,
  FUN.FANTASIA AS FUNCIONARIO,
  CT.DESCRICAO AS CENTRO_TRABALHO,
  L.DESCRICAO AS LOCAL,
  P.CODLOCAL, P.CODTIPO_IMPRESSAO, P.TIPO_IMPRESSAO,
  P.DT_EMISSAO
FROM PRODUCAO P
JOIN (SELECT FUN.CODIGO, FUN.FANTASIA FROM PESSOAS FUN) FUN
  ON FUN.CODIGO = P.PESSOA_FUNCIONARIO_CODIGO              -- INNER (funcionário obrigatório)
JOIN (SELECT CT.CODIGO, CT.DESCRICAO FROM CENTRO_TRABALHO CT) CT
  ON CT.CODIGO = P.CODCENTRO_TRABALHO                       -- INNER (centro obrigatório)
LEFT JOIN LOCAL L ON L.CODIGO = P.CODLOCAL
```

**Total: ~17 colunas, 3 JOINs (2 INNER + 1 LEFT).** ⚠️ Note: `INNER JOIN` em funcionário+centro de trabalho — produção **sem responsável atribuído não aparece no Kanban**. Recomendado mudar pra LEFT em Laravel pra mostrar "Sem atribuição".

🎯 **Subqueries inline** (`(SELECT ... FROM PESSOAS FUN) FUN`) — provavelmente otimização para evitar varredura completa de PESSOAS (que tem 329 colunas). Em Laravel, JOIN direto com SELECT explícito serve.

## 3. SQL base parent (`TControllerProducao.Create` linhas 51-66 — usado por outras views)

```sql
SELECT
  PO.*, U.LOGIN AS USUARIO,
  C.DESCRICAO AS CENTRO_TRABALHO,
  P.FANTASIA, PJ.DESCRICAO AS PROJETO,
  '0' as FATURA_ADICIONAL,
  PO.PESSOA_FUNCIONARIO_CODIGO AS CODFUNCIONARIO,
  FUN.FANTASIA AS FUNCIONARIO,
  PS.ESTILO, PS.FILA,
  PP.CODPRODUTO_ETAPA_PREREQUISITO,
  CP.DESCRICAO AS EQUIPEREQUISITO,
  EV.PLACA, EV.PLACA2, EV.CHASSI, EV.CHASSI2
FROM PRODUCAO PO
LEFT JOIN USUARIO U          ON PO.CODUSUARIO = U.CODIGO
LEFT JOIN CENTRO_TRABALHO C  ON C.CODIGO = PO.CODCENTRO_TRABALHO
LEFT JOIN EQUIPAMENTO_VEICULO EV ON EV.CODIGO = PO.CODEQUIPAMENTO        -- ⚠️ FK pra equipamento, não placa direta
LEFT JOIN PESSOAS P          ON P.CODIGO = PO.PESSOA_RESPONSAVEL_CODIGO  -- cliente
LEFT JOIN PESSOAS FUN        ON FUN.CODIGO = PO.PESSOA_FUNCIONARIO_CODIGO -- funcionário
LEFT JOIN PROJETO PJ         ON PJ.CODIGO = PO.CODPROJETO
LEFT JOIN PRODUCAO_SITUACAO PS ON PS.DESCRICAO = PO.SITUACAO
LEFT JOIN PRODUTO_PREREQUISITO PP
  ON PP.CODPRODUTO_ETAPA = PO.CODCENTRO_TRABALHO
 AND PP.CODPRODUTO = PO.CODPRODUTO
LEFT JOIN CENTRO_TRABALHO CP ON CP.CODIGO = PO.CODCENTRO_TRABALHO_PREREQUISITO
```

**8 JOINs** — view base completa. Kanban usa subset enxuto (item 2).

🚗 **Suporte a veículo (relevante pra OficinaAuto):** `LEFT JOIN EQUIPAMENTO_VEICULO EV ON (EV.CODIGO = PO.CODEQUIPAMENTO)` — mesma FK pattern de Venda. Trazendo `PLACA`, `PLACA2`, `CHASSI`, `CHASSI2` (cavalo+reboque).

## 4. Agrupadores (8 — `InitializeAgrupadores` linhas 102-118)

Esses são **as colunas Kanban** — cada agrupador vira um sub-grid (coluna do board):

| Botão UI | Campo SQL | Tipo de exibição |
|----------|-----------|-------------------|
| **Situação** | `SITUACAO` | `kmProcesso` (Kanban visual) |
| **Cliente** | `RAZAOSOCIAL` (via JOIN PESSOAS) | (default — sem `kmProcesso` explícito) |
| **Funcionário** | `FUNCIONARIO` (via JOIN PESSOAS FUN) | `kmProcesso` |
| **Equipe** | `CENTRO_TRABALHO` (via JOIN) | `kmProcesso` |
| **Estágio** | `PRODUCAO_ESTAGIO` | `kmProcesso` |
| **Tipo de Impressão** | `TIPO_IMPRESSAO` | `kmProcesso` |
| **Local de Aplicação** | `LOCAL` (via JOIN) | `kmProcesso` |
| **Produto** | `PRODUTO` | `kmProcesso` |

> **`kmProcesso`** = render Kanban (colunas + cartões drag-drop). 7 dos 8 são Kanban; "Cliente" é só agrupamento lateral comum.

## 5. Datas filtráveis (5 — `InitializeDatasNaConsulta` linhas 120-128)

| Label UI | Campo SQL |
|----------|-----------|
| **Última Alteração** | `DT_ALTERACAO` |
| **Última Criação** ("Data emissão do tiquet") | `DT_EMISSAO` |
| **Data prazo da etapa** | `DT_ENTREGA` |
| **Data finalização da etapa** | `DT_FIM` |
| **Data final do projeto** | `CALENDARIO_DT_PREVISAO_FIM` |

> 5 datas — comparável com Compra (3) e abaixo de Venda (7). Inclui datas a nível de **etapa** (DT_ENTREGA, DT_FIM) — produção tem granularidade de etapa que outras telas não têm.

## 6. Filtros (`ConsultaGetFiltros` linhas 210-244)

Filtros padrão herdados de TControllerMestre (Ativos/Arquivados). Filtros adicionais em comentário (linhas 223-241 desabilitadas):

```pascal
// Funcionário (DESABILITADO — toggle entre filtro por funcionário ativo)
SQLWhere.AddAnd(
  '(P.PESSOA_FUNCIONARIO_CODIGO = :Codigo) or ' +
  '((:Codigo is Null) and (P.PESSOA_FUNCIONARIO_CODIGO IS NULL))'
);

// Equipe (DESABILITADO)
SQLWhere.AddAnd(
  '(P.CODCENTRO_TRABALHO = :Codigo) or ' +
  '((:Codigo is Null) and (P.CODCENTRO_TRABALHO IS NULL))'
);

// Pré-filtro vindo de outra tela (Venda → Kanban da venda)
if FPreFiltro <> '' then
  SQLWhere.AddAnd(FPreFiltro);
```

**Pre-filtro venda** (`Controller.Producao.pas:129-133`): quando vem de uma Venda, aplica `PO.CodVenda = '<codigo>'` — Kanban filtrado pra OPs daquela venda.

## 7. Sub-grids dinâmicos (Kanban colunas — `AgrupaSubGrid` linhas 179-195)

🎯 **Mecanismo central do Kanban:**

```pascal
DataSetConsultaSubGrid.SQL.Text :=
  ' select S.CODIGO, S.DESCRICAO, K.ORDEM, K.COLUNA_FECHADA, K.CHAVE, K.COLUNA '+
  ' from PRODUCAO_SITUACAO S '+
  ' LEFT JOIN WR_KANBAN K ON (K.CHAVE = S.CODIGO) AND (COLUNA = :Coluna)'+
  ' WHERE (S.ATIVO = ''S'') '+
  ' Order By 3 ';
DataSetConsultaSubGrid.ParamByName('Coluna').AsString := ConsultaGroups.AgrupadorAtivo.FieldName;
```

**Tabela canônica `WR_KANBAN`:**

| Coluna | Significado |
|--------|-------------|
| `CHAVE` | valor do agrupador (ex: `'EM_PRODUCAO'` se agrupado por `SITUACAO`) |
| `COLUNA` | nome do agrupador (ex: `'SITUACAO'`) |
| `ORDEM` | ordem da coluna no board (drag-drop reordena) |
| `COLUNA_FECHADA` | se coluna está colapsada (`'S'`/`'N'`) |

→ Laravel: criar tabela `kanban_columns(business_id, agrupador, chave, ordem, is_collapsed)` espelhando.

## 8. Geração de chave primária (não específica — usa `GetProximoCodigoGen('CR_PRODUCAO')`)

```pascal
// Controller.Producao.pas:104
AControllerProducao.DataSetCadastro.FieldByName('CODIGO').AsInteger :=
  Trunc(GetProximoCodigoGen('CR_PRODUCAO'));
```

⚠️ **Não usa sufixo `-empresa`** como Venda/Pessoas/NF_Entrada. CODIGO é integer puro. Multi-empresa via `CODEMPRESA` coluna explícita (e não via parsing do CODIGO).

`PROTOCOLO` (string) é separado do CODIGO — gerado via `<PROTOCOLO_PRINCIPAL>-<SEQUENCIA_PROTOCOLO>` (linha 113). Pattern "OP-001-1, OP-001-2" pra duplicações da mesma OP.

## 9. Cadastros suporte (lookups)

Padrão: cada um tem `Controller.X.pas` (CRUD), `Controller.X.Definicoes.pas` (validações), tabela `X` no Firebird.

| Tabela suporte | Propósito | Campo em PRODUCAO |
|----------------|-----------|---------------------|
| `PRODUCAO_SITUACAO` | Situação (TODO/Doing/Done custom) — define estilos visuais (cor, ícone) | `SITUACAO` (varchar) |
| `PRODUCAO_ESTAGIO` | Estágio do fluxo (briefing/aprovação/produção/entrega) | `PRODUCAO_ESTAGIO` (varchar) |
| `PRODUCAO_STATUS` | Status final (concluído / cancelado / pausado) | `STATUS` |
| `PRODUCAO_MARCADOR` | Tags/labels (urgente/refazer/etc) | (1-N via `PRODUCAO_PRODUTO_MARCADOR`) |
| `PRODUCAO_PRIORIDADE` | Prioridade (baixa/normal/alta/urgente) | `PRIORIDADE_PRODUCAO` (numeric 0-5) |
| `PRODUCAO_MOTIVO` | Motivo de mudança status | `PRODUCAO_MOTIVO` |
| `PRODUCAO_ROTEIRO` | Roteiro pré-definido (template de etapas) | `CODROTEIRO` |
| `PRODUCAO_ROTEIRO_PERGUNTA` | Perguntas obrigatórias do roteiro (briefing) | (1-N) |
| `PRODUCAO_TEMPLATE` | Templates de OP (clonáveis) | `CODTEMPLATE` |
| `PRODUCAO_PROJETO` | Agrupador de OPs por projeto/obra | `CODPROJETO` |
| `PRODUCAO_ACAO` | Histórico ações na OP (timeline) | (1-N via `PRODUCAO_HISTORICO`) |
| `CENTRO_TRABALHO` | Equipes/máquinas (gargalo PCP) | `CODCENTRO_TRABALHO` |
| `PRODUCAO_ETAPAS` | Etapas da OP (1-N) | (1-N via `CODPRODUCAO` FK) |

→ Total: ~13 tabelas no ecossistema Produção. Cada uma vira **uma tabela Laravel** + CRUD admin.

## 10. Etapas (`PRODUCAO_ETAPAS`) — granularidade fina

`GeraProducaoEtapas` (Controller.Producao.pas:252-295) cria linha em PRODUCAO_ETAPAS pra cada etapa que a OP passa. Campos:

| Campo | Origem |
|-------|--------|
| `CODIGO` | gen `CR_PRODUCAO_ETAPAS` |
| `CODVENDA_PRODUTO` | item da venda |
| `CODPRODUCAO` | OP pai |
| `PROTOCOLO` | protocolo OP (denorm) |
| `CODCENTRO_TRABALHO` | onde a etapa será executada |
| `SITUACAO` | estado |
| `CODUSUARIO_RESPONSAVEL` | quem é dono |
| `ESTAGIO` | qual estágio macro |
| `MOTIVO` | motivo (se aplicável) |
| `DATA` | timestamp |
| `CODVENDA` | venda relacionada (denorm) |

→ Laravel: `production_steps` table — 1-N com `productions`.

## 11. Mapping de campos Delphi → Laravel/oimpresso (`PRODUCAO` → `productions`)

### 11.1 Core (sempre migrar)

| Delphi `PRODUCAO.*` | Laravel `productions.*` | Notas |
|----------------------|--------------------------|-------|
| `CODIGO` | `id` (bigint AI) | sem sufixo empresa |
| `PROTOCOLO` | `protocol` (string) | identificador human-readable |
| `PROTOCOLO_PRINCIPAL` | `parent_protocol` (string) | OP duplicada referencia original |
| `SEQUENCIA_PROTOCOLO` | `protocol_sequence` (int) | nº dentro do protocolo principal |
| `DESCRICAO` | `description` | |
| `RAZAOSOCIAL` (denorm cliente) | derivado JOIN contact | |
| `PRODUTO` (denorm) | derivado JOIN product | |
| `CODPRODUTO` | `product_id` (FK products) | |
| `QTDADEPECA` | `quantity` (decimal) | |
| `PRIORIDADE_PRODUCAO` | `priority` (smallint 0-5) | rating component |
| `CALENDARIO_DT_PREVISAO_FIM` | `forecast_end_at` (datetime) | "data prometida" |
| `SITUACAO` (string) | `situation` (FK `production_situations.id` ou string lookup) | |
| `PRODUCAO_ESTAGIO` (string) | `stage` (FK ou string) | |
| `STATUS` (string) | `status` (FK ou enum) | |
| `DT_EMISSAO` | `issued_at` ou `created_at` | |
| `DT_FIM` | `finished_at` | |
| `DT_ALTERACAO` | `updated_at` | nativo Laravel |
| `ATIVO` | soft delete | |

### 11.2 Relacionamentos

| Delphi | Laravel |
|--------|---------|
| `PESSOA_RESPONSAVEL_CODIGO` (cliente) | `contact_id` (FK contacts) |
| `PESSOA_FUNCIONARIO_CODIGO` (responsável) | `assigned_to_user_id` (FK users) ou contact_id |
| `CODCENTRO_TRABALHO` | `work_center_id` (FK work_centers) |
| `CODCENTRO_TRABALHO_PREREQUISITO` | `prerequisite_work_center_id` | etapa anterior |
| `CODVENDA` | `transaction_id` (FK transactions sell) | OP veio de venda |
| `CODVENDA_PRODUTO` | `transaction_line_id` (FK) | item específico da venda |
| `CODPROJETO` | `project_id` (FK projects) | |
| `CODEQUIPAMENTO` | `vehicle_id` (FK vehicles) | OficinaAuto |
| `CODLOCAL` | `location_id` (FK locations) | onde aplica produto (ComVis) |
| `CODTIPO_IMPRESSAO` | `print_type_id` (FK print_types) | ComVis |
| `CODUSUARIO` (lançador) | `created_by` (FK users) |
| `CODPRODUCAO` (parent OP — duplicação) | `parent_production_id` (FK self) |
| `CODEMPRESA` | `business_id` (multi-tenant) |

### 11.3 Flags / produção

| Delphi | Laravel | Notas |
|--------|---------|-------|
| `TEM_ARQUIVADO` (`S`/`N`) | derivado (soft delete) | |
| `TEM_TRABALHANDO` (`S`/`N`) | `is_in_progress` (bool) | OP com alguém produzindo agora |
| `IMAGEM` (BLOB) | `cover_image_path` (string — usar S3/storage) | |
| `PCONCLUSAO` (% conclusão) | `progress_percent` (decimal 0-100) | calculado por etapa |
| `TIPO_IMPRESSAO` (denorm) | derivado JOIN | |

### 11.4 Kanban

| Delphi `WR_KANBAN.*` | Laravel `kanban_columns.*` |
|----------------------|------------------------------|
| `COLUNA` (nome agrupador) | `agrupador` (string — ex: `situation`/`stage`/`work_center`) |
| `CHAVE` (valor coluna) | `value` (string) |
| `ORDEM` | `order` (int) |
| `COLUNA_FECHADA` | `is_collapsed` (bool) |

## 12. Configurações por business (relevantes pro Kanban)

Não há `InitializeConfig` no Producao.Kanban; configs herdadas/inferidas:

| Config | Descrição |
|--------|-----------|
| `KANBAN_AGRUPADOR_DEFAULT` | qual agrupador abre por default |
| `KANBAN_MOSTRAR_COLAPSADAS` | mostrar/esconder colunas colapsadas |
| `PRODUCAO_USAR_PROTOCOLO_FORMATADO` | format string protocolo (ex: `OP-{YYYY}-{seq:6}`) |
| `PRODUCAO_CALCULO_PCONCLUSAO_AUTOMATICO` | bool — recalcula progresso por etapa |

## 13. Tabelas relacionadas (resumo — ~15+ no ecossistema Produção)

```
PRODUCAO (master)
├── PRODUCAO_ETAPAS (1-N, etapas/checkpoints)
├── PRODUCAO_PRODUTO (1-N, sub-produtos da OP — kit/composição)
├── PRODUCAO_HISTORICO (1-N, audit log de mudanças)
├── PRODUCAO_MARCADOR_VINCULO (N-N tags)
├── PRODUCAO_ROTEIRO_RESPOSTA (1-N, respostas perguntas do roteiro)
├── PRODUCAO_ARQUIVO (1-N, anexos)
├── PRODUCAO_ANOTACAO (1-N, comentários)
├── PRODUCAO_TEMPO (1-N, apontamentos de tempo — quem trabalhou quanto)
└── WR_KANBAN (independente, persiste estado UI)

Lookups:
- PRODUCAO_SITUACAO, PRODUCAO_ESTAGIO, PRODUCAO_STATUS
- PRODUCAO_MARCADOR, PRODUCAO_PRIORIDADE, PRODUCAO_MOTIVO
- PRODUCAO_ROTEIRO, PRODUCAO_TEMPLATE, PRODUCAO_PROJETO
- CENTRO_TRABALHO, LOCAL, TIPO_IMPRESSAO
```

## 14. UI da Lista/Kanban (típicas — inferidas do source)

### 14.1 Modo Lista (TControllerProducaoTarefas)

| Coluna | Campo |
|--------|-------|
| Protocolo | `PROTOCOLO` |
| Descrição | `DESCRICAO` |
| Cliente | `RAZAOSOCIAL` (denorm) |
| Produto | `PRODUTO` (denorm) |
| Qtd | `QTDADEPECA` |
| Funcionário | `FUNCIONARIO` (via JOIN) |
| Equipe | `CENTRO_TRABALHO` (via JOIN) |
| Situação | `SITUACAO` |
| Estágio | `PRODUCAO_ESTAGIO` |
| Prioridade | rating 0-5 |
| Dt. Prevista Fim | `CALENDARIO_DT_PREVISAO_FIM` |
| Emissão | `DT_EMISSAO` |

### 14.2 Modo Kanban (TControllerProducaoKanban)

- **Header:** dropdown "Agrupar por" (8 opções — item 4)
- **Colunas:** dinâmicas vindas de `PRODUCAO_SITUACAO`/`_ESTAGIO`/`_STATUS`/`CENTRO_TRABALHO`/etc filtradas por `ATIVO='S'`
- **Cartões:** OPs (1 linha PRODUCAO = 1 cartão)
- **Drag-drop:** move OP entre colunas → UPDATE `SITUACAO`/`PRODUCAO_ESTAGIO`/etc + grava reorder em `WR_KANBAN`
- **Coluna colapsável:** `COLUNA_FECHADA='S'` esconde, mantém estado

## 15. Recomendações de implementação na ordem (relevância: Modules/OficinaAuto + Modules/ComunicacaoVisual)

| Ordem | Etapa | O que mudar |
|-------|-------|-------------|
| 1 | (P0) Schema base | `productions` table + 9 lookups (`production_situations`, `production_stages`, `production_statuses`, `production_priorities`, `production_motivations`, `work_centers`, `production_projects`, `production_templates`, `production_roteiros`) |
| 2 | (P0) Service `CreateProduction` | Geração protocolo (formato configurable), atribuição inicial (situation default, stage default) |
| 3 | (P0) UI Lista flat | DataTable com colunas item 14.1 + filtros (situation, stage, priority, work_center, assigned_to) |
| 4 | (P0) Schema Kanban | `kanban_columns(business_id, agrupador, value, order, is_collapsed)` |
| 5 | (P0) UI Kanban | React DnD library (`@dnd-kit/core`) — header dropdown 8 agrupadores → re-fetch colunas + cartões |
| 6 | (P0) Drag-drop endpoint | PATCH `/productions/{id}` muda campo agrupado + grava posição |
| 7 | (P1) Etapas | `production_steps` 1-N + UI checklist na show page |
| 8 | (P1) Templates / Roteiros | Clone template + perguntas obrigatórias briefing |
| 9 | (P1) Histórico | `production_history` audit log append-only |
| 10 | (P2) Apontamentos tempo | `production_time_entries` — quem trabalhou quanto (relógio start/stop) |
| 11 | (P2) OficinaAuto specifics | Vincular `vehicle_id` + km na entrada + checklist por veículo |
| 12 | (P2) ComVis specifics | `print_type_id`, `location_id`, m² calc, multi-acabamento |

## 16. Para Modules/OficinaAuto (Martinho caso candidato)

Pegadinhas relevantes:

- **Veículo via `EQUIPAMENTO_VEICULO.CODIGO`** (FK integer) — mesmo padrão Venda; trazer PLACA/PLACA2/CHASSI/CHASSI2 via JOIN
- **Caminhão cavalo+reboque** (Vargas/Martinho): PLACA2/CHASSI2 são preenchidos para veículos articulados
- **KM na entrada** (`KM` no EQUIPAMENTO_VEICULO snapshot momento OS) — não há campo dedicado em PRODUCAO; criar `mileage_at_entry` em `productions`
- **Checklist típico oficina**: usar `production_roteiro_perguntas` (briefing) — adapta pra "Check-list entrada" (luz funciona / freio firme / pneus etc)

## 17. Para Modules/ComunicacaoVisual (Extreme/Gold caso candidato)

Pegadinhas relevantes:

- **`TIPO_IMPRESSAO` + `LOCAL` + `PRODUTO`** triade caracteriza job ComVis (ex: "Lona / Fachada / Banner")
- **`PRODUCAO_ETAPAS`** crítico — PCP industrial precisa de várias etapas (impressão → recorte → solda → instalação)
- **`CENTRO_TRABALHO_PREREQUISITO`** — etapa B só inicia quando etapa A concluída (workflow)
- **`PRIORIDADE_PRODUCAO` 0-5** — Extreme massivamente usa (91.4% têm `PROJETO_DT_FIM`); priorização real-world
- **`PROJETO`** agrupador — obra completa pode ter 20 OPs (Extreme casos paradigmáticos)

## 18. Erros corrigidos por este mapping

| Erro anterior | Causa | Correção |
|---------------|-------|----------|
| Kanban no Delphi é só "lista colorida" | Pre-conceito legacy | É **Kanban real** com tabela `WR_KANBAN` persistente, agrupadores configuráveis, drag-drop, columns colapsáveis |
| 1 agrupador = sempre `SITUACAO` | Default UI | São **8 agrupadores configuráveis** — usuário escolhe (situação, cliente, funcionário, equipe, estágio, tipo, local, produto) |
| `STATUS`, `SITUACAO`, `PRODUCAO_ESTAGIO` são sinônimos | Naming similar | São **3 dimensões distintas**: STATUS (concluído/cancelado/pausado) ≠ SITUACAO (TODO/Doing/Done custom) ≠ ESTAGIO (briefing/produção/entrega) |
| Funcionário/Centro são opcionais | Boas práticas | `TControllerProducaoKanban` faz **INNER JOIN** com PESSOAS e CENTRO_TRABALHO — OP sem atribuição **não aparece** no Kanban (bug/feature) |
| `PROTOCOLO` = `CODIGO` formatado | Inferência naming | São **independentes** — CODIGO é PK numérica, PROTOCOLO é human-readable separado com formato `<PROTOCOLO_PRINCIPAL>-<SEQUENCIA>` |
| Kanban Delphi é a referência completa de PCP | Wagner descoberta 2026-05-11 | É **a referência** — Modules/OficinaAuto e ComunicacaoVisual devem espelhar 80%+ do que tem aqui antes de inventar nada |

## 19. Refs

- [TELA-LISTA-VENDAS.md](TELA-LISTA-VENDAS.md) — referência de formato + OP vem de Venda
- [TELA-PESSOAS.md](TELA-PESSOAS.md) — Funcionário (FK PESSOAS)
- [TELA-COMPRA.md](TELA-COMPRA.md) — input de matéria-prima pra OP
- `Modules/Repair/` (oimpresso) — Kanban OS existente — referência arquitetural
- UltimatePOS schema: não tem PCP nativo — modelo é greenfield Modules/OficinaAuto/ComunicacaoVisual

---
**Última atualização:** 2026-05-11 — mapping canônico via Controllers Delphi (source-first). Wagner descobriu via leitura que Delphi tem Kanban built-in robusto — pré-arte de referência pra Modules/OficinaAuto + Modules/ComunicacaoVisual.
