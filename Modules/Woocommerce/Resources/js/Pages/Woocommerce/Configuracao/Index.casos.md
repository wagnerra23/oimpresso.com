---
id: modules-woocommerce-resources-js-pages-woocommerce-configuracao-index-casos
casos: Configuração da loja WooCommerce · /woocommerce/api-settings
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a consumer key/secret ou o segredo de webhook na página é vazamento de credencial da loja (Tier 0); salvar que apaga a chave sem querer derruba a sincronização.
owner: wagner
last_run: "2026-10-14"
---

# Casos de Uso & Aceite — Configuração da loja WooCommerce

> Thread `woocommerce/playbook/02`. Derivados do `WoocommerceController::apiSettings`/`updateSettings` e da Blade
> `woocommerce::woocommerce.api_settings` (RUNBOOK-configuracao) — não do `Index.tsx`.
> Teste: [`Modules/Woocommerce/Tests/Feature/ConfiguracaoContratoTest.php`](../../../../../Tests/Feature/ConfiguracaoContratoTest.php),
> lane `acessos-pest.yml` (MySQL). A Blade está travada em `ConfiguracaoBaselineTest`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-WOOC-01 · Com a flag ligada, a configuração abre em React
- **Persona:** administrador da loja virtual.
- **Aceite:** Dado `woocommerce.access_woocommerce_api_settings` e a flag `useV2WoocommercePainel` ligada · Quando faço
  `GET /woocommerce/api-settings` como o browser faz (`X-Inertia` **e** `X-Requested-With`) · Então renderiza
  **`Woocommerce/Configuracao/Index`**.
- **Status: 🧪**

## UC-WOOC-02 · Com a flag desligada, ou com `?classico=1`, a Blade continua
- **Aceite:** Dado a flag desligada · Quando faço `GET /woocommerce/api-settings` · Então a resposta é a Blade
  `woocommerce::woocommerce.api_settings` · E com a flag ligada e `?classico=1` também.
- **Status: 🧪**

## UC-WOOC-03 · Nenhum segredo chega à página · `[T0]`
- **Aceite:** Dado consumer key, consumer secret e os 4 segredos de webhook salvos · Quando abro a configuração ·
  Então nenhum dos 6 valores aparece na resposta · E cada um aparece só como preenchido · E o endereço da loja aparece.
- **Status: 🧪**

## UC-WOOC-04 · Salvar pela tela nova não apaga segredo deixado em branco · `[T0]`
- **Aceite:** Dado segredos salvos · Quando salvo pela tela nova com o consumer secret e um segredo de webhook em branco
  e uma consumer key nova · Então o secret e o webhook continuam os de antes · E a key é a nova · E o marcador
  `manter_segredos` não entra na configuração salva · E a configuração do outro negócio não muda.
- **Status: 🧪**

## UC-WOOC-05 · Só os locais e grupos de preço do meu negócio · `[T0]`
- **Aceite:** Dado um grupo de preço meu e um de outro negócio · Quando abro a configuração · Então vejo o meu e não o
  do outro.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Testar a conexão com a loja sem travar a abertura da página.
- [BACKLOG] Frequência da sincronização automática (o ERP só guarda ligado/desligado).

## Trilha do tempo
- 2026-10-14 · [CL] criado com a F3 da thread `woocommerce/playbook/02`.
