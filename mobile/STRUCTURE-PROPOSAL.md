# STRUCTURE-PROPOSAL.md — Oimpresso ERP Mobile

> Plano de reestruturação para tornar o app **production-grade** para empresas reais, com RBAC, telas dedicadas (não popups), cadastros completos compatíveis com NF-e, anexos universais entre venda↔produção, integração WhatsApp com mídia, e Home contextual por papel.

Status atual: 8.4/10 (auditoria sênior). Este doc é o roadmap pra chegar em 9.5/10 e estar comparável aos líderes (Bling, Omie, Conta Azul, TOTVS, NetSuite).

---

## 1. RBAC — Controle de Acesso por Papel

### Papéis padrão

| Papel | O que vê | O que faz |
|---|---|---|
| **owner** | Tudo | Tudo + gerenciar usuários, billing, empresa |
| **admin** | Tudo (sem billing) | Tudo + gerenciar usuários da empresa |
| **gerente** | Operação completa + relatórios + financeiro | Aprovar, cancelar, criar, editar tudo |
| **vendedor** | Próprios pedidos, clientes, catálogo, comissões | Criar pedido, editar cliente, enviar orçamento |
| **producao** | OPs, anexos de arte, OSs, checklist, fotos | Avançar status, anexar fotos, marcar pronto |
| **estoquista** | Estoque, recebimento, movimentações, inventário | Entrada/saída, ajuste, inventário |
| **financeiro** | Receitas/despesas, pagamentos, NFe, conciliação | Lançar, conciliar, emitir NFe, cancelar |
| **mecanico** | OSs próprias, checklist DVI | Diagnóstico, executar serviço, anexar foto |
| **viewer** | Read-only configurável (auditoria, contador) | Ver e exportar |
| **cliente_portal** | Próprios pedidos/orçamentos/OSs | Aprovar, comentar, baixar PDF |

### Implementação técnica

**Schema** — estender `companyMembers`:
```sql
ALTER TABLE companyMembers
  ADD COLUMN role_v2 enum('owner','admin','gerente','vendedor','producao','estoquista','financeiro','mecanico','viewer') DEFAULT 'viewer',
  ADD COLUMN permissions_override JSON NULL;  -- override individual de permissões
```

**Server-side**: criar `requirePermission(perm: Permission)` middleware tRPC:
```ts
// server/_core/permissions.ts
export const PERMISSIONS = {
  "produtos.read": ["owner","admin","gerente","vendedor","estoquista","producao","mecanico","viewer"],
  "produtos.write": ["owner","admin","gerente","estoquista"],
  "pedidos.read": ["owner","admin","gerente","vendedor","producao","financeiro","viewer"],
  "pedidos.create": ["owner","admin","gerente","vendedor"],
  "pedidos.cancel": ["owner","admin","gerente"],
  "pedidos.see_value": ["owner","admin","gerente","vendedor","financeiro"],  // ← produção NÃO vê valor
  "financeiro.read": ["owner","admin","gerente","financeiro","viewer"],
  "financeiro.write": ["owner","admin","gerente","financeiro"],
  "estoque.read": ["owner","admin","gerente","estoquista","producao","vendedor"],
  "estoque.write": ["owner","admin","gerente","estoquista"],
  "ops.advance": ["owner","admin","gerente","producao","mecanico"],
  "ops.photos.upload": ["owner","admin","gerente","producao","mecanico"],
  "fiscal.emit": ["owner","admin","gerente","financeiro"],
  "fiscal.cancel": ["owner","admin","gerente","financeiro"],
  "reports.financial": ["owner","admin","gerente","financeiro"],
  "reports.operational": ["owner","admin","gerente","vendedor","producao","estoquista","financeiro"],
  "users.manage": ["owner","admin"],
  "company.settings": ["owner","admin"],
};

export const sensitiveFieldRedactor = (user, entity) => {
  if (!can(user, "pedidos.see_value")) {
    return { ...entity, valor: null, valorPecas: null, valorMaoObra: null, custoUnit: null };
  }
  return entity;
};
```

**Client-side**: hook `usePermissions()` + componente `<Gate perm="...">`:
```tsx
const { can } = usePermissions();
{can("financeiro.read") && <FinanceCard />}
{can("pedidos.see_value") && <OiMoney value={pedido.valor} />}
```

---

## 2. Home Contextual por Papel

Cada papel vê **uma versão diferente do Dashboard**, focada nas tarefas dele. Inspirado em TOTVS, Omie e SAP B1 que mostram "widgets por papel".

### Home — Vendedor
- 📊 **Minhas vendas (mês)**: valor + qtd pedidos próprios + ticket médio
- 🎯 **Meta do mês**: barra de progresso + dias restantes
- 🔔 **Tarefas**: orçamentos pendentes aprovação, pedidos atrasados, follow-ups
- ⚡ **Atalhos**: Novo pedido, Novo orçamento, Buscar cliente, Catálogo
- 👥 **Clientes recentes** (top 5 que ele atendeu)
- 🏆 **Ranking** (opcional, se gamificação ativa)
- ❌ NÃO mostra: faturamento total da empresa, vendas de outros vendedores, financeiro

