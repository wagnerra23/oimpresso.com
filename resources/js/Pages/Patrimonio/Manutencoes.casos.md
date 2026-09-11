---
casos: Patrimonio/Manutencoes — listagem de manutenções do patrimônio (MWART, ADR 0104)
irmaos: Manutencoes.charter.md (lei) · memory/requisitos/AssetManagement/RUNBOOK-manutencoes.md (F1 PLAN)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-09-11"
---

# Casos de Uso & Aceite — Patrimonio/Manutencoes

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2 (ADR 0264): UC declarado sem teste citando o id = órfão.
> Teste que os defende: [`Modules/AssetManagement/Tests/Feature/ManutencoesContratoTest.php`](../../../../Modules/AssetManagement/Tests/Feature/ManutencoesContratoTest.php).

> **Por que três UC com id.** A onda entrega a listagem migrada em **paridade com o Blade**, e
> cada UC aqui tem teste que roda. Os cenários que o protótipo desenha mas o banco não sustenta
> (custo, prestador, devolução, KPIs, "Concluir") ficam no `[BACKLOG]` — prosa honesta, sem id —
> e **viram UC na onda que trouxer a fonte de dado**. Declará-los agora criaria órfão e
> quebraria o G-2.

> **Onde os testes rodaram:** CT 100 (`oimpresso-staging`, MySQL real), 2026-09-08 — nunca local
> ([`proibicoes.md §Ambiente`](../../../../memory/proibicoes.md)). Tenant fictício **98**,
> adversário **99** ([ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)).

---

## UC-MANU-01 · A listagem mostra as manutenções da MINHA empresa, e só dela

- **Persona:** quem acompanha o patrimônio — precisa confiar que a fila é da casa dele.
- **Aceite:** Dado dois businesses com manutenções cadastradas · Quando o usuário do business
  98 abre `/asset/asset-maintenance` com `asset.view_all_maintenance` · Então a listagem traz a
  manutenção de 98 e **nenhuma** de 99.
- **Teste:** `ManutencoesContratoTest.php` — **dois** `it()` citando `UC-MANU-01`: o cenário
  direto e **o espelho** (o usuário de 99 vê a sua e não a de 98). O espelho existe porque,
  sozinho, o `not->toContain` do primeiro passaria também se a manutenção de 99 simplesmente
  não existisse — mesmo raciocínio do `UC-BENS-01`.
- **Regressão que defende:** vazamento cross-tenant
  ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md), Tier 0).
  `AssetMaintenance` **não tem global scope** — o isolamento é o `where` explícito do `index()`.
- **Status: 🧪** — os dois cenarios (direto e espelho) passam no CT 100 (run 2026-09-08): 4 passed / 34 assertions no arquivo, estavel em 3 execucoes seguidas.

## UC-MANU-02 · Quem só pode ver as suas, vê só as suas — e a tela AVISA

- **Persona:** o técnico com `asset.view_own_maintenance` (papel restrito). Ele precisa saber
  que está vendo um recorte, senão "não há manutenção" e "não há manutenção **minha**" viram a
  mesma tela.
- **Aceite:** Dado duas manutenções do MESMO business, uma com o usuário como `assigned_to` e
  outra de terceiro · Quando ele abre a tela tendo **apenas** `view_own_maintenance` · Então a
  listagem traz **só** a dele, **e** o payload declara `permissoes.vejo_todas = false` (é o que
  faz a tela renderizar o aviso de escopo).
- **Teste:** `ManutencoesContratoTest.php`, `it()` citando `UC-MANU-02`.
- **Regressão que defende:** o filtro de escopo do `index()` (`:73`) — escrito exatamente para
  este perfil, e que o gate quebrado **barrava** até o [#7034](https://github.com/wagnerra23/oimpresso.com/pull/7034)
  (o `&&` exigia as duas permissões, que a UI de papéis torna mutuamente exclusivas).
- **Nota de método:** as duas manutenções são do mesmo business de propósito — assim o recorte
  por dono é a **única** explicação possível para a segunda sumir; se fossem de businesses
  diferentes, o filtro de tenant já bastaria e o teste mediria outra coisa.
- **Status: 🧪** — passa no CT 100 (run 2026-09-08). O mutante B (controller sem o recorte por dono) o DERRUBA — bite-test por mutacao, nao afirmacao.

## UC-MANU-03 · A tela não inventa dinheiro que o banco não guarda

- **Persona:** [W]. A decisão de 2026-09-08 foi *"o custo já foi decidido nas regras do Blade,
  deve ser igual"* — e "igual ao Blade" significa **sem custo**.
- **Aceite:** Dado a tela de manutenções renderizada · Quando se inspeciona o payload Inertia ·
  Então **nenhuma** linha traz campo de valor (`custo`, `cost`, `valor`, `amount`, `preco`,
  `price`, `total`), e a tela não declara coluna de custo.
- **Teste:** `ManutencoesContratoTest.php`, `it()` citando `UC-MANU-03`.
- **Regressão que defende:** o Non-Goal do charter virando código — é o canon *"Non-Goals +
  Automation Anti-hooks: cada item vira Pest GUARD"*. Sem ele, uma onda futura acrescenta a
  coluna "porque o protótipo mostra" e ninguém percebe que **não há fonte**: a tabela
  `asset_maintenances` não tem coluna de valor, e o Model ainda audita `amount`, que **não
  existe** (`_saida-04.md §2a-bis`) — ou seja, o nome errado já está no repo esperando ser
  copiado.
- **Nota de método:** o teste asserta sobre o **payload servido**, não sobre o texto do `.tsx` —
  grep em fonte mediria a escrita, não o contrato (LC-11: presença ≠ comportamento).
- **Status: 🧪** — passa no CT 100 (run 2026-09-08). O mutante A (payload com campo `custo`) o DERRUBA — bite-test por mutacao, nao afirmacao.

---

## `[BACKLOG]` — o que o protótipo desenha e esta onda não entrega

Prosa sem id de propósito: **vira UC quando existir teste que o cite** (G-2).

- `[BACKLOG]` Coluna **Custo** e os KPIs "Custo no ano" / "Maior conserto" — não há coluna de
  valor na tabela, e o Blade não os mostra. **Decisão [W] 2026-09-08: igual ao Blade, sem
  custo.** Reabrir exige decidir a fonte do dado primeiro, e aí é migration + REGRA MESTRE de
  VALOR.
- `[BACKLOG]` Colunas **Prestador** e **Devolvido** — não existem no banco.
- `[BACKLOG]` KPI **"Em aberto"** — contagem sobre o conjunto inteiro; derivá-la da página
  corrente daria número que mente. Pede agregação no servidor.
- `[BACKLOG]` Ação **"Concluir"** — sem endpoint, e o efeito declarado (criar título a pagar no
  Financeiro) é dinheiro.
- `[BACKLOG]` **Escopo de escrita por dono** — `edit`/`update`/`destroy` filtram só por
  `business_id`. Não é regressão desta tela; é decisão de produto pendente de [W], e a
  permissão para isso não existe no módulo.

