---
slug: proposal-comunicacao-visual-modulo-canonico
number: TBD
title: "Modules/ComunicacaoVisual canônico — schema dedicado + FSM CV-específico + dual-doc fiscal + comissão multi-vendedor"
type: adr
status: proposed
authority: candidate
lifecycle: proposal
decided_by: []
decided_at: null
proposed_at: 2026-05-12
proposed_by: [Claude + W]
module: ComunicacaoVisual
tags: [comunicacao-visual, modular, vertical, fsm, schema, fiscal-dual, comissao, proposed]
supersedes: []
amends: []
related: [0121, 0143, 0093, 0094, 0105, 0106, 0117, 0136, 0011, 0024]
pii: false
review_triggers:
  - "1ª piloto migrada (Gold/Extreme) chegar a ≥30 OS em prod → revisar stages efetivos vs propostos"
  - "Sinal qualificado de 2º cliente Comunicação Visual diverge do schema (campos faltando) → revisar Entities"
  - "ROI matriz mostrar feature P1 (ex acabamento split) sem demanda em 6m → degradar P2"
---

# ADR proposta — Modules/ComunicacaoVisual canônico

> **Status:** `proposed`. Wagner decide accept/reject após revisar SPEC + MATRIZ-ROI + ROADMAP. Esta ADR consolida 7 decisões arquiteturais críticas pra evitar retrabalho na construção do módulo.

## Contexto

`Modules/ComunicacaoVisual` foi declarado em [ADR 0121](../../0121-oimpresso-modular-especializado-por-vertical.md) (modular especializado por vertical) como **vertical em construção** com 1 piloto candidato (Gold — confirmado comvis por Wagner 2026-05-11, ver [perfil](../../../research/clientes-legacy-officeimpresso/04-gold-comvis/01-perfil.md)). SPEC base ([SPEC.md](../../../requisitos/ComunicacaoVisual/SPEC.md)) e charter ([ComunicacaoVisual.charter.md](../../../requisitos/ComunicacaoVisual/ComunicacaoVisual.charter.md)) existem mas **NÃO definem decisões fundacionais** — schema vs reuso Repair, FSM CV vs venda_com_producao, vinculação Transaction, comissão multi-papel.

Com [ADR 0143](../../0143-fsm-pipeline-live-prod-marco-2026-05-12.md) (FSM Pipeline canônico LIVE em prod biz=1) habilitando a fundação, é **agora a hora barata** pra firmar essas decisões: ainda sem código produtivo CV, ainda sem cliente pagante no módulo (sinal-zero, Caminho [ADR 0105](../../0105-cliente-como-sinal-guiar-sem-mandar.md) permite "fundação" mas trava expansão até sinal).

## Decisões propostas (7 pontos)

### D1 — Schema dedicado `Modules/ComunicacaoVisual/Entities/*`, NÃO estender `repair_job_sheets`

**Decisão:** módulo cria suas próprias tabelas `cv_*` (FK pra `transactions` via `transaction_id` nullable + `current_stage_id` FSM canônico).

**Razão:** Modules/Repair é shared infra ([ADR 0121 §P8](../../0121-oimpresso-modular-especializado-por-vertical.md)), mas o domínio CV tem campos próprios incompatíveis com Repair OS de bancada (substrato, dimensões m², acabamento split, instalação tipo, endereço, equipamento NR-35). Reusar `repair_job_sheets` força hard-code de vocabulário gráfico no shared infra — quebra P1 ADR 0121.

**Alternativa rejeitada:** caminho 2 (estender Repair) — viola P1; caminho 3 (híbrido — UI Repair Kanban + tabela CV) ACEITO via **reuso de COMPONENTES Kanban frontend Repair** com Models CV próprios (componente puro React `<Kanban items columns onMove>` recebe items CV, não Repair Jobs).

### D2 — FSM CV reusa fundação `sale_processes` + processo seed `OS Comunicação Visual`

**Decisão:** stages CV ficam em `sale_process_stages` com `business_id` global scope (FSM canon ADR 0143). Vincular a OrdemProducaoCv via `current_stage_id`. **NÃO criar tabelas FSM próprias.**