### Home — Produção / Mecânico
- 🛠 **Minhas OPs/OSs em andamento**: lista priorizada (atrasados em vermelho)
- 📅 **Agenda do dia**: o que entregar hoje
- 📎 **Anexos pendentes**: artes aguardando upload de prova
- 🚦 **Status do funil**: cards com counts por etapa (Fila / Em andamento / Revisão)
- ⚡ **Atalhos**: Próxima OP, Anexar foto, Marcar pronto, Buscar OS por placa
- ❌ NÃO mostra: valores monetários, faturamento, comissões

### Home — Estoquista
- 📦 **Itens críticos**: lista de SKUs com estoque baixo (vermelho/amarelo)
- 📥 **Recebimentos pendentes**: pedidos de compra pra dar entrada
- 📊 **Movimentações hoje**: count entradas vs saídas
- ⚡ **Atalhos**: Nova entrada, Inventário, Buscar SKU, Etiquetas
- ❌ NÃO mostra: financeiro, NFe, comissões

### Home — Financeiro
- 💰 **Saldo atual**: por conta bancária
- 📈 **A receber hoje + vencidos**: dois cards destacados
- 📉 **A pagar hoje + atrasados**: dois cards destacados
- 🧾 **NFe pendentes**: emissão + retransmissões
- 📊 **DRE simplificado** (mês corrente vs anterior, sparkline)
- ⚡ **Atalhos**: Lançar receita, Lançar despesa, Conciliar, Emitir NFe

### Home — Gerente / Admin / Owner
- 💼 **Cockpit completo**: receita, despesas, margem, ticket médio (com filtros: hoje/semana/mês)
- 👥 **Performance da equipe**: vendas por vendedor, OPs por produção
- 🚨 **Alertas**: estoque crítico, contas atrasadas, OS travada, NFe rejeitada
- 📊 **KPIs operacionais**: count por status em cada pipeline
- ⚡ **Atalhos**: tudo

### Home — Viewer (read-only)
- Cockpit lite (sem ações), focado em relatórios e exportação

### Implementação

Criar `app/(tabs)/index.tsx` como **switch por papel**:

```tsx
export default function HomeScreen() {
  const { user } = useAuthContext();
  const role = useCurrentRole(); // resolve via companyMembers

  switch (role) {
    case "vendedor":    return <HomeVendedor />;
    case "producao":
    case "mecanico":    return <HomeProducao />;
    case "estoquista":  return <HomeEstoque />;
    case "financeiro":  return <HomeFinanceiro />;
    case "viewer":      return <HomeViewer />;
    default:            return <HomeGerencial />; // owner, admin, gerente
  }
}
```

Cada componente vive em `app/(tabs)/_home/HomeXxx.tsx` para isolamento. Compartilham OI primitives.

---

## 3. Telas Dedicadas (Fim dos Popups)

### Problema atual
Hoje muita coisa abre em `ModalDialog`/`OiSheet` — limita formulário, dificulta anexos, atrapalha navegação. Vamos migrar pra **stack screens dedicadas**.

### Estrutura proposta de rotas

```
app/
  (tabs)/                       # 5 tabs visíveis
    index.tsx                   # Home contextual
    tarefas.tsx
    vendas.tsx                  # LISTA de pedidos (não cria/edita aqui)
    producao.tsx                # LISTA + kanban de OPs
    mais.tsx                    # Hub de módulos

  pedidos/
    [id].tsx                    # NOVO — tela completa de pedido (Stack)
    new.tsx                     # NOVO — formulário de novo pedido (Stack)

  clientes/
    [id].tsx                    # Detalhe completo (Stack)
    new.tsx                     # Cadastro completo (Stack)
    [id]/
      veiculos.tsx              # Lista veículos do cliente
      historico.tsx             # Pedidos, OSs, financeiro do cliente
      anexos.tsx                # Documentos: contrato, RG, comprovante

  produtos/
    [id].tsx                    # Detalhe + variações + fotos
    new.tsx                     # Cadastro completo

  ops/
    [id].tsx                    # Detalhe da OP com anexos+fotos
    [id]/
      arte.tsx                  # Tela dedicada de upload+aprovação de arte
      execucao.tsx              # Checklist de execução + fotos
      finalizacao.tsx           # Fotos finais + checklist envio

  oss/[id].tsx                  # Já existe — manter
  oss/[id]/dvi.tsx              # NOVO — DVI (Digital Vehicle Inspection)

  financeiro/
    [id].tsx                    # Detalhe da transação
    new.tsx                     # Lançamento completo
    conciliacao.tsx             # Tela de conciliação bancária
    contas-bancarias.tsx        # Cadastro de contas

  fiscal/
    [id].tsx                    # Detalhe da NFe
    emit.tsx                    # Formulário de emissão

  configuracoes/                # NOVO — área de configurações
    usuarios.tsx
    permissoes.tsx
    empresa.tsx
    fiscal.tsx
    integracoes.tsx
```

