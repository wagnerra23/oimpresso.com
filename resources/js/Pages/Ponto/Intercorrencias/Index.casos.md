---
id: resources-js-pages-ponto-intercorrencias-index-casos
casos: Fila de intercorrências · /ponto/intercorrencias
irmaos: Index.charter.md (lei) · SDD-espelho-e-jornada-v1.0.md §5.3 F4 + §6.2/§6.5 (contrato)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é onde o RH acompanha o que ainda não foi decidido — e o que ficou pendente vira falta na folha.
owner: wagner
last_run: "2026-09-05"
last_run_ci: "O bump e REVALIDACAO DE LEITURA disparada pelo G-6 (o .tsx mudou depois do last_run anterior), NAO veredito: 0 UC executado por mim, e o numero e esse mesmo -- Pest roda no CT100/CI (ADR 0062). MOTIVO do diff: o A11yAxeBrowserTest passou a auditar esta tela (entrou em tests/Browser/visreg-screens.json neste mesmo PR) e ela reprovou com axe CRITICAL no run 33939809556. A regra foi `button-name` (Buttons must have discernible text): os 2 SelectTrigger do bloco de filtros (Estado, Tipo) sao <button role='combobox'> e o axe nao conta o texto interno deles como nome acessivel. O diff e ATRIBUTO PURO -- htmlFor no <label> ja visivel + id no SelectTrigger. Zero pixel: so atributo, sem classe nem markup novo. POR QUE nenhum UC muda de sentido, MEDIDO no arquivo de teste vigente e nao herdado: varredura contada em Modules/Ponto/Tests/Feature/IntercorrenciaContratoTest.php (335 linhas) da 5 asserts sobre o payload Inertia (json('props / ->props / assertInertia) e ZERO sobre DOM/HTML (assertSee/assertDontSee/querySelector/getContent/assertViewHas), mais ZERO ocorrencia de aria-/htmlFor/label=/accessible. Os 3 ids UC-INTIDX-01..03 estao todos la. Atributo `id`/`htmlFor` nao aparece em prop nenhuma, logo nenhum aceite pode mudar POR CONSTRUCAO -- nao por conveniencia. O veredito dos UC segue com a lane PHP / Pest (Ponto - MySQL), que e advisory."
---

# Casos de Uso & Aceite — Fila de intercorrências

> **Âncora:** `CU-PONTO-05` (§6.2) e `CU-PONTO-12` (§6.5) do
> [SDD](../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md) + **US-PONTO-003**.
> Fonte 4 (Delphi) **ausente** — SDD §0.1.
>
> 🔗 **Não duplica as telas irmãs.** `Aprovacoes/Index.casos.md` cobre a **decisão** (aprovar,
> rejeitar com motivo, lote sem atravessar tenant) e `Intercorrencias/Show.casos.md` cobre o
> **detalhe**. Aqui só entra o que é da **fila**.
>
> ⚖️ **Força do veredito:** lane `PHP / Pest (Ponto · MySQL)` — **advisory**: fica vermelha
> visível, não bloqueia merge (SDD §8.1).
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-INTIDX-01 | A fila traz as intercorrências do meu empregador | must | `CU-PONTO-05` + US-PONTO-003 | `IntercorrenciaContratoTest` | 🧪 sem veredito |
| UC-INTIDX-02 | Intercorrência de outro empregador não aparece na fila | must `[T0]` | `CU-PONTO-12` + ADR 0093 | `IntercorrenciaContratoTest` | 🧪 sem veredito |
| UC-INTIDX-03 | Filtrar por estado devolve só aquele estado | should | `CU-PONTO-05` (estados canon) | `IntercorrenciaContratoTest` | 🧪 sem veredito |

**[BACKLOG]:**

- `[BACKLOG]` Ordem por `data` desc + `created_at` desc e paginação 25/pág — contrato de
  apresentação sem âncora em lei nem US; vira UC quando [W] confirmar que a ordem é parte do
  contrato e não escolha de implementação.
- `[BACKLOG]` **A edição de intercorrência ainda é Blade** (SDD §9 **D-4**): a rota
  `GET /ponto/intercorrencias/{id}/edit` renderiza `pontowr2::intercorrencias.edit` e **não existe
  `Intercorrencias/Edit.tsx`**. Quem clica "editar" num rascunho sai do shell React e cai no
  AdminLTE. Não vira UC desta tela — é a única Blade viva do módulo e fechá-la é trabalho de
  migração (Onda 2 do roadmap), não caso de teste de lista.

