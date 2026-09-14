---
sessao: "06-painel"
titulo: "Painel — a tela foi entregue por OUTRA sessão durante esta; o que sobra é uma regressão Tier 0 achada e consertada"
dono: "[CC]"
criado: 2026-09-08
base: "0f39a46a06 no início → 4f70a09460 no fim (o main andou 3× durante a sessão; a última vez trazendo a MESMA tela)"
thread: 07-painel.md
prefixo_escrito: "Modules/AssetManagement/Tests/Feature/SmokeRoutesTest.php (ÚNICO arquivo — o resto foi descartado, ver §2)"
pr: "#7048 — aberto, NÃO mergeado"
veredito: "a tela NÃO foi entregue por esta thread (duplicada pelo #7040, mergeado no meio do caminho) · o que sobra é REAL e urgente: a catraca Tier 0 do #7018 estava MORTA no main e voltou a rodar e a morder"
invalida: "07-painel.md §prefixo e §Execução passo 5: o nome é `Index.tsx`, NÃO `Painel.tsx` — e a tela já existe (#7040), então a ficha inteira está CUMPRIDA, não pendente · toda thread-filha do Patrimônio: migrar um método do `AssetController` para Inertia QUEBRA em silêncio todo teste que leia `View::getData()` daquele método — grep obrigatório antes (§4) · toda thread que use o CT 100: o ambiente é DISPUTADO por sessões simultâneas, md5 antes E depois de cada run é obrigatório, senão o número é de outro código (§5)"
---

# 06-painel · Saída — a thread que virou conserto

> **Esta thread não entregou a tela que foi pedida a ela.** Entregou o conserto de uma
> regressão Tier 0 que a entrega da tela (por outra sessão) causou e não viu. O registro
> abaixo é honesto sobre as duas metades.

---

## 1 · A colisão, na ordem em que aconteceu