### Padrão de tela dedicada (pedido como exemplo)

`app/pedidos/[id].tsx`:

```
┌──────────────────────────────────┐
│ ← Pedido #1247                ⋮│  ← OiDetailHeader
├──────────────────────────────────┤
│ ┌────────────────────────────┐ │
│ │ João Silva                 │ │  ← Cliente (tap → /clientes/[id])
│ │ Whatsapp: (11) 99999-9999  │ │
│ │ Email: joao@gmail.com      │ │
│ └────────────────────────────┘ │
│                                  │
│ STATUS  [Em Produção]  [Avançar]│  ← Pill + ação
│                                  │
│ ITENS (3)              + Item   │  ← OiSectionHeader com action
│ ┌────────────────────────────┐ │
│ │ 1× Banner 2x1m       150,00│ │
│ │ 2× Cartão 9x5 4/4    248,00│ │
│ │ 1× Adesivo vinil      75,00│ │
│ └────────────────────────────┘ │
│                                  │
│ TOTAIS                           │
│ Subtotal           R$ 473,00    │
│ Desconto              -50,00    │
│ Total           R$ 423,00       │  ← OiMoney destaque
│                                  │
│ ANEXOS DE VENDA (2)    + Upload │  ← Importante!
│ ┌────────────────────────────┐ │
│ │ [thumb] arte-final.pdf  ⋮  │ │
│ │ [thumb] referencia.jpg  ⋮  │ │
│ └────────────────────────────┘ │
│                                  │
│ PRAZO & PAGAMENTO                │
│ Entrega: 28/05/2026             │
│ Forma:   Pix à vista            │
│ Status:  Aguardando NFe         │
│                                  │
│ HISTÓRICO                        │
│ • Hoje 14:30 — Vendedor avançou │
│ • Hoje 12:15 — Cliente aprovou  │
│ • Ontem 16:00 — Pedido criado   │
│                                  │
│ AÇÕES                            │
│ [Editar] [Duplicar] [Cancelar]  │
│ [Gerar OP] [Enviar WhatsApp]    │
│ [Emitir NFe]                    │
└──────────────────────────────────┘
```

Mesma estrutura pra OS, orçamento, transação financeira.

---

## 4. Cadastro de Cliente Completo (compatível NF-e)

### Schema estendido

```sql
ALTER TABLE customers
  ADD COLUMN tipo_pessoa enum('PF','PJ') DEFAULT 'PF',  -- já existe como `tipo`
  ADD COLUMN razao_social varchar(255),                  -- PJ
  ADD COLUMN nome_fantasia varchar(255),                 -- PJ
  ADD COLUMN cpf varchar(14),                            -- PF (com máscara)
  ADD COLUMN cnpj varchar(18),                           -- PJ
  ADD COLUMN rg varchar(20),                             -- PF opcional
  ADD COLUMN inscricao_estadual varchar(32),
  ADD COLUMN inscricao_municipal varchar(32),
  ADD COLUMN indicador_ie enum('contribuinte','isento','nao_contribuinte') DEFAULT 'nao_contribuinte',
  ADD COLUMN cep varchar(9),
  ADD COLUMN logradouro varchar(255),
  ADD COLUMN numero varchar(20),
  ADD COLUMN complemento varchar(100),
  ADD COLUMN bairro varchar(120),
  ADD COLUMN cidade varchar(120),
  ADD COLUMN uf char(2),
  ADD COLUMN codigo_municipio_ibge varchar(7),           -- pra NFSe
  ADD COLUMN pais varchar(60) DEFAULT 'Brasil',
  ADD COLUMN telefone varchar(32),                        -- já existe
  ADD COLUMN telefone_secundario varchar(32),
  ADD COLUMN whatsapp varchar(32),                        -- pode diferir do telefone
  ADD COLUMN email_nfe varchar(320),                      -- pra envio automático
  ADD COLUMN aceita_whatsapp tinyint DEFAULT 1,           -- LGPD consent
  ADD COLUMN aceita_email tinyint DEFAULT 1,
  ADD COLUMN aceita_sms tinyint DEFAULT 0,
  ADD COLUMN consentimento_data varchar(32),              -- ISO 8601
  ADD COLUMN consentimento_ip varchar(64),
  ADD COLUMN segmento varchar(64),                        -- "Indústria", "Varejo", "Pessoa Física"
  ADD COLUMN classificacao enum('A','B','C','D') DEFAULT 'C',  -- curva ABC
  ADD COLUMN vendedor_responsavel int REFERENCES users(id),
  ADD COLUMN limite_credito decimal(12,2) DEFAULT 0,
  ADD COLUMN prazo_padrao_dias int DEFAULT 0,
  ADD COLUMN forma_pagamento_padrao varchar(32),
  ADD COLUMN tabela_preco_id varchar(36),                 -- referência pra tabela específica
  ADD COLUMN aniversario varchar(10),                     -- MM-DD pra campanha
  ADD COLUMN status enum('ativo','inativo','bloqueado') DEFAULT 'ativo',
  ADD COLUMN motivo_bloqueio text,
  ADD COLUMN fonte_origem varchar(64);                    -- "Indicação", "Google", "Instagram"
```

