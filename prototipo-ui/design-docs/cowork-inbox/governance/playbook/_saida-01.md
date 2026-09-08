---
sessao: "01"
titulo: Saída — descer o contrato de governança pra pasta de estágio
dono: "[CL]"
base: origin/main (worktree rebaseado em origin/main — 0 ahead / 0 behind no início)
constituicao: CONSTITUICAO-COWORK.md (C1–C12) + memory/proibicoes.md
prefixo_escrito: prototipo-ui/design-docs/contrato-cowork/governance.contract.json
veredito: ENTREGUE — com PARE(b) disparado (divergência copy × tela registrada, não corrigida)
---

# 01 · Saída

## A · O que foi feito

1. **PASSO 1 — `gh pr list` × pasta de estágio.** 5 PRs abertos (#7049, #7045, #7030, #6427, #6425); nenhum toca `contrato-cowork/` nem governança. `prototipo-ui/contrato/` (vigente) tem **0** arquivos de governança. **O dono não mudou** — segui.
2. **PASSO 2 — cópia pro estágio.** `cowork-inbox/governance/governance.contract.json` (10.174 B) → `design-docs/contrato-cowork/governance.contract.json` (10.477 B, +303 B).
   A cópia saiu **por script**, não por transcrição — inserção textual ancorada na linha única `"_nota":`, com **teste de identidade**: remover o bloco inserido devolve o original **byte a byte**. Sem reserialização (`JSON.parse`+`stringify` teria reescrito a formatação inteira, e o §C do brief proíbe reescrever).
3. **`cobertura_parcial` acrescentado**, literal como o §B.2 pede, logo após `_nota`: 5 cobertas, **4 não cobertas** (`Custos`, `DsRollout`, `QualidadeIa`, `ModuleGrades/Show`), motivo citando D-CONTRATO-9.
4. **Nada mais tocado.** `git status --short` = 1 arquivo novo. `prototipo-ui/contrato/` e `.github/workflows/contrato-de-tela.yml` intocados; `Pages/governance/` e `Modules/Governance/` intocados.

### Checklist de saída (os 7)

| # | item | veredito |
|---|---|---|
| 1 | arquivo em `design-docs/contrato-cowork/` (estágio) | ✅ 10.477 B |
| 2 | `cobertura_parcial` com as 4 não cobertas | ✅ n=4 |
| 3 | JSON parseia | ✅ 10 chaves de topo |
| 4 | `prototipo-ui/contrato/` + workflow intocados | ✅ diff = 1 arquivo |
| 5 | caminho minúsculo preservado | ✅ **5 de 5** com `Pages/governance/`; **0** com `Pages/Governance/` |
| 6 | divergências registradas | ✅ §B (6 achados) |
| 7 | placar no PR | ✅ §C |

Bônus medido: o contrato **bate com o schema real** do repo (`prototipo-ui/contrato/contract.schema.json` exige `alvo`+`secoes` — tem os dois; `additionalProperties: true`, logo `cobertura_parcial` é permitido).

## B · Achados — registrados, NÃO corrigidos

### B1 · PARE(b) DISPARADO — metade da copy do contrato não existe em produção

O gatilho (b) do brief mandava registrar divergência copy × tela vista sem abrir o `.tsx`. Ela existe, e é grande.

Classifiquei as **118** strings de `secoes[].copy` contra dois corpora — produção (`resources/js`, 578 arquivos **+** `Modules/Governance`, 147 arquivos PHP) e protótipo (`prototipo-ui/cowork/governance-*.jsx`, 3 arquivos):

| balde | n | leitura |
|---|---:|---|
| em **prod E** no protótipo | 58 | o contrato honra os dois |
| **só em prod** | 1 | `drift-lista :: "não declarado"` — protótipo atrasado num ponto |
| **só no protótipo** | **43** | produção não tem a string |
| em **lugar nenhum** | **16** | nem prod nem protótipo |

**59 de 118 (50%) não estão em produção.** A causa aparente: a copy foi medida contra o campo `fonte` (o protótipo Cowork), não contra o campo `alvo` (as 5 telas de produção) — o próprio `_nota` do contrato diz "trava seções, copy literal e estados das cinco vistas" enquanto `fonte` aponta pro `.jsx`.

**Consequência prática:** se este contrato fosse promovido a gate contra o `alvo` como está, reprovaria em ~metade das strings. Isso reforça a decisão do §B.3 do brief (não registrar no `contrato-de-tela.yml` neste PR) e é insumo direto pra decisão [W] de promoção.

**Ressalva honesta:** a medição é `String.includes` literal. Ela não distingue "a tela não tem essa copy" de "a tela **compõe** essa copy" (interpolação `{n} h`, título montado por template, label vindo de prop). Portanto **59 é piso de divergência candidata, não veredito de 59 bugs**. O que a medição prova com segurança é a *classe*: o contrato foi ancorado na fonte, não no alvo.

**Controle positivo rodado** antes de confiar no número: `includes("Auditoria")` e `includes("Governança")` = `true` no `GovernancaSubNav.tsx` — encoding e acentuação casam, o zero não é artefato de charset.

**Correção do meu próprio denominador, registrada:** a primeira passada varreu só os 5 `.tsx` do `alvo` e deu 69 ausentes. Estava errada — o `GovernancaSubNav.tsx` declara que os labels vêm de `shell.menu`, populado pelo **backend** (`DataController::modifyAdminMenu` via `LegacyMenuAdapter`). Refeita com o backend no corpus, 69 caiu para 59. O número publicado é o segundo.

### B2 · O campo `copy` mistura rótulo de tela com glosa do autor

Das 16 "em lugar nenhum", a maioria não é copy — é **anotação explicativa** dentro de um campo que um gate leria como texto literal de tela:

- `conformidade-regua :: "O número é escrito à mão no controller, não apurado"`
- `auditoria-selo :: "alterar uma linha é incidente P0"`
- `drift-historico :: "mcp_alertas não aceita a categoria module_drift"`

São observações verdadeiras e valiosas (documentam dívida real), mas num campo que promete literalidade viram falso-negativo garantido em qualquer gate. Separá-las (`copy` × `nota`) é decisão de formato — **não fiz**: é fora do prefixo e o §C proíbe reescrever seções.

### B3 · Brief §A diz "31 contratos" na pasta vigente; medi **30**

`git ls-files prototipo-ui/contrato/ | grep -c '\.contract\.json$'` → **30**. A pasta tem **32** arquivos: 30 `.contract.json` + `contract.schema.json` + `financeiro-unificado.intent.json`. O 31 do brief provavelmente somou o `.intent.json` aos contratos. Número pequeno, mas o brief o apresenta como medição.

### B4 · Os 2 vizinhos de estágio apontam pra um `$schema` que não existe

`configuracoes.contract.json` e `patrimonio.contract.json` declaram `"$schema": "prototipo-ui/contrato/contrato-tela.schema.json"` — **esse arquivo não existe** (varredura `rg --hidden -g '!.git/**'` no repo inteiro: 0 hits; `git ls-files`: 0). O schema real chama-se `contract.schema.json`. Ponteiro morto nos dois.

**Por isso NÃO acrescentei `$schema` ao contrato de governança** — copiar o campo teria propagado o ponteiro quebrado, e o brief só autorizou acrescentar `cobertura_parcial`.

Nota de forma: os 3 vizinhos de estágio têm **três formatos diferentes entre si** (`configuracoes`/`patrimonio` usam `$schema/id/titulo/modulo/rota/…`; `venda-menu` usa `schema/page/source/gerado/…`; nenhum usa `tela`/`alvo`). A pasta de estágio não impõe schema único — mais uma razão pra não ter reescrito o de governança pro formato dos vizinhos.

### B5 · Frontmatter do brief: `depois: 1 contrato advisory no CI` é **falso** (reconfirmado)

A errata de 2026-09-08 §6 já apontava. Reconfirmei por medição própria: `scripts/contrato-de-tela.mjs:136` define `ehDocDesign`, que exclui `prototipo-ui/design-docs/` inteiro, e é invocado em `:174` (pula o arquivo) e `:437` (filtra da lista de ativos). O arquivo fica **fora do CI**, não advisory dentro dele. Isso é o desenho correto de uma pasta de estágio — só a frase do frontmatter está errada.

### B6 · Índice §2-bis aponta pra um script que não existe

O §2-bis manda `node scripts/qa/placar-indice.mjs`. Esse caminho **não existe**. O script real está em `prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.mjs` (o que o pedido da thread usa, e o que rodei). Há também uma cópia em `prototipo-ui/cowork/cowork-inbox/_scripts/placar-indice.mjs`.

## C · Placar

```
node prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.mjs \
  --indice prototipo-ui/design-docs/cowork-inbox/governance/playbook/00-INDICE.md --root . --proximo
```

Antes deste PR: `entregue 0 de 5 · próximo 2 · em curso 2 · bloqueada 1` — a 01 constava `em curso — sem _saida`. O placar pós-PR vai no corpo dele.

## D · Fora do prefixo — não fiz, de propósito

- Não promovi à pasta vigente nem toquei `contrato-de-tela.yml` (§B.3 do brief).
- Não criei contrato pras 4 telas não cobertas (D-CONTRATO-9, RECUSA por teto).
- Não corrigi nenhuma das divergências de B1/B2 — o brief é explícito: registrar, **não** editar os dois lados.
- Não normalizei o caminho pra maiúsculo (`governance/` minúsculo é o caminho real, exceção declarada).