| Momento | Estado do `main` | O que eu media |
|---|---|---|
| início | `0f39a46a06` | `Pages/Patrimonio/` **vazio** — reportei ao [W] que a dependência (Bens) não existia e **parei** |
| +minutos | `9d0041c0e2` | Bens entrou pelo [#7035](https://github.com/wagnerra23/oimpresso.com/pull/7035). Corrigi o veredito e segui |
| meio | `e6d53daf1f` | [W] pediu *update branch*; terreno intacto |
| fim | `4f70a09460` | **[#7040](https://github.com/wagnerra23/oimpresso.com/pull/7040) entregou o Painel** — a mesma tela, como `Index.tsx` |

**A raiz da duplicação é uma divergência de nome que já estava no canon.** A instrução
desta sessão dizia `resources/js/Pages/Patrimonio/Painel.tsx`; a ficha
[`07-painel.md`](07-painel.md) dizia, no `prefixo:`, `resources/js/Pages/Patrimonio/Index.tsx`.
Eu vi a divergência no começo e escolhi `Painel.tsx` (a instrução do [W] é mais recente, e
`Bens.tsx` estabeleceu a convenção "nome da aba"). A sessão irmã seguiu a ficha. **Nenhuma
das duas errou de leitura** — o canon tinha dois nomes.

**Checagem de concorrência: eu fiz, e ela não bastou.** No início rodei `gh pr list --state
open` (4 PRs, nenhum de Painel) e `git ls-remote --heads` (3 branches de patrimônio, nenhum
de Painel). O #7040 foi **aberto e mergeado depois disso**. O que faltou foi **re-checar
antes de investir**, e não a checagem inicial.

---

## 2 · O que descartei, e por quê

Cheguei a ter tela, charter, casos, RUNBOOK e teste Pest — todos verdes no CT 100
(`10 passed · 34 assertions`). **Joguei fora todos menos um.** Manter `Painel.tsx` ao lado de
`Index.tsx` criaria **dois componentes para a mesma rota** — um segundo dono da mesma tela,
que é exatamente o defeito que o `PatrimonioSubNav` derivar de `shell.menu` existe para
evitar. Autorização não é obrigação.

Descartados: `Painel.tsx` · `Painel.charter.md` · `Painel.casos.md` · `RUNBOOK-painel.md` ·
`PainelContratoTest.php` · minhas mudanças no `AssetController.php` e no `SUPERFICIE.md`.

---

## 3 · O que sobrou, e é o motivo deste PR existir

**A catraca Tier 0 do [#7018](https://github.com/wagnerra23/oimpresso.com/pull/7018) estava
morta no `main`.** O #7040 migrou o `dashboard()` para `Inertia::render` e não tocou no teste
que a defendia — ele lia `$view->getData()`, método que `Inertia\Response` não tem.

Medido no CT 100, no main atual, **antes** do meu PR:

```
FAILED  BadMethodCallException
Method Inertia\Response::getData does not exist.
at SmokeRoutesTest.php:319
Tests: 1 failed, 6 passed (8 assertions)
```

E não havia rede embaixo: os `UC-PAT-01..04` do #7040 são testes **JS/e2e**, e o `UC-PAT-01`
— justamente *"vejo o patrimônio da MINHA empresa"* — é um **`test.fixme`**, stub que não
roda. O painel novo ficou **sem cobertura Pest de isolamento cross-tenant**.

**O conserto teve de reescrever o predicado observável, não só o transporte.** O
`painelGarantia()` de hoje agrega em baldes e não devolve mais `name`/`asset_code`: o
vazamento seria **numérico** (um bem a mais na contagem), não um nome à mostra. O predicado
Tier 0 é o mesmo; o que se observa é outro.

| Evidência (CT 100, md5 conferido antes e depois de cada run) | |
|---|---|
| `main` atual (`4f70a09460`) | **1 failed**, 6 passed (8 assertions) — `BadMethodCallException` |
| com o PR #7048 | **7 passed** (22 assertions) |
| bite-test (mutante) | **1 failed** na linha 377 — o assert de isolamento |

O bite-test simula a contaminação **sem desligar guarda nenhuma**: faz o bem "do adversário"
nascer no tenant do dono. Cai exatamente onde deve — não é carimbo.

---

## 4 · Achado que vale para as 5 threads-filhas restantes

> **Migrar um método do `AssetController` para Inertia quebra, em silêncio, todo teste que
> leia `View::getData()` daquele método.**

O `--stat` do PR não acusa: o teste vive em outro arquivo, que o PR não toca. O CI acusa —
mas só depois, e o #7040 mergeou assim. **Antes de migrar um método, faça o grep:**

```bash
git grep -n "getData()\|->render()" -- Modules/<Mod>/Tests/
```

Se algum teste lê a View daquele método, ele entra no **mesmo PR** da migração. É a mesma
família do que o `_saida-06-bens.md §1-bis` catalogou (o `PAGES_NS`): o segundo mecanismo que
não sabia da mudança.

---

## 5 · O CT 100 é DISPUTADO — md5 antes e depois, sempre

O `_saida-06-bens.md` já avisava que há trabalho não-commitado de outras sessões lá. Nesta
sessão foi **pior: o ambiente mudou DURANTE os runs.**

- entre dois runs meus, `AlocacoesContratoTest` e `ManutencoesContratoTest` **sumiram**
  (−4 testes) e apareceu um `SondaCreateTest` alheio;
- meus `AssetController.php` e `SmokeRoutesTest.php` foram **substituídos** por versões de
  outra sessão — um run inteiro (`85 passed`) mediu **código que não era o meu**;
- o `/tmp` do container é compartilhado: um `cp` de backup meu foi sobrescrito, e o "restore"
  devolveu uma versão antiga de 4 testes.

**A regra que salvou os números:** `md5sum` do arquivo **no mesmo comando** que roda o teste,
antes e depois. Sem isso, "80 passed" é uma frase sobre o código de outra pessoa.

Corolário: **o total absoluto da suíte do módulo não é comparável entre runs** enquanto
sessões irmãs adicionam e removem os testes delas (86 → 89 → 85 → 80 em uma tarde, sem que
nada meu mudasse). O que é comparável é o veredito **dos arquivos que você fixou por md5**.

---

## 6 · Duas observações para o [W] — registradas, sem ação minha

1. **`SUM(quantity * unit_price)` entrou no painel** pelo #7040 (`painelKpis`,
   `painelPorCategoria`, `painelGarantia`). Soma de valor é a **REGRA MESTRE Tier 0** — prova
   por dois caminhos independentes + antes→depois apresentado ao [W]. Eu **recusei** esse KPI
   na minha versão por essa regra; a irmã o implementou. Não li o corpo do #7040 inteiro, então
   **não afirmo que a prova não foi dada** — sinalizo que é o tipo de mudança que a regra cobre,
   e a decisão é sua.
2. **A divergência de nome (`Painel.tsx` × `Index.tsx`) custou uma sessão inteira.** Se o
   canon tem dois nomes para a mesma tela, a próxima colisão é questão de tempo. A ficha
   `07-painel.md` continua dizendo `Index.tsx` no `prefixo:` e "esta thread cria o `_shared`"
   no corpo — as duas coisas hoje **cumpridas**, não pendentes.

---

## 7 · Checklist da ficha — o que dá e o que não dá para marcar

| # | Item do `07-painel.md` | Estado |
|---|---|---|
| 1 | RUNBOOK da tela | ❌ **descartado** — o #7040 entregou `RUNBOOK-patrimonio-index.md` |
| 2 | charter + casos | ❌ **descartado** — idem, `Index.charter.md` / `Index.casos.md` |
| 3 | `Inertia::render` no `dashboard()` | ✅ **feito pelo #7040**, não por mim |
| 4 | `_shared/PatrimonioSubNav.tsx` | ✅ já vinha do #7035 (a errata da ficha estava certa) |
| 5 | `Inertia::defer` nas props caras | ✅ #7040 |
| 6 | KPI sem fonte renderiza `—` | ✅ #7040 (`valorResidual => null`) |
| 7 | Pest verdes no CT 100 | ✅ **este PR** — e antes dele havia 1 vermelho no main |
| 8 | placar no PR | ✅ #7048 |
