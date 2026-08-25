---
id: modules-superadmin-pages-superadmin-pacotes-index-charter
page: /superadmin/packages
component: Modules/Superadmin/Resources/js/Pages/superadmin/Pacotes/Index.tsx
related_prototype: prototipo-ui/cowork/superadmin-page.jsx
owner: wagner
status: draft
last_validated: "2026-08-20"
related_us: [US-SUPER-002]
parent_module: Superadmin
related_adrs: [104, 93]
tier: B
charter_version: 1
---

# Page Charter — /superadmin/packages

> **Status:** criado em 2026-08-20 na onda SA-O4c (Blade → Inertia). Nasce `draft`: o
> `charter-live-signal` exige **sinal de prod**, e a tela ainda não foi ao ar. Vai a `live` no
> PR pós-deploy, com a evidência do smoke.
>
> Os **Non-Goals** e **Anti-hooks** vêm do F1 do Cowork
> (`cowork-inbox/SUPERADMIN-F1-2026-08-18.md`, projeto `019dcfd3-…`), transportados e não
> inferidos — [W] ratifica.
>
> Backend: `Modules\Superadmin\Http\Controllers\PackagesController@index`, rota
> `Route::resource('/packages', …)`. Ver
> [RUNBOOK-pacotes](../../../../../../../memory/requisitos/Superadmin/RUNBOOK-pacotes.md).

---

## Mission

Responde **uma** pergunta: *"o que estamos vendendo?"*. É a grade comercial — preço, limites,
módulos liberados e quantos clientes cada pacote sustenta, lado a lado. Não é BI, não cobra e
não administra assinatura de ninguém.

Persona única: [W], escritório, 1440px. Admin de negócio toma 403.

---

## Goals — Features (faz)

O que a tela entrega **hoje**:

- Grid de **cards**, um por pacote, com o pacote inteiro visível: preço + ciclo · os 4 limites ·
  dias de teste · módulos liberados · descrição · assinantes.
- **"0 = ilimitado"** escrito por extenso ("locais ilimitados"), nunca `0 locais` — é a
  convenção da coluna no UltimatePOS e a leitura ingênua diz o oposto do que o dado significa.
- Tags de visibilidade: **privado** · **avulso** · **ativo/inativo**, com o card inativo
  visualmente recuado.
- Contagem de assinantes por pacote, resolvida em **uma** consulta agregada (nunca N+1).
- Plural PT-BR explícito: `1 local / 2 locais`, `1 assinante / 2 assinantes`, `mês → meses`,
  `ano → anos`.
- Catálogo **completo**, incluindo pacote inativo e privado — esta é a tela que enxerga tudo.

## Non-Goals — Features (NÃO faz)

> Do F1 §Non-goals. Cada item vira Pest GUARD quando [W] ratificar.

- **Não é BI** — nenhum gráfico aqui.
- **Não faz cobrança** — o gateway é `Modules/PaymentGateway`.
- **Não edita dado operacional do cliente**.
- **Não pagina** — a grade comercial é curta por natureza, e paginar meia dúzia de itens
  esconderia justamente a comparação que é o valor da tela.

## Automation Anti-hooks (o que a próxima sessão NÃO pode "consertar")

> Cada item existe porque a correção óbvia quebra o produto.

- ❌ **Não aplicar escopo de `business_id`.** `packages` é catálogo **global**: a tabela não tem
  a coluna e nunca terá
  ([ADR 0093](../../../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
  §exceções Superadmin).
- ❌ **Não unificar esta consulta com a de `/pricing`.** A vitrine pública usa
  `Package::listPackages(true)`, que **exclui privados**; esta usa o catálogo inteiro. Unificar
  "pra não repetir query" expõe grade privada ao público — quebra a **R7** do F1.
- ❌ **Não desenhar `0` como zero.** `0` em locais/usuários/produtos/faturas significa **sem
  teto**. Trocar o texto por `0 locais` inverte o sentido do dado.
- ❌ **Não traduzir os limites no backend.** Eles chegam como **número** de propósito: a tela
  precisa do valor pra decidir entre "ilimitado" e o plural correto. Mandar string pronta tira
  essa decisão de quem tem o vocabulário.
- ❌ **Não paginar nem cortar a lista** ("só os ativos", "top 6"). Pacote inativo aparece porque
  contrato antigo ainda aponta pra ele.
- ❌ **Não escrever literal monetário** (nem em fixture, nem em teste, nem em comentário). O
  card formata o número que vem do payload — Tier 0,
  [proibicoes](../../../../../../../memory/proibicoes.md).

---

## Contrato visual

Travado por `prototipo-ui/contrato/superadmin-pacotes.contract.json` (gate `contrato-de-tela`),
com âncoras `data-contract` no `.tsx`. A copy literal e a ordem das seções são de lá — esta
seção **aponta**, não repete.

---

## Divergências declaradas contra o F1

| F1 pede | Produção entrega | Por quê |
|---|---|---|
| FormDrawer novo/editar/duplicar (UC-SA-010/011) | ausente | **SA-O4d** — escreve `price`, exige a REGRA MESTRE (2 caminhos + antes→depois) e a decisão [W] do RUNBOOK §5.1 |
| kebab com 4 ações | ausente | todas escrevem; mesma razão |
| aviso "migre antes de excluir" | ausente | depende do RUNBOOK §5.3, decisão [W] |

---

## Refs

- Casos: [Index.casos.md](Index.casos.md)
- RUNBOOK: [RUNBOOK-pacotes.md](../../../../../../../memory/requisitos/Superadmin/RUNBOOK-pacotes.md)
- Protótipo: `prototipo-ui/cowork/superadmin-page.jsx` → `ViewPacotes()` (L1176)
- Irmãos: [Assinaturas](../Assinaturas/Index.charter.md) · [Negócios](../Negocios/Index.charter.md)
