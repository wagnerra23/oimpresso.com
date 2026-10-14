---
id: resources-js-pages-restaurante-modificadores-index-casos
casos: Modificadores · /modules/modifiers
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: conjunto ou produto de outro negócio na tela é incidente Tier 0; esconder a lista de quem cadastra é a classe A (#5432).
owner: wagner
last_run: "2026-10-13"
---

# Casos de Uso & Aceite — Modificadores

> Thread `restaurante/playbook/02` (Modificadores). Derivados do `ModifierSetsController` e da Blade
> `restaurant.modifier_sets.index` (RUNBOOK-modificadores) — não do `Index.tsx`.
> Teste: [`tests/Feature/Restaurante/ModificadoresContratoTest.php`](../../../../../tests/Feature/Restaurante/ModificadoresContratoTest.php),
> lane `acessos-pest.yml` (MySQL). A Blade está travada em `ModificadoresBaselineTest`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-MODIF-01 · Com a flag ligada, a tela abre em React
- **Persona:** quem cuida do catálogo e dos acabamentos oferecidos no PDV.
- **Aceite:** Dado `product.view` e a flag `useV2RestauranteModificadores` ligada · Quando faço `GET /modules/modifiers`
  como o browser faz (`X-Inertia` **e** `X-Requested-With`) · Então renderiza **`Restaurante/Modificadores/Index`**.
- **Status: 🧪**

## UC-MODIF-02 · Com a flag desligada, ou com `?classico=1`, a Blade continua
- **Aceite:** Dado a flag desligada · Quando faço `GET /modules/modifiers` · Então a resposta é a Blade
  `restaurant.modifier_sets.index` · E com a flag ligada e `?classico=1` também.
- **Status: 🧪**

## UC-MODIF-03 · Meus conjuntos, com modificadores e produtos do meu negócio · `[T0]`
- **Aceite:** Dado um conjunto meu com os modificadores Bastão e Ilhós, ligado a um produto meu e a um produto de outro
  negócio, e um conjunto de outro negócio · Quando a lista carrega (prop deferida `conjuntos`) · Então vejo o meu com
  `modificadores` [Bastão, Ilhós] · E `produtos` só com o produto meu · E não vejo o conjunto alheio.
- **Status: 🧪**

## UC-MODIF-04 · Quem só cadastra também vê a lista
- **Aceite:** Dado um usuário só com `product.create` (sem `product.view`) · Quando abro a tela com a flag ligada ·
  Então ela abre · E `pode_gerenciar` vem verdadeiro · E um usuário só com `product.view` recebe `pode_gerenciar` falso.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Cadastrar, editar e ligar produtos no drawer.
- [BACKLOG] Preço do modificador na lista — decisão [W], com dupla prova (regra mestre de valor).

## Trilha do tempo
- 2026-10-13 · [CL] criado com a F3 da thread `restaurante/playbook/02`.
