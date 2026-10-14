---
id: modules-woocommerce-resources-js-pages-woocommerce-log-index-casos
casos: Log de sincronização do WooCommerce · /woocommerce/view-sync-log
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: log de outro negócio na tela é vazamento Tier 0; um tipo que a permissão esconde aparecendo na tela nova é escalada de acesso; o DataTables da Blade quebrar no meio da migração tira o log de quem ainda usa a tela clássica.
owner: wagner
last_run: "2026-10-14"
---

# Casos de Uso & Aceite — Log de sincronização do WooCommerce

> Thread `woocommerce/playbook/02`. Derivados do `WoocommerceController::viewSyncLog`/`getLogDetails`, do
> `WoocommerceSyncLogRepository` e da Blade `woocommerce::woocommerce.sync_log` (RUNBOOK-log) — não do `Index.tsx`.
> Teste: [`Modules/Woocommerce/Tests/Feature/LogContratoTest.php`](../../../../../Tests/Feature/LogContratoTest.php),
> lane `acessos-pest.yml` (MySQL). A Blade está travada em `LogBaselineTest`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-WOOL-01 · Com a flag ligada, o log abre em React
- **Persona:** quem cuida da loja virtual e confere por que algo não subiu.
- **Aceite:** Dado acesso ao módulo e a flag `useV2WoocommercePainel` ligada · Quando faço `GET /woocommerce/view-sync-log`
  como o browser faz (`X-Inertia` **e** `X-Requested-With`) · Então renderiza **`Woocommerce/Log/Index`**.
- **Status: 🧪**

## UC-WOOL-02 · A Blade e o DataTables dela continuam
- **Aceite:** Dado a flag desligada · Quando faço `GET /woocommerce/view-sync-log` · Então a resposta é a Blade
  `woocommerce::woocommerce.sync_log` · E com a flag ligada e `?classico=1` também · E com a flag ligada, o pedido AJAX
  do DataTables (sem `X-Inertia`) devolve o JSON com `data`.
- **Status: 🧪**

## UC-WOOL-03 · Só os meus logs, e só os tipos que posso ver, nos dois caminhos · `[T0]`
- **Aceite:** Dado um log de produtos e um de pedidos meus e um log de outro negócio · E eu só com a permissão de
  sincronizar produtos · Quando a lista carrega · Então vejo o de produtos · E não vejo o de pedidos nem o alheio · E o
  conjunto é o mesmo que o DataTables devolve.
- **Status: 🧪**

## UC-WOOL-04 · A execução com erro mostra o erro
- **Aceite:** Dado um log de pedidos com o erro "produto não encontrado" registrado · Quando a lista carrega · Então a
  execução traz a mensagem com o nome do produto e o número do pedido · E os registros sincronizados.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Busca por quem sincronizou no servidor (hoje filtra a página).

## Trilha do tempo
- 2026-10-14 · [CL] criado com a F3 da thread `woocommerce/playbook/02`.
