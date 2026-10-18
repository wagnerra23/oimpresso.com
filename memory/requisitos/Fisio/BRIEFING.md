---
id: requisitos-fisio-briefing
module: Fisio
status: em-construcao
status_nota: "ADR 0437 aceita em 2026-10-18; esqueleto de Modules/Fisio no PR #9536 (US-FISIO-002), sem schema nem telas"
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

Decisão aceita ([ADR 0437](../../decisions/0437-modulo-fisio-vertical-fisioterapia.md), 2026-10-18). O esqueleto de `Modules/Fisio` (pacote, permissões, menu condicionado, instalação) está no PR #9536 (US-FISIO-002), junto com a suíte de testes da F1, escrita antes do código. Ainda não há schema, rotas de negócio nem telas. Responsável técnica do conteúdo clínico: Dra. Dejeane.

## Portas canônicas

- **Herança geral (componentes/layouts/templates compartilhados):** [`../_Geral/BRIEFING.md`](../_Geral/BRIEFING.md)
- **Fronteira/ownership:** [`SCOPE.md`](SCOPE.md)
- **Requisitos:** [`SPEC.md`](SPEC.md)
- **Execução da F1:** [`HANDOFF-F1.md`](HANDOFF-F1.md)
- **Especificações por etapa:** [`etapas/`](etapas/)
- **Telas:** `Modules/Fisio/Resources/js/Pages/Fisio/` + charters/casos ao lado das Pages (a criar)

## Decisões e riscos que exigem atenção

- WhatsApp pelo driver `whatsmeow` com risco de banimento aceito e mitigado — [ADR 0437 §13.1](../../decisions/0437-modulo-fisio-vertical-fisioterapia.md)
- Linha da Anvisa (o que vira SaMD) — [ADR 0437 §5](../../decisions/0437-modulo-fisio-vertical-fisioterapia.md)
- Decisões ainda abertas — [ADR 0437 §14](../../decisions/0437-modulo-fisio-vertical-fisioterapia.md)
- Fisioterapeuta responsável pelo paciente (2026-10-18): a transferência fica no `activity_log`, sem tabela própria; pacientes anteriores à coluna ficam sem responsável até alguém atribuir — [SPEC US-FISIO-005](SPEC.md)

## Próxima ação verificável

- US-FISIO-001 (trio SDD) e US-FISIO-002 (esqueleto) — dono: [W]/Claude Code — evidência: `feature-lint --check` verde e testes de visibilidade passando.

## Regra de manutenção

1. Mudou requisito: altere `SPEC.md`/charter/casos.
2. Mudou um tema: altere um único `topicos/<slug>.md` e ajuste só a linha-resumo deste índice.
3. Conteúdo clínico (biblioteca, protocolos, contraindicações, sinais de alerta, textos ao paciente) só muda com revisão da responsável técnica anotada no PR.
4. Componente/layout/template compartilhado não é copiado para o módulo: consulte `_Geral`.
