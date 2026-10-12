---
sessao: "04"
titulo: "Catálogo QR — gerar QR em Inertia — entregue (trio + controller + teste numa lane de PR)"
autor: "[CL]"
data: 2026-10-09
base: origin/main 0b304816
thread: 04-catalogo-qr.md
veredito: "entregue — CatalogueQr.tsx + charter (draft) + casos (UC-CQR-01/03/06/08/10) + teste na lane sells-pest. Acesso: módulo na assinatura + product.view (PERM-CQR). Contrato de forma e remoção da Blade ficam para depois."
---

# _saida-04 · Catálogo QR em Inertia

## 0 · Portão

- Placar em `0b304816`: 04 `[proximo]` (PERM-CQR respondida em 2026-10-07; sem dependência de thread).
- Sem PR aberto no prefixo (`gh api pulls?state=open`: 4 PRs, nenhum em `Modules/ProductCatalogue/` ou
  `Pages/ProductCatalogue/`).

## 1 · Feito

| arquivo | o quê |
|---|---|
| `Modules/ProductCatalogue/Http/Controllers/ProductCatalogueController.php` | `generateQr()` responde `Inertia::render('ProductCatalogue/CatalogueQr')`. Rota e middleware iguais. |
| `Modules/ProductCatalogue/Services/CatalogueQrService.php` | `authorizeAccess()` passa a exigir `product.view` além do módulo na assinatura (PERM-CQR). `buildPagePayload()`: `locais` (mesmo `forDropdown` da Blade), `negocio` (nome, `logo_url` ou `null`), `link_base` com o negócio da sessão, `qr_script`. |
| `resources/js/Pages/ProductCatalogue/CatalogueQr.tsx` | Duas colunas: formulário (local, cor, título, subtítulo, logo) × saída (QR, link, baixar PNG, copiar link, aviso de catálogo público). O QR nasce no navegador pelo `easy.qrcode.min.js` do módulo — nenhuma dependência nova. Âncoras `data-contract`: `cabecalho`, `formulario`, `instrucoes`, `saida`. |
| `resources/js/Pages/ProductCatalogue/CatalogueQr.charter.md` | `status: draft`; `related_prototype` = `catalogo-qr-page.jsx`. |
| `resources/js/Pages/ProductCatalogue/CatalogueQr.casos.md` | UC-CQR-01/03/06/08/10 com teste; os casos só de navegador ficam em `[BACKLOG]` até o E2E. |
| `tests/Feature/Sells/CatalogueQrContratoTest.php` | cita os 5 UCs; 2 deles Tier 0 (link e locais só do negócio da sessão), 403 com controle positivo. |
| `.github/workflows/sells-pest.yml` | o teste entra **por arquivo** no comando + controller e serviço no gatilho. |
| `memory/requisitos/ProductCatalogue/RUNBOOK-catalogue-qr.md` | F1 do MWART (o hook exige antes da Page). |

## 2 · Diferenças do pedido, ditas

- **Caminho da Page:** ficou em `resources/js/Pages/ProductCatalogue/`, como a prova do índice pede. O módulo não
  tem `Resources/js/Pages`.
- **Lane:** `Modules/ProductCatalogue/Tests` não está em lane nenhuma (`test-lane-coverage`: 7 de 7 órfãos). O teste
  foi para `tests/Feature/Sells/` (o catálogo é ghost do hub Vendas, ADR 0180) e roda na `sells-pest.yml`.
- **UC-CQR-02/04/05/07/09** do caso proposto viraram `[BACKLOG]` sem id: são comportamento só do navegador e o
  casos-gate exige teste citando cada UC. **UC-CQR-09** (falha ao carregar locais) não se aplica como estava: os
  locais vêm na resposta da página; a tela mostra erro quando o **gerador** não carrega.
- **UC-CQR-10:** sem permissão é 403 do servidor, como antes — a tela não chega a abrir para explicar.
- **Bug da Blade corrigido:** o subtítulo do QR repetia o título (`opts.subTitle = $('#title').val()`). Agora usa o
  campo próprio.
- **Cores:** os 4 swatches do protótipo, escritos em `rgb()` (o protótipo tinha um em `oklch`, que o canvas do
  gerador não garante). A Blade aceitava cor livre em texto; o seletor livre ficou fora (`<input type="color">` só
  aceita hex, e hex em Page conta como cor crua no ui:lint R1 — aqui são pixels do PNG, não interface).

## 3 · Não medido

- Teste rodado só pelo CI (lane `sells-pest`) — esta sessão não roda Pest local (regra do projeto) e o CT 100 não foi usado.
- Smoke em produção com screenshot: depois do merge, por quem alcançar `oimpresso.com`.
- Alvo e contrato de forma (`governance/design/contracts/catalogo-qr.contract.json`): não feitos.

## 4 · Pendências

- [W]: screenshot para o charter virar `live`; PNG maior para impressão A4?
- Cutover: remover `catalogue/generate_qr.blade.php`.

## 5 · Prefixo tocado

Dentro do prefixo: `Modules/ProductCatalogue/` e `resources/js/Pages/ProductCatalogue/`. Fora do prefixo, por
necessidade de máquina: `tests/Feature/Sells/`, `.github/workflows/sells-pest.yml` e o RUNBOOK em
`memory/requisitos/ProductCatalogue/`. O `nao_toca` (`show.blade.php`, `Modules/VozDoCliente/`) não foi tocado.