---

## UC-INTIDX-01 · A fila traz as intercorrências do meu empregador · `must`

- **Persona:** RH no fechamento do mês, conferindo o que ainda não foi decidido. O que ficar
  pendente vira falta na folha — a fila é o instrumento de não deixar passar.
- **Aceite:** Dada uma intercorrência registrada no meu business · Quando abro
  `/ponto/intercorrencias` · Então ela aparece na fila, com estado e identificação do colaborador.
- **Teste:** `Modules/Ponto/Tests/Feature/IntercorrenciaContratoTest.php` — `UC-INTIDX-01`.
- **Contrato:** `CU-PONTO-05` (SDD §6.2) · US-PONTO-003 · F4 (§5.3).
- **Regressão que defende:** a linha é montada por `transform()` com `optional()` encadeado em
  `colaborador.user`. Um eager-load removido não quebra a query — devolve `nome: '—'` e segue
  verde. O UC observa que a linha chega **com identidade**, não só que a rota responde 200.
- **Status: 🧪 sem veredito.**

---

## UC-INTIDX-02 · Intercorrência de outro empregador não aparece na fila · `must` `[T0]`

- **Persona:** plataforma multi-tenant. A justificativa de uma intercorrência é dado sensível —
  atestado médico, motivo de ausência. Vazamento aqui é LGPD, não bug de UI.
- **Aceite:** Dada uma intercorrência de **outro** business · Quando abro
  `/ponto/intercorrencias` do meu · Então ela **não** aparece.
- **Teste:** `IntercorrenciaContratoTest.php` — `UC-INTIDX-02`.
- **Contrato:** `CU-PONTO-12` (SDD §6.5) · US-PONTO-007 ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) · LGPD Art. 7º II.
- **Regressão que defende:** aqui a defesa é **dupla** — `where('business_id', …)` explícito **e**
  o global scope. Justamente por ser dupla, remover uma não quebra nada visível. O UC fixa o
  **comportamento**, para que a remoção da última defesa apareça. Complementa o
  `UC-PAPR-02` (lote cross-tenant) pelo lado da leitura.
- **Nota `[V0]` de PII:** o teste compara **ids**, nunca o texto da justificativa — a fixture usa
  texto neutro e o assert não imprime conteúdo de intercorrência.
- **Nota de teste:** biz=1 vs stub biz=99 — **nunca biz=4** ([ADR 0101]).
- **Status: 🧪 sem veredito.**

---

## UC-INTIDX-03 · Filtrar por estado devolve só aquele estado · `should`

- **Persona:** o RH que quer ver **só** o que está `PENDENTE` antes de sentar para decidir.
- **Aceite:** Dadas duas intercorrências em estados diferentes · Quando filtro por um estado ·
  Então a fila traz a daquele estado e **não** traz a do outro.
- **Teste:** `IntercorrenciaContratoTest.php` — `UC-INTIDX-03`.
- **Contrato:** `CU-PONTO-05` (ciclo canônico `RASCUNHO → PENDENTE → APROVADA|REJEITADA →
  APLICADA`, com `CANCELADA` como saída) · US-PONTO-003 · `IntercorrenciaController@index`
  (`when($estado, …)`).
- **Regressão que defende:** o filtro é `when($estado, …)` — se a chave do request mudar de nome,
  o `when` recebe `null`, **não filtra nada** e a tela devolve a lista inteira **sem erro**. O
  operador acha que está vendo só os pendentes e decide sobre uma lista errada. Filtro que
  silenciosamente vira no-op é a classe de defeito que só teste pega.
- **Nota de escrita:** o assert verifica os **dois lados** (o do estado pedido está, o do outro
  não). Só o lado positivo passaria com o filtro desligado.
- **Status: 🧪 sem veredito.**

## Trilha do tempo
- 2026-08-08 · [CC] revalidado (bump `last_run`): migração do primary "Nova" do shim
  DEPRECATED `PontoPrimaryButton` pro canon `<PageHeaderPrimary>` (ADR 0190). O shim emitia
  `.os-btn primary`, cuja única família de regras no CSS servido é escopada `.sells-cowork` →
  nenhuma casava e o botão rendia nu (medido em prod: padding 0, radius 0, texto em 2 linhas).
  Só o chrome do header mudou — `label` e `onClick` idênticos; nenhum UC descreve o botão e
  nenhum `Status:` foi promovido. O bump afirma "trio reconciliado com a tela nesta data",
  não "testes rodados" (rodam no CT 100).
