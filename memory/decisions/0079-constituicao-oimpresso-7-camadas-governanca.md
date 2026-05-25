---
slug: 0079-constituicao-oimpresso-7-camadas-governanca
number: 79
title: "Constituição do Oimpresso ERP — 10 artigos supremos sobre 7 camadas de governança"
type: adr
status: superseded
authority: canonical
lifecycle: substituido
decided_by: [W]
decided_at: "2026-05-05"
superseded_at: 2026-05-06
superseded_by_adr: '0094'
module: governance
quarter: 2026-Q2
tags: [governance, constitution, foundation, p0, supreme, superseded]
supersedes: []
supersedes_partially:
  - 0078-constituicao-uma-frase-skill-unidade-evolucao
superseded_by:
  - '0094'
  - 0094-constituicao-v2-7-camadas-8-principios
related:
  - 0040-policy-publicacao-claude-supervisiona
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0070-jira-style-task-management-current-md-removed
  - 0072-maturacao-memoria-team-mcp-openclaw-soa-2026
  - 0076-skills-db-primary-git-destino-drift-alert
  - 0077-mcp-resolver-owner-via-mcp-handle
  - 0078-constituicao-uma-frase-skill-unidade-evolucao
pii: false
review_triggers:
  - "Mudança regulatória em LGPD ou Portaria 671/2021 que invalide algum artigo"
  - "Time crescer >10 pessoas com necessidade de Trust Tiers refinados"
  - "Auditoria externa indicar gap em audit"
  - "Anthropic/AWS/Cedar publicarem pattern superior pra governança formal"
---

# ADR 0079 — Constituição do Oimpresso ERP

## Contexto

Sessão 2026-05-05 percorreu três pivôs sobre governança:

**Pivô 1 — Drift por falta de fronteira.** Auditoria de 30 módulos revelou: nenhum SCOPE.md, 9 controllers em módulos errados (Copiloto/ADS dumping ground), CYCLE-02 nunca persistido em DB apesar de aparecer em handoff, 24 commits anteriores sem entries em `mcp_tasks`. Causa raiz: ausência de fronteira documentada e enforced.

**Pivô 2 — Aposta concentrada (ADR 0078).** Wagner pediu "1 única aposta. Skill e uma missão. só isso." e cunhou a fórmula recursiva `Planejar→Executar→Analisar→Organizar`. Construímos meta-skill `meta-skill-roi-erp-autonomo` + comando `php artisan skill:scaffold` + sistema de scaffolding de skills. ADR 0078 formalizou a aposta de que **governança emerge da unidade** (skill+missão) em vez de descer em camadas.

**Pivô 3 (atual) — Caminho seguro.** Wagner reavaliou: *"eu vou querer segurança. isso não me parece correto. Construa a constituição que tinha proposto antes. e vamos seguir pelos caminhos seguros."*

A escolha entre concentrar tudo numa skill recursiva vs construir governança em camadas tradicionais resolveu-se a favor da abordagem em camadas. Razões da reversão (declaradas pelo Wagner em sessão):

1. **Aposta concentrada não dá visibilidade visual.** Sem UI de governança consolidada, Wagner não consegue operar como root. A aposta de "skills auto-load no contexto = governance" depende de algo invisível ao operador.
2. **Risco de blast radius.** Se a meta-skill tem bug de validação, todas as skills filhas saem mal. Sem camadas separadas, a falha cascata.
3. **Compliance exige formalização.** LGPD, Portaria 671, NF-e exigem trail explícito — não dá pra confiar que "skill carregada = compliance verificado". Tem que ter audit log + ActionGate rastreáveis.
4. **Time crescer requer estrutura.** 5 pessoas hoje, IAs externas conectando amanhã. Identity Mesh + Trust Tiers + SCOPE.md são necessários antes de escalar.

## Decisão

**Adotar a Constituição em 10 artigos sobre 7 camadas de governança como fundação canônica do oimpresso.**

### Os artefatos canônicos (criados nesta ADR)

