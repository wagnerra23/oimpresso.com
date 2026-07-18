---
slug: 0115-recuperacao-cliente-gold-via-bundle-oimpresso
number: 115
title: "Recuperação cliente Gold Comunicação Visual via bundle oimpresso + NF-e 55 (anti-fuga Mubsys)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by:
  - W
decided_at: '2026-05-09'
quarter: 2026-Q2
related:
  - 0026-posicionamento-erp-grafico-com-ia
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0103-eventos-fiscais-separados-por-modelo
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0106-recalibracao-velocidade-fator-10x-ia-pair
pii: false
---

# ADR 0115 — Recuperação cliente Gold Comunicação Visual via bundle oimpresso + NF-e 55

**Status:** ✅ Aceita
**Data:** 2026-05-09
**Decisão por:** Wagner Rocha
**Categoria:** estratégia comercial / arq

**Confirmações Wagner 2026-05-09:**
- Gold roda **oimpresso Laravel on-prem** (não Delphi WR Comercial)
- **Track paralelo** ao Cycle 03 — não aguarda Cycle 04
- Pricing on-prem: placeholder no template, decisão fica em US dedicada

---

## Contexto

**Cliente Gold Comunicação Visual** roda oimpresso instalado **on-premise** em versão antiga (provável 3.7→6.7-bootstrap, sem `Modules/NfeBrasil`) e está em processo de migração para concorrente **Mubsys** (mapeado em [memory/comparativos/oimpresso_vs_concorrentes_capterra_2026_04_25.md](../comparativos/oimpresso_vs_concorrentes_capterra_2026_04_25.md)).

**Gap real identificado em sessão 2026-05-09:**

A demanda inicial veio como MDF-e (Gold tem caminhão próprio pra transportar placas), mas a qualificação revelou que o **dealbreaker é NF-e modelo 55** — sem isso ela não opera B2B com tranquilidade fiscal. MDF-e é demanda secundária, derivada de NF-e 55 funcionando.

**Pré-requisitos no oimpresso atual (main):**

| Capacidade | Estado |
|---|---|
| NF-e modelo 55 emissão | ✅ Entregue (Fase 2 NfeBrasil — `Modules/NfeBrasil/Services/NfeService.php`) |
| Cert A1 cifrado por business | ✅ Entregue (`Modules/NfeBrasil/Models/NfeCertificado.php`) |
| Motor tributário ICMS-ST/DIFAL/FCP | ✅ Entregue (Fase 5) |
| Templates regionais SP industrial | ✅ Existe (`industria-grafica-presumido-sp.php` + `industria-grafica-simples-sp.php`) |
| Officeimpresso módulo licença | ✅ Existe (Superadmin-only) |
| MDF-e modelo 58 | ❌ Fase 6 NfeBrasil — não entregue |
| Onboarding cliente on-prem | ❌ Sem runbook formalizado |

Conclusão: **NF-e 55 já está pronta no oimpresso atual.** O esforço é majoritariamente **upgrade de plataforma + onboarding fiscal + comercial**, não dev novo.

## Decisão

Recuperar Gold antes da migração pra Mubsys com bundle de **5 etapas**:

1. **Discovery** — audit técnico da instalação Gold: versão, banco, cert A1 disponível, IE habilitada, regime tributário, perfil de operação
2. **Proposta comercial** — diferenciação vs Mubsys ancorada em [comparativos Capterra](../comparativos/oimpresso_vs_concorrentes_capterra_2026_04_25.md), com pricing on-prem (one-time + manutenção anual) ou SaaS R$ [redacted Tier 0]/mês (Plano Enterprise — README NfeBrasil)
3. **Upgrade plataforma on-prem** — versão atual com `Modules/NfeBrasil` ativo
4. **Configuração NfeBrasil** — cert + IE + regime + CSOSN/CST + template SP industrial gráfico
5. **Smoke fiscal homologação SEFAZ-SP** + treinamento operadora + cutover prod

**Fora do escopo desta ADR:**
- ❌ MDF-e (Fase 6 NfeBrasil) — fica pra entrega futura quando volume justificar
- ❌ Codar NF-e 55 no Office Impresso Delphi legado — investimento em stack em desuso
- ❌ Forçar SaaS Hostinger — Gold valoriza on-prem; insistir = perder de novo

## Consequências

