---
casos: Patrimonio/Bens — listagem de bens do patrimônio (MWART, ADR 0104)
irmaos: Bens.charter.md (lei) · memory/requisitos/AssetManagement/RUNBOOK-bens.md (F1 PLAN)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-09-23"
---

# Casos de Uso & Aceite — Patrimonio/Bens

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2 (ADR 0264): UC declarado sem teste citando o id = órfão.
> Teste que os defende: [`Modules/AssetManagement/Tests/Feature/BensContratoTest.php`](../../../../Modules/AssetManagement/Tests/Feature/BensContratoTest.php).

> **Por que só três UC com id.** A onda 1 entrega a listagem migrada — e cada UC aqui tem
> teste que roda. Declarar de uma vez os cenários do protótipo (`patrimonio-page.jsx`)
> criaria órfãos e quebraria o G-2, porque o teste que os defende ainda não existe: os
> recortes de garantia/manutenção pedem predicado SQL novo, o total somado esbarra na REGRA
> MESTRE de valor, e as ações em lote não têm endpoint. Eles ficam no `[BACKLOG]` abaixo —
> prosa honesta, sem id — e **viram UC na onda que traz o teste**.

> **Onde os testes rodaram.** Duas execuções independentes, e a segunda é a que se
> re-verifica sozinha:
>
> · **CI — lane `assetmanagement-pest.yml` (MySQL), run 34597014205, 2026-09-11.**
>   Suíte do módulo: **105 passed · 348 assertions · 0 skipped**. Este arquivo:
>   **4 passed · 23 assertions** — o mesmo número da execução manual abaixo, reproduzido
>   por outra máquina, o que é o ponto de ter as duas.
>
> · **CT 100** (`oimpresso-staging`, MySQL real), 2026-09-08 — nunca local
>   ([`proibicoes.md §Ambiente`](../../../../memory/proibicoes.md)). Suíte do arquivo:
>   **4 passed · 23 assertions**; módulo inteiro: **76 passed · 254 assertions**, 0 falhas.
>
> **Por que a linha do CI foi acrescentada.** Até 2026-09-11 estes três UC tinham por único
> lastro o run MANUAL de CT 100 — e a lane que os cobria no CI, `Pest AssetManagement`
> (`modules-pest.yml`), ficava **verde pulando exatamente eles**: ela roda
> `DB_CONNECTION=sqlite` sem `migrate`, e os quatro casos saíam como `WARN … SQLite-incompatível`
> num rodapé `40 skipped, 58 passed`. Skip sai exit 0, então o verde não provava nada
> (LC-13). Recibo de execução à mão não se re-verifica sozinho; agora há quem o re-verifique
> a cada PR que toque o módulo ou a tela.

---

## UC-BENS-01 · A listagem mostra o patrimônio da MINHA empresa, e só dela

- **Persona:** quem administra o patrimônio — precisa confiar que a lista é da casa dele.
- **Aceite:** Dado dois businesses com bens cadastrados · Quando o usuário do business A abre
  `/asset/assets` com `asset.view` · Então a listagem traz o bem de A e **nenhum** bem de B.
- **Teste:** `BensContratoTest.php` — **dois** `it()` citando `UC-BENS-01`:
  1. o cenário direto (usuário de A não vê o bem de B);
  2. **o espelho** — usuário de B, na mesma tela e com a mesma busca, **vê** o bem de B e não
     o de A. O espelho existe porque, sozinho, o `not->toContain` do primeiro também passaria
     se o bem de B simplesmente não existisse. É o substituto do bite-test por mutação:
     desligar o `where('assets.business_id')` provaria o mesmo, mas seria remover uma proteção
     Tier 0 pra ver o alarme tocar.
- **Regressão que defende:** vazamento cross-tenant na listagem do patrimônio
  ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md), Tier 0).
- **Nota de método:** a busca do cenário (`q=BENS-CTR`) casa o código dos **dois** fixtures de
  propósito — eles disputam a mesma página, então o isolamento é a única explicação possível
  para o resultado. Sem esse recorte o teste era não-determinista: o CT 100 é base persistente
  e já tinha 82 assets no tenant 98, então com `paginate(25)` o fixture caía fora da primeira
  página e o teste falhava por paginação, não por regra. Medido, não suposto.