1. **`memory/governance/_README.md`** — mapa das 7 camadas (Constitution → SRS → Trust Tiers → Identity Mesh → Module Charter → Policy Gating → Audit) + estado de implementação + como navegar.
2. **`memory/governance/CONSTITUTION.md`** v1.0.0 — 10 artigos supremos:
   1. Soberania (Wagner é root)
   2. Multi-tenancy invariante
   3. Imutabilidade onde lei/negócio exige
   4. Compliance regulatório inegociável
   5. Trust Tiers (L0-L4)
   6. Identity Mesh (manifest por actor)
   7. Module Charter (SCOPE.md por módulo)
   8. Policy Gating (ActionGate)
   9. Auditoria mandatória
   10. Evolução constitucional (semver + ADR)

### Relação com ADR 0078 (relativizada, não revogada)

ADR 0078 fica como **`supersedes_partially`** — a aposta de "constituição é 1 frase" foi superseded pelo modelo em 10 artigos. Mas:

- A **meta-skill `meta-skill-roi-erp-autonomo`** continua válida e operacional como ferramenta de scaffolding de skills. Vira **L2 OPERATOR ferramenta**, não L1 CONSTITUTION.
- O **comando `php artisan skill:scaffold`** continua funcionando.
- A **fórmula `Planejar→Executar→Analisar→Organizar`** continua válida como template do ciclo de vida de qualquer unit (skill, módulo, decisão, integração).
- O que muda: a frase deixa de ser "constituição" e vira "missão da meta-skill" — uma das ferramentas dentro do framework constitucional, não o framework em si.

### Plano de implementação em fases

**Fase 1 (FEITO nesta ADR):**
- ✅ `memory/governance/_README.md`
- ✅ `memory/governance/CONSTITUTION.md` v1.0.0
- ✅ Esta ADR
- ✅ Atualização de ADR 0078 com `superseded_partially_by: 0079`
- ✅ Session log

**Fase 2 (próxima sessão — Wagner valida):**
- Wagner lê CONSTITUTION + _README amanhã
- Corrige onde divergir
- Aprova versão final (1.0.0 ou ajusta pra 1.0.1)

**Fase 3 (Trust Tiers + Module Charter):**
- `memory/governance/TRUST-TIERS.md` — operacionalização do artigo 5
- `Modules/<X>/SCOPE.md` nos 6 módulos críticos (Copiloto, ADS, KB, MemCofre, TeamMcp, ProjectMgmt) com `trust_required` declarado
- Cache `mcp_modules` via webhook git → DB
- Pre-commit hook detecta drift (warn-only inicialmente)

**Fase 4 (Identity Mesh):**
- `memory/governance/IDENTITY-MESH.md` — operacionalização do artigo 6
- Tabela `mcp_actors` com manifest YAML de cada humano + IA conectada
- Migração: token id=10 (Wagner) → actor `wagner` L0; resolver bug do `my-work` (ADR 0077 superseded por essa fase)

**Fase 5 (ActionGate + UI Governance):**
- Middleware ActionGate em Modules/Governance/ (módulo novo) avaliando `mcp_governance_rules`
- UI `/governance` consolidada: ADR pending approvals, policies active/draft, audit highlights, drift alerts
- Trigger MySQL append-only em `mcp_audit_log` (artigo 9)

**Fase 6 (operacional):**
- Wagner opera 5min/dia em `/governance` dashboard
- Skills L2+ todas com `parent_actor` declarado
- Drift detection cron diário

### O que NÃO é decidido nesta ADR

- Implementação concreta dos artigos 5-9 (fica pra ADRs 0080-0084)
- Renomeações de módulos pedidas (Copiloto→Jana, Essentials→Notas, etc.) — fica pra Fase 3 com SCOPE.md
- Deletar Writebot e extrair Project legado — fica pra Fase 3
- Repurpose de MemCofre como SRS — fica pra Fase 2 (Wagner decide se SRS começa em MemCofre repurposed ou em pasta nova `memory/governance/srs/`)

## Justificativa

**Por que governança em camadas em vez de aposta concentrada.** A aposta concentrada (ADR 0078) é tecnicamente elegante (recursão pura, governance emerge da unidade) mas operacionalmente arriscada pra solo founder + time crescente + compliance brasileiro. As 7 camadas são redundantes em alguns pontos (Trust Tiers + Identity Mesh + Module Charter podem se sobrepor), mas redundância em governança é **defesa em profundidade** — característica desejada, não bug.

**Por que 10 artigos.** Constitucionalismo formal usa 7-15 seções (US Constitution: 7 + amendments; ISO 27001: 14 controles; Anthropic Constitutional AI: ~12 princípios). Pra oimpresso, 10 cobre o necessário (soberania, multi-tenancy, imutabilidade, compliance, trust, identity, charter, policy, audit, evolução) sem inflar ou faltar.

