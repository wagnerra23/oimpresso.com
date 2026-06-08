# Sessão 2026-04-26 — Fix Hero hidratação prematura via CMS

## Sintoma reportado

Wagner abriu `https://oimpresso.test` e o Hero da landing aparecia em inglês:
"Automate your business management at very-Low cost". Resto da página
(header, badge, footer, DashboardMockup, FeatureGrid) renderizando em PT-BR.

Hipótese inicial dele: cache do Vite — bundle compilado em `public/build-inertia/`
ainda com copy antigo.

## Diagnóstico real

**Não era cache.** O bundle anterior (`Home-CdN6ncQy.js`) **já tinha** o copy
PT-BR baked in (`grep "orça, imprime, monta"` confirmou). O problema era em 2
camadas:

### 1. node_modules dessincronizado

`framer-motion` listado em `package.json` (^11.18.0) mas ausente de
`node_modules/`. `npm run build:inertia` falhava em
`Rollup failed to resolve import "framer-motion"`. Corrigido com `npm install`
(adicionou 3 packages).

### 2. Hero adiantado pra PR2 antes de PR2 acontecer

O comentário no [CmsController.php:40-41](../../Modules/Cms/Http/Controllers/CmsController.php) diz:

```php
// PR2: hidratar Hero/Features/SocialProof a partir destes dados
// (atualmente Pages/Site/Home.tsx tem copy hardcoded em PT-BR pra acelerar PR1)
```

Mas o `Hero.tsx` JÁ lia `page?.title` / `page?.content` e usava como **override**
do copy hardcoded. E `cms_pages` row id=3 (`layout='home'`) ainda tinha o seed
original do UltimatePOS em inglês:

```json
{
  "title": "Automate your business management at very-Low cost",
  "content": "<p>Best POS, Invoicing, Inventory & Service management...</p>"
}
```

Resultado: Hero "funcionava" exatamente como programado — só que contradizendo
o plano de fases (PR1 hardcoded, PR2 hidrata).

## Fix aplicado

Removida a hidratação prematura do Hero. Prop signature mantida (`page?: HeroPage`)
pra não quebrar `Home.tsx` nem o caminho de PR2 futuro. Diff: −22 +7 linhas em
`resources/js/Components/Site/Hero.tsx`.

`FeatureGrid` não precisou mexer — ele procura keys `feature_N_(title|description|icon)`
em `page_meta` e a row tem `industry`/`feature` (JSON), então cai no
`FALLBACK_FEATURES` PT-BR corretamente.

`SocialProof` usa `statistics` (não `page`) e o seed está em inglês
("REGISTERED BUSINESSES", "DAILY USERS"). Wagner não mencionou — pode estar
escondido ou ainda renderizando em inglês. **Pendência pra checar.**

## Verificação

- Novo bundle: `Home-DclIyG54.js` contém "orça, imprime, monta" e **não**
  contém `cmsTitle`/`cmsContent` nem "Automate your business".
- Inertia version hash mudou (`8707d2e3…` → `c4277cdd…`) forçando re-fetch.
- Wagner confirmou no browser: "O ERP pra quem orça, imprime, monta e entrega."

## Aprendizado pra PR2

Quando for hidratar Hero/Features/SocialProof via CMS de fato:

1. **Antes** de re-adicionar a hidratação no Hero, atualize `cms_pages` row id=3
   (e os `page_meta` JSONs `industry`/`feature` em inglês) com copy PT-BR.
2. Considere também `SocialProof.statistics` (provavelmente em inglês ainda).
3. Ou — mais limpo — drop as rows seed do UltimatePOS e deixe só rows criados
   manualmente pelo cliente WR2.

## Comandos rodados

```bash
cd /d/oimpresso.com
npm install                    # framer-motion + 2 outros
npm run build:inertia          # ✓ 23s
php artisan view:clear         # ✓
php artisan cache:clear        # ✓
php artisan config:clear       # ✓
php artisan route:clear        # ✓
composer dump-autoload         # ✓
# edit Hero.tsx
npm run build:inertia          # ✓ 23s
php artisan view:clear         # ✓
curl -skL https://oimpresso.test/   # verify
```

Restart do Herd não foi necessário.
