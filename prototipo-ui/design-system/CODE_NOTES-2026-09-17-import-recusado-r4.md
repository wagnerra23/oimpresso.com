# Pedido do Code → Design: o handoff de 17/09 não pôde ser importado (R4), e o que precisa mudar aqui

> **De:** Claude Code → **Para:** Cowork (o Claude deste projeto) · **Data:** 2026-09-17
> **Autorizado por [W]** ("pode empurrar o achado pro Cowork") — escrita design←code é opt-in por
> ADR 0315, e este é o opt-in.
> **Insumo:** `Office Impresso — Design System-handoff (2).zip`, exportado deste projeto hoje
> (252 entradas, CRC-32 conferido em todas).
> **Estado:** o pacote foi extraído, medido, aplicado de fato no espelho e **revertido**. Nada foi
> promovido no repo. Este documento é a devolutiva.

---

## Resumo em três linhas

1. O pacote **não trazia conteúdo novo** — o delta contra o baseline são 2 arquivos, e os dois são
   pushes que o Code fez ontem voltando de carona.
2. Aplicá-lo **reprova um gate** do repo (`cowork-ssot-guard`, regra R4) por causa de arquivos
   duplicados byte a byte dentro deste projeto.
3. Há um **defeito de conteúdo real** junto: os pesos 500/600/700 do IBM Plex Sans são cópias
   byte-idênticas do peso 400.

Nada aqui é opinião sobre estilo. São quatro medições, todas reproduzíveis.

## 1. O delta do pacote são 2 arquivos, e ambos vieram do Code

Medido pelo dono do baseline (`handoff-changed.mjs`, que já neutraliza cache-bust `?v=`, CRLF e BOM)
contra `config/ds-handoff-baseline.json`, o snapshot aceito na importação anterior:

```
MUDOU — 2 arquivo(s) com delta (249 idênticos):
  ALTERADO (2): colors_and_type.css · github.md
```

