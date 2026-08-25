---
id: requisitos-superadmin-runbook-pacotes
title: "RUNBOOK — /superadmin/packages (Pacotes · Blade → Inertia)"
module: Superadmin
tela: superadmin/Pacotes/Index
owner: W
status: ativo
last_validated: "2026-08-20"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
spec_ref: memory/requisitos/Superadmin/SPEC.md
---

# RUNBOOK — `/superadmin/packages` (grade comercial, Inertia/React)

F1 do MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) para a
onda **SA-O4c**. A tela era `superadmin::packages.index` (Blade/AdminLTE, tabela) e passa a
`Inertia::render` com **grid de cards**, como o F1 desenha.

- **Fonte de design:** projeto Cowork `019dcfd3-…`, arquivo
  `cowork-inbox/SUPERADMIN-F1-2026-08-18.md` §1 view `pacotes` (charter) e §2 UC-SA-010/011
  (casos). O desenho renderizado é
  [`prototipo-ui/cowork/superadmin-page.jsx`](../../../prototipo-ui/cowork/superadmin-page.jsx)
  → `ViewPacotes()` (L1176) e `PacoteForm()` (L484).
- **Page:** `Modules/Superadmin/Resources/js/Pages/superadmin/Pacotes/Index.tsx`.
- **Rota:** `Route::resource('/packages', PackagesController::class)` → `index()`. A rota **não
  muda** nesta onda.

---

## 1. Por que cards e não tabela

Não é gosto. Um pacote tem **quatro limites** (locais · usuários · produtos · faturas), **três
flags** de visibilidade (ativo · privado · avulso), uma lista de módulos liberados e uma
contagem de assinantes. Em tabela isso vira 12 colunas, e o Blade resolvia escondendo metade.
O card mostra o pacote inteiro e a comparação entre pacotes acontece lado a lado.

## 2. "0 = ilimitado" é a regra que a tela não pode errar

Os quatro contadores usam a convenção do UltimatePOS: **`0` significa sem teto**, não "zero
permitido". Está no comentário da própria coluna
(`location_count int(11) COMMENT 'No. of Business Locations, 0 = infinite option.'`).

A tela escreve *"locais ilimitados"* quando o valor é `0`. Desenhar `0 locais` seria dizer o
oposto do que o dado significa — e é a leitura que qualquer pessoa faria sem esta seção.

Pareia com **R5** do F1: reduzir um limite **não corta quem já passou dele**, só bloqueia novo
cadastro acima do teto. A tela diz isso; quem faz cumprir é o `ModuleUtil`, não esta página.

## 3. Plural PT-BR é explícito, nunca concatenado

O F1 §2 cobra: `1 local / 2 locais` · `1 assinante / 2 assinantes` · `6 meses` (nunca "mêses").
Intervalo tem mapa próprio (`mês → meses`, `ano → anos`) porque concatenar "s" erra nos dois.

## 4. Assinantes por pacote — a contagem que decide se dá pra excluir

O card mostra quantas assinaturas apontam para aquele pacote. É uma subquery agregada por
`package_id`, calculada em **uma** consulta para todos os pacotes — contar por card seria N+1
numa tela que sempre lista o catálogo inteiro.

A contagem é de assinaturas **vivas ou históricas**, não só vigentes: pacote com contrato antigo
apontando para ele não pode ser excluído sem migrar, e a fila histórica é o que prova isso.

## 5. Três achados de MEDIÇÃO no backend (nenhum corrigido aqui)

> Medidos em 2026-08-20 contra `origin/main`, no pré-flight desta onda. Estão aqui porque os
> três tocam **valor** ou **contrato**, e o §"CÁLCULO DE VALOR ou ESTOQUE" das
> [proibicoes](../../proibicoes.md) exige prova por dois caminhos + antes→depois apresentado
> a [W] **antes** de aplicar. Nenhum deles é migração de tela — são decisão [W].

### 5.1 `store()` interpreta o preço em pt-BR; `update()` grava cru

`PackagesController::store()` faz `num_uf($input['price'], $currency)` antes de gravar.
`PackagesController::update()` **não faz**. Contado: `num_uf` aparece **1 vez** no arquivo
inteiro, na linha 102, dentro do `store()` — com controle positivo (`packages_details`, padrão
que eu sabia existir, retornou 9 ocorrências, então o `grep` estava funcionando).

