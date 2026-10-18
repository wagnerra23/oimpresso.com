---
id: requisitos-fisio-runbook-pacientes
title: "RUNBOOK — Fisio · Pacientes e prontuário"
module: Fisio
tela: Fisio/Pacientes/Index
telas: [Fisio/Pacientes/Index, Fisio/Pacientes/Show]
us: US-FISIO-005
owner: W
status: ativo
last_validated: "2026-10-18"
related_adrs: [0437-modulo-fisio-vertical-fisioterapia, 0093-multi-tenant-isolation-tier-0, 0104-processo-mwart-canonico-unico-caminho]
---

# RUNBOOK — Fisio · Pacientes e prontuário (`/fisio/pacientes`, `/fisio/pacientes/{id}`)

> F1 do MWART para as duas telas da US-FISIO-005. Módulo novo: **não há Blade legado** a descobrir
> (SCOPE `migracao_ui: n/a`). Telas moram no módulo dono (`Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/`).

## Fonte (ordem fixa)

1. **Canon:** ADR 0437 §3/§4 · HANDOFF-F1 (modelo de dados, T-05) · `Pacientes/Index.casos.md` e `Show.casos.md` (UC-FPI-*, UC-FPS-*).
2. **Testes que fixam o contrato:** `FisioPacienteCrudTest`, `FisioProntuarioTest`, `FisioMultiTenantTest` (casos de paciente).
3. **Design:** não há vista própria no protótipo (o canvas "Prescreve Pro" desenha Painel, Evolução e Biblioteca). As telas herdam **PT-01 Lista** e **PT-03 Detalhe / Cockpit + Apps Vinculados (UI-0008)**, só com componentes shared e tokens.

## Backend baseline (F2)

- `PacientesController` (index/contatos/store/show/update/destroy) e `ProntuarioController` (store/show) em `Modules/Fisio/Http/Controllers/`.
- Rotas no grupo `FisioLiberado` (bloco próprio em `Routes/web.php`). Ler = `fisio.access`; gravar = `fisio.editar`.
- Props caras com `Inertia::defer` (lista, contagem, prontuário, série de dor, consentimentos).

## Smoke (F4)

- Tela: biz=1 com o pacote `fisio_module` ligado pelo superadmin; abrir `/fisio/pacientes`, cadastrar a partir de contato, registrar avaliação e evolução, adendo, bloquear. **Nunca biz=4.**
- Banco: `SHOW TRIGGERS LIKE 'fisio_records'` continua com os 2 triggers da US-FISIO-003.
