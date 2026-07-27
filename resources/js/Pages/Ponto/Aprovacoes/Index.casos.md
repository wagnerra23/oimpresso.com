---
id: resources-js-pages-ponto-aprovacoes-index-casos
casos: Fila de aprovação de intercorrências · /ponto/aprovacoes
irmaos: Index.charter.md (lei) · SDD-espelho-e-jornada-v1.0.md §5.3 F5 + §6.2 (contrato)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é onde a ausência vira (ou não) abono — a decisão daqui altera apuração e banco de horas.
owner: wagner
last_run: "2026-07-27"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Ponto · MySQL)"
---

# Casos de Uso & Aceite — Fila de aprovação de intercorrências

> **Âncora:** `CU-PONTO-06`, `CU-PONTO-07` e `CU-PONTO-12` do
> [SDD §6.2/§6.5](../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md), cruzados com
> **US-PONTO-003** (estados canon + trilha de aprovação) e a Blade legada `aprovacoes/index.blade.php`.
> Fonte 4 (Delphi) **ausente** — SDD §0.1.
>
> ⚖️ **Força do veredito:** lane `PHP / Pest (Ponto · MySQL)` — **advisory**: reprova visível,
> **não bloqueia merge**.
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-APROV-01 | Rejeitar exige motivo registrado | must | `CU-PONTO-06` + US-PONTO-003 | `JornadaWorkflowContratoTest` | 🧪 sem veredito |
| UC-APROV-02 | Aprovação em lote não decide fora do meu empregador | must `[T0]` | `CU-PONTO-07` + ADR 0093 | `JornadaWorkflowContratoTest` | 🧪 sem veredito |
| UC-APROV-03 | A fila abre no que está pendente | should | `CU-PONTO-06` + charter | `JornadaWorkflowContratoTest` | 🧪 sem veredito |
| UC-APROV-04 | Urgente sobe na fila | should | `CU-PONTO-06` + F5 | `JornadaWorkflowContratoTest` | 🧪 sem veredito |

**[BACKLOG]:**

- `[BACKLOG]` Decidir uma intercorrência já decidida (aprovar duas vezes, ou aprovar a rejeitada) é
  recusado — o `IntercorrenciaService` é quem guarda a máquina de estados; sem 2ª âncora escrita, fica
  backlog até alguém medir o serviço.
- `[BACKLOG]` A decisão aplica o efeito na apuração/banco de horas conforme `impacta_apuracao` e
  `descontar_banco_horas` — é `[V0]` e exige dupla confirmação antes de virar UC com teste.

---

## UC-APROV-01 · Rejeitar exige motivo registrado · `must`

- **Persona:** gestor decidindo o atestado da equipe. Rejeição sem justificativa é passivo trabalhista —
  em reclamatória, "por que negou?" é a primeira pergunta.
- **Aceite:** Dado uma intercorrência pendente · Quando tento rejeitá-la **sem informar motivo** · Então
  a rejeição é **recusada** (erro de validação) e a intercorrência **continua pendente**.
- **Teste:** `Modules/Ponto/Tests/Feature/JornadaWorkflowContratoTest.php` — `UC-APROV-01`.
- **Contrato:** `CU-PONTO-06` (SDD §6.2) · US-PONTO-003 (aceitação: *"`solicitante_id`, `aprovador_id`,
  `aprovado_em`, `motivo_rejeicao`"*) · `AprovacaoController@rejeitar`
  (`'motivo' => 'required|string|max:500'`).
- **Regressão que defende:** tornar o motivo opcional "pra agilizar" apaga a trilha exatamente no caso em
  que ela é necessária. A validação é a única coisa que hoje garante o registro.
- **Status: 🧪 sem veredito.**

---

## UC-APROV-02 · Aprovação em lote não decide fora do meu empregador · `must` `[T0]`

- **Persona:** plataforma multi-tenant. O lote recebe uma **lista de ids** vinda do cliente — é o vetor
  mais fácil de forjar de todo o módulo.
- **Aceite:** Dado que envio para aprovação em lote uma lista contendo o id de uma intercorrência de
  **outro** business · Quando o lote é processado · Então essa intercorrência **permanece intacta** (não
  aprovada, sem aprovador registrado).
- **Teste:** `JornadaWorkflowContratoTest.php` — `UC-APROV-02`.
- **Contrato:** `CU-PONTO-07` (SDD §6.5) · US-PONTO-007 ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** medido no fonte — `AprovacaoController@{aprovar,rejeitar}` usam
  `Intercorrencia::findOrFail($id)` **sem** `where('business_id')`. Funciona **hoje** porque a entity tem
  `HasBusinessScope`, mas é **defesa única**: o dia em que o trait sair (refactor, model novo, cópia do
  padrão), os 3 handlers passam a decidir intercorrência alheia — e nada avisa. Este UC é o alarme
  (SDD §9 D-5).
- **Nota de teste:** biz=1 vs business fictício — **nunca biz=4** ([ADR 0101]).
- **Status: 🧪 sem veredito.**

---

## UC-APROV-03 · A fila abre no que está pendente · `should`

- **Persona:** gestor que abre a tela para trabalhar, não para navegar. A fila é uma caixa de entrada:
  o default tem que ser "o que falta decidir".
- **Aceite:** Dado que abro `/ponto/aprovacoes` sem escolher filtro · Então a fila vem filtrada pelas
  intercorrências **pendentes**, e o painel informa **quantas** existem em cada estado do ciclo.
- **Teste:** `JornadaWorkflowContratoTest.php` — `UC-APROV-03`.
- **Contrato:** `CU-PONTO-06` · `AprovacaoController@index` (default `ESTADO_PENDENTE`) ·
  US-PONTO-003 (6 estados canon) · charter §Mission.
- **Regressão que defende:** trocar o default para "todas" enterra o pendente no meio do histórico —
  a fila deixa de ser fila. E perder os contadores por estado tira a única visão de backlog do gestor.
- **Status: 🧪 sem veredito.**

---

## UC-APROV-04 · Urgente sobe na fila · `should`

- **Persona:** gestor com 40 itens pendentes. Um atestado urgente precisa aparecer antes do pedido de
  folga de mês que vem — senão a priorização vira sorte.
- **Aceite:** Dado intercorrências pendentes com prioridades diferentes · Quando abro a fila · Então as
  **urgentes vêm antes** das normais, e dentro de cada grupo as mais recentes primeiro.
- **Teste:** `JornadaWorkflowContratoTest.php` — `UC-APROV-04`.
- **Contrato:** `CU-PONTO-06` · SDD §5.3 F5 (`orderByRaw("FIELD(prioridade,'URGENTE','NORMAL')")` +
  `orderByDesc('created_at')`).
- **Regressão que defende:** a ordenação por prioridade é feita com expressão SQL específica de MySQL.
  Qualquer refactor de portabilidade (ou troca por `orderBy('prioridade')`, que ordenaria **alfabeticamente**
  — `NORMAL` antes de `URGENTE`) inverte a fila silenciosamente.
- **Status: 🧪 sem veredito.**
