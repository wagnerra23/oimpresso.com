# Pedido pro [CL] — re-exportar o build do Cowork pro `main` (e fechar o gate que não existe)

> Autor: [CC] · 2026-08-27 · leitura do `main` neste turno: tree `6268f318bca0`
> Alvo: `prototipo-ui/cowork/` · `scripts/` · `package.json`
> Motivo: [W] quer compartilhar o protótipo com a equipe **via git**. Hoje o que está no `main` é uma versão atrasada, e os dois gates que o `CLAUDE.md` promete não existem.

---

## 1. Os dois fatos, com recibo

Leitura do `main` @ tree `6268f318bca0`, hoje:

**(a) O export está atrasado.** `prototipo-ui/cowork/oimpresso.com.html` no `main` tem **31.800 bytes**. O host vivo no Cowork tem **32.644 bytes** e declara **253 refs locais** (`<link>`/`src`/`data-src`, fora `_ds/`). A raiz de `prototipo-ui/cowork/` no `main` lista **156 arquivos**; o projeto Cowork tem **275**. Quem clonar agora vê um app velho — e pior, um app com refs que podem não resolver.

**(b) Os gates não existem.** Busca por nome em toda a árvore (15.998 arquivos, regex `(ssot-guard|paridade|publisher|design-sync).*\.(mjs|js|sh)$`): **1 resultado**, e é `scripts/design-sync/mirror-snapshot/_ds_bundle.js` — um bundle, não um script.

- `scripts/cowork-ssot-guard.mjs` → **não existe**
- `scripts/cowork-paridade.mjs` → **não existe**

O `CLAUDE.md` do projeto Cowork fala dos dois no presente do indicativo ("o guard dá erro se quebrar isso", "paridade = máquina no git"). É combinado no papel. Nenhuma máquina está segurando nada. Isso precisa virar ou código, ou errata no `CLAUDE.md` — não pode ficar como está.

## 2. O que NÃO estou pedindo

- **Não é lista de arquivos à mão.** Não anexei manifesto, não anexei mapa tela↔arquivo, não anexei contagem de rotas. Isso é derivado do build (L-42) e envelhece no minuto seguinte. O host `oimpresso.com.html` **é** o manifesto; o `app.jsx` **é** a tabela de rotas. Derive, não transcreva.
- **Não é ADR.** É reposição de export + duas máquinas pequenas. Se [W] quiser elevar a decisão, é dele.
- **Não é build novo.** Não compile, não empacote, não rode Vite em cima disso. O export do Cowork é jsx/babel cru servido estático, de propósito — é o que deixa [W] editar e ver na hora.

## 3. Entrega A — repor o build

[W] baixa o zip do projeto Cowork e larga em `cowork-inbox/export-2026-08-27/`. A partir daí:

1. **Substituir** o conteúdo de `prototipo-ui/cowork/` (raiz) pelos arquivos do zip. Substituição, não merge: arquivo que sumiu do Cowork deve sumir do export.
2. **Preservar** as subpastas existentes que não vêm do zip — `ds-v6/`, `prototipo-ui-patch/`, `venda-v3/`, `produto-preco-especial/`, e `.gitignore`. Elas são histórico/patch, não build corrente.
3. **Filtro de export (R1)** — só entra `.jsx` `.tsx` `.css` `.html` `.js` e assets referenciados pelo host. **Não entra:** memória, charter, `.casos.md`, process-doc, screenshot, `.bak`, dupe `?v=`, nem os `.md` do próprio `cowork-inbox`.
4. Conferir que **toda ref do host resolve** depois da cópia. Se sobrar 404, o export está quebrado — não commite.

## 4. Entrega B — o README que a equipe precisa

Criar `prototipo-ui/cowork/README.md`, curto, PT-BR, só isso:

```md
# Protótipo Cowork — Office Impresso ERP

App único: `oimpresso.com.html` (shell Cockpit V2, todas as telas como rotas).

## Abrir

    npx serve .
    # http://localhost:8000/oimpresso.com.html

Precisa de servidor local. Abrir por `file://` não funciona — o host
carrega ~250 arquivos jsx/css por fetch e o navegador bloqueia.

## O que é isto

Protótipo visual (F1), não produção. Serve pra revisar layout, fluxo e
copy antes de virar Inertia/React real. Divergência com o app rodando é
esperada — a fonte da UI de produção é `resources/js/`.
```

Nada além disso. README não é lugar de inventário nem de changelog.

## 5. Entrega C — os dois gates que faltam

### C1 · `scripts/cowork-ssot-guard.mjs`

Roda sobre `prototipo-ui/cowork/`. Sai `1` com a lista do que violou:

- **R1 — só build.** Recusa `.md` (exceto o `README.md` da Entrega B), `.png`/`.jpg` não referenciado pelo host, `.bak`, `~`, `.orig`.
- **R2 — sem dupe.** Recusa dois arquivos cujo nome só difere por sufixo de versão (`foo.jsx` + `foo-v2.jsx`, `foo copy.jsx`).
- **R3 — host único.** Recusa `.html` novo na raiz que não seja `oimpresso.com.html`. (Os `.html` das subpastas de histórico ficam fora do escopo — escaneie só a raiz.)

Mensagem de recusa **nomeia o arquivo e a regra**, no formato que o `criar-tela.mjs` já usa. Não invente formato novo — copie o de lá.

### C2 · `scripts/cowork-paridade.mjs`

Não mantém lista: **deriva**.

- `--manifesto` → lê `oimpresso.com.html`, extrai todo `href`/`src`/`data-src` local (tirando o `?v=`), imprime o conjunto.
- (sem flag) → cruza manifesto × arquivos em disco e imprime as duas diferenças: **ref sem arquivo** (404 na cara do usuário) e **arquivo órfão** (peso morto no repo).
- `--check` → o mesmo, mas `exit 1` se qualquer uma das listas não estiver vazia. É esse que entra no CI.

Rotas: ler `app.jsx` e listar as que o host não consegue montar. Se der trabalho demais nesta leva, deixe fora e me diga — o valor principal é o cruzamento de arquivos.

Registrar os dois em `package.json`:

```json
"cowork:guard": "node scripts/cowork-ssot-guard.mjs",
"cowork:paridade": "node scripts/cowork-paridade.mjs",
"cowork:check": "npm run cowork:guard && node scripts/cowork-paridade.mjs --check"
```

**Ordem importa:** o R1 do guard vai reclamar do `README.md` da Entrega B. A exceção pro `README.md` (e só pra ele) tem que nascer junto com a regra, senão o primeiro CI já quebra.

## 6. DoD

- [ ] `prototipo-ui/cowork/` reposto; `npx serve` + `oimpresso.com.html` abre sem 404 no console
- [ ] `README.md` criado
- [ ] `npm run cowork:check` passa em verde no estado recém-exportado
- [ ] `cowork:check` plugado no CI
- [ ] Se algo da seção 5 não couber nesta leva: **não silencie** — devolva o que ficou de fora e por quê

## 7. Errata pendente pro [W]

Independente desta leva: o `CLAUDE.md` do Cowork descreve `cowork-ssot-guard.mjs` e `cowork-paridade.mjs` como se estivessem rodando. Ou a Entrega C torna aquilo verdade, ou o texto vira futuro do pretérito. Decisão do [W] — só não deixar a doc afirmando máquina que não existe.
