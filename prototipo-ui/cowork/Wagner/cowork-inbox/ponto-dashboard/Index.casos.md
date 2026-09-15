---
id: resources-js-pages-ponto-dashboard-index-casos
casos: Painel do ponto — visão ao vivo do dia · /ponto
irmaos: Index.charter.md (lei) · prototipo-ui/contrato/ponto-painel.contract.json (contrato de tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a home do módulo — o que ela mostra define o que o gestor acha que precisa resolver hoje.
owner: wagner
last_run: "2026-08-23"
last_run_ci: "0 UC executado — trio nasce neste arquivo; veredito pendente da lane PHP / Pest (Ponto · MySQL)"
correcao_ids: "2026-08-23 — ids eram UC-PTPAINEL-NN. INVÁLIDOS: scripts/lib/uc-regex.mjs aceita prefixo de no máximo 6 chars ([A-Z][A-Z0-9]{0,5}) e PTPAINEL tem 8 — o readiness não reconheceria nenhum UC e eu concluiria erradamente 'faltou critério'. Medido por [CL]. Renomeados para UC-PAINEL-NN, que casa e segue a convenção do módulo (APROV, BHIDX, ESPSHOW). Id de UC é infraestrutura de contrato, não copy — copy e ordem seguem intocadas."
---

# Casos de Uso & Aceite — Painel do ponto

> **Âncora:** charter `resources/js/Pages/Ponto/Dashboard/Index.charter.md` (§Mission, §Non-Goals,
> §Automation hooks) + `prototipo-ui/contrato/ponto-painel.contract.json` (seções `painel-kpis`,
> `painel-fila-aprovacoes`, `painel-atividade`, `painel-nota-fechamento`).
>
> ⚖️ **Força do veredito:** lane `PHP / Pest (Ponto · MySQL)` — **advisory**: reprova visível,
> **não bloqueia merge**.
>
> ⚠️ **CORRIGIDO 2026-08-23:** ids `UC-PTPAINEL-NN` → `UC-PAINEL-NN` (limite de 6 chars no prefixo, `scripts/lib/uc-regex.mjs`). ⚠️ **T3 é no-op:** as 4 âncoras `data-contract` já existem e a catraca de copy já dá exit 0 — o F3 desta tela foi feito em #6114 (21/08).
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-PAINEL-01 | Os 6 KPIs aparecem na ordem contratada | must | contrato `painel-kpis` | — | ⬜ não verificado |
| UC-PAINEL-02 | O painel não atravessa empregadores | must `[T0]` | charter §Non-Goals · ADR 0093 | — | ⬜ não verificado |
| UC-PAINEL-03 | Fila de aprovações vazia se declara, não some | must | contrato `painel-fila-aprovacoes` | — | ⬜ não verificado |
| UC-PAINEL-04 | O painel é read-only — nenhuma ação muta | must | charter §Anti-hooks | — | ⬜ não verificado |
| UC-PAINEL-05 | O polling de 30s recarrega só props de leitura e morre no unmount | should | charter §Automation hooks | — | ⬜ não verificado |
| UC-PAINEL-06 | Divergência que trava o fechamento aparece antes dos KPIs | should | contrato `painel-nota-fechamento` + `ordem` | — | ⬜ não verificado |

**[BACKLOG]:**

- `[BACKLOG]` "Presentes agora" em tempo real — o contrato registra a pendência [W]: hoje é número
  apurado; no vivo depende de marcação aberta (última sem par). Vira UC quando [W] decidir entre
  consulta a cada carga e refresh manual.
- `[BACKLOG]` Copy fixa da nota de fechamento — hoje a frase nomeia o mês corrente. Se [W] pedir copy
  fixa, entra como literal no contrato e vira aceite aqui.

---

## UC-PAINEL-01 · Os 6 KPIs aparecem na ordem contratada · `must`

- **Persona:** gestor abrindo o dia. A ordem é a hierarquia de atenção: quadro → presença → problema →
  custo → decisão pendente. Reordenar muda o que ele olha primeiro.
- **Aceite:** Dado um business com marcações do dia · Quando abro `/ponto` · Então vejo, nesta ordem,
  "Colaboradores ativos", "Presentes agora", "Atrasos hoje", "Faltas hoje", "HE do mês" e
  "Aprovações pendentes" — copy literal.
- **Contrato:** `ponto-painel.contract.json` §`painel-kpis` · charter §Goals.
- **Regressão que defende:** trocar um rótulo por sinônimo ("Presentes" por "Online") ou reordenar num
  refactor de grid — a catraca de copy pega, este UC explica por quê.
- **Status: ⬜ não verificado.**

---

## UC-PAINEL-02 · O painel não atravessa empregadores · `must` `[T0]`

- **Persona:** plataforma multi-tenant. Presença nominal é PII e o painel a exibe a cada 30 segundos.
- **Aceite:** Dado colaboradores em outro business · Quando abro `/ponto` como usuário do meu business ·
  Então nenhum aparece em KPI, faixa de presença, feed ou fila.
- **Contrato:** charter §Non-Goals (*"Não agrega dados de outro business"*) · ADR 0093 · LGPD Art. 7º II.
- **Regressão que defende:** agregados com `sum()`/`join` são onde o escopo se perde — cada KPI é uma
  query, e basta uma sem `business_id`.
- **Nota de teste:** biz=1 vs business fictício — **nunca biz=4** (ADR 0101).
- **Status: ⬜ não verificado.**

---

## UC-PAINEL-03 · Fila de aprovações vazia se declara, não some · `must`

- **Persona:** gestor sem pendências. Bloco ausente é ambíguo (não tem, ou não carregou?); frase explícita
  encerra a dúvida.
- **Aceite:** Dado nenhuma intercorrência aguardando decisão · Quando abro `/ponto` · Então o bloco
  "Fila de aprovações" continua visível e diz "Nenhuma intercorrência aguardando decisão."
- **Contrato:** `painel-fila-aprovacoes` (estados `com-pendentes`/`vazio`) · DS `EmptyState` (WHY + WHAT).
- **Regressão que defende:** `{count > 0 && <Fila/>}` — o atalho que apaga o bloco inteiro.
- **Status: ⬜ não verificado.**

---

## UC-PAINEL-04 · O painel é read-only — nenhuma ação muta · `must`

- **Persona:** gestor curioso clicando tudo. Aprovar por engano a partir de um resumo é decisão sem
  contexto — a decisão mora em `/ponto/aprovacoes`.
- **Aceite:** Dado o painel carregado · Quando aciono qualquer controle (inclusive "Bater ponto" e
  "Ver fila completa") · Então nenhuma requisição de escrita parte desta tela — só navegação.
- **Contrato:** charter §Non-Goals + §Anti-hooks (*"Não bate ponto aqui"*, *"Não aprova/rejeita"*,
  *"dashboard é read-only"*) · Portaria MTP 671/2021 (marcação append-only).
- **Regressão que defende:** "só um botãozinho de aprovar rápido" — o pedido que reaparece a cada demo.
- **Status: ⬜ não verificado.**

---

## UC-PAINEL-05 · O polling de 30s recarrega só props de leitura e morre no unmount · `should`

- **Persona:** operação com o painel aberto o dia todo num monitor de parede.
- **Aceite:** Dado o painel aberto · Quando passam 30s · Então recarregam apenas
  `kpis`, `presenca_agora`, `atividade_recente`, `alertas`, `server_time` · E quando navego para outra
  tela · Então o intervalo é limpo e nenhuma requisição adicional parte.
- **Contrato:** charter §Automation hooks + §Anti-hooks (*"não persiste estado do polling"*) ·
  §Pendências (custo do polling com `Inertia::defer`).
- **Regressão que defende:** `router.reload()` sem `only` — refaz todos os agregados a cada 30s por
  aba aberta.
- **Status: ⬜ não verificado.**

---

## UC-PAINEL-06 · Divergência que trava o fechamento aparece antes dos KPIs · `should`

- **Persona:** RH no fechamento. Um dia em divergência trava o mês; se a nota vier depois dos números, ele
  fecha lendo totais que ainda vão mudar.
- **Aceite:** Dado ao menos um dia com estado `DIVERGENCIA` no mês corrente · Quando abro `/ponto` ·
  Então a nota aparece **acima** do bloco de KPIs e cita "DIVERGENCIA" · E Dado nenhuma divergência ·
  Então a nota não é renderizada.
- **Contrato:** `painel-nota-fechamento` (estados `com-pendencia`/`sem-pendencia`/`so-divergencia`) +
  `ordem` (primeiro id da sequência).
- **Regressão que defende:** mover a nota para um rodapé "de avisos" numa arrumação de layout — a
  `ordem` do contrato existe exatamente para isso.
- **Status: ⬜ não verificado.**