### Formulário em abas (tela `clientes/[id]/edit.tsx`)

```
[Dados]  [Endereço]  [Fiscal]  [Contato/LGPD]  [Comercial]  [Anexos]
```

- **Dados**: tipo (PF/PJ toggle), nome ou razão social, fantasia, CPF/CNPJ (com máscara + validação), RG, aniversário, segmento, fonte origem
- **Endereço**: integração com **viacep.com.br** (digita CEP → preenche tudo)
- **Fiscal**: IE, IM, indicador de IE, código IBGE (auto via viacep)
- **Contato/LGPD**: tel principal, secundário, WhatsApp (separado!), e-mail principal, e-mail NFe, consentimentos (toggles WhatsApp/Email/SMS) com timestamp + IP capturado no save
- **Comercial**: vendedor responsável (Select), classificação A/B/C/D, limite de crédito, prazo padrão, forma pagamento, tabela preço
- **Anexos**: documentos (CNH, RG, contrato social, comprovante endereço)

### Validações
- CPF: validação de dígito verificador
- CNPJ: validação de dígito verificador + consulta CNPJ na Receita (opcional, via API externa) pra auto-preencher
- CEP: viacep auto-preenche
- E-mail: regex padrão
- Consentimento WhatsApp: obrigatório aceite explícito antes de qualquer envio

---

## 5. Cadastro de Produto Completo (compatível NF-e)

### Schema estendido

```sql
ALTER TABLE produtos
  ADD COLUMN sku varchar(64),                           -- código interno
  ADD COLUMN gtin_ean varchar(14),                      -- código de barras
  ADD COLUMN ncm varchar(8),                            -- obrigatório NFe
  ADD COLUMN cest varchar(7),                           -- ICMS-ST
  ADD COLUMN cfop_dentro_estado varchar(4),             -- ex: 5102
  ADD COLUMN cfop_fora_estado varchar(4),               -- ex: 6102
  ADD COLUMN origem enum('0','1','2','3','4','5','6','7','8') DEFAULT '0',
  ADD COLUMN unidade_comercial varchar(6) DEFAULT 'UN', -- UN, KG, M, M2, M3, L, PC
  ADD COLUMN unidade_tributavel varchar(6) DEFAULT 'UN',
  ADD COLUMN peso_bruto_kg decimal(10,3),
  ADD COLUMN peso_liquido_kg decimal(10,3),
  ADD COLUMN icms_csosn varchar(4),                     -- Simples: 101, 102, 500, etc
  ADD COLUMN icms_cst varchar(3),                       -- Lucro presumido/real
  ADD COLUMN icms_aliquota decimal(5,2),
  ADD COLUMN pis_cst varchar(3) DEFAULT '01',
  ADD COLUMN pis_aliquota decimal(5,4) DEFAULT 0.65,
  ADD COLUMN cofins_cst varchar(3) DEFAULT '01',
  ADD COLUMN cofins_aliquota decimal(5,4) DEFAULT 3.00,
  ADD COLUMN ipi_cst varchar(3),
  ADD COLUMN ipi_aliquota decimal(5,2),
  ADD COLUMN preco_custo decimal(12,2),
  ADD COLUMN preco_venda decimal(12,2) NOT NULL,
  ADD COLUMN preco_promo decimal(12,2),
  ADD COLUMN markup_percent decimal(5,2),
  ADD COLUMN margem_lucro_percent decimal(5,2),         -- calculado
  ADD COLUMN estoque_atual decimal(12,3) DEFAULT 0,
  ADD COLUMN estoque_minimo decimal(12,3) DEFAULT 0,
  ADD COLUMN estoque_maximo decimal(12,3),
  ADD COLUMN controla_estoque tinyint DEFAULT 1,
  ADD COLUMN movimenta_estoque tinyint DEFAULT 1,       -- serviços não movimentam
  ADD COLUMN tipo_item enum('produto','servico','materia_prima','combo','variavel') DEFAULT 'produto',
  ADD COLUMN tem_variacoes tinyint DEFAULT 0,
  ADD COLUMN destaque tinyint DEFAULT 0,
  ADD COLUMN ativo tinyint DEFAULT 1,
  ADD COLUMN descricao_curta varchar(255),
  ADD COLUMN descricao_longa text,
  ADD COLUMN observacoes_fiscais text,                  -- "Conforme art. 4 LC 116..."
  ADD COLUMN fornecedor_principal_id varchar(36),
  ADD COLUMN local_armazenamento varchar(120),
  ADD COLUMN lote_padrao varchar(64),
  ADD COLUMN data_validade_dias int,
  ADD COLUMN imagem_principal varchar(255),
  ADD COLUMN galeria JSON,                              -- array de S3 keys
  ADD COLUMN tags JSON;                                 -- array de strings p/ busca
```

