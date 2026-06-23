<!-- cowork: target: prototipo-ui/handoffs/erros-deflexao.md -->
---
handoff_id: erros-deflexao
tela: Plataforma/Suporte
files: [app/Http/Controllers/StatusController.php, app/Console/Commands/DailyDigestCommand.php, resources/js/Pages/Status/Index.tsx, app/Support/Errors/ErrorRecovery.tsx]
created_by: CC
audited_against: 0f98814eb4f8
---
## Onda E-4 (Fase 2 · Defletir) — Status page + digest + UX de recuperação

**Depende de:** E-1, E-2. **Objetivo:** o ticket que **não abre**. Sobe a "taxa de deflexão". Tira o S2
do canal humano.

**§10.4:** validar contra o `main`; main vence.

### Design
- **Página de status** (`/status`, pública): lê health checks + incidentes abertos. "Pagamento: ok ·
  NF-e: degradado (SEFAZ) · Impressão: ok". O cliente confere sozinho antes de reclamar.
- **Digest diário por e-mail** (`DailyDigestCommand`, cron): agrega os **S2** do dia (erro-rate,
  certificados a vencer, grupos novos). O S2 sai do WhatsApp/push e vira leitura calma. Sem PII.
- **UX de recuperação única** (`ErrorRecovery`): componente reusável — mensagem humana + ações
  ("tentar de novo · salvar rascunho · falar com suporte"). Amplia o da E-1 pras demais telas.

### NÃO FAZER
- ❌ Status page com detalhe técnico/PII. ❌ Digest com S0/S1 (esses já foram em tempo real). ❌ trace
  pro operador.

### PRONTO QUANDO (Pest/visual)
- `/status` reflete o estado real (health + incidentes).
- Digest agrega só S2, 1×/dia, sem PII.
- Componente `ErrorRecovery` nas telas-alvo mostra recuperação, não trace.

> Cowork read-only no git — DESIGN; código é PR revisado do [CL].
