---
sessao: "07"
titulo: Painel do Patrimônio — consome o shell (que já existe)
dono: "[CL]"
base: main pos-ADR-0394
constituicao: CONSTITUICAO-COWORK.md (C1-C12)
prefixo: resources/js/Pages/Patrimonio/Index.tsx (+ charter, casos) · AssetController::dashboard
nao_toca: `_shared/` (JÁ EXISTE — ver ERRATA) · as outras 5 telas · Services/ · Modules/Auditoria
depende: — (a fundação está no main desde o PR #7035)
---

> ⚠️ **ERRATA 2026-09-08 [CL] — esta thread NÃO cria mais o `_shared`.**
> Ela nasceu como "a tela que cria o shell", mas [W] mandou a tela de **Bens** fundar o
> compartilhado, e ele foi mergeado antes (PR [#7035](https://github.com/wagnerra23/oimpresso.com/pull/7035),
> 17:42Z). O `resources/js/Pages/Patrimonio/_shared/PatrimonioSubNav.tsx` **está no main** —
> esta thread o **importa**, e ele virou `nao_toca`.
>
> **Três coisas que você herda dele, medidas e não supostas:**
> 1. O SubNav **deriva** as abas de `shell.menu` (`DataController::modifyAdminMenu`). Não
>    declare lista de abas em lugar nenhum — criaria um segundo dono.
> 2. **São 6 ghosts vivos, não as 7 do protótipo** (tem *Devoluções*, não tem
>    *Garantias*/*Auditoria* — essas duas são decisão ABERTA do [W], itens 4 e 5 do §6).
> 3. **Escreva o charter ANTES do `.tsx`**, com `related_runbook:` apontando pro
>    `memory/requisitos/AssetManagement/RUNBOOK-<tela>.md`. O hook `block-mwart-violation`
>    deriva o RUNBOOK do nome da pasta de `Pages/` (`Patrimonio` ⇒ `requisitos/Patrimonio/`,
>    que não existe nem deve) e **bloqueia sem override**. A declaração do charter é a saída
>    prevista pelo próprio hook.
>
> Detalhe e recibos: [`_saida-06-bens.md`](_saida-06-bens.md) §1, §1-bis e §2.
# 07 · Painel do Patrimônio — a tela que cria o shell

## ÂNCORA (congelada — remedir se o sha mudou)
```
rota      GET /asset/dashboard  ->  AssetController::dashboard()   :467-:520
arquivo   Modules/AssetManagement/Http/Controllers/AssetController.php   24.416 B  sha 3eba5a4faae5
proto     prototipo-ui/cowork/patrimonio-page.jsx  aba "Painel"  (58 KB — leia SÓ a aba)
endereco  resources/js/Pages/Patrimonio/   (ADR 0394 — modulo proprio, NAO Pages/Estoque/)
NAO ler   os outros 4 controllers · as 17 views Blade
```

## A · O alvo
O `dashboard()` hoje devolve Blade com 3 cards e 2 tabelas vazias. O protótipo mostra 4 KPIs com
semântica própria (Patrimônio bruto · Valor residual · Alocados X de Y · Garantia vencida), 3 blocos
de análise e um "Resumo de hoje" em prosa.

**Esta thread é a primeira da frente**: ela cria o `_shared/PatrimonioSubNav.tsx` que as outras cinco
vão importar. Errar aqui custa seis telas, não uma.

## B · Não inventar
- **Número sem fonte não renderiza** (C7): o "Resumo de hoje" do protótipo cita valores que o backend
  hoje NÃO calcula (valor residual, depreciação). Onde não houver fonte, renderize `—` e declare no PR.
- **Depreciação é decisão [W] em aberto** (RESÍDUO 6: linear ou SAC). A coluna existe e é gravada, mas
  nunca calculada. Não invente a fórmula.
- Reusar os átomos de `resources/js/Components/` — rode `npm run reuse:check` antes de criar componente.

## Execução
```
PASSO  1) gh pr list --state open x Pages/Patrimonio/
       2) MWART (ADR 0104) F1: RUNBOOK da tela em memory/requisitos/AssetManagement/
       3) charter + casos.md ANTES do .tsx (o casos-gate e required)
       4) Controller: Inertia::render('Patrimonio/Index', ...) com Inertia::defer nas props caras
       5) _shared/PatrimonioSubNav.tsx com as 7 abas (Auditoria visivel mas inerte ate a 06g)
       6) _saida-06a.md
PARAR SE (a) algum KPI exigir calculo que o backend nao faz -> renderize `—` e declare
         (b) o SubNav precisar de rota inexistente (Garantias/Auditoria) -> item desabilitado
             com title explicando; NAO invente rota
```

## Checklist de saída
1. RUNBOOK · 2. charter + casos · 3. `Inertia::render` no dashboard() · 4. `_shared/PatrimonioSubNav.tsx` · 5. `Inertia::defer` nas props caras · 6. KPI sem fonte renderiza `—` · 7. 9 Pest verdes · 8. placar no PR