**Por que manter ADR 0078 como supersede parcial.** A meta-skill funciona; comando funciona; usuários (Claude Code) já carregam. Quebrar = retrabalho desnecessário. Relativizar = preservar valor + corrigir framing.

**Por que NÃO seguir Cedar/OPA/Constitutional AI direto.** Esses são apropriados pra Big Tech. Pra oimpresso (5 pessoas + IAs + clientes brasileiros) o pattern destilado em 10 artigos + 7 camadas é o tamanho certo. Se evoluir pra 50+ pessoas, revisita-se com Cedar (review_trigger).

**Reabrir esta decisão se:** (a) auditoria externa LGPD/Fiscal exigir mudança em artigo específico, (b) algum artigo provar-se inoperável em prod (sinal: >3 violações por trimestre que policy não capturou), (c) Anthropic publicar pattern oficial superior.

## Consequências

**Positivas:**

- **Visibilidade.** Wagner pode apontar pra qualquer regra/decisão e dizer "está aqui, artigo X, camada Y". Audit fica concreto.
- **Defesa em profundidade.** Falha em uma camada não compromete o sistema. Identity Mesh + SCOPE.md + ActionGate são redundantes propositalmente.
- **Compliance verificável.** LGPD/Portaria/Fiscal têm artigos dedicados (3, 4, 9). Auditor externo encontra rastro.
- **Escalável pra time + IAs.** Trust Tiers (artigo 5) + Identity Mesh (artigo 6) preparam pra IAs externas conectando.
- **Reversibilidade.** Cada camada pode ser desligada/refinada sem quebrar as outras.

**Negativas / Trade-offs:**

- **Custo de implementação maior.** 38h estimados pra Fases 3-5. Tempo investido em estrutura, não em feature.
- **Complexidade conceitual.** 7 camadas + 10 artigos + ADRs cross-cutting + Skills é mais pra absorver que "1 frase recursiva".
- **Risco de over-engineering.** Camadas que não geram valor proporcional ao custo precisam ser podadas — review_trigger explícito previne.
- **ActionGate latência.** Toda action L2+ passa por gate. Latência não-zero (mensurável: <5ms target). Aceitável.
- **Audit log cresce.** 5 anos retention + cobertura ampla = TBs ao longo do tempo. Particionamento mensal mitigando.

**Riscos mitigados:**

- **Solo founder ficar bottleneck.** UI de governança consolidada (Fase 5) permite Wagner operar 5min/dia em vez de 1h.
- **IAs externas tomarem ação fora do escopo.** Identity Mesh + ActionGate enforçam scope declarado.
- **Drift de módulos repetir.** SCOPE.md + pre-commit hook + drift detection cron previnem.
- **Audit modificável.** Trigger MySQL append-only + retention 5 anos + backup separado.

## Próximos passos (não-decisões deste ADR — viram ADRs 0080-0084)

- ADR 0080 — Trust Tiers operacionais + matriz de capabilities por tier
- ADR 0081 — Identity Mesh: schema `mcp_actors`, migração tokens, manifest pattern
- ADR 0082 — Module Charter: SCOPE.md template + cache mcp_modules + drift hook
- ADR 0083 — ActionGate middleware + UI Governance consolidada
- ADR 0084 — Audit append-only trigger + retention + UI

## Referências

- [Constituição do Oimpresso v1.0.0](../governance/CONSTITUTION.md)
- [Mapa das 7 camadas](../governance/_README.md)
- [ADR 0078 — Constituição em 1 frase (superseded parcial)](0078-constituicao-uma-frase-skill-unidade-evolucao.md)
- [ADR 0072 — Maturação memória + Team MCP — OpenClaw SOA 2026](0072-maturacao-memoria-team-mcp-openclaw-soa-2026.md)
- [ADR 0061 — Conhecimento canônico git/MCP zero auto-mem](0061-conhecimento-canonico-git-mcp-zero-automem.md)
- [ADR 0070 — Jira-style task management](0070-jira-style-task-management-current-md-removed.md)
- Sessão: `memory/sessions/2026-05-05-noite-constituicao-7-camadas.md`
- Pesquisa externa: NIST Zero Trust SP 800-207, AWS Cedar policy language, Anthropic Constitutional AI
