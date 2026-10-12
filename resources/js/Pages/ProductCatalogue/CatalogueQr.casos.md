---
id: resources-js-pages-productcatalogue-catalogueqr-casos
casos: Catálogo QR · /product-catalogue/catalogue-qr
irmaos: CatalogueQr.charter.md (lei) · CatalogueQr.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o QR publica preço de um local sem login; QR apontando pro negócio errado ou aberto a quem não vê produto é vazamento.
owner: wagner
last_run: "2026-10-09"
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

## Sem teste ainda (comportamento só no navegador — entra com o E2E)

- [BACKLOG] Gerar sem local fica bloqueado, com "Escolha o local comercial primeiro." (a Blade dava `alert()`).
- [BACKLOG] Trocar título, subtítulo, cor ou logo limpa o QR gerado; nada é gerado até clicar de novo.
- [BACKLOG] "Copiar link" vira "Link copiado" por ~1,6 s e copia o mesmo link do QR.
- [BACKLOG] Sem local comercial cadastrado, o vazio leva a Configurações › Locais comerciais.

## Anti-regressão

- O link nunca é montado sem `location_id`.
- O aviso de catálogo público aparece sempre que há QR.
- Nenhuma escrita: a tela é geradora, não publicadora.
