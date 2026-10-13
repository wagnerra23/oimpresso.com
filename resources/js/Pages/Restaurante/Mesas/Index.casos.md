---
id: resources-js-pages-restaurante-mesas-index-casos
casos: Mesas · /modules/tables
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a mesa de outro negócio na tela é incidente Tier 0; uma mesa dada como livre com pedido em aberto engana o salão.
owner: wagner
last_run: "2026-10-13"
---

# Casos de Uso & Aceite — Mesas

> Thread `restaurante/playbook/01` (Mesas). Derivados do `TableController`, da Blade `restaurant.table.index` e da regra
> de pedido aberto do `RestaurantUtil` (RUNBOOK-mesas) — não do `Index.tsx`.
> Teste: [`tests/Feature/Restaurante/MesasContratoTest.php`](../../../../../tests/Feature/Restaurante/MesasContratoTest.php),
> lane `acessos-pest.yml` (MySQL). A Blade está travada em `MesasBaselineTest`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-MESA-01 · Com a flag ligada, a tela abre em React
- **Persona:** quem cuida do salão e confere quais mesas estão em uso.
- **Aceite:** Dado `access_tables` e a flag `useV2RestauranteMesas` ligada · Quando faço `GET /modules/tables` como o
  browser faz (`X-Inertia` **e** `X-Requested-With`) · Então renderiza **`Restaurante/Mesas/Index`**.
- **Status: 🧪**

## UC-MESA-02 · Com a flag desligada, ou com `?classico=1`, a Blade continua
- **Aceite:** Dado a flag desligada · Quando faço `GET /modules/tables` · Então a resposta é a Blade
  `restaurant.table.index` · E com a flag ligada e `?classico=1` também.
- **Status: 🧪**

## UC-MESA-03 · Minhas mesas, ocupadas pelo pedido aberto · `[T0]`
- **Aceite:** Dado três mesas minhas — uma com pedido `final` ainda não servido do atendente Larissa Prado, uma com
  pedido já `served` e uma sem pedido — e uma mesa de outro negócio · Quando a lista carrega (prop deferida `mesas`) ·
  Então a primeira vem `ocupada` com 1 pedido aberto e atendente "Larissa Prado" · E as outras duas vêm livres, sem
  atendente · E não vejo a alheia.
- **Status: 🧪**

## UC-MESA-04 · Pedidos de hoje e quem gerencia · `[T0]`
- **Aceite:** Dado duas vendas `final` de hoje com mesa no meu negócio (uma servida) e uma de outro negócio · Quando a
  lista carrega · Então `pedidos_hoje` sobe 2 · E um usuário sem o papel `Admin#<negócio>` recebe `pode_gerenciar`
  falso (a DataTable também esconde editar e excluir dele).
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Cadastrar, editar e excluir mesa no drawer, mantendo o "só `Admin#`" do legado.
- [BACKLOG] Excluir mesa com pedido aberto: o legado deixa; decidir se a tela nova impede — decisão [W].
- [BACKLOG] Aba Atendentes, quando o índice decidir onde ela mora.

## Trilha do tempo
- 2026-10-13 · [CL] criado com a F3 da thread `restaurante/playbook/01`.
