---
casos: governance/Operacao — carimbado do Padrão de Tela
irmaos: Operacao.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-07-31"
---

# Casos de Uso & Aceite — governance/Operacao

> Nascido de `criar-tela.mjs`. **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão. Por isso só **UC-OPERAC-01** tem id —
> é o único que o stub `e2e/governance-operacao.spec.ts` cita. Os demais ficam no Backlog como
> prosa até existir teste que os defenda ([ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md) G-2).

> **Fonte do contrato:** as 5 perguntas formuladas pelo [W] em 2026-07-31 + o pedido literal que
> originou a tela. **Não** derivado do `.tsx` (stub) — derivar caso de código é tautológico e
> proibido ([proibicoes.md §5, 2026-06-05](../../../../memory/proibicoes.md)).

---

## UC-OPERAC-01 · Abrir a tela e saber o que está parado sem clicar em nada

- **Persona:** [W] — operador sênior. Não quer relatório: quer saber, em segundos, qual peça parou
  e de quem cobrar. Hoje descobre isso caçando em quatro arquivos diferentes.
- **Aceite:**
  **Dado** que existe ao menos um item parado (advisory com `promote_by` vencido, métrica armada
  ausente, ou etapa sem dono declarado),
  **Quando** o [W] abre `/governance/operacao` sem aplicar filtro nenhum,
  **Então** o primeiro item da lista é o de maior `parado há`, exibindo — na mesma linha e sem
  interação — a **etapa**, o **responsável** (ou `sem dono`, em rose), a **máquina** envolvida e a
  **ação pendente** em texto imperativo.
- **Teste:** `e2e/governance-operacao.spec.ts` — hoje `test.fixme` citando `UC-OPERAC-01`. Troca
  por asserção real: ordenação default decrescente por `parado há` + as 4 células presentes na
  primeira linha.
- **Regressão que defende:** a tela virar painel bonito que exige clicar pra descobrir o problema.
  Se o gargalo não está na primeira linha ao abrir, a tela não cumpre o motivo de existir — mesmo
  com tudo verde.
- **Status: ⬜** — stub; vira 🧪/✅ quando o teste executar e passar.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Filtrar por dono e ver só o que é meu — depende da decisão [W] sobre a fonte de
  ownership (charter §Dependência bloqueante). Sem ela a coluna nasce vazia.
- **[BACKLOG]** Toggle "só o que exige decisão [W]" — separa o que trava esperando o líder do que
  trava esperando outra pessoa. É a medida do gargalo-do-líder que o perfil dele no TEAM.md §1
  manda evitar.
- **[BACKLOG]** Bus factor por peça: módulo com autor único em 180d é sinalizado. Medido em
  2026-07-31 — 18 de 34 módulos, e 96,7% dos commits numa identidade só.
- **[BACKLOG]** Projeção exibida como "N dias no ritmo atual até cruzar o piso", nunca como
  probabilidade — aritmética verificável em vez de número calibrado que ninguém calibrou.
- **[BACKLOG]** Item verde sem mudança de estado há N dias aparece como estagnado, não como saudável.
- **[BACKLOG]** Número com data de medição ao lado, em toda célula derivada, e link pra re-rodar
  a fonte em vez de editar o número.
- **[BACKLOG]** Etapa sem dono renderiza em rose, nunca em verde nem cinza neutro.
- **[BACKLOG]** Fonte ausente mostra `—` mais a razão — nunca zero silencioso, que leria como "tudo certo".
- **[BACKLOG]** Cross-tenant: `business_id` scopado, biz=1 não enxerga biz=99.
- **[BACKLOG]** Nenhuma prop de estado é editável — prova o Non-Goal "não guarda estado próprio".

## Trilha do tempo

- 2026-07-31 · [CC] UC-OPERAC-01 escrito das 5 perguntas do [W]; demais casos em Backlog sem id
  pra não nascerem órfãos (G-2). Tela renomeada de `Responsabilidades` → `Operacao` (camada
  Operacional da pilha de governança). Refs: ADR 0264 · UI-0013.
