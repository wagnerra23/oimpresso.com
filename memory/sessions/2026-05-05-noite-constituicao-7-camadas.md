---
date: 2026-05-05
slot: noite-tarde
title: "Constituição do Oimpresso — 10 artigos sobre 7 camadas (caminho seguro)"
participants: [W, C]
duration_min: 60
tags: [governance, constitution, foundation, adr-0079, supersedes-0078]
---

# 2026-05-05 noite-tarde — Constituição em 10 artigos

## Trajetória

Mesma sessão tarde+noite com 3 pivôs:

**Pivô 1** — audit de drift entre módulos → propus framework de 7 camadas (38h).
**Pivô 2** — Wagner recusou complexidade, cunhou "1 única aposta. Skill+missão." Construímos meta-skill `meta-skill-roi-erp-autonomo` + scaffolder + ADR 0078 (~2h).
**Pivô 3 (este)** — Wagner reavaliou: *"eu vou querer segurança. isso não me parece correto. Construa a constituição que tinha proposto antes. e vamos seguir pelos caminhos seguros."*

Resultado: Constituição completa em 10 artigos sobre 7 camadas. ADR 0079 supera parcialmente ADR 0078.

## Razão da reversão (declaradas por Wagner em sessão)

1. **Aposta concentrada não dá visibilidade visual** — Wagner: "se eu não ver na tela isso não vai funcionar"
2. **Risco de blast radius** — falha na meta-skill cascata pra todas filhas
3. **Compliance exige formalização** — LGPD/Portaria/Fiscal precisam audit explícito, não confiança em "skill carregada"
4. **Time crescer requer estrutura** — 5 pessoas + IAs externas precisam Identity Mesh + Trust Tiers

## Entregas concretas

| Artefato | Localização | Função |
|---|---|---|
| Mapa das 7 camadas | `memory/governance/_README.md` | índice operacional |
| Constituição v1.0.0 | `memory/governance/CONSTITUTION.md` | 10 artigos supremos |
| ADR formalizando | `memory/decisions/0079-constituicao-oimpresso-7-camadas-governanca.md` | autoridade |
| ADR 0078 atualizada | `memory/decisions/0078-*.md` | nota de supersede parcial + status `superseded_partially` |
| Session log | este arquivo | trilha cronológica |

## Os 10 artigos

1. **Soberania** — Wagner é root, capability concedida explicitamente
2. **Multi-tenancy** invariante — `business_id` sagrado
3. **Imutabilidade** — append-only onde lei/negócio exige
4. **Compliance** inegociável — LGPD, Portaria 671, NF-e, NFSe
5. **Trust Tiers** L0-L4 — default-deny
6. **Identity Mesh** — todo actor com manifest declarado
7. **Module Charter** — `Modules/<X>/SCOPE.md` por módulo
8. **Policy Gating** — ActionGate em toda ação L2+
9. **Auditoria** mandatória — `mcp_audit_log` append-only
10. **Evolução** — semver + ADR + Wagner explícito

## O que sobrevive da ADR 0078

- Meta-skill `meta-skill-roi-erp-autonomo` (operacional como L2 ferramenta)
- Comando `php artisan skill:scaffold`
- Fórmula `Planejar→Executar→Analisar→Organizar` (válida como ciclo de qualquer unit)

O que muda: a frase deixa de ser "constituição" e vira "missão da meta-skill". Skill é unit operacional, não fundamento.

## Plano de execução em fases

| Fase | O quê | Tempo | Status |
|---|---|---|---|
| **1** | _README + CONSTITUTION + ADR 0079 + 0078 atualizada | 2h | ✅ feito |
| **2** | Wagner valida amanhã, corrige onde divergir | — | aguardando |
| **3** | TRUST-TIERS.md + SCOPE.md em 6 módulos críticos + cache mcp_modules | 8h | pendente |
| **4** | IDENTITY-MESH.md + tabela mcp_actors + migração tokens (resolve ADR 0077) | 8h | pendente |
| **5** | ActionGate middleware + UI Governance consolidada + audit dashboard | 12h | pendente |
| **6** | Operacional: Wagner opera 5min/dia em /governance | — | meta |

## Próximas ADRs derivadas

- ADR 0080 — Trust Tiers operacionais (matriz capabilities por tier)
- ADR 0081 — Identity Mesh schema + migração tokens (supersede ADR 0077)
- ADR 0082 — Module Charter SCOPE.md template + drift hook
- ADR 0083 — ActionGate middleware + UI Governance
- ADR 0084 — Audit append-only trigger + retention 5y + UI

## P0 amanhã

1. Wagner ler CONSTITUTION.md + _README.md (45min)
2. Corrigir artigos onde divergir, ajustar redação (15min)
3. Aprovar versão final (1.0.0 ou bump pra 1.0.1 se patches)
4. Decidir se Fase 3 (Trust Tiers + SCOPE.md) começa amanhã ou semana que vem

## Aprendizado

**Wagner alterna entre "decida não pergunte" e "preciso ver na tela pra confirmar".** A primeira favorece velocidade conceitual; a segunda exige que decisão arquitetural seja **acompanhada de UI visual** ou pelo menos **artefato concreto e legível**. ADR + CONSTITUTION em markdown atendem o segundo.

A reversão entre ADR 0078 e 0079 não é erro — é **aprendizado em tempo real**. Construir a aposta concentrada (0078), ver os limites operacionais, voltar pra defesa em profundidade (0079). Em 90min Wagner viu na prática o que cada abordagem entrega e escolheu informado.

## ROI desta sessão (autoteste vs meta-skill)

A meta-skill da ADR 0078 diz "skill substitui trabalho humano repetitivo com ROI provado, rumo ao ERP R$ [redacted Tier 0]M / 24m". Aplicando à criação da Constituição em si:

- **Substitui?** ✅ Substitui Wagner ter que tomar 100+ decisões pequenas no futuro — Constituição decide uma vez.
- **Trabalho humano repetitivo?** ✅ Cada nova feature/módulo/IA conectando teria que reinventar regras. Agora consulta artigo.
- **ROI mensurável?** ✅ Tempo: cada decisão arquitetural futura economiza 30-60min de discussão (consulta artigo X).
- **Acelera R$ [redacted Tier 0]M / 24m?** ✅ Sem governança formal, escalar pra IAs externas + time crescer fica inviável. Constituição é prerequisito de autonomia segura.

A Constituição passa nos próprios 4 testes que a meta-skill define. Recursão sobrevive ao pivô.