Consequência: criar e editar o mesmo pacote com o mesmo texto digitado pode gravar valores
diferentes, porque só um dos caminhos passa pelo parser pt-BR. A coluna é `decimal(22,4)`, então
a diferença é silenciosa — não estoura, grava outro número.

É a mesma família do incidente de 2026-06-05 (`num_uf` e o separador de milhar). **Não toquei**:
decidir se `update()` passa a parsear (e o que fazer com os pacotes já gravados) é decisão [W]
com antes→depois na mesa.

### 5.2 `UpdatePackageRequest` existe e nunca foi ligado

O FormRequest está escrito, com as 19 regras de validação, e `PackagesController::update()`
continua recebendo `Request` genérico. Contado no repositório inteiro (`--hidden`): **3**
ocorrências — o próprio arquivo, o `SecurityHardeningTest` que assere as regras dele, e o
`SUPERFICIE.md`. **Zero** em código de produção.

O teste fica verde porque mede a **classe**, não o **endpoint** — é a classe de defeito LC-11
(gate que mede presença em vez de comportamento). Nenhuma validação de pacote roda hoje no
`update`.

### 5.3 `destroy()` apaga sem olhar assinante

`Package::where('id',$id)->delete()` — soft delete, então é recuperável, e
`Subscription::package()` usa `withTrashed()`, então contrato antigo não perde a referência. Mas
não há aviso: o F1 pede que excluir pacote com assinante seja barrado ("migre antes de excluir").

---

## 6. Smoke prod (R1 — evidência, não narração)

```bash
curl -sv https://oimpresso.com/superadmin/packages 2>&1 | grep '^< HTTP'
```

Esperado: `302` para `/login` sem sessão. Autenticado como superadmin: `200`, e o `data-page`
traz `"component":"superadmin/Pacotes/Index"`.

Regressão adjacente (não podem mudar):

```bash
curl -sv https://oimpresso.com/superadmin 2>&1 | grep '^< HTTP'
```

```bash
curl -sv https://oimpresso.com/pricing 2>&1 | grep '^< HTTP'
```

> A segunda importa mais do que parece: `/pricing` é a vitrine **pública** e lê os mesmos
> pacotes (`Package::listPackages(true)`, excluindo privados). Mexer no catálogo sem olhar para
> ela é como um pacote privado vaza para o site.

## 7. Tier 0 — invariantes

- **Cross-tenant é intencional** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
  §exceções Superadmin): `packages` é catálogo **global** — a tabela não tem `business_id` e
  nunca terá. Nada de escopo de tenant aqui.
- **Esta onda é LEITURA.** `store`, `update` e `destroy` não são tocados. O FormDrawer do F1
  (UC-SA-010/011) é a **SA-O4d** e não entra sem o tratamento da REGRA MESTRE (§5.1).
- **Pacote privado não vaza.** `is_private` é o que separa esta tela de `/pricing`; a lista aqui
  mostra todos por desenho, a de lá exclui privados. Uma "simplificação" que unifique as duas
  consultas expõe grade privada ao público (**R7** do F1).
- **Nenhum valor em R$ entra em log, PR, commit ou arquivo** — e esta tela é sobre preço. O
  `.tsx` formata o número que vem do payload; não há literal monetário no código nem nos
  fixtures.

## 8. O que NÃO entrou nesta onda

| Peça do F1 | Situação |
|---|---|
| **FormDrawer novo/editar/duplicar** (UC-SA-010/011) | **SA-O4d** — escreve `price`, então exige a REGRA MESTRE (2 caminhos + antes→depois) e a decisão [W] do §5.1 |
| **Kebab** (editar · duplicar · ativar/desativar · excluir) | idem — todas as ações escrevem |
| **Aviso "migre antes de excluir"** | depende do §5.3, que é decisão [W] |

## 9. Refs

- Protótipo: [`prototipo-ui/cowork/superadmin-page.jsx`](../../../prototipo-ui/cowork/superadmin-page.jsx) `ViewPacotes()` L1176
- Charter/casos: ao lado do `.tsx`
- Contrato: `prototipo-ui/contrato/superadmin-pacotes.contract.json`
- Irmãos: [RUNBOOK-assinaturas.md](RUNBOOK-assinaturas.md) · [RUNBOOK-negocios.md](RUNBOOK-negocios.md) · [RUNBOOK-dashboard.md](RUNBOOK-dashboard.md)
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)