### Variações (produtos variáveis)

```sql
CREATE TABLE product_variations (
  id varchar(36) PRIMARY KEY,
  produto_id varchar(36) NOT NULL REFERENCES produtos(id),
  sku varchar(64) UNIQUE,
  gtin_ean varchar(14),
  atributos JSON,         -- {"cor": "azul", "tamanho": "M"}
  preco_venda decimal(12,2),
  estoque_atual decimal(12,3) DEFAULT 0,
  ativo tinyint DEFAULT 1
);
```

### Formulário em abas (tela `produtos/[id]/edit.tsx`)

```
[Dados]  [Preços]  [Fiscal]  [Estoque]  [Imagens]  [Variações]
```

- **Dados**: nome, SKU, GTIN/EAN (com scanner câmera 📷), categoria, tipo (produto/serviço/MP), descrição curta + longa, tags
- **Preços**: custo, venda, promo, markup auto-calculado, margem auto-calculada
- **Fiscal**: NCM (com busca por código + descrição), CEST, CFOP (dentro/fora estado), origem, ICMS (CSOSN/CST), PIS, COFINS, IPI, observações
- **Estoque**: atual, mínimo, máximo, controla estoque (toggle), unidade comercial vs tributável, local, lote
- **Imagens**: upload múltiplo (até 8 fotos), reordenar, principal destacada
- **Variações**: tabela CRUD de atributos × SKU × estoque × preço

### Helpers
- **Busca NCM**: integração com tabela NCM do Brasil (json local) — ex: digita "tinta" → lista NCMs 3208.x, 3209.x
- **Scanner GTIN**: usa `expo-camera` + barcode reader (já planejado em F5-01)
- **Cálculo automático**: ao mudar custo ou venda, recalcula markup e margem
- **Duplicar**: clonar produto pra criar similar

---

## 6. Financeiro com Filtros Multi-Data

### Problema atual
Hoje só temos filtro de período "Este mês / Últimos 3 meses / Todos". Insuficiente.

### Filtros propostos

Cada lançamento financeiro tem **3 datas distintas**:

| Data | Definição |
|---|---|
| **emissao** | Quando o lançamento foi emitido (NF, fatura, contrato) |
| **vencimento** | Quando deve ser pago/recebido |
| **pagamento** | Quando foi efetivamente pago/recebido (null se pendente) |

### Schema estendido

```sql
ALTER TABLE transacoes
  ADD COLUMN tipo_documento enum('NFe','NFSe','NFCe','Boleto','Recibo','Outro') DEFAULT 'Outro',
  ADD COLUMN numero_documento varchar(64),
  ADD COLUMN data_emissao varchar(32) NOT NULL,      -- ISO 8601
  ADD COLUMN data_vencimento varchar(32),            -- ISO 8601
  ADD COLUMN data_pagamento varchar(32),             -- null = pendente
  ADD COLUMN forma_pagamento varchar(32),            -- pix, boleto, cartao, dinheiro, transferencia
  ADD COLUMN conta_bancaria_id varchar(36),
  ADD COLUMN parcela_atual int DEFAULT 1,
  ADD COLUMN parcela_total int DEFAULT 1,
  ADD COLUMN status enum('pendente','pago','parcial','vencido','cancelado') DEFAULT 'pendente',
  ADD COLUMN juros decimal(12,2) DEFAULT 0,
  ADD COLUMN multa decimal(12,2) DEFAULT 0,
  ADD COLUMN desconto decimal(12,2) DEFAULT 0,
  ADD COLUMN valor_pago decimal(12,2),                -- pode diferir do nominal
  ADD COLUMN customer_id varchar(36),
  ADD COLUMN fornecedor_id varchar(36),               -- nova entidade pra despesas
  ADD COLUMN referencia_tipo varchar(32),             -- "pedido","os","manual"
  ADD COLUMN referencia_id varchar(36);

CREATE TABLE contas_bancarias (
  id varchar(36) PRIMARY KEY,
  company_id varchar(36) NOT NULL,
  nome varchar(120),
  banco varchar(64),
  agencia varchar(20),
  conta varchar(40),
  tipo enum('corrente','poupanca','aplicacao','caixa'),
  saldo_inicial decimal(12,2) DEFAULT 0,
  saldo_atual decimal(12,2) DEFAULT 0,
  ativa tinyint DEFAULT 1
);

CREATE TABLE fornecedores (
  id varchar(36) PRIMARY KEY,
  company_id varchar(36) NOT NULL,
  nome varchar(255),
  razao_social varchar(255),
  cnpj_cpf varchar(18),
  -- demais campos similares a customers
);
```