**Razão:** ADR 0143 §"Reusabilidade" prova fundação é multi-domínio (Sells + Repair já compartilham). `sale_*` é prefixo histórico — backlog ADR ([ADR 0143 §"Plano de adoção"](../../0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) propõe rename `Sale*` → `Fsm*` em ADR futura. CV entra na fundação tabular existente; ganha gratuito: audit trail (`sale_stage_history`), RBAC granular per-business (Spatie suffix `#{biz}`), gateway obrigatório `ExecuteStageActionService`, fail-secure `is_critical`, side-effects isolados.

**Stages CV-específicos propostos** (13 ativos + 2 laterais + 1 terminal — espelha caso prático [CASO-PRATICO-OS-COMUNICACAO-VISUAL.md](../../../requisitos/Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md) ampliado):

```
quote_draft (initial)
  → quote_sent
  → quote_approved
  → arte_em_aprovacao              ← split do "aprovado" pra refletir designer→cliente
  → arte_aprovada
  → aguardando_maquina             ← stage opcional PCP gráfico industrial (Extreme-style)
  → em_impressao
  → impressao_concluida
  → aguardando_acabamento          ← split obrigatório (corte/ilhós/costura/perfuração — 1 stage genérico, sub-itens em JSON acabamento_json)
  → acabamento_concluido
  → aguardando_instalacao          ← skip se instalacao_tipo='cliente_busca'
  → em_instalacao
  → instalado_aguardando_aprovacao_final  ← cliente recebe foto pós + assina digital
  → entregue_completo (T)
Laterais (terminais):
  → rejeitar_arte → quote_approved (loop volta + side-effect: NotificarDesigner)
  → refazer_impressao → em_impressao (side-effect: ConsumirEstoqueExtra + AlertaMargem)
  → reagendar_instalacao → aguardando_instalacao (side-effect: AtualizarAgenda)
  → cancelado (T) — qualquer stage não-terminal, side-effect CancelarVendaCascade
  → garantia_acionada (T) — pós entregue_completo
```

**Actions críticas (🔒) propostas:**
- `aprovar_arte` (🔒 — designer confirma; bloqueia recálculo m² pós-NFe — anti-hook #2 charter)
- `iniciar_impressao` (🔒 — confirmação humana, NUNCA disparar plotter automaticamente — anti-hook #1 charter)
- `concluir_impressao` (🔒 ConsumirEstoque substrato — m² lona consumida do reservation)
- `concluir_instalacao` (🔒 — desbloqueia faturar; gera assinatura cliente LGPD-consent)
- `emitir_nfe_e_nfse` (🔒 — dispatch DOIS jobs em paralelo, ver D3)
- `cancelar_os` (🔒 — qualquer stage não-terminal, LiberarReserva)

**Override por business:** cada gráfica habilita/desabilita stages opcionais (`aguardando_maquina` ativo só em Extreme PCP industrial, off em Gold comvis sob demanda — observação cross-cliente `_ANALISE-CROSS-CLIENTE.md` §3.2).

### D3 — Vinculação NFe55 + NFSe56 simultânea via `transaction_documents` poly (já existente)

**Decisão:** 1 OrdemProducaoCv → 1 Transaction → N documents (NFe55 banner + NFSe56 instalação + opcional MDFe58 transporte). Reusar `transaction_documents` poly N:1 já modelado em US-SELL-014.

**Razão:** caso prático [CASO-PRATICO-OS-COMUNICACAO-VISUAL.md](../../../requisitos/Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md) já cobre. Action FSM `emitir_nfe_e_nfse` dispatcha **AMBOS jobs em paralelo** (`EmitirNfeJob(banner_id)` + `EmitirNfseJob(instalacao_id, item_lc=17.06)`) — falha 1 NÃO bloqueia o outro (cada job retry exponencial 24h independente).

**Stage `aguardando_emissao_fiscal` adicionado entre `instalado_aguardando_aprovacao_final` e `entregue_completo`?** Não — emissão fiscal é side-effect, não stage. Stage representa estado físico (cliente aprovou? produto entregue?), fiscal é evento orçamental separado. Documents preenchem ao longo do tempo, UI mostra status independente do stage.

**Diferencial competitivo (battle card):** Mubisys/Zênite/Calcgraf fazem **2 vendas separadas** pra OS produto+serviço (cadastro duplo, financeiro duplo, estoque duplo). oimpresso 1 OS → 2 docs atrelados é wedge real.

### D4 — Pricing m² × substrato × acabamento — Service `OrcamentoCalculator` (não fórmula no Model)

**Decisão:** cálculo m² fica em `Modules/ComunicacaoVisual/Services/OrcamentoCalculator.php` (SoC brutal ADR 0094 §5). Model `OrdemProducaoCv` armazena dimensões + FK substrato; Calculator computa.

**Razão:** server-side authoritative (regra R-COMVIS-001 charter) — nunca confiar em frontend pra preço. Calculator tem 1 método público `calcularOrdem(OrdemProducaoCv $os): OrcamentoCalculadoDTO` retornando subtotal/extras/total + breakdown auditável. Frontend mostra preview em tempo real chamando endpoint `POST /comvis/orcamento/calcular` (não Model.toArray).

**Fórmula canônica:**
```
area_m2     = max(largura_m × altura_m, substrato.minimo_m2)
subtotal    = area_m2 × substrato.preco_venda_m2 × qtd
acabamentos = Σ (acabamento.preco_m_linear × area_m2.perimetro_m
                  OR acabamento.preco_unit × qtd)
instalacao  = instalacao.preco_base + (instalacao.preco_m2 × area_m2_instalada)
              + (instalacao.preco_km × distancia_km_endereco)
extras      = acabamentos + instalacao + (entrega ? frete_calc : 0)
total       = subtotal + extras − desconto
```

### D5 — Comissão multi-vendedor/instalador via `commission_distribution_json` em OrdemProducaoCv

**Decisão:** campo JSON `commission_distribution_json` no Model OrdemProducaoCv contém array:

```json
[
  {"user_id": 12, "papel": "vendedor",   "tipo": "pct_total",         "valor": 5.0},
  {"user_id": 19, "papel": "designer",   "tipo": "fixo",              "valor": 50.00},
  {"user_id": 7,  "papel": "instalador", "tipo": "pct_instalacao",    "valor": 30.0}
]
```

**Razão:** cenário Gold/Extreme tem múltiplos papéis (vendedor calcula+aprova, designer faz arte, instalador externo cobra). Tabela separada `commission_lines` superengineering pra V1 — JSON suficiente, indexação só sobre `os_id` (consulta "comissões deste mês" via Service `ComissaoService::calcularDoMes($business_id, $mes)`).

**Trigger:** action FSM `concluir_instalacao` ou `marcar_pago` (dependendo do business — alguns pagam comissão sobre faturado, outros sobre recebido) dispatcha `CalcularComissaoOsJob` que lê JSON, calcula valores, cria lançamentos `Modules/Financeiro` em `comissao_pendente`.

**Override config-business:** flag `business.comvis_settings.comissao_sobre = 'faturado'|'recebido'` (default 'recebido' — alinha tax cash, regime maioria Simples).

### D6 — NFSe per-município começa com 3 prefeituras (não 100+)

**Decisão:** Modules/NfeBrasil já tem framework abstrato pra NFSe (PR #653 ADR 0143). CV consome via interface `NfseDriver`. **Implementar 3 drivers prioritários no M5-M6 baseado em geografia das 6 saudáveis:**

| Driver | Município | Padrão técnico | Razão |
|---|---|---|---|
| `NfseDriverGravatal` | Gravatal/SC | ABRASF v2.04 (modelo Floripa SC) | ROTA LIVRE biz=4 — mesmo Modules/Vestuario consome |
| `NfseDriverFloripa` | Florianópolis/SC | ABRASF v2.04 SOAP | hub regional — Extreme se em SC |
| `NfseDriverGoiania` (a confirmar) | Goiânia/GO | ABRASF v2.04 ou IPM | se Gold for Goiás conforme post-mortem |

**Razão:** ADR 0143 §"Negativas/Trade-offs" reconhece "0 drivers reais implementados (cada padrão exige cert A1 + sandbox município)". Não implementar especulativamente — sinal qualificado ([ADR 0105](../../0105-cliente-como-sinal-guiar-sem-mandar.md)): só implementar driver com cliente pagante no município.

**Stub para os demais:** action `emitir_nfse` rodando em município sem driver registrado dispara Event `NfseMunicipioSemDriver` + lança Exception graceful (job marcado falhado, UI mostra "Município X precisa de driver — registre US-NFSE-CANCEL-XXX").

### D7 — Workflow arte aprovação via WhatsApp consume `Modules/Whatsapp` multi-números ADR 0117

**Decisão:** ao chegar stage `arte_em_aprovacao`, side-effect `NotificarClienteAprovacaoArteJob` envia mensagem WhatsApp via número configurado em `business.whatsapp_numero_arte_id` (FK pra `whatsapp_numeros` per-business).

**Razão:** ADR 0117 já permite múltiplos números per-business (vendas vs suporte vs cobrança). Gráficas multi-canal usam número dedicado "aprovação de arte" pra cliente não confundir. Default = número principal se não configurado.

**Conteúdo mensagem:** link assinado curta-validade pra `/b/{slug}/arte-aprovacao/{token}` (rota pública sem auth, token 7d, valida via signed URL Laravel). Cliente clica → vê preview imagem + 2 botões (Aprovar / Solicitar alteração). Aprovar dispatcha action FSM `aprovar_arte` em nome do `system_user`. Solicitar dispatcha `rejeitar_arte` + campo motivo livre.

**LGPD consent:** action `enviar_para_aprovacao_arte` confirma `contact.whatsapp_consent === true` antes de dispatch (ADR 0143 §LGPD). Sem consent → fallback email (se `email_consent === true`) → sem nenhum → log warning + UI alerta "Cliente sem canal — contate manualmente" (segue padrão Sells/Repair).

## Multi-tenant Tier 0 amarração ([ADR 0093](../../0093-multi-tenant-isolation-tier-0.md))

- ✅ Toda Model CV (OrdemProducaoCv, SubstratoCatalogo, AcabamentoCatalogo, InstalacaoCatalogo) tem `business_id` indexado + FK + global scope `BusinessIdScope`
- ✅ `commission_distribution_json` referencia `user_id` que devem pertencer ao `business_id` da OS (validação no Service, não DB constraint)
- ✅ Jobs assíncronos (`EmitirNfeJob`, `EmitirNfseJob`, `CalcularComissaoOsJob`, `NotificarClienteAprovacaoArteJob`) recebem `$businessId` no constructor (nunca `session()`)
- ✅ Rotas web scoped por `session('user.business_id')` — 404 silencioso anti-info-leak
- ✅ Stages CV-específicos cadastrados PER-business (cada gráfica habilita/desabilita `aguardando_maquina` etc) via `sale_process_stages.business_id` FK
- ⛔ NUNCA hard-code CNAE 1813 / vocabulário gráfico no núcleo UltimatePOS — quebra ADR 0121 §P1

## Schema proposto (resumo — detalhes em SPEC.md)

```sql
-- cv_ordens_producao (substituí "comvis_os" simples por OS produção rica)
id, business_id (FK + scope), orcamento_id (FK opcional), transaction_id (FK opcional pro fiscal),
codigo (sequencial biz-scoped), contato_id (FK contacts),
current_stage_id (FK sale_process_stages — FSM canon ADR 0143),
substrato_id (FK cv_substratos), largura_m DECIMAL(8,3), altura_m DECIMAL(8,3),
qtd INT, area_m2 DECIMAL(10,4) GENERATED virtual,
acabamento_json JSON, -- [{tipo:"corte_reto", m_linear:6.0}, {tipo:"ilhos", qtd:8}]
instalacao_tipo ENUM('cliente_busca','equipe_propria','terceirizada'),
endereco_instalacao_json JSON, equipamentos_necessarios_json JSON, -- ["escadote","andaime_6m"]
arte_url VARCHAR(500), arte_aprovada_em TIMESTAMP NULL,
estimated_completion DATETIME, prazo_prometido DATE, -- mapeia PROJETO_DT_FIM Delphi
commission_distribution_json JSON,
subtotal DECIMAL(12,2), extras DECIMAL(12,2), total DECIMAL(12,2),
created_at, updated_at

-- cv_substratos (catálogo material com preço m²)
id, business_id, nome, categoria ENUM('lona','vinil','adesivo','acm','tela','mdf','neon','letra_caixa'),
gramatura_g_m2 INT NULL, preco_custo_m2 DECIMAL(8,2), preco_venda_m2 DECIMAL(8,2),
minimo_m2 DECIMAL(6,3) DEFAULT 0.5, fornecedor_id (FK), ncm, cfop_padrao, csosn_padrao,
ativo BOOLEAN, created_at, updated_at

-- cv_acabamentos (catálogo: corte, ilhós, costura, perfuração, aplicação adesivo)
id, business_id, nome, tipo ENUM('m_linear','unitario','m2','fixo'),
preco DECIMAL(8,2), -- semantica varia por tipo
descricao, ativo

-- cv_instalacoes_catalogo (kit alvará, escadote, equipe — preço composto)
id, business_id, nome, preco_base DECIMAL(8,2), preco_m2 DECIMAL(8,2),
preco_km DECIMAL(8,2), exige_nr35 BOOLEAN, ferramentas_necessarias_json, ativo

-- cv_instalacoes (execução real — agendamento + comprovação)
id, business_id, ordem_id (FK cv_ordens_producao),
equipe_user_ids_json, data_agendada DATETIME, data_realizada DATETIME NULL,
foto_pre_url, foto_pos_url, assinatura_cliente_url,
lat_lng_inicio POINT, lat_lng_fim POINT,
nfse_emissao_id (FK nfe_documents NULLABLE), comissao_calculada_json
```

Total: **4 catálogos** (substrato, acabamento, instalacao_catalogo, máquinas se Extreme) + **2 transacionais** (ordens, instalações). Mais 2 herdadas (`cv_orcamentos`, `cv_apontamentos` máquina opcional Extreme).

## Alternativas avaliadas

### Caminho A — Repair compartilhado puro (rejeitado)
- Estender `repair_job_sheets` com colunas CV (substrato_id, area_m2, etc).
- ❌ Quebra ADR 0121 §P1 (vocabulário vertical no shared infra).
- ❌ Repair OS de bancada (eletrônica/oficina) tem semântica radicalmente diferente — peça quebrada vs substrato impresso.
- ❌ Inflar `repair_job_sheets` com 15+ campos NULL pra 90% das OSes Repair = dívida técnica imediata.

### Caminho B — Schema dedicado SEM reuso FSM (rejeitado)
- Criar `cv_stages` próprio, `cv_stage_actions`, `cv_stage_history`.
- ❌ Duplica fundação canon ADR 0143. Resultado: 2 implementações FSM no mesmo monolith — manutenção dobra.
- ❌ Perde RBAC granular per-business + audit trail unificado.

### Caminho C — Schema dedicado + FSM reuso (ESCOLHIDO)
- `Modules/ComunicacaoVisual/Entities/*` próprio.
- FSM em `sale_process_stages` (canon ADR 0143) com processo seed "OS Comunicação Visual" cadastrado per-business.
- ✅ Vocabulário CV isolado em Models próprios.
- ✅ Fundação RBAC + audit + gateway reaproveitada sem duplicar.
- ✅ ADR 0121 §P8 (Repair shared infra) preservado — CV reusa Kanban COMPONENTE Inertia/React, não Models Repair.

## Consequências

### Positivas
1. **Schema CV expressivo** — vocabulário gráfico nativo (substrato, gramatura, m², acabamento, instalação NR-35) sem poluir shared infra
2. **FSM canon reaproveitado** — sem duplicar fundação ADR 0143; herda RBAC + audit + gateway gratuitos
3. **Dual-doc fiscal nativo** — NFe55 + NFSe56 simultâneos em 1 OS (wedge vs Mubisys/Zênite)
4. **Comissão multi-papel via JSON** — flexível pra papéis variados (vendedor/designer/instalador) sem nova tabela
5. **Driver NFSe sob demanda** — só implementar municípios com cliente pagante (ADR 0105 sinal qualificado)
6. **WhatsApp arte-aprovação** — reuso ADR 0117 multi-números (cliente percebe canal dedicado)
7. **Override per-business** — Extreme PCP industrial ativa `aguardando_maquina`, Gold comvis sob demanda desativa

### Negativas / Trade-offs
1. **commission_distribution_json sem FK validation** — `user_id` referenciado pode estar inválido; validação só no Service. Mitigação: Pest test guard cobrindo casos.
2. **Stages 13+2+1 = 16 stages CV** — mais que Sells canon (9) e Repair (10). Justificado pela natureza multi-etapa do domínio gráfico (arte + impressão + acabamento + instalação são fases físicas distintas), mas UI Kanban precisa scroll horizontal — design challenge.
3. **`current_stage_id` pode apontar pra stage de outro business** — multi-tenant Tier 0 exige validação na ação FSM (já feita por ExecuteStageActionService canon).
4. **Driver NFSe 3 prioritários** = lock-in nos primeiros 3 municípios. Mitigação: framework abstrato + interface registry permite extensão sem refactor.
5. **JSON acabamento_json não-relacional** — busca "todas OS com ilhós este mês" exige SQL JSON_EXTRACT (MySQL 8+). Aceito — analytics secundário, não core path.

## Plano de adoção

1. **Wagner aprova esta ADR** (status: proposed → accepted)
2. **Criar `Modules/ComunicacaoVisual/` scaffold** via skill `criar-modulo` (8 peças + 3 rotas Install) + Pest GUARD Tier 0 (multi-tenant + anti-hooks charter)
3. **Migrations** das 6 tabelas core + seed processo `OS Comunicação Visual` (13 stages + 6 actions críticas + 10 roles per-business)
4. **Service `OrcamentoCalculator`** + Pest 6+ casos (banner, vinil adesivo recortado, ACM com instalação, brinde unitário, etc.)
5. **Reuso Kanban COMPONENTE** Inertia/React (`Modules/Repair/resources/js/_components/KanbanBoard.tsx` extraído pra `Components/shared/`)
6. **1ª piloto Gold migrada** via Migration Factory (snapshot financeiro pré-venda + import Firebird → cv_substratos/cv_ordens)

## Refs

- [ADR 0121](../../0121-oimpresso-modular-especializado-por-vertical.md) — Modular especializado por vertical (mãe)
- [ADR 0143](../../0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM Pipeline canônico LIVE em prod
- [ADR 0093](../../0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0094](../../0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0105](../../0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal qualificado
- [ADR 0106](../../0106-recalibracao-velocidade-fator-10x-ia-pair.md) — Recalibração velocidade 10x IA-pair
- [ADR 0117](../../0117-multiplos-numeros-whatsapp-por-business.md) — Múltiplos números WhatsApp per-business
- [ADR 0136](../../0136-sells-grade-avancada-modo-toggle.md) — Sells Grade Avançada (heatmap CV)
- [SPEC.md ComunicacaoVisual](../../../requisitos/ComunicacaoVisual/SPEC.md) — base ampliada
- [ComunicacaoVisual.charter.md](../../../requisitos/ComunicacaoVisual/ComunicacaoVisual.charter.md) — charter módulo
- [CASO-PRATICO-OS-COMUNICACAO-VISUAL.md](../../../requisitos/Sells/CASO-PRATICO-OS-COMUNICACAO-VISUAL.md) — caso prático fiscal
- [_ANALISE-CROSS-CLIENTE.md](../../../research/clientes-legacy-officeimpresso/_ANALISE-CROSS-CLIENTE.md) — sinal cross-cliente (Gold/Extreme)
- [04-gold-comvis/01-perfil.md](../../../research/clientes-legacy-officeimpresso/04-gold-comvis/01-perfil.md) — piloto Gold qualificado
- [_LICOES-CRITICAS.md](../../../research/clientes-legacy-officeimpresso/_LICOES-CRITICAS.md) — anti-bugs prévia

## Decisões pendentes pra Wagner

1. **Stage `aguardando_maquina` opcional vs sempre presente** — Extreme tem PCP industrial (52k linhas centro_trabalho), Gold não. Proposta: cadastrar mas `disabled=true` per-business via UI admin. Wagner: aceita?
2. **Comissão JSON vs tabela `cv_commission_lines`** — JSON suficiente pra V1 ou já preparar tabela relacional pensando em escala (analytics "top vendedores trimestre")? Proposta: JSON; promover pra tabela quando 1ª gráfica passar 100 OS/mês.
3. **Driver NFSe ordem** — Floripa primeiro (ABRASF v2.04 + cliente potencial Extreme em SC?) ou Goiânia (se Gold confirmar GO)? Wagner valida geografia das 6 saudáveis após snapshot financeiro.

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-12 | Claude (sub-agent CV-discovery) sob direção W | Proposta inicial — consolida 7 decisões arquiteturais críticas pra Modules/ComunicacaoVisual evitar retrabalho. Status `proposed`. Aguarda Wagner aceitar/ajustar/rejeitar. |
