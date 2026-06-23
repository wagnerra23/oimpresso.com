<!-- cowork: target: prototipo-ui/handoffs/erros-jardineiro.md -->
---
handoff_id: erros-jardineiro
tela: Plataforma/ErrorHandling
files: [app/Console/Commands/NoiseGardenerCommand.php, app/Support/Errors/RoadmapFeeder.php]
created_by: CC
audited_against: 0f98814eb4f8
---
## Onda E-7 (Fase 3 · Jardinar) — Poda do ruído + suporte vira sinal de produto

**Depende de:** E-2, E-5, E-6. **Objetivo:** a Lei 4 — **alguém cuida do jardim do ruído**. Sem
jardineiro, todo sistema de alerta apodrece em meses (fadiga). E fecha o ciclo: reclamação recorrente
vira **feature no roadmap**.

**§10.4:** validar contra o `main`; main vence. Reusar `mcp_tasks` (não criar backlog novo).

### Design
- **`NoiseGardenerCommand`** (semanal): lista os grupos mais **barulhentos** (maior `count`, menor valor
  de ação), sugere **podar/ajustar limiar/silenciar**. Saída = relatório pro construtor (um agente pode
  revisar e propor; [W] decide). Mantém o painel honesto.
- **`RoadmapFeeder`**: categorias **recorrentes** de erro/reclamação (de `error_groups`/`incidents`) →
  cria item `proposto` em `mcp_tasks` project=FORJA (entra na **Triagem** — agente propõe, [W] aprova).
  O suporte deixa de ser só apagar incêndio e vira **insumo de produto**.
- Tudo auditado; nada vira oficial sem [W] (mesma soberania do loop).

### NÃO FAZER
- ❌ Silenciar alerta sem registro (poda é proposta, [W]/agente confirma). ❌ Criar backlog paralelo
  (usa `mcp_tasks`/Triagem). ❌ Virar feature sem passar por Triagem.

### PRONTO QUANDO
- Relatório semanal de ruído gerado; poda é proposta auditada.
- Categoria recorrente vira ticket `proposto` na Triagem da Forja.

> Cowork read-only no git — DESIGN; código é PR revisado do [CL]. Fecha o Plano Sustentável.
