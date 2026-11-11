---
module: ComunicacaoVisual
purpose: "Vertical gráfica rápida BR (CNAE 1813-0/01), em construção sem piloto registrado: orçamento por m2 server-authoritative (salvar, aceite do cliente por link, aprovar), OS que nasce do orçamento e entra no quadro do PCP do Modules/Producao, agenda da instalação com aviso ao cliente, pós-cálculo da OS em JSON e o spool de apontamento sem tela. Faturamento da OS, cadastro de materiais e FSM própria não existem."
migracao_ui: "concluido — 0 Blade servido"
contains:
  - "AppOrcamentoRapidoController (orçamento rápido do app das lojas, /api/app/orcamentos — PR 16 propostas-app)"
  - "ApontamentoController"
  - "DataController"
  - "InstallController"
  - "OrcamentoController"
  - "OrcamentoArquivoController (arquivos anexados ao orçamento antes de virar OS — /comunicacao-visual/api/orcamentos/{id}/arquivos, UC-FLX-01)"
  - "OrcamentoAceiteController (gera o link de aceite do orçamento — POST /comunicacao-visual/api/orcamentos/{id}/link, US-COMVIS-023 · UC-CVACE-01/02)"
  - "Public/OrcamentoAceitePublicoController — página pública SEM login onde o cliente aceita ou pede ajuste do orçamento (/orcamento-cv/{token}, token sha256 de uso único, throttle 30/1, US-COMVIS-023 · UC-CVACEP-01..04)"
  - "RoteiroPadraoController (roteiro padrão da OS da gráfica — GET/PUT /comunicacao-visual/api/roteiro-padrao, US-COMVIS-003 · UC-CVOS-09..11)"
  - "Services/MargemDoOrcamento (custo e margem por peça do orçamento — UC-FLX-03)"
  - "Entities/Substrato (cv_substratos)"
  - "Entities/Acabamento (cv_acabamentos)"
  - "Entities/InstalacaoCatalogo (cv_instalacoes_catalogo)"
  - "Entities/OrdemProducao (cv_ordens_producao + FSM canon ADR 0143)"
  - "Entities/Instalacao (cv_instalacoes)"
  - "InstalacaoController (agenda da instalação por OS — /comunicacao-visual/api/instalacoes, US-COMVIS-007 · UC-FLX-21)"
  - "AgendaController (tela da agenda de instalação em semana × equipe — /comunicacao-visual/agenda, US-COMVIS-007 · UC-FLX-21)"
  - "Services/AgendaInstalacao (agenda equipe e veículo na OS; recusa sem janela — US-COMVIS-007 · UC-FLX-21)"
  - "Entities/OsItem (comvis_os_itens — itens da OS copiados do orçamento, UC-FLX-01)"
  - "Services/OrcamentoViraOs (orçamento aprovado vira OS com itens, medidas e arquivos — US-COMVIS-020 · UC-FLX-01)"
  - "OsPosCalculoController + Services/PosCalculoDaOs (custo orçado × real e margem da OS, flag COMVIS_POS_CALCULO_ENABLED — US-COMVIS-005)"
  # Mora em `database/seeders/` (convenção do projeto pros seeders de FSM canon), NÃO sob
  # Modules/ComunicacaoVisual/ — o SDD §5.4.5 já registra isso. Path anotado em 2026-08-10
  # porque a ausência sob o módulo já foi lida como "nunca construído": é código real,
  # criado em #676 (2026-05-12) e exercitado por 6 pontos do Tier0GuardTest.
  - "FsmProcessoComunicacaoVisualSeeder (16 stages + 10 roles per-business)"
not_contains:
  - "Kanban shared infra → Modules/Repair (consumido via repair_settings)"
  - "Núcleo transactions/contacts → UltimatePOS core"
  - "NFe backbone → Modules/NfeBrasil (consumido)"
  - "FSM canon (sale_processes/stages/actions) → app/Domain/Fsm/ (shared infra ADR 0143)"
  - "Quadro do PCP, roteiros e apontamento por etapa → Modules/Producao (consumido via App\\Contracts\\Producao, ADR 0431/0436)"
  - "Aviso ao cliente por WhatsApp → Modules/Whatsapp (ouve o evento InstalacaoAgendada)"
trust_required: L2
owner: wagner
permission_prefix: comvis.*
charter_adr: 0121
related_adrs:
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0093-multi-tenant-isolation-tier-0
url_prefixes:
  - /comunicacao-visual/* (web + api + install)
drift_alerts: []
---

# Modules/ComunicacaoVisual — vertical gráfica rápida BR

> ADR mãe: [0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) §P7
> SPEC: [memory/requisitos/ComunicacaoVisual/SPEC.md](../../requisitos/ComunicacaoVisual/SPEC.md)
> Status: 🟡 em construção · a previsão de piloto em 2026-Q3 passou sem piloto registrado (nova data é decisão [W])
> CNAE: 1813-0/01 · Concorrentes: Mubisys, Zênite, Calcgraf

## Estado Fase 1 V0 scaffold (2026-05-12)

Scaffold nWidart **completo** + 5 migrations canon `cv_*` SPEC §12.1 + 5 Entities com BusinessIdScope + FsmProcessoComunicacaoVisualSeeder (16 stages × 30+ actions × 10 roles per-business).

Coexiste com legacy Sprint 1 (`comvis_*` tables — Material/Orcamento/Os/Apontamento) — Migration Factory US-COMVIS-NEW-014 (Sprint 2+) decide caminho de unificação.

1. **Habilitar piloto Q3/2026** entre 6 saudáveis OfficeImpresso (Gold confirmado vertical comvis; Extreme/Zoom/Fixar/Mhundo/Produart a confirmar — ROADMAP Fase 2)
2. **Consumir FSM canon shared** `app/Domain/Fsm/` (ADR 0143 LIVE prod biz=1 desde 2026-05-12) via `current_stage_id` em `cv_ordens_producao` + trait `GuardsFsmTransitions`
3. **Diferencial vs concorrentes:** cálculo m² + PCP gráfico FSM + dual-doc fiscal NFe55+NFSe56 paralelo + NFe-de-boleto-pago + IA conversacional (5 capacidades juntas, nenhum concorrente tem todas — Mubisys/Zênite/Calcgraf SPEC §4-5)

## User stories

A numeração e o significado das US são os do [SPEC](SPEC.md): US-COMVIS-001 cálculo por m², 002 cadastro de
material, 003 PCP (OS no quadro do Producao), 004 apontamento de máquina, e assim por diante. Até 2026-11-10 esta
seção trazia uma tabela "Sprint 2+" com outra numeração (001 = scaffold, 002 = migration, 003 = telas, 004 = NfeBrasil),
que contradizia o SPEC. O estado de cada US está na linha `**Implementado em:**` dela.

## Não-goals

- ❌ NÃO abrir backlog por hipótese do agente sem sinal de cliente ou métrica [ADR 0105]; pedido do [W] é decisão, não proposta [ADR 0382]
- ❌ NÃO duplicar o quadro, os roteiros nem o apontamento por etapa do Modules/Producao (consome via `App\Contracts\Producao`)
- ❌ NÃO substituir núcleo UltimatePOS
