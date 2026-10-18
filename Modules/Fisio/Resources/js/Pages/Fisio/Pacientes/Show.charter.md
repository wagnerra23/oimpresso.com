---
id: modules-fisio-pages-fisio-pacientes-show-charter
page: /fisio/pacientes/{id}
component: Modules/Fisio/Resources/js/Pages/Fisio/Pacientes/Show.tsx
related_prototype: "n/a (a aba Evolução do canvas Prescreve Pro chega com a US-FISIO-006; esta etapa herda o padrão Cockpit)"
owner: wagner
status: draft
last_validated: "2026-10-18"
parent_module: Fisio
related_adrs: [93, 358, 437]
alcance:
  rota: /fisio/pacientes/{id}
  rota_nome: fisio.pacientes.show
  permission: fisio.access
  menu_hook: Modules/Fisio/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: fisio_module
tier: B
charter_version: 1
---

# Page Charter — /fisio/pacientes/{id} (DRAFT · perfil clínico e prontuário)

> Nasce na US-FISIO-005 (ADR 0437 §4 e §5; Res. COFFITO 414/2012). Casos: [`Show.casos.md`](Show.casos.md).
> Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Mostrar e editar o perfil clínico do paciente e manter o prontuário: avaliação, evolução e adendo, cada um carimbado com usuário, CREFITO e hora do servidor.

## Goals — Features (faz)

- Editar queixa, objetivos, dias por semana e marcadores — UC-FPS-02.
- Assinar avaliação (campos mínimos da Res. 414) e evolução, com carimbo do servidor — UC-FPS-03.
- Corrigir por adendo apontando o original, que não muda — UC-FPS-04, UC-FPS-05.
- Bloquear o paciente sem apagar nada — UC-FPS-07.
- Exportar o prontuário em PDF (identificação, perfil clínico e todos os registros assinados em ordem cronológica, com carimbo; rodapé com empresa, emissão e "Página X de Y"); cada exportação fica no `activity_log` sem PII — UC-FPS-19, UC-FPS-20. Exportar exige só `fisio.access`.
- Registrar questionários de uso livre (EVA, RMDQ, PSFS, LEFS, SPADI) com pontuação do servidor e ver o histórico — UC-FPS-13..16 (US-FISIO-011). Mostra o número e a referência; não interpreta (SaMD, ADR 0437 §5).

## Non-Goals — Features (NÃO faz)

- ❌ Editar ou apagar registro assinado (não existe rota; o banco recusa).
- ❌ Programa de exercícios e envio pelo WhatsApp (US-FISIO-006 e seguintes).
- ❌ PDF do programa de exercícios (vem depois da US-FISIO-006); o PDF daqui é só o prontuário.

## Refs

- ADR 0437 · `memory/requisitos/Fisio/HANDOFF-F1.md` (T-05) · Res. COFFITO 414/2012 · Lei 13.787/2018 · UI-0013
