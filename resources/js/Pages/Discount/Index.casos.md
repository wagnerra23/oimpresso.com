---
id: resources-js-pages-discount-index-casos
casos: Descontos · /discount
irmaos: Index.charter.md (lei) · Index.tsx
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: desconto é valor que o PDV aplica sozinho — o que a tela garante é QUEM grava e O QUE é gravado, e isso não muda num refactor visual.
owner: wagner
last_run: "2026-10-02"
last_run_ci: "0 UC executado — trio nasce na thread 04; veredito pendente da lane PHP / Pest (Sells · MySQL)"
---

# Casos de Uso & Aceite — Descontos

> **Fonte:** casos revisados em `prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/Descontos.casos.md`
> + decisão **D1 de [W]** (2026-10-02): ver × editar em permissões separadas. O aviso "Uma
> permissão só" do protótipo deixou de ser verdade com a D1 e foi trocado pelo aviso de ver × editar.
>
> **Teste:** `tests/Feature/Sells/DescontosContratoTest.php` — tenant 98 × adversário 99
> (`seededSupportClientTenant()`), `DatabaseTransactions`, papel próprio do negócio (nunca
> `Admin#`, que passa pelo `Gate::before`).
>
> ⚖️ **Lane:** `PHP / Pest (Sells · MySQL)` — [`.github/workflows/sells-pest.yml`](../../../../.github/workflows/sells-pest.yml).
> Se ela bloqueia merge é o [`required-checks-baseline.json`](../../../../governance/required-checks-baseline.json) que diz, não este arquivo.
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.
>
> `[BACKLOG]` Dado dois descontos no mesmo produto, vale o de prioridade menor — regra do PDV,
> fora desta tela; vira UC quando houver teste do PDV que a cite.

## UC-DSC-01 · Gravar grava a mesma linha de antes `[V0]` `[must]`
- **Persona:** gerente que cadastra a promoção da semana.
- **Aceite:** Dado um payload de criar e um de editar · Quando gravo pela tela (POST/PUT do resource) · Então a linha em `discounts` é igual à que o controller gravava antes do FormRequest — números crus (sem `num_uf`), datas pelo formato do negócio, checkbox ausente = 0.
- **Teste:** `DescontosContratoTest` — os dois `UC-DSC-01 [V0] ...` (cópia congelada do código antigo + valores à mão).
- **Regressão que defende:** o FormRequest mudar valor, data ou situação gravados.
- **Status: 🧪**

## UC-DSC-02 · Produtos apagam marca e categoria `[must]`
- **Persona:** gerente que restringe o desconto a produtos.
- **Aceite:** Dado produtos escolhidos junto com marca e categoria · Quando gravo · Então marca e categoria ficam vazias e os produtos ficam ligados; a tela avisa isso no drawer.
- **Teste:** `DescontosContratoTest` — `UC-DSC-02 escolher produtos apaga marca e categoria`.
- **Regressão que defende:** o servidor guardar os dois e o PDV aplicar por critério errado.
- **Status: 🧪**

## UC-DSC-03 · Desativar em massa e reativar `[must]`
- **Persona:** gerente encerrando promoções.
- **Aceite:** Dado 2 descontos ativos · Quando desativo os selecionados · Então os dois ficam inativos (sem exclusão); Quando reativo um · Então só ele volta.
- **Teste:** `DescontosContratoTest` — `UC-DSC-03 desativar em massa e reativar mudam só a situação`.
- **Regressão que defende:** massa excluindo em vez de desativar.
- **Status: 🧪**

## UC-DSC-04 · Ver × editar `[must]`
- **Persona:** balconista que confere preço e não pode apagar desconto (D1).
- **Aceite:** Dado papel só com `discount.view` · Quando abro a lista · Então vejo; Quando tento criar, editar, excluir, desativar ou reativar · Então recebo 403 e nada muda. Sem `view` nem `manage` · Então a lista responde 403. Na tela, os botões de gravação aparecem desabilitados com o motivo.
- **Teste:** `DescontosContratoTest` — os dois `UC-DSC-04 ...` e `UC-DSC-08` (prop `permissoes.editar`).
- **Regressão que defende:** quem só vê voltar a poder apagar.
- **Status: 🧪**

## UC-DSC-05 · Nome vazio não grava `[must]`
- **Persona:** gerente que esquece o nome.
- **Aceite:** Dado nome vazio · Quando a tela tenta salvar · Então ela recusa antes de enviar, com mensagem. No servidor, nome vazio segue sem gravar e responde `success:false`, como antes do FormRequest (o banco recusa `NULL`).
- **Teste:** `DescontosContratoTest` — `UC-DSC-05 nome vazio segue sem gravar nada, igual a antes`. A recusa no front não tem teste de render.
- **Regressão que defende:** desconto sem nome no PDV; FormRequest mudando o que é aceito.
- **Status: 🧪**

## UC-DSC-06 · Outro negócio não entra `[T0]` `[must]`
- **Persona:** gerente do negócio 98.
- **Aceite:** Dado um desconto do negócio 99 · Quando o 98 lista, edita, exclui, reativa ou desativa · Então o do 99 não aparece e não muda.
- **Teste:** `DescontosContratoTest` — `UC-DSC-06 [T0] ...` e `UC-DSC-08` (payload da tela).
- **Regressão que defende:** consulta perdendo o `where business_id` (ADR 0093).
- **Status: 🧪**

## UC-DSC-07 · Ninguém perde acesso no deploy `[must]`
- **Persona:** admin do negócio que já tinha dado acesso a descontos.
- **Aceite:** Dado papel ou usuário com `discount.access` · Quando a migration roda (mesmo 2×) · Então ele ganha `discount.view` e `discount.manage`; quem não tinha não ganha nada.
- **Teste:** `DescontosContratoTest` — `UC-DSC-07 a migration concede ver e editar ...`.
- **Regressão que defende:** troca de permissão tirando a tela de quem usava.
- **Status: 🧪**

## UC-DSC-08 · A tela React abre com os dados do negócio `[must]`
- **Persona:** qualquer um com `discount.view`.
- **Aceite:** Dado o navegador pedindo `/discount` com `X-Inertia` e `X-Requested-With` · Então a resposta é a Page `Discount/Index`, a lista (deferida) traz só descontos do negócio e `permissoes.editar` reflete `discount.manage`.
- **Teste:** `DescontosContratoTest` — `UC-DSC-08 a tela React renderiza ...`.
- **Regressão que defende:** o ramo ajax engolir a visita Inertia (devolver JSON do DataTable).
- **Status: 🧪**
