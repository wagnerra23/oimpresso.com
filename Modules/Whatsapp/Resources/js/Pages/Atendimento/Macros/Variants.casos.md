---
id: modules-whatsapp-resources-js-pages-atendimento-macros-variants-casos
casos: Variantes A/B de uma macro · /atendimento/macros/{macro}/variants
irmaos: Variants.charter.md (lei)
tecnica: Caso de uso = narrativa do atendente + critério de aceite verificável (Dado/Quando/Então)
por_que: a variante é o texto que o sorteio manda ao cliente; peso fora de 0-100 quebra a distribuição e uma variante de outro business no sorteio manda texto alheio.
owner: wagner
last_run: "2026-09-30"
---

# Casos de Uso & Aceite — Variantes A/B da macro

> US-WA-049 · ADR 0093 (multi-tenant Tier 0)
>
> UCs derivados do **charter** (`Variants.charter.md` — Goals · Non-Goals · Anti-hooks).
> **Não derivados do `.tsx`.** O sorteio ponderado é Non-Goal desta tela (é do
> `MacroVariantPicker`, coberto por `MacroVariantPickerTest`), por isso não vira UC aqui.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa (manifesto não
> regravado) · ⬜ não verificado · ❌ quebrou.
>
> **Onde roda:** `Modules/Whatsapp/Tests/Feature/MacroVariantsCrudTest.php`, lane sqlite
> (`.github/ci-sqlite-pest.list`). Fora de sqlite o teste pula — skip não é verde.

---

## UC-MACV-01 · Criar, editar e remover uma variante
- **Persona:** admin testando duas versões da saudação.
- **Aceite:** Dado uma macro · Quando crio a variante "Versão A" com peso 70 · Então ela fica ligada à macro, ativa, com peso 70; Quando edito título e peso · Então grava; Quando removo · Então some da lista.
- **Teste:** `Modules/Whatsapp/Tests/Feature/MacroVariantsCrudTest.php` — `UC-MACV-01 · R-WA-049-CRUD-001 — store + update + destroy variant funciona`.
- **Regressão que defende:** charter Goals "Criar/editar variante em modal" e "Remover variante".
- **Status: ⬜** — aguarda o run do CI deste PR.

---

## UC-MACV-02 · Rótulo obrigatório e peso entre 0 e 100
- **Persona:** admin preenchendo o modal.
- **Aceite:** Dado o modal · Quando mando rótulo vazio, ou peso 150, ou peso -10 · Então é recusado; Quando mando peso 0 · Então é aceito (variante pausada sem apagar).
- **Teste:** `Modules/Whatsapp/Tests/Feature/MacroVariantsCrudTest.php` — `UC-MACV-02 · R-WA-049-CRUD-002 — validação rejeita label vazio e weight fora 0-100`.
- **Regressão que defende:** charter Goal "peso 0-100" e Automation hook "peso normalizado no backend".
- **Status: ⬜** — aguarda o run do CI deste PR.

---

## UC-MACV-03 · Variante de outra empresa não é vista nem editada
- **Persona:** admin do business 1 — o business 99 tem macro e variante próprias.
- **Aceite:** Dado uma macro com variante no business 99 · Quando abro, listo ou edito por id logado no business 1 · Então não vejo nada dela e a edição responde "não encontrado".
- **Teste:** `Modules/Whatsapp/Tests/Feature/MacroVariantsCrudTest.php` — `UC-MACV-03 · R-WA-049-CRUD-003 — Tier 0 (ADR 0093): biz=1 não acessa variante de biz=99`.
- **Regressão que defende:** **Tier 0** — Non-Goal do charter "não expõe variantes de macros de outro `business_id`".
- **Status: ⬜** — aguarda o run do CI deste PR. O teste usa biz=1/99 (anterior à ADR 0358); não reescrito aqui.

---

## UC-MACV-04 · Marcar a vencedora desativa as outras e guarda o histórico
- **Persona:** admin que viu a "Versão A" responder melhor.
- **Aceite:** Dado três variantes ativas com envios e respostas · Quando marco a A como vencedora · Então a A fica ativa com peso 100, as outras ficam inativas, e os contadores de envio e resposta de todas continuam os mesmos.
- **Teste:** `Modules/Whatsapp/Tests/Feature/MacroVariantsCrudTest.php` — `UC-MACV-04 · R-WA-049-CRUD-004 — mark_winner desativa outras + bump weight 100 + preserva histórico`.
- **Regressão que defende:** charter Goal "Marcar vencedora: desativa as outras e mantém histórico" e Non-Goal "não zera as métricas".
- **Status: ⬜** — aguarda o run do CI deste PR.

---

## Backlog (prosa, sem UC — sem teste que cite)

- `[BACKLOG]` Pendência do próprio charter: não há regra de que os pesos ativos somem 100.
- `[BACKLOG]` Aviso "0 variantes ativas → o envio usa o texto padrão" no cabeçalho — comportamento de tela, sem teste.