### Tela `financeiro.tsx` com filtros avançados

Painel de filtros (drawer ou OiSheet) com:

```
[Período por:]  ○ Emissão  ● Vencimento  ○ Pagamento
[De:] 01/05/2026  [Até:] 31/05/2026

[Status:]  ☑ Pendente  ☑ Pago  ☐ Vencido  ☐ Cancelado
[Tipo:]    ☑ Receita   ☑ Despesa
[Conta:]   [Itaú PJ ▾]
[Categoria:] [Todas ▾]
[Cliente/Fornecedor:] [busca]
[Forma pgto:] ☑ Pix ☑ Boleto ☐ Cartão...
[Valor mín:] R$ ___  [Valor máx:] R$ ___

[Limpar] [Aplicar]
```

Filtros persistem em URL params (`?periodoPor=vencimento&de=...`) pra deep link.

### Funcionalidades extras
- **Conciliação bancária**: importar extrato OFX/CNAB → match automático com lançamentos por valor+data
- **Fluxo de caixa projetado**: gráfico com saldo previsto por dia (considera vencimentos futuros)
- **DRE comparativo**: mês a mês com %delta
- **Pagamento em lote**: marca várias contas → "Pagar todas" → registra data + forma
- **Recorrências**: lançamentos automáticos (aluguel, internet, salários)

---

## 7. Anexos Universais (Venda → Produção → Cliente)

### Problema atual
Cada entidade tem anexo isolado (`artworks`, `serviceOrderPhotos`). Difícil reusar arte da venda na produção.

### Modelo proposto — Tabela única `attachments`

```sql
CREATE TABLE attachments (
  id varchar(36) PRIMARY KEY,
  company_id varchar(36) NOT NULL,
  user_id int NOT NULL,                          -- quem subiu
  -- Polymorphic associations
  entity_type varchar(32) NOT NULL,              -- 'pedido','os','quote','customer','produto','op'
  entity_id varchar(36) NOT NULL,
  -- File
  file_key varchar(255) NOT NULL,                -- S3 key
  file_name varchar(255),                        -- nome original
  mime_type varchar(64),
  file_size_bytes int,
  -- Metadata
  categoria varchar(32),                          -- 'arte','prova','referencia','foto_entrada','foto_durante','foto_saida','documento','assinatura'
  visibility enum('interno','cliente','publico') DEFAULT 'interno',
  legenda varchar(255),                          -- descrição visível
  ordem int DEFAULT 0,                            -- ordem de exibição
  -- Propagation
  propagated_from_id varchar(36),                -- referência ao anexo original (se foi propagado)
  propagated_at varchar(32),
  -- Approval
  status enum('pendente','aprovado','rejeitado') DEFAULT 'pendente',
  approval_comment text,
  approved_by varchar(36),
  approved_at varchar(32),
  -- Whatsapp tracking
  enviado_whatsapp tinyint DEFAULT 0,
  enviado_whatsapp_at varchar(32),
  -- Audit
  created_at timestamp DEFAULT CURRENT_TIMESTAMP,
  INDEX (company_id),
  INDEX (entity_type, entity_id),
  INDEX (propagated_from_id)
);
```

### Fluxo: anexo de venda → produção

```
Vendedor cria pedido /pedidos/123
  └─ Upload "arte-final.pdf" (categoria=arte, visibility=interno)
       attachments.insert(entity_type='pedido', entity_id='123', ...)

Gerar OP → pedido.create_op()
  └─ Server propaga TODOS anexos do pedido para a OP:
       SELECT * FROM attachments WHERE entity_type='pedido' AND entity_id=:pedidoId
       Para cada anexo:
         attachments.insert(
           entity_type='op',
           entity_id=novaOpId,
           file_key=anexo.file_key,          -- mesmo arquivo S3 (sem duplicar)
           propagated_from_id=anexo.id,
           ...
         )

Produção abre /ops/[id]
  └─ Vê anexos com badge "Vindo da venda"
  └─ Pode adicionar próprios anexos (categoria=foto_durante)

Produção finaliza
  └─ Sobe foto categoria=foto_saida + visibility=cliente
  └─ Clica "Enviar ao cliente via WhatsApp"
       sendWhatsApp({
         phone: customer.whatsapp,
         message: "Olá! Sua peça está pronta. Fotos:",
         mediaAttachments: [
           { url: "https://.../foto_saida_1.jpg", caption: "Frente" },
           { url: "https://.../foto_saida_2.jpg", caption: "Verso" },
         ]
       })
       Marca attachments.enviado_whatsapp = 1
```