- **Status: 🧪** — os dois cenários passam no CI (lane `assetmanagement-pest`, run 34597014205, 2026-09-11) e no CT 100 (run 2026-09-08, seed 1788886934).

---

## UC-BENS-02 · A tela de Bens é Inertia, no endereço que o [W] decidiu

- **Persona:** o próprio time — a migração precisa ser verificável, não afirmada.
- **Aceite:** Dado um usuário com `asset.view` e o módulo assinado · Quando abre
  `/asset/assets` · Então recebe **200** e a página Inertia é o componente
  **`Patrimonio/Bens`**, com as props `filtros`, `opcoes` e `permissoes`.
- **Teste:** `BensContratoTest.php` — `it()` citando `UC-BENS-02`, com `assertInertia`.
- **Regressão que defende:** a URL **não muda** na migração (`assets.index`, `GET
  /asset/assets`), então status 200 sozinho não distingue "virou Inertia" de "continua
  Blade". O que distingue é o componente — e o assert do Inertia **verifica que o arquivo do
  componente existe**: ele falhou de verdade na primeira execução, quando o `.tsx` ainda não
  estava na árvore do CT 100 (`Inertia page component file [Patrimonio/Bens] does not exist`).
  Defende também o endereço da [ADR 0394](../../../../memory/decisions/0394-endereco-de-ui-do-patrimonio-pages-patrimonio.md):
  mover a tela pra outra pasta quebra este teste.
- **Status: 🧪** — passa no CI (lane `assetmanagement-pest`, run 34597014205, 2026-09-11) e no CT 100 (run 2026-09-08).

---

## UC-BENS-03 · A busca recorta no servidor, não na página que já chegou

- **Persona:** quem procura um bem específico numa lista que não cabe numa tela.
- **Aceite:** Dado dois bens no mesmo business · Quando o usuário busca por um termo que casa
  só um deles (`?q=Fiorino`) · Então a página devolvida traz **o que casa** e **não traz** o
  que não casa.
- **Teste:** `BensContratoTest.php` — `it()` citando `UC-BENS-03`.
- **Regressão que defende:** busca implementada no cliente sobre a página corrente — que
  parece funcionar com poucos registros e mente com muitos, porque só enxerga as 25 linhas
  que já chegaram. O `toContain` vem junto do `not->toContain` pelo mesmo motivo: uma busca
  que devolvesse ZERO linha satisfaria a negativa sozinha e passaria pelo motivo errado.
- **Status: 🧪** — passa no CI (lane `assetmanagement-pest`, run 34597014205, 2026-09-11) e no CT 100 (run 2026-09-08).

---

## UC-BENS-04 · Nenhuma ação da lista leva a uma página em branco

- **Persona:** quem opera o patrimônio — clica num ícone esperando um formulário.
- **Aceite:** Dado a lista com um bem e **todas** as permissões ligadas (criar, editar, excluir,
  manutenção) · Quando a tela renderiza — cheia ou vazia · Então **nenhum** `href` aponta pra
  `create`/`edit` de bem, alocação ou manutenção; e o **excluir** (que funciona, via
  `router.delete`) continua na linha.
- **Teste:** `tests/js/patrimonio-sem-link-para-modal.test.tsx` — 2 `it()` citando `UC-BENS-04`.
- **Regressão que defende:** afordância falsa. MEDIDO em prod (biz=1, 2026-09-23): os endpoints `create`/`edit` do módulo só respondem sob `request()->ajax()` — numa navegação direta devolveram **200 com 0 bytes** (fragmento de modal jQuery, sem `@extends`). Até esta data a tela tinha 5 links
  pra lá: "Novo ativo", o CTA do vazio, alocar, manutenção e editar. O controle positivo (a linha
  renderizou + o excluir está lá) impede o caso de passar porque a tabela nem apareceu.
  **Bite-test (provado 2026-09-23):** com o `Bens.tsx` anterior, os 2 casos caem listando os hrefs.
- **Status: 🧪** — verde no vitest local pós-conserto; lane de CI a confirmar no PR.

---

## UC-BENS-05 · Cadastrar bem pelo drawer grava valor e quantidade exatamente como digitados

- **Persona:** quem cadastra o patrimônio — digita "1.234,56" e espera ver gravado exatamente
  esse valor, não cem vezes mais nem cem vezes menos.
