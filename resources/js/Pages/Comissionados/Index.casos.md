---
id: resources-js-pages-comissionados-index-casos
casos: Comissionados · /sales-commission-agents
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o percentual define quanto o vendedor recebe; valor gravado errado ou comissionado de outro negócio na tela é incidente.
owner: wagner
last_run: "2026-10-06"
---

# Casos de Uso & Aceite — Comissionados

> Thread `sistema/playbook/03` (= `comissoes/playbook/01`). Derivados do comportamento do
> `SalesCommissionAgentController` (Blade `sales_commission_agent/*` e as guardas de #5970/#6069/#6072,
> medidos em 2026-10-06) e do protótipo `comissionados-page.jsx` — não do `Index.tsx`.
> Teste: [`tests/Feature/Users/ComissionadosContratoTest.php`](../../../../tests/Feature/Users/ComissionadosContratoTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-CMSN-01 · Abrir os comissionados em React
- **Persona:** quem cadastra os agentes de venda e o percentual de cada um.
- **Aceite:** Dado `commission_agent.manage` · Quando faço `GET /sales-commission-agents` como o browser faz
  (`X-Inertia` **e** `X-Requested-With`) · Então renderiza Inertia **`Comissionados/Index`** com `pode.gerenciar = true`
  — não o JSON da DataTable antiga.
- **Status: 🧪**

## UC-CMSN-02 · A lista é do meu negócio, com quantas vendas apontam para cada um · `[T0]`
- **Aceite:** Dado um comissionado meu com 2 vendas vinculadas e um comissionado de outro negócio · Quando a
  lista carrega (prop deferida `agentes`) · Então vejo o meu com `vendas = 2` e não vejo o alheio.
- **Status: 🧪**

## UC-CMSN-03 · O percentual chega igual · `[valor]`
- **Persona:** quem cadastra "Larissa 2,5%" ou corrige para "3,75%".
- **Aceite:** Dado o texto que a tela manda (`"2,50"`, `"3,75"`, `"10"`, `"0,00"`) · Quando cadastra · Então o
  banco guarda o mesmo número que o `num_uf` devolve para o texto (dois caminhos, um resultado) · E editar
  para `"1,25"` grava 1,25.
- **Status: 🧪**

## UC-CMSN-04 · O formulário de edição não abre comissionado de outro negócio · `[T0]`
- **Aceite:** Dado um comissionado alheio · Quando peço `GET /sales-commission-agents/{alheio}/edit` · Então 404
  · E o do meu negócio abre (200).
- **Status: 🧪** — até esta thread o `edit()` fazia `findOrFail($id)` sem negócio.

## UC-CMSN-05 · Quem só vê não ganha as ações
- **Aceite:** Dado só `commission_agent.view` · Quando abro a tela · Então ela renderiza com `pode.gerenciar = false`.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] KPIs, período, meta e situação de pagamento do protótipo — dependem da apuração (`comissoes/02`, D-COM-2).
- [BACKLOG] Regra por faixa de meta ou sobre margem — `Modules/Comissao` (ADR 0151, dormente).
- [BACKLOG] "Ver vendas atribuídas" no menu da linha, abrindo as vendas filtradas pelo agente.

## Trilha do tempo
- 2026-10-06 · [CL] criado com a thread `sistema/playbook/03`.