### UI

Componente `<OiAttachments entityType="pedido" entityId={id} />`:
- Grid 3-col de thumbnails
- Tap → preview tela cheia + ações (Baixar, Excluir, Mover, Enviar)
- Botão "+ Upload" → ActionSheet (Câmera, Galeria, Arquivo)
- Filtros: `Todos | Arte | Prova | Foto entrada | Foto durante | Foto saída`
- Toggle visibilidade: 👁 interno / 👁🌐 cliente

### Z-API com mídia

Z-API suporta `send-image`, `send-document`, `send-video`. Estender `sendWhatsApp` em `server/_core/whatsapp.ts`:

```ts
export async function sendWhatsAppMedia(input: {
  userId: number;
  companyId: string;
  telefone: string;
  caption: string;
  mediaUrl: string;     // pública (S3 com URL assinada)
  mediaType: 'image' | 'document' | 'video';
}) {
  const endpoint = mediaType === 'document'
    ? `send-document/${ext}`
    : mediaType === 'video' ? 'send-video' : 'send-image';
  // POST com { phone, image/document/video: mediaUrl, caption }
}
```

---

## 8. Sequenciamento — Como Executar

Ordem proposta (sprints de ~1 semana cada):

### Sprint 1 — RBAC foundation
- Schema: `role_v2` em `companyMembers` + permissions matrix
- Server: middleware `requirePermission`
- Client: `usePermissions()` hook + `<Gate>` component
- Migrar 3 features de teste pra usar permissões (financeiro, fiscal, users.manage)
- Tela `/configuracoes/usuarios` com convite + atribuição de papel

### Sprint 2 — Home contextual
- Refator `app/(tabs)/index.tsx` em switch por papel
- Criar 5 sub-componentes Home (Vendedor, Producao, Estoque, Financeiro, Gerencial)
- Cada Home consome só os endpoints permitidos pra aquele papel
- Aplicar `<Gate>` em toda Home Gerencial pra ocultar widgets

### Sprint 3 — Cliente completo
- Migration: estender `customers` (PF/PJ split, fiscal, LGPD)
- Integração viacep
- Tela `clientes/[id].tsx` em abas
- Form validation (CPF/CNPJ)
- Consentimento LGPD com timestamp+IP

### Sprint 4 — Produto completo
- Migration: estender `produtos` (fiscal, GTIN, NCM, variações)
- Tabela NCM local (json) + busca
- Scanner GTIN (`expo-camera`)
- Tela `produtos/[id].tsx` em abas + galeria de imagens
- Tabela `product_variations`

### Sprint 5 — Telas dedicadas (substituir popups)
- `/pedidos/[id]` (tela cheia, sem popup)
- `/pedidos/new`
- `/financeiro/[id]`, `/financeiro/new`
- `/ops/[id]/arte`, `/ops/[id]/execucao`, `/ops/[id]/finalizacao`
- Manter compatibilidade com fluxos existentes durante transição

### Sprint 6 — Anexos universais
- Tabela `attachments` + migration
- Backfill: `artworks` e `serviceOrderPhotos` → `attachments`
- Server: helper `propagateAttachments(fromEntity, toEntity)`
- Client: `<OiAttachments>` reutilizável
- Wire em pedidos.create_op, ops.advance_to_done

### Sprint 7 — Financeiro multi-data
- Migration: `data_emissao`, `data_vencimento`, `data_pagamento`, `status`, `forma_pagamento`, `parcela_*`
- Tabela `contas_bancarias` + `fornecedores`
- Tela `/financeiro` com painel de filtros
- Conciliação OFX (parser + match)
- Fluxo de caixa projetado (gráfico)

### Sprint 8 — WhatsApp com mídia
- Estender `sendWhatsApp` → `sendWhatsAppMedia`
- Z-API `send-image`/`send-document`/`send-video`
- Trigger automático: ao marcar OP `concluido` + ter fotos categoria=foto_saida visibility=cliente
- Notificar admin se cliente abriu link (Z-API webhook)

### Sprint 9 — DVI (Mecânica)
- Tabela `vehicle_inspections` + items checklist
- Tela `/oss/[id]/dvi` com 30+ pontos verde/amarelo/vermelho
- Foto/vídeo por item via câmera
- Geração PDF inspeção
- Portal cliente com aprovação por item

### Sprint 10 — Polish + Onboarding
- Onboarding wizard (3 passos) com sample data
- Skeletons consistentes
- Swipe-actions em todas listas
- Empty states ilustrados
- Tour interativo

---

## 9. Benchmark — Como os líderes fazem

### Bling
- Home por papel via "perfis de acesso" configuráveis
- Tela única de pedido com tabs (Dados, Itens, Pagamento, NFe, Histórico)
- Anexos universais com `entity_type+entity_id` similar à proposta
- Filtros financeiros multi-data + conciliação OFX

