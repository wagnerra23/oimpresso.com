---
id: resources-js-pages-cliente-grupos-index-casos
casos: Grupos de cliente · /customer-group
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o grupo define o preço que o cliente paga na venda; valor gravado errado ou grupo de outro negócio na lista é incidente.
owner: wagner
last_run: "2026-10-05"
---

# Casos de Uso & Aceite — Grupos de cliente

> Thread `cliente/playbook/03` (D2 = tela própria). Derivados do comportamento do `CustomerGroupController`
> (Blade `customer_group/*`, medido em 2026-10-05) e do protótipo `cliente-grupos.jsx` — não do `Index.tsx`.
> Teste: [`tests/Feature/Cliente/ClienteGruposContratoTest.php`](../../../../../tests/Feature/Cliente/ClienteGruposContratoTest.php),
> lane `cliente-pest.yml` (MySQL).
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-CGRP-01 · Abrir os grupos em React
- **Persona:** quem cadastra cliente e quer ajustar o preço de um grupo (VIP, atacado).
- **Aceite:** Dado `customer.view` · Quando faço `GET /customer-group` como o browser faz (`X-Inertia` **e**
  `X-Requested-With`) · Então renderiza Inertia **`Cliente/Grupos/Index`** com `tabelas` e `pode` — não o JSON
  da DataTable antiga.
- **Status: 🧪**

## UC-CGRP-02 · A lista é do meu negócio, com quantos cadastros usam cada grupo · `[T0]`
- **Aceite:** Dado um grupo do meu negócio com 2 clientes e um grupo de outro negócio · Quando a lista carrega
  (prop deferida `grupos`) · Então vejo o meu com `cadastros = 2` e não vejo o alheio.
- **Status: 🧪**

## UC-CGRP-03 · O percentual chega igual, com sinal e decimal · `[valor]`
- **Persona:** quem cadastra "Atacado −10%" ou "Revenda +5,5%".
- **Aceite:** Dado o texto que a tela manda (`"10,50"`, `"-5,25"`, `"0,00"`, `"12"`, `"-10"`) · Quando grava ·
  Então o banco guarda o mesmo número que o `num_uf` devolve para o texto (dois caminhos, um resultado).
  Positivo aumenta o preço de venda; negativo diminui — é o que o sistema faz, não um desconto.
- **Status: 🧪**

## UC-CGRP-04 · Editar não alcança grupo de outro negócio · `[T0]`
- **Aceite:** Dado um grupo meu e um alheio · Quando edito o meu para `"-7,50"` · Então ele passa a −7,5 · E
  quando tento editar o alheio · Então a resposta é `success: false` e ele não muda.
- **Status: 🧪**

## UC-CGRP-05 · O grupo não aceita tabela de preço de outro negócio · `[T0]`
- **Aceite:** Dado uma tabela de preço de outro negócio · Quando crio um grupo apontando para ela · Então é
  recusado e nada é gravado · E com uma tabela do meu negócio, grava.
- **Status: 🧪** — até esta thread o `store/update` gravava qualquer `selling_price_group_id`.

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] "Ver cadastros do grupo" no menu da linha, abrindo a lista de clientes filtrada (o protótipo tem;
  `Cliente/Index` ainda não lê o filtro da URL).
- [BACKLOG] Grupo padrão que não pode ser excluído (o protótipo marca `id = 1`; o backend não tem essa regra).

## Trilha do tempo
- 2026-10-05 · [CL] criado com a thread `cliente/playbook/03`.
