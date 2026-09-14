---
id: resources-js-pages-cms-sitedetails-index-casos
casos: Detalhes do site · /cms/site-details
irmaos: SiteDetails.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: o contrato das chaves (`follow_us`, `faqs`, `btns`…) é durável — o site público lê exatamente esses nomes.
owner: wagner
last_run: "—"
---

# Casos de Uso & Aceite — Detalhes do site

> Teste-âncora proposto: `Modules/Cms/Tests/Feature/CmsSiteDetailsPainelTest.php`.
>
> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.

---

## UC-CMSD-01 · Abrir os detalhes do site
- **Aceite:** Dado superadmin · Quando `GET /cms/site-details` · Então renderiza Inertia **`Cms/SiteDetails/Index`** com `details{}` contendo as chaves já gravadas (JSON decodificado).
- **Teste:** `CmsSiteDetailsPainelTest::test_index_renderiza_inertia_com_details`
- **Status: ⬜**

## UC-CMSD-02 · Salvar em uma seção não apaga as outras
- **Persona:** Eliana — só quis trocar o telefone do suporte.
- **Aceite:** Dado `follow_us` e `faqs` já gravados · Quando envio o formulário mexendo só em `contact_us` · Então `follow_us` e `faqs` continuam intactos.
- **Teste:** `CmsSiteDetailsPainelTest::test_store_parcial_preserva_outras_chaves`
- **Regressão que defende:** um POST por seção apagaria o resto (o Model faz `updateOrCreate` por chave).
- **Status: ⬜**

## UC-CMSD-03 · Rede social vazia esconde o ícone
- **Aceite:** Dado `follow_us.twitter` vazio · Quando abro o site público · Então o ícone do X/Twitter não é renderizado.
- **Teste:** `[BACKLOG]` — assertar ausência no `Site/Home`.
- **Status: ⬜**

## UC-CMSD-04 · E-mail de aviso é o destinatário dos leads
- **Aceite:** Dado `notifiable_email = contato@exemplo.com` · Quando um lead chega pelo formulário público · Então a notificação vai para esse endereço; Dado vazio · Então nenhuma notificação sai e o lead fica marcado como não notificado.
- **Teste:** `CmsSiteDetailsPainelTest::test_notifiable_email_define_destinatario_do_lead`
- **Status: ⬜**

## UC-CMSD-05 · Logo trocada aparece no site
- **Aceite:** Quando envio um PNG como `logo` · Então `logo_url` aponta para `uploads/cms/<arquivo>` e o site público usa essa imagem.
- **Teste:** `[BACKLOG]` — exige disco fake.
- **Status: ⬜**

## UC-CMSD-06 · Alteração de settings deixa rastro
- **Aceite:** Quando altero `notifiable_email` · Então há registro no activitylog com o valor antigo e o novo (`logOnlyDirty`), sem PII em log de exceção.
- **Teste:** `CmsSiteDetailsPainelTest::test_alteracao_de_settings_gera_activitylog`
- **Status: ⬜**

## UC-CMSD-07 · Usuário comum não entra
- **Aceite:** Dado usuário sem `superadmin` · Quando `GET /cms/site-details` · Então 403.
- **Teste:** `CmsSiteDetailsPainelTest::test_usuario_sem_superadmin_recebe_403`
- **Status: ⬜**

## UC-CMSD-08 · Código de medição não roda no painel
- **Aceite:** Dado `custom_js` com `<script>` · Quando abro `/cms/site-details` · Então o script **não** é executado nem injetado no painel; aparece como texto no campo.
- **Teste:** `CmsSiteDetailsPainelTest::test_custom_js_nao_e_injetado_no_painel`
- **Status: ⬜**

---

## Rastreabilidade (UC → regra do charter)

| UC | Regra |
|---|---|
| UC-CMSD-01 | S1 |
| UC-CMSD-02 | S1 |
| UC-CMSD-03 | S1 |
| UC-CMSD-04 | S4 |
| UC-CMSD-05 | S2 |
| UC-CMSD-06 | S3 |
| UC-CMSD-07 | S6 |
| UC-CMSD-08 | S6 |

## Como rodar a suíte
1. `docker exec oimpresso-staging php artisan test --filter=CmsSiteDetailsPainelTest` (CT100).
2. `npm run casos:results`.
3. Rodar ao fim de toda mexida em `SettingsController` ou `CmsSiteDetail`.

## Trilha do tempo
- 2026-08-19 · [CC] criado junto do F1. Fonte: espelho local de `Modules/Cms` — [CL] revalida no `main`.
