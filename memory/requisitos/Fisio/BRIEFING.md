---
id: requisitos-fisio-briefing
module: Fisio
status: em-construcao
status_nota: "ADR 0437 aceita em 2026-10-18; esqueleto (US-FISIO-002) e schema (US-FISIO-003) no main; biblioteca (US-FISIO-004) com seed pendente da revisão clínica"
updated_at: "2026-10-18"
owner: W
related_adrs: ["0437-modulo-fisio-vertical-fisioterapia"]
---

# BRIEFING — `Fisio`

> **Função única:** resumo executivo e índice. O BRIEFING aponta para os donos; não recopia SCOPE, SPEC, tópicos ou contratos de tela.
> **Contrato:** `scripts/memory-schemas/briefing.schema.json`.

## O que é

Vertical de fisioterapia dentro do oimpresso: o profissional monta o programa de exercícios domiciliares, o paciente recebe pelo WhatsApp e faz pelo portal (link + PIN), e o profissional vê quem fez, quem está com dor e quem vai abandonar.

## Estado atual

Decisão aceita ([ADR 0437](../../decisions/0437-modulo-fisio-vertical-fisioterapia.md), 2026-10-18). Esqueleto (US-FISIO-002) e schema com append-only (US-FISIO-003) estão no main. A primeira tela é a biblioteca (`/fisio/biblioteca`, US-FISIO-004): grade com globais do oimpresso + exercícios próprios da clínica, filtro por região e equipamento, sem busca por texto. Os 38 exercícios globais do seed L0 nascem `em_revisao` e **não aparecem para as clínicas até a revisão clínica da Dra. Dejeane** (responsável técnica) publicá-los. Pacientes, programa, portal e painel são as US seguintes.

## Portas canônicas

- **Herança geral (componentes/layouts/templates compartilhados):** [`../_Geral/BRIEFING.md`](../_Geral/BRIEFING.md)
- **Fronteira/ownership:** [`SCOPE.md`](SCOPE.md)
- **Requisitos:** [`SPEC.md`](SPEC.md)
- **Execução da F1:** [`HANDOFF-F1.md`](HANDOFF-F1.md)
- **Especificações por etapa:** [`etapas/`](etapas/)
- **Telas:** `Modules/Fisio/Resources/js/Pages/Fisio/` + charters/casos ao lado das Pages (Biblioteca pronta; as demais com as US seguintes)

## Decisões e riscos que exigem atenção

- WhatsApp pelo driver `whatsmeow` com risco de banimento aceito e mitigado — [ADR 0437 §13.1](../../decisions/0437-modulo-fisio-vertical-fisioterapia.md)
- Linha da Anvisa (o que vira SaMD) — [ADR 0437 §5](../../decisions/0437-modulo-fisio-vertical-fisioterapia.md)
- Decisões ainda abertas — [ADR 0437 §14](../../decisions/0437-modulo-fisio-vertical-fisioterapia.md)

## Próxima ação verificável

- Revisão clínica dos 38 exercícios do seed L0 (`Modules/Fisio/Database/Seeders/biblioteca-l0.php`) — dona: Dra. Dejeane — evidência: `review_status = publicado` com nome e CREFITO da revisora.
- US-FISIO-005 (pacientes e prontuário) — dono: Claude Code — evidência: `FisioPacienteCrudTest` e `FisioProntuarioTest` verdes na lane MySQL.

## Regra de manutenção

1. Mudou requisito: altere `SPEC.md`/charter/casos.
2. Mudou um tema: altere um único `topicos/<slug>.md` e ajuste só a linha-resumo deste índice.
3. Conteúdo clínico (biblioteca, protocolos, contraindicações, sinais de alerta, textos ao paciente) só muda com revisão da responsável técnica anotada no PR.
4. Componente/layout/template compartilhado não é copiado para o módulo: consulte `_Geral`.