- **Aceite:** Dado o drawer "Adicionar recurso" com nome, categoria, local, data, valor
  `1.234,56` e quantidade `2` · Quando o usuário clica "Cadastrar bem" · Então o POST leva
  `unit_price=1234,56`, `quantity=2` e a data no formato do negócio, e o banco grava
  `unit_price = 1234.5600`, `quantity = 2.0000`, no business da sessão, com código gerado pelo
  servidor e a garantia (início + N meses) quando informada. Form incompleto não posta.
  Empresa **sem nenhuma categoria de ativo** (caso real de biz=1, medido em 2026-09-23): o drawer
  diz "Nenhuma categoria de ativo cadastrada" e aponta `/taxonomies?type=asset`, em vez de
  travar o cadastro sem explicação.
- **Teste (dupla prova — REGRA MESTRE de valor/estoque):**
  - caminho 1 — `tests/js/patrimonio-cadastro-bem.test.tsx`: as strings exatas do payload,
    e o drawer real postando o que o serializador monta;
  - caminho 2 — `BensContratoTest.php`, `it()` citando `UC-BENS-05`: posta as MESMAS strings
    no `store()` real (tenant 98) e lê o banco, incluindo o caso `1234567,8` (milhar).
- **Regressão que defende:** separador de milhar lido como decimal (ou o contrário) — o
  incidente ROTA LIVRE de 2026-06-05 (valor ×100k por float cru lido pelo `num_uf`). E data
  ISO crua no `uf_date`, que lança e vira "algo deu errado".
- **Status: 🧪** — caminho 1 verde no vitest local (8/8); caminho 2 roda na lane
  `assetmanagement-pest` no CI.

---

## Dívida declarada — `Alocado` não é número auditado

⚠️ Não é UC porque **não é comportamento que esta onda defende** — é defeito herdado que ela
documenta em vez de esconder. As agregações `allocated_qty` (join `AT`) e `revoked_qty`
(subconsulta `AR`) do `AssetController::index()` **não filtram por `business_id`**. Gêmeo
catalogado em `_saida-01.md §9(a)`, com thread dona.

**Medido no CT 100 em 2026-09-08** (leitura pura, as duas formas da agregação lado a lado):
**20 de 128** assets divergem — o padrão é `revogado: 4 → 0`, ou seja, a coluna "Alocado"
mostra 4 unidades **a menos** do que deveria, por contar revogação de outro tenant.
A pré-condição do vazamento (`asset_id` com transação de outro business) ocorre em **20**
assets nessa base.

**Ressalva de honestidade:** essa base é o staging do CT 100, e as linhas divergentes vêm de
fixtures acumulados dos testes cross-tenant (`AST-CRS-TNT01`), não de dado de cliente. A
medição prova que **a query vaza quando a pré-condição existe**; ela não afirma nada sobre
produção, que não foi medida.

Corrigir aqui esbarraria na REGRA MESTRE (mexer em quantidade exige prova por dois caminhos +
antes→depois + [W]) e em 1 PR = 1 intent. O que esta onda fez foi dar **um dono só** à
expressão (`AssetController::baseAssetsQuery`), lida pelos dois ramos.

---

## [BACKLOG] — vira UC na onda que trouxer o teste

- [BACKLOG] Os sub-recortes "Todos / Alocáveis / Garantia crítica / Em manutenção" contam e
  filtram sobre o **conjunto**, não sobre a página corrente.
- [BACKLOG] O rodapé soma o valor total do recorte, com a prova dupla que a REGRA MESTRE exige.
- [BACKLOG] Seleção em lote exporta a seleção e manda os selecionados pra manutenção.
- [BACKLOG] O usuário escolhe as colunas visíveis e a densidade, e a escolha sobrevive ao reload.
- [BACKLOG] Editar bem acontece em drawer, sem sair da lista (criar já acontece — UC-BENS-05).
- [BACKLOG] Alocar e mandar pra manutenção a partir da linha, em drawer — hoje não há caminho
  pela UI (os formulários só existem como fragmento de modal servido sob `ajax()`). Escrita de
  QUANTIDADE: REGRA MESTRE Tier 0.
- [BACKLOG] `permitted_locations()` restringe a listagem, e nenhum parâmetro de query a afrouxa.
  (Hoje o código faz isso — aplica a restrição **antes** dos filtros do usuário —, mas nenhum
  teste defende; virou visível quando o fixture sem `access_all_locations` zerou a lista.)
