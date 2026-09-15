---
sessao: "01"
titulo: Rede mínima — E2E de fumaça das 3 telas âncora
dono: "[CL]"
base: e86130722de1
prefixo: e2e/ponto-dashboard.spec.ts · e2e/ponto-espelho.spec.ts · e2e/ponto-espelho-show.spec.ts (CRIAR)
nao_toca: resources/js/Pages/Ponto/** (zero linha de .tsx) · Modules/Ponto/** · e2e/global-setup.ts · qualquer harness novo
depende: — (vaga 1). É o gate de toda mudança de UI do módulo (thread 03 espera esta).
---
# 01 · Rede mínima

## Por quê
Ponto é o módulo com obrigação legal (Portaria MTP 671/2021) e o único grande com **0 e2e** nesta sha (`e2e/` tem 17 specs: sells, produto, oficina, jana, essentials, manufacturing, arquivos — nenhum `ponto-*`). Sem rede, "não mudei layout" é opinião.

## Âncora (lida no `main`)
- Harness **existe**: `e2e/global-setup.ts` (2 KB) + `.github/workflows/e2e-gate.yml`. Convenção de nome: `e2e/<mod>-<tela>.spec.ts` (ex.: `essentials-metas.spec.ts`, `sells-index.spec.ts`, `produto-show.spec.ts`). **Copiar a forma do menor spec vivo** (`arquivos-index.spec.ts`, 672 B) — não inventar.
- Rotas alvo (`Modules/Ponto/Http/routes.php`): `GET /ponto` (`ponto.dashboard`) · `GET /ponto/espelho` · `GET /ponto/espelho/{colaborador}` — middleware `ponto.access`.
- Âncoras já no DOM (não criar): `data-contract="painel-kpis"` etc. (4 no Dashboard) · `espelho-totais`, `espelho-apuracao-diaria` etc. (5 no Show).

## Passo a passo
1. `gh pr list --state open` × `e2e/ponto-*`.
2. Ler `e2e/README.md` + `global-setup.ts` + 1 spec vivo pequeno.
3. Três specs: abrir a rota autenticado com `ponto.access` → esperar o `data-contract` da 1ª seção do contrato → título da página presente. **Nada de asserção de copy** (copy é lei do contrato, `contrato-de-tela.mjs` é quem confere).
4. Rodar 2× local; estável antes de commitar.
5. `_saida-01.md`.

## PARAR SE
- O harness não cobrir Inertia autenticado → parar e reportar; **não** criar harness paralelo (2º padrão no repo).
- VRT: **não verifiquei** que existe baseline visual no repo. Se existir, acrescentar as 3 telas ao padrão existente; se não existir, **não criar** — reportar e seguir só com E2E.

## Prova (PLACAR confere)
- `e2e/ponto-dashboard.spec.ts` (ou `ponto-smoke.spec.ts`) **e** `e2e/ponto-espelho.spec.ts` existem · `e2e-gate.yml` verde no PR
- `_saida-01.md`
