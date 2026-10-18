---
id: modules-fisio-pages-fisio-pacientes-show-charter
page: /fisio/pacientes/{id}
component: Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Show.tsx
related_prototype: "n/a (herda PT-03 Detalhe; Cockpit + Apps Vinculados UI-0008 — o canvas Prescreve Pro desenha a aba Evolução, que chega com a US-FISIO-010)"
owner: wagner
status: draft
last_validated: "2026-10-18"
parent_module: Fisio
related_adrs: [93, 358, 437]
related_us: [US-FISIO-005]
runbook: memory/requisitos/Fisio/RUNBOOK-pacientes.md
alcance:
  rota: /fisio/pacientes/{id}
  rota_nome: fisio.pacientes.show
  permission: fisio.access
  menu_hook: Modules/Fisio/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: fisio_module
tier: A
charter_version: 1
---

# Page Charter — /fisio/pacientes/{id} (DRAFT · ficha e prontuário do paciente)

> Nasce com a US-FISIO-005 (Res. COFFITO 414/2012, Lei 13.787/2018, ADR 0437 §4). Casos: [`Show.casos.md`](Show.casos.md).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Ser o prontuário legal do paciente: avaliação (anamnese) e evoluções assinadas com nome, CREFITO,
data e hora; correção só por adendo; perfil clínico e marcadores de condição; bloqueio em vez de exclusão.

## Goals — Features (faz)

- Abas Evolução · Programa · Avaliação · Prontuário · Questionários (Cockpit). Funcionam nesta US: **Avaliação** e **Prontuário**.
- Avaliação com o mínimo da Res. 414: queixa principal, HDA, exame físico-funcional, diagnóstico cinesiológico funcional, objetivos e plano (obrigatórios); antecedentes, prognóstico, sessões previstas, sinais de alerta e marcadores (opcionais) — UC-FPS-03.
- Evolução por atendimento (conduta + resposta do paciente) — UC-FPS-03.
- Carimbo do servidor (`signed_by`, `signed_crefito`, `signed_at`); registro assinado sem editar nem apagar (405) — UC-FPS-04.
- Adendo aponta o original (`amends_id`), que fica intacto; a linha do tempo mostra os dois — UC-FPS-05.
- Painel direito (Apps Vinculados): contato, perfil clínico, consentimentos.
- Editar perfil (queixa, objetivos, meta 1–7, marcadores) — UC-FPS-02. Bloquear com confirmação — UC-FPS-07.

## Non-Goals — Features (NÃO faz)

- ❌ Editar ou apagar registro assinado (só adendo).
- ❌ Criar `telemonitoramento` pela tela — só o portal gera (US-FISIO-007).
- ❌ Programa, gráfico de dor e questionários nesta US: as abas mostram "em breve" sem dado inventado (US-FISIO-006, 010, 011).
- ❌ Pré-anamnese pelo portal (US-FISIO-007).

## Anti-hooks

- ❌ Nunca ler `signed_at`/`signed_by` do corpo do request.
- ❌ Nunca `forceDelete`/`DELETE` em paciente ou registro.

## Refs

- ADR 0437 §4/§5 · Res. COFFITO 414/2012 · Lei 13.787/2018 · PT-03 Detalhe · UI-0008 · UI-0013 · [RUNBOOK](../../../../../../../memory/requisitos/Fisio/RUNBOOK-pacientes.md)