**Positivas:**
- Recuperação **sem custo de dev novo** — toda capacidade fiscal já existe no oimpresso main
- 1º caso real on-prem do oimpresso atual (com NfeBrasil) — valida bundle Officeimpresso + NfeBrasil produção
- Dados pra **Trilha 1 do roadmap** ([_Roadmap_Faturamento.md:85](../requisitos/_Roadmap_Faturamento.md:85)) — ativar 49 businesses dormentes
- Material de testimonial **anti-Mubsys**: "ERP gráfico que recupera cliente da concorrência"
- Justifica formalização do **runbook on-prem** que outros 49 dormentes vão precisar

**Negativas / Riscos:**
- Cert A1 fica no servidor da Gold — Wagner não controla rotação ([memory/proibicoes.md](../proibicoes.md))
- Upgrade on-prem requer SSH/acesso técnico — não automatizado pelo `quick-sync.yml`
- SEFAZ outbound depende de firewall/proxy da Gold — pode bloquear, exige diagnóstico
- Pricing on-prem ainda **não definido** — risco comercial se Wagner subestimar custo recorrente de suporte
- Cycle 03 ativo (5d restantes — smoke biz=1 NFC-e SEFAZ-SC) — **Gold entra Cycle 04**, não interrompe

**Critérios de sucesso:**
- Gold cancela migração Mubsys e renova/assina contrato com oimpresso
- 1ª NF-e 55 autorizada em produção pelo Gold em até **30 dias** após luz verde Wagner
- Sem regressão em ROTA LIVRE (biz=4) durante onboarding paralelo
- Runbook on-prem reutilizável pelos próximos 5 clientes recuperados

## Alternativas consideradas

| Alternativa | Por que rejeitada |
|---|---|
| **Codar NF-e 55 no Office Impresso Delphi legado** | Investimento em stack em desuso; Wagner já migrou linha principal pro Laravel UltimatePOS |
| **Forçar Gold pro SaaS Hostinger** | Perfil dela é on-prem; insistir = perder de novo. Ofertar como opção, não como única |
| **Antecipar MDF-e Fase 6 antes de NF-e 55** | NF-e 55 é dealbreaker; MDF-e é demanda secundária ([ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md): backlog só atende sinal qualificado) |
| **Esperar Cycle 04 sem ação** | Janela de recuperação fecha quando Mubsys assinar — urgência comercial real |

## Plano de execução (após aprovação)

US a serem criadas via `tasks-create` MCP (módulo NfeBrasil; sprint Cycle 04 ou paralelo):

1. **US-NFE-NNN — Discovery instalação Gold** (auditoria técnica, ~2h)
2. **US-NFE-NNN — Proposta comercial diferencial Mubsys** (template + pricing, ~3h)
3. **US-NFE-NNN — Upgrade on-prem versão atual com NfeBrasil** (~4-6h dependendo do delta de versão)
4. **US-NFE-NNN — Configuração fiscal Gold (cert + IE + regime + template)** (~2-3h)
5. **US-NFE-NNN — Smoke fiscal homologação SEFAZ-SP biz=Gold** (~2h)
6. **US-NFE-NNN — Treinamento operadora Gold + cutover prod** (~3h)
7. **US-NFE-NNN — Runbook reutilizável `memory/requisitos/Officeimpresso/RUNBOOK-recuperacao-on-prem.md`** (~2h)

**Esforço total recalibrado** ([ADR 0106](0106-recalibracao-velocidade-fator-10x-ia-pair.md)):
- Codável (runbook, templates, configs): ~2-3 dias com IA-pair
- Humano-limitado (visita Gold, cert real, SEFAZ smoke real, treinamento): ~5-7 dias relógio
- **Margem 2x** = ~10-14 dias corridos do OK até cutover

## Notas de execução

- **Não interromper Cycle 03** — smoke NFC-e biz=1 fecha primeiro
- **Larissa-equivalente Gold** — identificar operadora persona análoga (auto-mem `cliente_rotalivre.md` tem playbook)
- **Cert A1 storage on-prem** — usar mesmo padrão `NfeCertificado` cifrado, mas storage local Gold
- **Backup pré-upgrade obrigatório** — Gold opera produção; downtime mínimo

---

**Próxima ação após aprovação Wagner:**
1. Marcar `status: aceito` no frontmatter
2. Criar 7 tasks MCP `tasks-create module:NfeBrasil`
3. Criar `memory/requisitos/Officeimpresso/RUNBOOK-recuperacao-on-prem.md` (stub)
4. Agendar discovery Gold (Wagner contata cliente)