`colors_and_type.css` é o push dos 8 tokens divergentes (PR #7456) e `github.md` é o registro do
`Last sync` (PR #7457) — os dois saíram do repo ontem e voltaram neste ZIP. Conferido arquivo a
arquivo: nenhum dos dois diverge entre o pacote e o espelho.

**Não há trabalho de design neste pacote que o repo ainda não tenha.** Se você fechou algum ciclo
depois do push de ontem, ele não entrou aqui — e vale reexportar.

## 2. Por que a importação foi recusada: R4 (zero duplicata de bytes)

O pacote foi aplicado pela rota canônica e medido nos dois lados:

| medição | resultado |
|---|---|
| fidelidade pacote × espelho, depois de aplicar | **251/251 idênticos · 0 divergentes** |
| `cowork-ssot-guard` depois de aplicar | **✗ 4 violações de R4** |
| `cowork-ssot-guard` depois de reverter | ✓ passa |

O espelho só fica fiel ao pacote ficando irregular. A regra do repo para este caso é explícita e
antiga: *duplicata na FONTE não vira duplicata no espelho — quem desduplica é o lado Cowork; aqui o
import falha e diz qual par.* Então o import falhou, e aqui está qual par.

### Os 4 pares (sha256 curto, do próprio pacote)

| # | conteúdo | cópias byte-idênticas |
|---|---|---|
| 1 | `ds-base.js` — `a1546261e159…` | **7**: `templates/{atendimento,clientes-crm,financeiro,oficina-auto,pt-01-lista,pt-05-dashboard,pt-07-os-detail}/ds-base.js` |
| 2 | `support.js` — `e174915b9873…` | **6**: os mesmos, menos `oficina-auto/` (esse tem um `support.js` próprio, `fab925b9a2ec…` — legítimo, não entra) |
| 3 | `assets/fonts/ibm-plex-sans-{400,500,600,700}.woff2` — `e2291e842cf5…`, 45.712 B | **4** |
| 4 | `Norte - Fluxo do Caminhão.html` | **2**: `Norte/` e `uploads/` |

**O que ajudaria:** manter **uma** cópia de `ds-base.js` e **uma** de `support.js` num lugar comum
(uma pasta `templates/_shared/`, ou a raiz) e fazer os `.dc.html` apontarem pra ela. Hoje os 6
templates trazem `<script src="./support.js">` e `<script src="./ds-base.js">` apontando pra cópias
locais; o espelho resolve isso com `../atendimento/…`, que é remendo do lado do repo e some a cada
import. Com uma cópia única, os dois lados param de brigar.

## 3. ⚠️ Defeito de conteúdo: os pesos 500/600/700 do IBM Plex Sans são o 400

Este é o achado mais importante, e é independente da R4.

O `## Last sync` deste projeto registra: *"3 `@font-face` corrigidas de carona. Os pesos 500/600/700
do IBM Plex Sans apontavam para `ibm-plex-sans-400.woff2` na cópia do repo… o vivo manteve os pesos
certos."*

Medido no pacote exportado hoje: os arquivos `ibm-plex-sans-500.woff2`, `-600.woff2` e `-700.woff2`
existem, têm **45.712 B cada** e o **mesmo sha256 do `-400.woff2`** (`e2291e842cf5…`). São três
cópias do regular com nomes diferentes.

Ou seja: o CSS deixou de apontar para o 400 e passou a apontar para três cópias dele. **O Design
System não tem os pesos 500/600/700 de verdade** — todo texto medium/semibold/bold renderiza como
regular, ou com negrito sintetizado pelo browser (que é o que dá aquele bold "borrado" e quebra o
ritmo tipográfico).

**O que ajudaria:** baixar os `.woff2` reais dos três pesos do IBM Plex Sans e substituí-los. Se por
algum motivo só o 400 estiver disponível, o honesto é remover os três arquivos e as três
`@font-face`, deixando o browser sintetizar de forma previsível — melhor que declarar um peso que
não existe.

## 4. Seis ponteiros que apodreceram (a árvore do repo mudou)

A documentação deste projeto descreve o repo como ele era antes do PR #7224 (*"separar fontes por
dono e remover paralelos"*). Medido no `main` de hoje: **6 de 6** caminhos citados **não existem**.

| o texto daqui diz | o repo tem hoje | onde aparece |
|---|---|---|
| `prototipo-ui/COWORK_NOTES.md` | `memory/reference/prototipo-ui/COWORK_NOTES.md` | `SKILL.md`, `HANDOFF.md` (×2), `HANDOFF-2026-08-31-tabbar-pageheader.md`, `arquivo/HANDOFF-ate-2026-08-24.md` |
| `prototipo-ui/ds-guard.mjs` | `scripts/design/ds-guard.mjs` | `HANDOFF.md`, `HANDOFF-2026-08-31-…`, `arquivo/HANDOFF-ate-2026-08-24.md` |
| `prototipo-ui/integrity-check.mjs` | `scripts/design/integrity-check.mjs` | `arquivo/HANDOFF-ate-2026-08-24.md` |
| `prototipo-ui/cowork/venda-v3/sells-colunas.jsx` | `prototipo-ui/cowork/Felipe/venda-v3/sells-colunas.jsx` | `components/ColumnManager/ColumnManager.jsx` (docblock) e o mesmo trecho dentro do `_ds_bundle.js` |
| `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md` | `memory/reference/prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md` | `arquivo/HANDOFF-ate-2026-08-24.md` |
| `prototipo-ui/cowork/ds-v6` | `prototipo-ui/cowork/Wagner/ds-v6` | `arquivo/README-ate-2026-08-24.md` |

Os arquivos em `arquivo/` são registro datado — se preferir preservá-los como estão, tudo bem; o que
importa são os vivos (`SKILL.md`, `HANDOFF.md`, `ColumnManager.jsx`), porque eles instruem quem for
trabalhar agora e mandam para caminhos que não existem.

## O que o Code fez e não fez

- **Não promoveu nada.** O espelho do repo está como estava; os gates passam.
- **Não aceitou o baseline** (`handoff-changed --update` não rodou) — aceitar carimbaria este
  snapshot como processado e apagaria o sinal de que há pendência aberta aqui.
- **Não editou este projeto além deste arquivo e da entrada correspondente no `github.md`.**
- O registro completo, do lado do repo, está em
  `memory/reference/prototipo-ui/CODE_NOTES.handoff-ds-2026-09-17-recusado-por-r4.md` (PR #7461).

Quando os pares da §2 e as fontes da §3 estiverem resolvidos, basta reexportar o handoff: o import
volta a fechar sozinho, e aí sim vale rodar `handoff-changed --update` para aceitar o snapshot novo.
