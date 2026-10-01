---
id: modules-whatsapp-resources-js-pages-atendimento-macros-index-casos
casos: Macros do atendimento · /atendimento/macros
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do atendente + critério de aceite verificável (Dado/Quando/Então)
por_que: macro é texto que sai em nome da empresa para o cliente; se a lista ou a edição cruzar business, um tenant manda a resposta do outro. O atalho único por business é o que faz o `/atalho` do composer achar a macro certa.
owner: wagner
last_run: "2026-09-30"
---

# Casos de Uso & Aceite — Macros (gestão)

> US-WA-048 · US-WA-049 (contagem de variantes) · ADR 0093 (multi-tenant Tier 0) · ADR 0135
>
> UCs derivados do **charter** (`Index.charter.md` — Goals · Non-Goals · Anti-hooks) e do
> `MacrosController` só para confirmar o comportamento. **Não derivados do `.tsx`.**
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa (manifesto não
> regravado) · ⬜ não verificado · ❌ quebrou.
>
> **Onde roda:** `tests/Feature/Whatsapp/AtendimentoMacrosJanaTemplatesContratoTest.php`, lane
> sqlite (`.github/ci-sqlite-pest.list`). Fora de sqlite o teste pula — skip não é verde.

---

## UC-MAC-01 · A lista mostra as macros da empresa, as mais usadas primeiro
- **Persona:** admin do atendimento — abre `/atendimento/macros` para revisar as respostas prontas.
- **Aceite:** Dado um business com duas macros (uma usada 9×, outra 2×) e duas variantes na mais usada · Quando a tela carrega a lista · Então vêm as duas, a mais usada primeiro, cada uma com a contagem de variantes (2 e 0).
- **Teste:** `tests/Feature/Whatsapp/AtendimentoMacrosJanaTemplatesContratoTest.php` — `UC-MAC-01 — a lista traz só as macros do business, mais usadas primeiro, com a contagem de variantes`.
- **Regressão que defende:** charter Goals "Listar macros do business" + "Suportar A/B variants". A contagem é o que leva o admin à tela de variantes.
- **Status: ⬜** — aguarda o run do CI deste PR.

---

## UC-MAC-02 · A macro de outra empresa não aparece e não pode ser editada nem apagada
- **Persona:** admin do business 98 — o business 99 tem as macros dele na mesma instalação.
- **Aceite:** Dado uma macro (com variante) no business 99 · Quando abro a lista logado no 98 · Então ela não aparece e a contagem de variantes da minha não soma a dele; e Quando tento editar ou apagar pelo id dela · Então o servidor responde "não encontrado" e a macro dele fica intacta.
- **Teste:** `tests/Feature/Whatsapp/AtendimentoMacrosJanaTemplatesContratoTest.php` — `UC-MAC-02 — macro de outro business não aparece na lista e não é editável nem removível (Tier 0)`.
- **Regressão que defende:** **Tier 0** (ADR 0093) e o anti-hook do charter "macro de biz=X visível em biz=Y".
- **Status: ⬜** — aguarda o run do CI deste PR.

---

## UC-MAC-03 · O atalho é gravado limpo e não se repete dentro da empresa
- **Persona:** admin cadastrando a macro "Pedir CNPJ" com o atalho digitado como `/CNPJ `.
- **Aceite:** Dado o atalho `/CNPJ ` · Quando salvo · Então fica gravado `cnpj`; Quando tento outra macro com `cnpj` no mesmo business · Então é recusada (422) e nada novo é gravado; Quando outro business usa `cnpj` · Então é aceito.
- **Teste:** `tests/Feature/Whatsapp/AtendimentoMacrosJanaTemplatesContratoTest.php` — `UC-MAC-03 — criar normaliza o atalho e recusa atalho repetido no mesmo business, mas não em outro`.
- **Regressão que defende:** charter Mission (atendente dispara via `/<atalho>`): dois atalhos iguais no mesmo business tornam o comando ambíguo.
- **Status: ⬜** — aguarda o run do CI deste PR.

---

## UC-MAC-04 · Editar e remover uma macro
- **Persona:** admin corrigindo o texto da saudação e, depois, removendo-a.
- **Aceite:** Dado a macro `oi` · Quando edito o título e o texto mantendo o atalho `oi` · Então salva sem acusar atalho repetido; Quando removo · Então ela sai da lista.
- **Teste:** `tests/Feature/Whatsapp/AtendimentoMacrosJanaTemplatesContratoTest.php` — `UC-MAC-04 — editar mantém o próprio atalho e remover tira a macro só do business`.
- **Regressão que defende:** charter Goal "Criar/editar macro". A checagem de atalho repetido precisa ignorar a própria macro.
- **Status: ⬜** — aguarda o run do CI deste PR.

---

## Backlog (prosa, sem UC — sem teste que cite)

- `[BACKLOG]` **Charter × código divergem em "remover".** O charter pede *"Toggle ativo/inativo sem hard delete"*; o `MacrosController::destroy` apaga a linha (UC-MAC-04 descreve o que existe). Qual dos dois vale é decisão [W] — não foi resolvido aqui.
- `[BACKLOG]` Filtro por categoria e métricas por macro (enviadas/dia, taxa de resposta 30d) estão nos Goals do charter e não existem no controller (só `used_count`).
- `[BACKLOG]` Atalho `N` para nova macro e placeholders vindos de `MacroPlaceholders::all()` — não verificados nesta rodada.
