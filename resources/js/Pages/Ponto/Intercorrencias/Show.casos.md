---
id: resources-js-pages-ponto-intercorrencias-show-casos
casos: Detalhe da intercorrência · /ponto/intercorrencias/{id}
irmaos: Show.charter.md (lei) · SDD-espelho-e-jornada-v1.0.md §5.3 F4 + §6.2 (contrato)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a prova documental de por que uma ausência foi (ou não) abonada.
owner: wagner
last_run: "2026-07-27"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Ponto · MySQL)"
---

# Casos de Uso & Aceite — Detalhe da intercorrência

> **Âncora:** `CU-PONTO-05`, `CU-PONTO-06` e `CU-PONTO-12` do
> [SDD §6.2/§6.5](../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md) + **US-PONTO-003**.
> Fonte 4 (Delphi) **ausente** — SDD §0.1.
>
> ⚖️ **Força do veredito:** lane `PHP / Pest (Ponto · MySQL)` — **advisory**: não bloqueia merge.
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-INTSHOW-01 | Só rascunho pode ser editado | must | `CU-PONTO-05` + US-PONTO-003 | `JornadaWorkflowContratoTest` | 🧪 sem veredito |
| UC-INTSHOW-02 | Intercorrência de outro empregador → 404 | must `[T0]` | `CU-PONTO-12` + ADR 0093 | `JornadaWorkflowContratoTest` | 🧪 sem veredito |
| UC-INTSHOW-03 | O detalhe mostra quem decidiu e por quê | must | `CU-PONTO-06` + US-PONTO-003 | `JornadaWorkflowContratoTest` | 🧪 sem veredito |

**[BACKLOG]:**

- `[BACKLOG]` **A edição sai do app React e cai na Blade AdminLTE.** Medido: `Route::resource` expõe
  `GET /ponto/intercorrencias/{id}/edit` → `IntercorrenciaController@edit` → `view('pontowr2::intercorrencias.edit')`,
  e **não existe `Intercorrencias/Edit.tsx`**. Varredura contada: **21 renders nos controllers = 20 Inertia
  + 1 Blade** (SDD §5.4 #1). Não vira UC agora porque a decisão — portar a tela ou aposentar a rota — é
  do [W], não do teste.
- `[BACKLOG]` Anexo (atestado digitalizado) fica em storage privado escopado por business — US-PONTO-003
  cita `anexo_path`, mas nenhuma tela do trio o exibe hoje.

---

## UC-INTSHOW-01 · Só rascunho pode ser editado · `must`

- **Persona:** colaborador que registrou um atestado e quer corrigir a data. Enquanto é rascunho, é dele.
  Depois de submetido, virou peça de um processo de decisão — mexer nele por fora quebra a trilha.
- **Aceite:** Dado uma intercorrência que **já saiu** do estado de rascunho (pendente, aprovada ou
  rejeitada) · Quando tento abrir a edição dela · Então o acesso é **negado** (403), não uma tela de
  edição.
- **Teste:** `Modules/Ponto/Tests/Feature/JornadaWorkflowContratoTest.php` — `UC-INTSHOW-01`.
- **Contrato:** `CU-PONTO-05` (SDD §6.2) · US-PONTO-003 (ciclo
  `RASCUNHO → PENDENTE → APROVADA|REJEITADA → APLICADA`) · `IntercorrenciaController@edit`
  (`abort_unless($estado === ESTADO_RASCUNHO, 403)`).
- **Regressão que defende:** o `update()` **não** repete a guarda do `edit()` — ele valida o payload e
  salva. Hoje a proteção mora só na porta de entrada da tela. Se alguém chamar o update direto, ou se a
  guarda do `edit` cair num refactor, uma intercorrência aprovada pode ser reescrita depois da decisão.
  Este UC fixa a regra no lugar onde ela é observável.
- **Status: 🧪 sem veredito.**

---

## UC-INTSHOW-02 · Intercorrência de outro empregador → 404 · `must` `[T0]`

- **Persona:** plataforma multi-tenant. A intercorrência carrega motivo médico e justificativa em texto
  livre — é o dado mais sensível do módulo depois da biometria.
- **Aceite:** Dado o id de uma intercorrência de **outro** business · Quando acesso
  `/ponto/intercorrencias/{id}` · Então recebo **404** — nunca 200 com o conteúdo.
- **Teste:** `JornadaWorkflowContratoTest.php` — `UC-INTSHOW-02`.
- **Contrato:** `CU-PONTO-12` · US-PONTO-007 ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) · LGPD Art. 7º II.
- **Regressão que defende:** `IntercorrenciaController@show` usa `Intercorrencia::with(...)->findOrFail($id)`
  **sem** filtro explícito — a defesa é só o global scope (SDD §9 D-5). Este UC é o que faz a defesa única
  virar defesa **testada**.
- **Nota de teste:** biz=1 vs business fictício — **nunca biz=4** ([ADR 0101]).
- **Status: 🧪 sem veredito.**

---

## UC-INTSHOW-03 · O detalhe mostra quem decidiu e por quê · `must`

- **Persona:** RH montando defesa em reclamatória, ou o próprio colaborador conferindo por que a falta
  não foi abonada. A resposta tem que estar no documento, não na memória de alguém.
- **Aceite:** Dado uma intercorrência **rejeitada** · Quando abro o detalhe · Então vejo o estado, **quem
  decidiu** e o **motivo da rejeição** registrado.
- **Teste:** `JornadaWorkflowContratoTest.php` — `UC-INTSHOW-03`.
- **Contrato:** `CU-PONTO-06` (SDD §6.2) · US-PONTO-003 (aceitação nomeia `aprovador_id`, `aprovado_em`,
  `motivo_rejeicao`) · pareia com `UC-APROV-01` (que garante que o motivo **existe**); este garante que
  ele **aparece**.
- **Regressão que defende:** exigir o motivo na entrada (UC-APROV-01) e não exibi-lo na saída é trilha
  que não serve pra nada. Os dois UC juntos fecham o ciclo — separados, cada um passa sozinho enquanto o
  conjunto falha.
- **Status: 🧪 sem veredito.**
