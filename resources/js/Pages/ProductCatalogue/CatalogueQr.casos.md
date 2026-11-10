---
id: resources-js-pages-productcatalogue-catalogueqr-casos
casos: Catálogo QR · /product-catalogue/catalogue-qr
irmaos: CatalogueQr.charter.md (lei) · CatalogueQr.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o QR publica preço de um local sem login; QR apontando pro negócio errado ou aberto a quem não vê produto é vazamento.
owner: wagner
last_run: "2026-11-10"
---

# Casos de Uso & Aceite — Catálogo QR

> Thread `modulos-faltantes/playbook/04`. Derivados do comportamento da Blade `catalogue/generate_qr.blade.php`
> + `CatalogueQrService`, do caso proposto pelo [CC] (`cowork-inbox/modulos-faltantes/catalogo-qr.casos.md`,
> UC-CQR-01…10) e da decisão `PERM-CQR` — não do `.tsx`.
> Teste: [`tests/Feature/Sells/CatalogueQrContratoTest.php`](../../../../tests/Feature/Sells/CatalogueQrContratoTest.php),
> lane `sells-pest.yml` (MySQL).
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-CQR-01 · Abrir a tela em React
- **Persona:** quem prepara e imprime o QR no escritório.
- **Aceite:** Dado `product.view` e o módulo na assinatura · Quando faço `GET /product-catalogue/catalogue-qr` como o
  browser faz (`X-Inertia` **e** `X-Requested-With`) · Então renderiza Inertia **`ProductCatalogue/CatalogueQr`** com
  o nome do negócio e o script do gerador.
- **Status: 🧪**

## UC-CQR-03 · O link é do meu negócio · `[T0]`
- **Aceite:** Dado um negócio na sessão · Quando a tela abre · Então `link_base` termina em
  `/catalogue/{business_id da sessão}` — a tela só acrescenta o local.
- **Status: 🧪**

## UC-CQR-06 · Negócio sem logo
- **Aceite:** Dado um negócio sem `logo` · Quando a tela abre · Então `negocio.logo_url` é `null` (a tela desabilita o
  switch e o QR sai limpo) · E com logo cadastrado vem a URL de `uploads/business_logos/`.
- **Status: 🧪**

## UC-CQR-08 · Os locais são só os do meu negócio · `[T0]`
- **Aceite:** Dado um local meu e um local de outro negócio · Quando a tela abre · Então `locais` traz o meu e não
  traz o alheio.
- **Status: 🧪**

## UC-CQR-10 · Papel sem acesso
- **Aceite:** Dado um usuário do negócio sem `product.view` · Quando abre a tela · Então 403 · E sem o módulo na
  assinatura, 403 mesmo com `product.view`.
- **Status: 🧪**

## UC-CQR-11 · A Blade saiu (F5 2026-10-27)
- **Aceite:** Dado o cutover · Quando abro a tela · Então recebo a tela React `ProductCatalogue/CatalogueQr` · E a view
  `catalogue/generate_qr` não existe mais · E a chave `mwart.product_catalogue_qr` não existe no config.
- **Histórico:** até 2026-10-27 este caso dizia "a tela React só abre com a chave ligada" (Blade com ela desligada).
- **Status: 🧪**

## UC-CQR-12 · "Link copiado" só depois que o navegador copiou
- **Persona:** quem cola o link no WhatsApp ou no site da loja.
- **Origem:** auditoria do módulo, 2026-11-09. A tela dizia "Link copiado" sem esperar a resposta do navegador, então
  dizia isso também quando nada tinha sido copiado.
- **Aceite:** Dado um QR gerado · Quando clico em "Copiar link" e o navegador confirma · Então a área de transferência
  recebe o mesmo link do QR e o botão mostra "Link copiado" por ~1,6 s, voltando a "Copiar link" · E enquanto o navegador
  não responde o botão não diz que copiou · E quando o navegador recusa ou não oferece a área de transferência, o botão
  continua "Copiar link" e aparece o aviso para copiar manualmente.
- **Teste:** [`tests/js/catalogue-qr-copiar.test.tsx`](../../../../tests/js/catalogue-qr-copiar.test.tsx), lane `ci.yml`.
- **Status: 🧪**

## UC-CQR-13 · Sem local, gerar fica bloqueado
- **Aceite:** Dado que nenhum local foi escolhido · Quando olho o formulário · Então "Gerar QR code" está desabilitado e
  a tela diz "Escolha o local comercial primeiro." · E ao escolher o local o botão libera e a frase some.
- **Status: 🧪**

## UC-CQR-14 · O QR só nasce do clique
- **Aceite:** Dado a tela aberta · Quando ela carrega ou escolho o local · Então nenhum QR é desenhado · E ao clicar em
  gerar o QR aparece, o link termina em `/{business_id}/{location_id}` e o aviso de catálogo público é exibido.
- **Status: 🧪**

## UC-CQR-15 · Mudar uma opção invalida o QR
- **Aceite:** Dado um QR gerado · Quando troco título, subtítulo ou cor · Então o QR some e volta o "Nenhum QR gerado
  ainda." · E nada é gerado até eu clicar de novo.
- **Status: 🧪**

## UC-CQR-16 · Sem local comercial cadastrado
- **Aceite:** Dado um negócio sem local ativo · Quando abro a tela · Então vejo "Nenhum local comercial cadastrado.", sem
  formulário, e um link para `/business-location`.
- **Status: 🧪**

## UC-CQR-17 · O logo só liga quando existe
- **Aceite:** Dado um negócio sem logo · Quando abro a tela · Então o switch está desabilitado e a tela diz que o QR sai
  limpo · E com logo cadastrado o switch abre ligado.
- **Status: 🧪**

> UC-CQR-13 a 17 são exercitados em Chromium real por
> [`tests/Browser/ProductCatalogue/CatalogueQrBrowserTest.php`](../../../../tests/Browser/ProductCatalogue/CatalogueQrBrowserTest.php),
> step "E2E de comportamento · Catálogo QR" do `visual-regression.yml` (tenant 98, com axe).

## Anti-regressão

- O link nunca é montado sem `location_id`.
- O aviso de catálogo público aparece sempre que há QR.
- Nenhuma escrita: a tela é geradora, não publicadora.
