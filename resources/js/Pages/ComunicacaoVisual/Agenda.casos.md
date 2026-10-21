---
id: resources-js-pages-comunicacao-visual-agenda-casos
casos: Agenda de instalação · /comunicacao-visual/agenda
irmaos: Agenda.charter.md (lei) · Agenda.tsx (código)
tecnica: Caso de uso = narrativa do coordenador + critério de aceite verificável (Dado/Quando/Então)
por_que: a agenda só serve se mostrar a semana da empresa certa e se a recusa por falta de janela aparecer para quem agenda; dado de outra empresa na grade ou no formulário é vazamento.
owner: wagner
last_run: "2026-10-21"
last_run_ci: "2026-10-21 — nasce com a tela; veredito vem do CI do PR (Pest ComunicacaoVisual MySQL e vitest)."
---

# Casos de Uso & Aceite — Agenda de instalação (`/comunicacao-visual/agenda`)

> **Âncora:** SPEC [US-COMVIS-007](../../../../memory/requisitos/ComunicacaoVisual/SPEC.md) — DoD
> "Agenda calendário (semanal)" e "Bloqueia sem janela … dizendo com quem e quando" — e o UC-FLX-21 do
> catálogo de fluxos. A regra de conflito em si é da API e está em
> [`Index.casos.md`](Index.casos.md) UC-CVINST-01..07; aqui fica só o que é da tela.

> Sem impacto em valor ou estoque.

| UC | Caso | Prioridade | Origem | Teste | Status |
|---|---|---|---|---|---|
| UC-CVAG-01 | A semana lista só as instalações desta empresa que caem nela, sem as canceladas | must `[T0]` | US-COMVIS-007 · UC-FLX-21 | `AgendaTelaContratoTest` | 🧪 |
| UC-CVAG-02 | Equipe e OS do formulário são só desta empresa, e OS concluída fica fora | must `[T0]` | US-COMVIS-007 | `AgendaTelaContratoTest` | 🧪 |
| UC-CVAG-03 | Sem permissão não abre; quem só vê abre sem poder agendar | must | US-COMVIS-007 | `AgendaTelaContratoTest` | 🧪 |
| UC-CVAG-04 | A recusa sem janela aparece como alerta com quem e quando | must | US-COMVIS-007 · UC-FLX-21 | `cv-agenda.test.tsx` | 🧪 |

## UC-CVAG-01 · A semana lista só as instalações desta empresa que caem nela, sem as canceladas · `must` `[T0]`

**Dado** hoje terça 20/10/2026 e instalações da empresa em 21/10 e 28/10, uma cancelada em 22/10 e uma da
empresa 99 em 21/10 **Quando** o coordenador abre a agenda **Então** a semana exibida começa na segunda 19/10,
e a agenda (prop deferida) traz só a de 21/10 desta empresa; pedir qualquer dia da semana seguinte abre a
semana de 26/10, que traz a de 28/10.

Status: 🧪

## UC-CVAG-02 · Equipe e OS do formulário são só desta empresa, e OS concluída fica fora · `must` `[T0]`

**Quando** o coordenador abre a agenda **Então** a lista de equipe tem as pessoas desta empresa e nenhuma da
99, e a lista de OS tem as OS abertas desta empresa, sem a concluída e sem a da 99.

Status: 🧪

## UC-CVAG-03 · Sem permissão não abre; quem só vê abre sem poder agendar · `must`

**Quando** quem não tem permissão da CV abre a agenda **Então** 403. Com `comvis.os.view` abre e não pode
agendar; com `comvis.os.update_status` pode agendar.

Status: 🧪

## UC-CVAG-04 · A recusa sem janela aparece como alerta com quem e quando · `must`

**Dado** o formulário preenchido **Quando** a API responde 422 "Sem janela: … de 21/10/2026 09:00 a 11:00"
**Então** aparece o alerta "Sem janela — não agendado" com o nome e o horário, e a agenda não recarrega.
Controle: 422 de dado inválido mostra o erro sem o alerta de bloqueio; 201 recarrega só a agenda.

Status: 🧪
