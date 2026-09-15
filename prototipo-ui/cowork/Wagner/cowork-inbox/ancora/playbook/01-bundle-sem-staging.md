---
sessao: "01"
titulo: perna do bundle resolve no LUGAR_FIXO sem --staging
dono: "[CL]"
base: 752041ac450d
prefixo: prototipo-ui/ancora.mjs
nao_toca: resources/js/Pages/** · .claude/hooks/** · scripts/governance/** · prototipo-ui/cowork/**
depende: —
---
# 01 · a âncora de bundle existe no git e a máquina diz que não existe

## A · IDENTIDADE (ancoragem dupla)
- **âncora (código):** `prototipo-ui/ancora.mjs` — **49.089 B**, sha de árvore `752041ac450d`. Blocos que importam: `const LUGAR_FIXO = 'prototipo-ui/cowork'`, `caminhoDaAncora` (formato 3), `resolveAncora` (`if (stagingDir) { … }`), `printResolve` (a linha `'  âncora:     ⚠️ charter sem related_prototype nem -page.jsx — registre o protótipo'`).
- **oráculo, não leitura:** os 14 charters da tabela abaixo e os 5 `-page.jsx` — provados por existência, não por leitura de conteúdo.
- **arquétipo:** ferramenta de linha de comando. **persona:** [CC]/[CL] em sessão de comparação.

## B · NÃO INVENTAR
- **Zero constante nova de lugar.** `LUGAR_FIXO` já existe **neste arquivo** e já é usada pelo formato 3 do `caminhoDaAncora`. É ela, não um path novo, não um `.env`, não um argumento novo.
- **Zero mudança de API.** Nenhuma assinatura exportada muda de forma; a perna nova é mais um item no array `ancoras`, com o mesmo shape (`{tipo, valor, raiz}`).
- **`raiz` é obrigatória e é o repo** nessa perna nova (o arquivo está no git, não num staging) — passar staging como `repoRoot` é o defeito de 2026-08-25, já catalogado no docblock de `defeitosDaAncora`.

## C · O DEFEITO MEDIDO
Sem `--staging`, o bloco do `-page.jsx` nunca roda. Resultado: charter cujo único vínculo é `bundle_source`/`visual_source` sai como **sem protótipo**, com o arquivo presente no git.

| charter | campo | arquivo declarado | existe no `main` |
|---|---|---|---|
| `Repair/Dashboard/Index` | `bundle_source` | `repair-page.jsx` | **46.532 B** ✓ |
| `Repair/JobSheet/Index` | `bundle_source` | `repair-page.jsx` | ✓ |
| `Repair/Status/Index` | `bundle_source` | `repair-page.jsx` | ✓ |
| `Repair/Index` | `n/a` + `bundle_source` | `repair-page.jsx` | ✓ |
| `Repair/DeviceModels/Index` | `n/a` + `bundle_source` | `repair-page.jsx` | ✓ |
| `Repair/ProducaoOficina/Index` | `n/a` + `bundle_source` | `repair-page.jsx` | ✓ |
| `governance/Audit` | `bundle_source` | `governance-page.jsx` | **20.212 B** ✓ |
| `governance/Dashboard` | `bundle_source` | `governance-page.jsx` | ✓ |
| `governance/DriftAlerts` | `bundle_source` | `governance-page.jsx` | ✓ |
| `governance/Policies` | `bundle_source` | `governance-page.jsx` | ✓ |
| `governance/ModuleGrades/Index` | `bundle_source` | `governance-page.jsx` | ✓ |
| `OficinaAuto/ServiceOrders/Board` | `visual_source` | `oficina-page.jsx` | **72.534 B** ✓ |
| `OficinaAuto/ServiceOrders/Show` | `visual_source` | `oficina-os-page.jsx` | **18.896 B** ✓ |
| `Produto/Index` | `n/a` + `bundle_source` | `produtos-page.jsx` | **44.275 B** ✓ |

`Sells/Index` declara os três campos (`bundle_source` + `related_prototype` + `visual_source`) e por isso **não** entra — é o controle positivo desta thread: já resolve hoje e tem que continuar resolvendo igual.

**Por que é defeito e não escolha:** o `--list` já lê `bundle_source`/`visual_source` desde 2026-08-28 (o comentário no `listAll` conta o caso: 18 charters lidos como "fonte ausente" por uma auditoria). As duas portas do mesmo arquivo passaram a discordar — `--list` diz que tem fonte, o comando de 1 tela diz que não.

## D · COMO VALIDAR
1. `ancora.mjs <tela>` **sem** `--staging`, para uma das 14: aparece a perna de bundle resolvida em `prototipo-ui/cowork/<arquivo>`, com `tipo` dizendo de qual campo veio (`bundle_source` ou `visual_source`) **e** que veio do lugar fixo, não de staging.
2. `--staging <dir>` continua **vencendo** (staging é explícito; o fixo é fallback) e continua carregando `raiz` = staging.
3. Charter com `n/a` + `bundle_source` imprime **as duas coisas**: `sem âncora: n/a (…)` (declaração legítima) **e** a perna do bundle. Não colapsar uma na outra.
4. Charter sem nenhum dos três campos **continua** imprimindo `⚠️ charter sem related_prototype nem -page.jsx`. Ausência tem que continuar visível.
5. Selftest novo, hermético, com controle negativo: `BITE bundle sem staging` (fixture com `bundle_source` e o arquivo no lugar fixo da fixture → resolve) + `CONTROLE bundle sem staging` (arquivo **ausente** no lugar fixo → **não** inventa perna) + `CONTROLE staging vence o fixo`.
6. Os BITEs de 2026-08-25 (`raiz` de staging, `git grep` no repo) **seguem verdes** — eles provam a outra perna e não podem regredir.
7. `node prototipo-ui/ancora.mjs --selftest` verde (é step required em `.github/workflows/design-memory-gate.yml:316`).
8. PLACAR no corpo do PR.

## 4-ter · EXECUÇÃO
- **ARQUIVOS A EDITAR:** `prototipo-ui/ancora.mjs` — **só este**.
- **REUSAR:** `LUGAR_FIXO`, `mockupJsx`, `caminhoDaAncora`, `ehArquivo`, o shape `{tipo, valor, raiz}`.
- **CRIAR:** nada de arquivo novo. Nem script, nem gate, nem doc.
- **NÃO TOCAR:** nenhum charter, nenhum hook, nenhum workflow, nenhum arquivo de `prototipo-ui/cowork/`.
- **PASSO A PASSO:** 1) remedir o sha (a árvore andou pra `7742b9621c32` durante a auditoria) · 2) ler o arquivo inteiro · 3) na perna do bundle, quando `stagingDir` é nulo, resolver `mockupJsx(fm.bundle_source) || mockupJsx(fm.visual_source)` contra `join(repoRoot, LUGAR_FIXO)` e só empurrar em `ancoras` **se abrir** · 4) selftest com os 3 casos do item 5 · 5) rodar `--selftest`.
- **DADO:** nenhum.
- **PARAR SE:** encaixar exigir mudar assinatura exportada, tocar hook, ou fazer o `git grep` do P-1 rodar fora do repo — **pare e reporte**.

## PRÉ / PÓS
- **antes:** as 14 telas saem "sem protótipo" sem `--staging`; `LUGAR_FIXO` existe e é usada só pelo formato 3.
- **depois:** as 14 resolvem; `Sells/Index` inalterada; ausência real continua ausente.
- **quebra:** se alguém já somou o fallback, **não execute** — reporte e pare.

## PROVA
`prototipo-ui/ancora.mjs` contém a resolução por `LUGAR_FIXO` na perna do bundle + `BITE bundle sem staging` + `CONTROLE` · `--selftest` verde · hook importando `caminhoDaAncora` intacto (guarda) · `_saida-01.md`.