### Omie
- "Cargos" customizáveis + matriz de permissões granular
- Home com widgets arrastáveis por usuário
- Cadastro de cliente em 6 abas (Dados, Endereço, Contato, Fiscal, Financeiro, Outros)
- Integração viacep + consulta CNPJ na receita auto-preenche

### Conta Azul
- Roles fixos: Empresário, Contador, Funcionário
- Cobranças automáticas via WhatsApp/Email/SMS com templates
- Conciliação bancária automatizada via Open Finance (Belvo, Pluggy)

### TOTVS Meu Protheus
- App de **decisão móvel** (aprovações), não ERP completo no mobile
- Push notification por evento ERP
- Mantém o forte no web — mobile como complemento

### SAP Business One Sales/Service
- Mobile cache offline seletivo (escolher quais clientes/itens baixar)
- DVI integrado pra service orders
- Workflow customizável por filial

### Insights extraídos
1. **Roles configuráveis** (não só presets) é o padrão enterprise
2. **Cadastros em abas** (não scroll infinito) é padrão consagrado
3. **Anexos universais** é regra, não exceção
4. **Multi-data nos lançamentos** é obrigatório pra qualquer ERP brasileiro sério
5. **Mobile como complemento** (não substituto do web) é a estratégia dominante — vale termos web também no roadmap

---

## 10. Decisões de Produto Pendentes

Antes de começar, validar com o cliente:

1. **Papéis fixos ou configuráveis?**
   - Fixos (presets): mais simples, suficiente pra 80% dos casos
   - Configuráveis (matrix custom): mais flexível, padrão enterprise
   - **Recomendação**: começar com presets + permitir custom no plano Business

2. **Tabela única de attachments ou manter por entidade?**
   - Única: simpler queries, propagação fácil, padrão de mercado
   - Por entidade: tipagem mais forte
   - **Recomendação**: única (com tipo polymorphic)

3. **Fornecedor é entidade separada ou usa customers?**
   - Separada: mais limpo conceitualmente
   - Usar customers com flag `tipo_relacionamento=fornecedor`
   - **Recomendação**: separada (`fornecedores`) — tem campos próprios (banco fornecedor, certidões, condição pagto)

4. **DVI estoure no mobile ou só web?**
   - Mobile: ideal pro mecânico anotar no piso
   - Web: melhor visualização do resumo
   - **Recomendação**: mobile-first com fallback web pra impressão

5. **Sincronização offline expandida?**
   - Hoje: TanStack persist + mutation queue (Phase 3 P)
   - Próximo nível: WatermelonDB com full sync (precisa dev build)
   - **Recomendação**: manter pragmatic até hit de adoção real justificar

---

## 11. Métricas de Sucesso

Como saber que melhorou:

| Métrica | Hoje | Meta |
|---|---|---|
| Tempo médio criar pedido | ~2 min (popup) | <45s (tela cheia + atalhos) |
| Cliques pra fechar venda completa | 15+ | <8 |
| % pedidos com anexo | desconhecido | >60% |
| Tempo de produção até envio cliente | manual | <30s automatizado |
| Erro em NFe por dado de cliente | desconhecido | <1% |
| Adoção de papéis distintos | 1 (admin) | 3+ por empresa |
| Satisfação papel-específico | desconhecido | >8/10 NPS por papel |

---

## 12. Próximos Passos Imediatos

Pra começar, foco em **3 ondas** com entregas visíveis ao usuário:

### Onda 1 (2 semanas) — Foundation invisible mas essencial
- RBAC schema + middleware + Gate component
- Cadastro de cliente completo (substitui popup atual)
- Cadastro de produto completo (substitui popup atual)

### Onda 2 (2 semanas) — Telas dedicadas
- `/pedidos/[id]` + `/pedidos/new` (tela cheia)
- `/ops/[id]` melhorado com anexos + fotos
- Anexos universais (attachments table + propagação)

### Onda 3 (2 semanas) — Diferenciação
- Home contextual por papel
- Financeiro multi-data + conciliação
- WhatsApp com mídia + trigger automático em OP concluída

Cada onda termina com release no celular pra usuário testar e validar antes de avançar.

---

## Notas para a IA Desenvolvedora

- **Não quebrar** as features existentes — extension over modification
- **Schema additivo** sempre (nullable columns, novas tabelas, FKs sem cascade destrutivo)
- **Backfill** explícito em cada migration que muda significados
- **Permissões** server-side primeiro, client-side como UX overlay (nunca confiar no client)
- **LGPD**: registrar consentimento em audit log e oferecer "esquecer cliente" (anonimizar)
- **Português pt-BR** em toda UI até F5-07 (i18n) ser implementado
- **Mobile-first** mas com olhar pra web futura — não acoplar lógica de negócio ao componente RN

---

Documento revisado em 17 de maio de 2026. Status: **proposta — aguardando aprovação para início**.
