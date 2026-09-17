# Handoff do Design System de 2026-09-17 — RECUSADO por R4, e por que ele não trazia conteúdo novo

> **De:** Claude Code → **Para:** Cowork (o Claude do `claude.ai/design`) + [W] · **Data:** 2026-09-17
> **O que é:** devolutiva de uma importação que **não foi promovida**, com o achado que precisa
> voltar pra origem. Append-only. Insumo: `Office Impresso — Design System-handoff (2).zip`
> (252 entradas, CRC-32 conferido em todas), entregue por [W] no chat.
> Regra que decide o desfecho: `importar-bundle.mjs` §R4 — *"duplicata na FONTE não vira duplicata
> no espelho; quem desduplica é o lado Cowork; aqui o import falha e diz qual par"* — combinada com
> [ADR 0406](../../decisions/0406-o-que-ultimo-importado-decide-emenda-0404.md) **D4** (recusa por
> REGRA) e **D3** (*o achado vai para a origem*).

---

## 1. O delta real do pacote é 2 arquivos — e o espelho já os tinha idênticos

Medido pelo **dono do baseline** (`handoff-changed.mjs`, que normaliza cache-bust/CRLF/BOM),
contra `config/ds-handoff-baseline.json` (251 entradas, aceito no #7096):

```
MUDOU — 2 arquivo(s) com delta (249 idênticos):
  ALTERADO (2): colors_and_type.css · github.md
```

Os dois são **nossos próprios pushes de ontem** voltando de carona: `colors_and_type.css` é o push
dos 8 tokens divergentes ([#7456](https://github.com/wagnerra23/oimpresso.com/pull/7456)) e
`github.md` é o registro do `Last sync` ([#7457](https://github.com/wagnerra23/oimpresso.com/pull/7457),
cujo `Last sync` cita `5c55e4f96f1`). Conferido arquivo a arquivo: **nenhum dos dois aparece como
divergente entre ZIP e espelho** — o espelho já estava idêntico ao pacote nos dois.

**Consequência:** esta importação não tinha conteúdo novo a trazer. Tudo o que ela mudaria seria
desfazer o [#7224](https://github.com/wagnerra23/oimpresso.com/pull/7224) (*"separar fontes por dono
e remover paralelos"*), que é edição **deste lado** — confirmado pelo item 4 do roteiro da 0406
(`git log -- <path>`): os 13 arquivos divergentes têm **exatamente os mesmos 2 commits**, o import
do #7096 e o refactor do #7224, sem exceção.

## 2. A prova do conflito: espelho fiel ⟺ gate vermelho

O pacote **foi aplicado de fato** pela rota canônica (`--export-from --ds`, bytes saindo do ZIP por
script, nada pelo contexto), medido, e **revertido** em seguida:

| medição | resultado |
|---|---|
| fidelidade ZIP × espelho, pós-apply | **251/251 idênticos · 0 divergentes · 0 só-de-um-lado** |
| `cowork-ssot-guard` pós-apply | **✗ exit 1 — 4 violações de R4** |
| `cowork-ssot-guard` após reverter | ✓ exit 0 |

As duas regras não podem ser satisfeitas ao mesmo tempo com este pacote: **o espelho só fica fiel
ficando irregular.** Por isso o desfecho é recusa por regra, não reconciliação à mão (que a D4
proíbe) nem aplicação parcial.

## 3. O que a origem precisa desduplicar (R4 — os 4 pares, com sha256 curto)

| # | conteúdo | cópias byte-idênticas no pacote |
|---|---|---|
| 1 | `ds-base.js` (`a1546261e159…`) | **7** — `templates/{atendimento,clientes-crm,financeiro,oficina-auto,pt-01-lista,pt-05-dashboard,pt-07-os-detail}/` |
| 2 | `support.js` (`e174915b9873…`) | **6** — os mesmos, menos `oficina-auto` (que tem um `support.js` próprio, `fab925b9a2ec…`) |
| 3 | `ibm-plex-sans-{400,500,600,700}.woff2` (`e2291e842cf5…`, 45.712 B) | **4** — os pesos 500/600/700 são **cópias byte-idênticas do 400** |
| 4 | `Norte - Fluxo do Caminhão.html` | **2** — `Norte/` e `uploads/` |

⚠️ **O par 3 é mais que duplicata — é um defeito de conteúdo.** O `github.md` deste pacote registra
*"3 `@font-face` corrigidas de carona… os pesos 500/600/700 apontavam para `ibm-plex-sans-400.woff2`
na cópia do repo"*. Medido agora: no **vivo**, os três arquivos existem e são **o próprio 400**
byte a byte. O CSS deixou de apontar pro 400, mas passou a apontar pra três cópias dele — então
**o DS não tem os pesos 500/600/700 de verdade**, e qualquer peso acima de 400 renderiza como
regular (ou sintetizado pelo browser). Vale conferir na origem antes de re-exportar.

## 4. O que a origem precisa reapontar (6 ponteiros podres)

O pacote descreve a árvore do repo como ela era antes do #7224. Medido no `main` de hoje, **6 de 6
caminhos citados pelo pacote não existem**, e **6 de 6 equivalentes do espelho existem**:

| o pacote diz (inexistente) | o repo tem hoje | onde aparece no pacote |
|---|---|---|
| `prototipo-ui/COWORK_NOTES.md` | `memory/reference/prototipo-ui/COWORK_NOTES.md` | `SKILL.md`, `HANDOFF.md` ×2, `HANDOFF-2026-08-31…`, `arquivo/HANDOFF-ate-2026-08-24.md` |
| `prototipo-ui/ds-guard.mjs` | `scripts/design/ds-guard.mjs` | `HANDOFF.md`, `HANDOFF-2026-08-31…`, `arquivo/HANDOFF-ate-2026-08-24.md` |
| `prototipo-ui/integrity-check.mjs` | `scripts/design/integrity-check.mjs` | `arquivo/HANDOFF-ate-2026-08-24.md` |
| `prototipo-ui/cowork/venda-v3/sells-colunas.jsx` | `prototipo-ui/cowork/Felipe/venda-v3/sells-colunas.jsx` | `components/ColumnManager/ColumnManager.jsx` e o mesmo trecho dentro do `_ds_bundle.js` |
| `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md` | `memory/reference/prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md` | `arquivo/HANDOFF-ate-2026-08-24.md` |
| `prototipo-ui/cowork/ds-v6` | `prototipo-ui/cowork/Wagner/ds-v6` | `arquivo/README-ate-2026-08-24.md` |

Os 6 `.dc.html` de `templates/` são caso à parte e **o pacote está certo neles**: eles passaram a
apontar `./support.js` e `./ds-base.js` porque o pacote traz essas cópias locais. Assim que o par 1
e o par 2 da §3 forem desduplicados na origem, esses `src` precisam apontar pra cópia única que
sobrar (hoje o espelho resolve com `../atendimento/…`).

## 5. O que NÃO foi feito, de propósito

- **Nada foi promovido.** O espelho está como estava; `git status` limpo e `cowork-ssot-guard` verde.
- **O baseline não foi aceito** (`handoff-changed --update` não rodou): aceitar carimbaria este
  snapshot como processado e apagaria o sinal de que ele tem pendência aberta na origem.
- **Nada foi escrito no espelho** — 0406 **D5**: anotação do Code não mora lá, morre no próximo
  import. Por isso este achado está aqui, em `memory/reference/prototipo-ui/`.
- **Nada foi empurrado pro projeto Cowork.** Escrita espelho→origem é **gated por opt-in de [W]**
  ([ADR 0315](../../decisions/0315-design-sync-claude-design-vs-cowork-charter.md)); o `DesignSync`
  é livre só pra leitura. Este documento é o insumo pronto pra quando [W] autorizar.

## 6. Achado de máquina, registrado e não consertado aqui

O **PASSO 0** (`protocolo.config.mjs --de-quem`) deu **`nao-vinculada` (exit 3)** neste ZIP, e o
veredito está **tecnicamente correto**: medido, nenhum dos 2 UUIDs registrados aparece no material
— nem em path, nem em conteúdo (`grep -rl`, 0 ocorrências para os dois). A causa é estrutural: um
export **do próprio projeto DS** não carrega o próprio id em lugar nenhum (quem carrega id é o
cache `_ds/<slug>-<uuid>/` de um export **de telas**, que descreve o DS que ele *consome*).

Ou seja, o detector tem um vão conhecido na única classe de material que é o DS em si — e o
fail-closed dele manda "vincule a conta primeiro", instrução que aqui não se aplica (a conta `w` e o
projeto `ds` **já estão** registrados). A proveniência foi estabelecida por outra via, medida:
**236 de 236** paths do espelho existem no pacote, **0** arquivos só no espelho, o `README.md` nomeia
o projeto (`Office Impresso — Design System`, idêntico ao registro) e o `github.md` cita o commit
`5c55e4f96f1` deste repo. Não mexi no detector: consertá-lo é outro intent, e a forma honesta
(reconhecer "o material É o projeto X" sem id) precisa de critério medido antes de virar código.
