---
id: resources-js-pages-ponto-espelho-index-casos
casos: Seleção de colaborador para o espelho · /ponto/espelho
irmaos: Index.charter.md (lei) · SDD-espelho-e-jornada-v1.0.md §5.3 F1 + §6.1 (contrato)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a porta do espelho — quem entra na lista define quem tem jornada auditável no mês.
owner: wagner
last_run: "2026-07-27"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Ponto · MySQL)"
---

# Casos de Uso & Aceite — Seleção de colaborador para o espelho

> **Âncora:** `CU-PONTO-04` e `CU-PONTO-12` do
> [SDD §6.1/§6.5](../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md), cruzados com a
> **Blade legada** `espelho/index.blade.php` (paridade da migração). Fonte 4 (Delphi) **ausente** no
> módulo — declarado no SDD §0.1.
>
> ⚖️ **Força do veredito:** lane `PHP / Pest (Ponto · MySQL)` — **advisory**: reprova visível,
> **não bloqueia merge**.
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-ESPIDX-01 | Só entra na lista quem tem controle de ponto ativo e não foi desligado | must | `CU-PONTO-04` + Blade | `EspelhoContratoTest` | 🧪 sem veredito |
| UC-ESPIDX-02 | A lista não atravessa empregadores | must `[T0]` | `CU-PONTO-12` + ADR 0093 | `EspelhoContratoTest` | 🧪 sem veredito |
| UC-ESPIDX-03 | O mês escolhido viaja junto para o espelho | should | `CU-PONTO-04` + Blade | `EspelhoContratoTest` | 🧪 sem veredito |

**[BACKLOG]:**

- `[BACKLOG]` Busca por matrícula/nome/CPF filtra a lista — hoje o campo existe `disabled` ("em breve").
  **Não é regressão**: a Blade legada também não tinha busca (SDD §5.3 F1). Vira UC quando virar feature.
- `[BACKLOG]` Coluna "Controla ponto" da Blade não foi portada — perda **cosmética**, já que a query
  filtra `controla_ponto = true` e a coluna era constante "Sim" (SDD §5.3 F1).

---

## UC-ESPIDX-01 · Só entra na lista quem tem controle de ponto ativo e não foi desligado · `must`

- **Persona:** RH abrindo o fechamento do mês. A lista é a definição operacional de *"quem tenho que
  auditar"*. Colaborador desligado ou sem controle de ponto na lista gera trabalho inútil e confusão.
- **Aceite:** Dado colaboradores do meu business em três situações — (a) ativo com `controla_ponto`,
  (b) com `controla_ponto` **desligado**, (c) **desligado** (com data de desligamento) · Quando abro
  `/ponto/espelho` · Então **apenas (a)** aparece na lista.
- **Teste:** `Modules/Ponto/Tests/Feature/EspelhoContratoTest.php` — `UC-ESPIDX-01`.
- **Contrato:** `CU-PONTO-04` (SDD §6.1) · charter §Mission (*"dentre os que têm `controla_ponto` ativo e
  não desligados"*) · Blade `espelho/index.blade.php` (mesma seleção) ·
  CLT Art. 74 §2º (o registro é de quem está sujeito a controle).
- **Regressão que defende:** afrouxar qualquer um dos dois filtros (`controla_ponto` / `desligamento`)
  polui a lista com quem não tem jornada a apurar — e, pior, sugere ao RH que há espelho pendente onde
  não há.
- **Status: 🧪 sem veredito.**

---

## UC-ESPIDX-02 · A lista não atravessa empregadores · `must` `[T0]`

- **Persona:** plataforma multi-tenant. Nome + matrícula + CPF + e-mail de colaborador são PII; a lista os
  expõe toda vez que carrega.
- **Aceite:** Dado colaboradores existentes em outro business · Quando abro `/ponto/espelho` como usuário
  do meu business · Então **nenhum** deles aparece na lista, em nenhuma página.
- **Teste:** `EspelhoContratoTest.php` — `UC-ESPIDX-02`.
- **Contrato:** `CU-PONTO-12` (SDD §6.5) · US-PONTO-007 ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) · LGPD Art. 7º II ·
  charter §Non-Goals (*"Não cruza tenants"*).
- **Regressão que defende:** o `index` tem **defesa dupla** hoje (filtro explícito `where('business_id')`
  **+** global scope da entity). Remover o filtro explícito "porque o scope já cobre" reduz a duas
  camadas para uma — e é exatamente o tipo de simplificação que passa em review.
- **Nota de teste:** biz=1 vs business fictício — **nunca biz=4** ([ADR 0101]).
- **Status: 🧪 sem veredito.**

---

## UC-ESPIDX-03 · O mês escolhido viaja junto para o espelho · `should`

- **Persona:** RH auditando um mês fechado (ex.: competência anterior). Escolher o mês na lista e o
  espelho abrir no mês corrente obriga a escolher duas vezes — e convida ao erro de conferir o mês errado.
- **Aceite:** Dado que selecionei um mês de referência na lista · Quando navego para o espelho de um
  colaborador · Então o espelho abre **naquele mês**, não no mês corrente.
- **Teste:** `EspelhoContratoTest.php` — `UC-ESPIDX-03`.
- **Contrato:** `CU-PONTO-04` · charter §Goals (*"Seletor de mês (...) que propaga para os links"*) ·
  Blade `espelho/index.blade.php` (*"Seletor de mês é propagado para o show via querystring"* — comentário
  literal na view legada).
- **Regressão que defende:** o mês é o único estado da lista. Perdê-lo na navegação faz o RH conferir a
  competência errada — erro silencioso e caro no fechamento.
- **Status: 🧪 sem veredito.**
