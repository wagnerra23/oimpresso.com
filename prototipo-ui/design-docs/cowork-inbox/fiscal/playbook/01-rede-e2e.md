---
sessao: "01"
titulo: Rede — 2 specs E2E do Fiscal (cockpit + NF-e)
dono: "[CL]"
base: 11eff17f13db
prefixo: e2e/fiscal-cockpit.spec.ts (CRIAR) · e2e/fiscal-nfe.spec.ts (CRIAR)
nao_toca: Pages/Fiscal/** · Modules/Fiscal/** · os 7 contratos fiscal-* (JÁ existem — insumo) · os casos.md (oráculo)
depende: — (vaga 1)
---
# 01 · Rede E2E do Fiscal

## A · Por que esta thread existe
`e2e/` tem **17 specs** (essentials, jana, oficina, produto, sells, manufacturing, arquivos) e **nenhum** do Fiscal — medido em `11eff17f13db`. É a única lacuna de **código** do módulo cujas telas já estão todas em produção com trio completo.

## B · Ancoragem — RECORTE, não arquivo
```
ÂNCORA (congelada 2026-09-08)
  contrato   prototipo-ui/contrato/fiscal-cockpit.contract.json     1.113 B  sha f2f341620799
  contrato   prototipo-ui/contrato/fiscal-nfe.contract.json         1.070 B  sha 100685ee6e62
  ler        os 2 contratos INTEIROS (2,2 KB — são pequenos) = seções, copy literal e estados
  recorte    Cockpit.tsx :: a linha da lista — faixa de :590 a :640 (o onKeyDown está em :605)
  recorte    Nfe.tsx :: a linha da lista — faixas :215–:250 e :300–:330 (onKeyDown em :231 e :316)
  NÃO ler    Cockpit.tsx inteiro (34.457 B) · Config.tsx (42.005 B) · Cockpit.casos.md (43.511 B)
             Nfe.casos.md (39.405 B) — são ORÁCULO: abrir só para dirimir dúvida pontual
  frescor    sha mudou ⇒ REMEDIR antes de escrever, e dizer no _saida
```
Leitura obrigatória total: **~6 KB** (2 contratos + 2 recortes). Teto do §13.2 é 40 KB.

## C · Casos a provar
| # | QUANDO → O SISTEMA DEVE | prova |
|---|---|---|
| 1 | abrir o cockpit → renderizar as seções **na ordem do contrato** | contagem + ordem, não "parecido" |
| 2 | **Tab até a linha e Enter** → abrir o drawer da nota | é o `UC-FCKP-11`; `Cockpit.casos.md:201` já registra que remover o `onKeyDown` derruba 3 casos |
| 3 | **Space** na linha focada → mesmo efeito do Enter | nesta tela o handler da linha é o **único** dono do teclado (não há segundo caminho por `window`) |
| 4 | filtrar / chip de visão salva → total muda e a querystring carrega o filtro | `SavedViewsChips` já existe |
| 5 | alternar densidade → a tabela troca de classe e **persiste** | `DensidadeToggle` + `_lib/densidade-fiscal.ts` |
| 6 | NF-e: `J`/`K` navegam a lista | handler global de `window`, já vivo — o Enter tem **dois** caminhos aqui (declarado no `Nfe.charter.md:95`) |

## D · Não inventar
- **Reusar:** `e2e/global-setup.ts` · o formato de `e2e/sells-index.spec.ts` (índice com grade) e `e2e/produto-show.spec.ts` (drawer).
- **Oráculo de regra:** os `casos.md` das duas telas e as mutações já provadas. O E2E prova **caminho de tela**; não reimplemente asserção de domínio.
- **Nunca** `role="button"` na linha: o `Cockpit.charter.md:226` proíbe — apaga o papel `row` e o leitor de tela perde a estrutura da tabela. O alvo é linha **focável**.
- Zero `waitForTimeout` como sincronismo. Copy PT-BR literal; `NF-e`/`NFC-e` com o casing legal (C2).

## Execução
```
1) gh pr list --state open × e2e/ e workflows
2) LER os 2 contract.json e derivar seletores/copy DELES (não de lembrança)
3) e2e/fiscal-cockpit.spec.ts — casos 1..5
4) e2e/fiscal-nfe.spec.ts — casos 2, 3, 6
5) rodar 3× (flake é reprovação) · placar no corpo do PR · _saida-01.md
PARAR SE : (a) contrato citar âncora data-contract que a tela não tem → NÃO inventar seletor;
               reportar no _saida (a âncora vira PR próprio de 1 arquivo)
           (b) o caso 2/3 exigir seed de nota autorizada que não existe → declarar, não criar
               fixture paralela ao Pest
           (c) D-LANE (onde a lane mora) sem resposta → specs fora do CI, dito no PR; NÃO criar
               workflow por conta própria
```

## Checklist de saída (marcar item por item no `_saida-01.md`)
1. sha conferido (`11eff17f13db` ou remedido) · 2. os 2 contratos lidos · 3. spec do cockpit com 5 casos · 4. spec de NF-e com 3 casos · 5. 3 execuções verdes · 6. guarda: `onKeyDown` ainda em `Cockpit.tsx` e os 2 contratos intactos · 7. placar no PR.

## Prova (o que o PLACAR confere)
`e2e/fiscal-cockpit.spec.ts` · `e2e/fiscal-nfe.spec.ts` existem · **guarda:** `Cockpit.tsx` contém `onKeyDown` e `contrato/fiscal-cockpit.contract.json` existe · `_saida-01.md` presente. Não verificável daqui: verde no CI · T7.
