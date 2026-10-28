---
id: modules-woocommerce-resources-js-pages-woocommerce-painel-index-casos
casos: Painel do WooCommerce · /woocommerce
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: produto de outro negócio na fila, ou as chaves da loja na página, é incidente Tier 0; um número diferente do da Blade engana quem sincroniza.
owner: wagner
last_run: "2026-10-14"
---

# Casos de Uso & Aceite — Painel do WooCommerce

> Thread `woocommerce/playbook/01` (Painel). Derivados do `WoocommerceController::index` e da Blade
> `woocommerce::woocommerce.index` (RUNBOOK-painel) — não do `Index.tsx`.
> Teste: [`Modules/Woocommerce/Tests/Feature/PainelContratoTest.php`](../../../../../Tests/Feature/PainelContratoTest.php),
> lane `acessos-pest.yml` (MySQL). A Blade está travada em `PainelBaselineTest`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-WOOP-01 · Com a flag ligada, o painel abre em React
- **Persona:** quem cuida da loja virtual e confere o que falta subir.
- **Aceite:** Dado acesso ao módulo e a flag `useV2WoocommercePainel` ligada · Quando faço `GET /woocommerce` como o
  browser faz (`X-Inertia` **e** `X-Requested-With`) · Então renderiza **`Woocommerce/Painel/Index`**.
- **Status: 🧪**

## UC-WOOP-02 · Com a flag desligada, ou com `?classico=1`, a Blade continua
- **Aceite:** Dado a flag desligada · Quando faço `GET /woocommerce` · Então a resposta é a Blade
  `woocommerce::woocommerce.index` · E com a flag ligada e `?classico=1` também.
- **Status: 🧪**

## UC-WOOP-03 · Meus produtos, com a situação certa e o mesmo número da Blade · `[T0]`
- **Aceite:** Dado quatro produtos meus — um sem id na loja, um com id, um com a sincronização desligada e um com id
  alterado depois da última sincronização — e um produto sem id de outro negócio · Quando a lista carrega · Então vejo
  os meus como pendente, sincronizado, fora da fila e alterado · E não vejo o alheio · E `resumo.produtos_pendentes` é
  o mesmo número que a Blade usa no alerta.
- **Status: 🧪**

## UC-WOOP-04 · As chaves da loja nunca chegam à página · `[T0]`
- **Aceite:** Dado URL, consumer key e consumer secret salvos em `woocommerce_api_settings` · Quando abro o painel ·
  Então `configurado` é verdadeiro · E nem a key nem o secret aparecem em nenhum lugar da resposta.
- **Status: 🧪**

## UC-WOOP-05 · Cada bloco de sincronização segue a permissão da Blade
- **Aceite:** Dado um usuário só com `woocommerce.sync_orders` e o módulo na assinatura · Quando abro o painel · Então
  `pode.pedidos` é verdadeiro e `pode.categorias`, `pode.sincronizar` e `pode.configurar` são falsos (os mesmos `@can`
  dos blocos da Blade) · E `ultimas` vem adiada.
- **Status: 🧪**

## UC-WOOP-06 · Reiniciar categorias pela tela nova só limpa o meu negócio · `[T0]`
- **Aceite:** Dado uma categoria minha e uma de outro negócio, as duas com id na loja · Quando chamo
  `/woocommerce/reset-categories` como a tela nova chama (`X-Requested-With`) · Então a minha perde o id na loja · E a
  alheia mantém o dela.
- **Status: 🧪**

## UC-WOOP-07 · O mapa de impostos traz só as minhas taxas, e avisa quando a loja não devolve taxa · `[T0]`
- **Aceite:** Dado uma taxa minha com id na loja e uma de outro negócio · Quando abro o mapa (`/woocommerce/tax-rates-map`) ·
  Então a lista do ERP traz a minha, com o id da loja salvo, e não traz a alheia · E sem lista de taxas da loja,
  `loja_respondeu` é falso e a tela avisa em vez de mostrar um mapa vazio.
- **Status: 🧪**

## UC-WOOP-08 · Gravar o mapa como a tela nova grava não toca taxa de outro negócio · `[T0]`
- **Aceite:** Dado uma taxa minha e uma alheia · Quando mando `taxes[minha] = ''` e `taxes[alheia] = 99` para
  `/woocommerce/map-taxrates` · Então a minha fica sem vínculo · E a alheia mantém o vínculo dela.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Sincronizar produtos em páginas de 100 até `total_products = 0` (só no navegador; precisa de loja de teste).
- [BACKLOG] Preço e estoque enviados por produto — decisão [W] (regra mestre).
- [BACKLOG] Testar a conexão com a loja sem travar a abertura da página.

## Trilha do tempo
- 2026-10-13 · [CL] criado com a F3 da thread `woocommerce/playbook/01`.
- 2026-10-14 · [CL] revalidado na thread 02: as abas saíram para `_components/WoocommerceAbas.tsx`; UC-WOOP-01..04 verdes no CT 100.
- 2026-10-27 · [CL] F3-2: sincronizar e reiniciar na tela nova (UC-WOOP-05, UC-WOOP-06).
- 2026-10-27 · [CL] F3-2: mapa de impostos na tela nova (UC-WOOP-07, UC-WOOP-08).
