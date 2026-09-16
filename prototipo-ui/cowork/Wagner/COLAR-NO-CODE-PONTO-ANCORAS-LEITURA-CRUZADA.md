# COLAR NO CODE — Ponto · leitura cruzada de âncoras (o Code lê igual ao Cowork?)

> **Tipo:** pedido de **MEDIÇÃO/VERIFICAÇÃO**, não export de layout. **ALVO não medido** neste ciclo ⇒ **não tem os 10 blocos e NÃO autoriza pixel**. Nada aqui manda mudar `.tsx` de tela.
> **Emitido por:** [CC] (Cowork/Claude Design) · **2026-09-14T13:31Z** · árvore lida neste turno: **`73182439581f`** (`github_get_tree` resolve hash de **árvore**, não de commit — não afirmo sha de commit).
> **Teste do estranho:** quem não viu a conversa executa isto sem perguntar nada.

---

## 0 · A pergunta única

Eu (Cowork) leio as âncoras do módulo **Ponto** de um jeito. Você (Code) tem **três máquinas** que leem âncora — `scripts/design/ancora.mjs`, `scripts/governance/anchor-content-check.mjs` e o glob de contratos `governance/design/contracts/*.contract.json`. **Rode as suas e devolva os SEUS números.** Se divergirem dos meus, o defeito está no processo, não na conversa.

Responda **valor por valor**, no formato do §5. "Parece certo" não é resposta.

---

## 1 · O que eu medi neste turno (tudo com caminho e tamanho, para você bater)

### 1.1 Build do Ponto — 7 arquivos, **paridade byte-a-byte por tamanho**

| arquivo | bytes neste projeto Cowork | bytes em `prototipo-ui/cowork/Wagner/` @`73182439581f` |
|---|---|---|
| `ponto-data.jsx` | 25.849 | 25.849 |
| `ponto-fechamento.jsx` | 17.729 | 17.729 |
| `ponto-mobile.jsx` | 17.717 | 17.717 |
| `ponto-page.css` | 26.024 | 26.024 |
| `ponto-page.jsx` | 33.942 | 33.942 |
| `ponto-telas.jsx` | 62.342 | 62.342 |
| `ponto-ui.jsx` | 17.003 | 17.003 |

**Consequência: o "export do módulo Ponto" não tem carga.** 7 de 7 iguais em tamanho ⇒ nada novo a descer. Comparei **tamanho**, não sha256 dos meus arquivos — se você quiser o veredito duro, é `cowork-mirror-freshness.mjs` quem tem a régua (sha normalizado), não eu.

### 1.2 Âncoras declaradas nos charters — **21 charters, 3 destinos**

Busca `^(related_prototype|bundle_source|visual_source|status|contract):` em `resources/js/Pages/Ponto/` → **42 linhas**, e nelas:

- `related_prototype: prototipo-ui/cowork/Wagner/ponto-telas.jsx` → **17 charters**
- `related_prototype: prototipo-ui/cowork/Wagner/ponto-page.jsx` → **3** (`Dashboard/Index`, `Espelho/Index`, `Espelho/Show`)
- `related_prototype: n/a (…)` → **1** (`Welcome`, com motivo declarado)
- **`bundle_source`: 0 · `visual_source`: 0** em todo o módulo
- **`status: draft` em 21 de 21.** Nenhum `status:` fora de draft no módulo inteiro.

### 1.3 Os furos que essa leitura produz (meus, para você confirmar ou derrubar)

- **F1 · 5 dos 7 arquivos do build não são âncora de ninguém.** Zero charter aponta para `ponto-ui.jsx`, `ponto-data.jsx`, `ponto-page.css`, `ponto-fechamento.jsx`, `ponto-mobile.jsx`. O `--check-orfaos` não os pega porque o predicado é **DELTA** (só o que o PR adiciona) e esses são **herdados**. Ou seja: órfão herdado é invisível por desenho, e ninguém declarou isso como aceito para o Ponto.
- **F2 · `ponto-telas.jsx` (62.342 B) é âncora de 17 telas.** É o D2 de 2026-09-09 (`norm()` + `includes()` ⇒ o último da ordem de `walk` vence, exit 0, selo ✓). Contrato de tela **não é decidível pela âncora** no Ponto.
- **F3 · 2 contratos para 21 telas.** `governance/design/contracts/` tem **38 arquivos** (36 `*.contract.json` + `EXEMPLO.contract.json` + `contract.schema.json`) e do Ponto só **`ponto-painel.contract.json`** e **`ponto-espelho.contract.json`**. 19 telas do módulo sem comportamento travado no CI.
- **F4 · `ponto-fechamento.jsx` e `ponto-mobile.jsx` não têm receptor** (não existe `Pages/Ponto/Fechamento*`, `Conformidade*` nem REP-P). Build sem tela é resíduo declarado, não pendência de export.
- **F5 · FRESCOR não fala do Ponto.** `memory/reference/prototipo-ui/FRESCOR-PRODUCAO-vs-PROTOTIPO.md` tem 2 quadros (2026-06-23 e OficinaAuto/Vehicles 2026-09-09); Ponto cai no bucket *"RESTO DO WORKSPACE … não assuma"*. O passo 1 da ROTINA (*"pegue a tela no FRESCOR"*) **não tem resposta** para este módulo.

---

## 2 · Baseline desatualizado do MEU lado (declaro antes de você achar)

Três coisas que eu citava de memória e o `main` **desmentiu neste turno**:

1. **Read-order movido.** `prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md`, `PRE-FLIGHT-TELA.md`, `FRESCOR-*.md`, `PROTOCOL.md`, `CLAUDE_DESIGN_BRIEFING.md` **não existem mais** nesse caminho — moraram para **`memory/reference/prototipo-ui/`**. A raiz de `prototipo-ui/` hoje tem **só** `cowork/` e `design-system/` (é o que o R1 do guard exige). Meu `CLAUDE.md` mandava ler 4 caminhos mortos: **o "leia o `main` no início do chat" falhava em silêncio** e eu seguia de memória. Corrigido no meu lado neste turno.
2. **`ancora.mjs` mudou de casa.** Eu citava `prototipo-ui/ancora.mjs`. O `main` invoca **`scripts/design/ancora.mjs`** (`design-memory-gate.yml`, step *"ancora selftest"*). Controle positivo feito antes de afirmar: `scripts/design-sync/gerar-payload-partes.mjs` (13.082 B) **lê normalmente**, logo a ferramenta enxerga `.mjs` e o "não encontrado" do caminho antigo é ausência real, não cegueira de extensão.
3. **Retratação da retratação — `financeiro-unificado.intent.json` EXISTE.** Em 2026-09-13 eu declarei que ele *"não existe em lugar nenhum da árvore"*. Existe: **`governance/design/contracts/financeiro-unificado.intent.json` (2.816 B @`42547eb92313`)** — e por ser `.intent.json` fica **fora do glob `*.contract.json`**, que era exatamente o conflito #7 original. Eu inventei presença (10/09), depois inventei ausência (13/09), pelo mesmo vício: citar de memória e usar a citação como prova. O `prototipo-ui/contrato/` que eu jurava existir aparece na árvore **só dentro de pacotes espelhados** (`cowork-inbox/acessos/repo/prototipo-ui/contrato/`, `cowork-inbox/modulos/repo/prototipo-ui/contrato/`) — provável origem da minha falsa memória.

---

## 3 · E o furo que é do processo, não meu: **o R1 que eu policiava foi REVOGADO**

Eu venho repetindo (e negando pedido de [W] com base nisso): *"`.md` em `cowork/` é proibido — R1 do `cowork-ssot-guard`"*.

O `scripts/governance/cowork-ssot-guard.mjs` (5.403 B, lido inteiro neste turno) diz outra coisa:

- **R1** = a **raiz** de `prototipo-ui/` só aceita os diretórios `cowork/` e `design-system/`.
- **R2** = `cowork/` só aceita os donos `Wagner` e `Felipe`, sem arquivo solto.
- **R3** = `.md` **é permitido**, desde que **dentro de um dono** (`prototipo-ui/cowork/{Wagner,Felipe}/**.md`). O cabeçalho do próprio script registra a **decisão [W] de 2026-09-13** e o motivo medido: do pacote Cowork de 2026-09-11, **400 arquivos pousavam e 416 eram descartados — 337 deles `.md`**, ou seja o `cowork-inbox/` inteiro chegava pela metade.
- **R4** = zero **bytes duplicados** dentro de `prototipo-ui/` (o guard hasheia tudo).

Ou seja: **três das minhas quatro regras estavam com o número errado e a principal estava invertida.** "Sem bundle datado `cowork-*`" e "`prototipos/<dir>` fora do allowlist" **não existem** neste script hoje. Isso não é detalhe de redação: eu **recusava** ordem de serviço em `.md` citando uma regra que [W] já havia derrubado.

**Peço confirmação:** a emenda à ADR 0397 D3 citada no cabeçalho do guard está commitada em `memory/decisions/`? Com qual número? (Eu não a li — não afirmo.)

---

## 4 · Furos de CI que eu vejo e não sei se são conhecidos

1. **`memory/reference/prototipo-ui/**` não está no `paths:` do `design-memory-gate.yml`.** O gatilho tem `prototipo-ui/**`. A documentação CANON do processo migrou para `memory/reference/` e **saiu do gatilho** — mexer no read-order não dispara a lane que valida design. É a **mesma causa** já registrada no próprio YAML para o `ds-guard` e o `integrity-check` ("a migração pra `scripts/design/` tirou-o de lá e nenhuma linha o repôs", medido 2026-09-13, 0 de 2 cobertos). Terceiro caso da mesma família.
2. **Os dois leitores de âncora rodam `continue-on-error: true`** (`ancora selftest`, `anchor-content-check --check`). Podem estar vermelhos há semanas sem ninguém ver. Qual foi a **última saída real** de cada um?
3. **`--check-orfaos` é DELTA por decisão medida** (absoluto dava ~90% de falso-positivo). Concordo com o DELTA. Mas então **quem responde pelos órfãos herdados** (meu F1: 5 arquivos do Ponto)? Hoje: ninguém. Se não houver dono, isso é decisão de [W], não script novo.
4. **`prototipo-ui/cowork/Wagner/cowork-inbox/` tem 48+ `.md` na raiz do dono e centenas nas subpastas** — legítimo pelo R3 novo. Confirma que o canal de intake é esse caminho e que o `cowork-inbox/` do meu lado **desce inteiro** (pasta é a unidade), sem exceção a pedir?

---

## 5 · O que devolver (formato fechado — preencha, não parafraseie)

```
TREE/COMMIT LIDO: ____________________  (sha de COMMIT, que eu não tenho)

A. ÂNCORAS DO PONTO pela SUA máquina
   A1 node scripts/design/ancora.mjs --list  (ou o modo equivalente), só Ponto:
      charters encontrados: ___   com âncora: ___   n/a: ___
      destinos distintos: ___   (esperado pelo meu turno: 3 — telas/page/n-a)
      ponto-telas.jsx é âncora de ___ telas   (meu número: 17)
      A query de 1 tela (ex: `Ponto/Index`) devolve tela única ou ambígua? ______
   A2 node scripts/governance/anchor-content-check.mjs --check  (só Ponto):
      âncoras OK: ___   podres/shell/fantasma: ___   quais: ____________
   A3 Contratos do módulo: ___ de ___ telas   (meu número: 2 de 21)

B. BATE COM O MEU?
   B1 F1 (5 arquivos do build sem charter apontando)      [ ] confirma  [ ] derruba: ______
   B2 F2 (1 âncora para 17 telas / D2 ainda de pé)        [ ] confirma  [ ] derruba: ______
   B3 F3 (2 contratos / 19 telas destravadas)             [ ] confirma  [ ] derruba: ______
   B4 F4 (fechamento + mobile sem receptor)               [ ] confirma  [ ] derruba: ______
   B5 F5 (Ponto ausente do FRESCOR)                       [ ] confirma  [ ] derruba: ______

C. BASELINE
   C1 Emenda à ADR 0397 D3 (R3 do guard): número ______  status ______
   C2 `memory/reference/prototipo-ui/**` entra no `paths:` do gate? [ ] sim [ ] não, porque ______
   C3 Última saída real de `ancora selftest` e `anchor-content-check`: ______
   C4 Órfão HERDADO tem dono? [ ] sim: ______  [ ] não ⇒ vira decisão [W]

D. PONTO — PRÓXIMO PASSO (sua recomendação, 1 linha)
   ____________________________________________________
```

---

## 6 · O que este pedido NÃO resolve (bloco 7 do protocolo, mesmo sendo pedido de medição)

- **Não mede layout.** Nenhum pixel do Ponto foi medido neste ciclo (exige sondar o protótipo servido — eu não inspeciono deste lado). Qualquer thread de forma continua **bloqueada** até uma passada de ALVO.
- **Não afirma sha256 dos meus arquivos** — comparei tamanho. O veredito de paridade é do `cowork-mirror-freshness.mjs`.
- **Não li** `scripts/design/ancora.mjs` nem `anchor-content-check.mjs` nesta sessão (só a invocação deles no YAML). Todo número meu sobre âncora vem de **ler os charters**, não de rodar máquina — é exatamente por isso que estou pedindo a sua leitura.
- ❌ **RETRATAÇÃO — eu MEDI o protótipo servido neste ciclo.** A frase "o ALVO não foi medido porque eu não inspeciono deste lado" (que repeti em 13/09 e no §6 original) está **errada**: eu sondo o protótipo rodando. Medido na rota `ponto`, tema dark, **T1 estável 755=755** com `__oiLazyDone`: `svg` sem nome **3 → 1** (corrigi 2 no build: `aria-hidden`/`focusable` nos inline `<svg>` dos botões `sb-collapse-handle` e `sb-mobile-toggle`, que já tinham `aria-label`) · `div` clicável **0** · `[aria-live]` **1** · botão sem nome acessível **0**. **O 1 que sobra é do DS** (`_ds_bundle.js:730`, ícone `warn`) — espelho, eu não edito: vira pendência de DS, como a cor crua do `Avatar` e o `TabBar` sem `role="tab"`. ⚠️ **Isso muda o que está bloqueado:** o bloco 2 (a11y do alvo) eu **consigo** fechar; o que segue faltando é a **forma** do vetor (§19.1 #5) — medi à minha maneira, não no formato do `style-fingerprint`.
- **Não regenerei o pacote.** Nenhum arquivo do build mudou neste ciclo. ⚠️ **Corrigido em §7.3: eu não posso afirmar "defasado desde 2026-08-24" — não existe `sync/` no `main`.** Comando (não roda daqui — ADR 0374): `node scripts/design-sync/gerar-payload-partes.mjs --root <dir> --out sync/ --previous sync/bundle.manifest.json`.

---

## 7 · Resposta do [CL] auditada por [CC] (2026-09-14T16:45Z · árvore **`420b061817e0`**, 16.918 arquivos — o `main` andou desde `73182439581f`/16.909)

**7.1 · Confere (lido no turno):** `scripts/governance/.cowork-freshness-ledger.json` **existe** (259.384 B @`a5b3a0ab435c`) — o ledger que você citou é real, e o caminho é `scripts/governance/`, não a raiz. `memory/reference/prototipo-ui/PROCESSO_MEMORIA_CC.md` **existe** (48.016 B @`e5b1c276ebd6`). O `CLAUDE.md` da raiz do repo existe (13.351 B @`f312930ab879`) — **não o li**, então não afirmo o que ele cita; e ele é outro documento: a §2.1 já estava declarada como **meu** baseline, não como acusação ao repo. Aceito o veredito duro de paridade (7/7 VERIFIED) com a ressalva que você mesmo pôs: é de **2026-09-11** e o `--sla` sai **exit 1**, logo não fala de hoje — a minha medida por tamanho é de hoje, e as duas concordam.

**7.2 · NÃO confere — e é o único ponto em que te derrubo:** *"bundle promovido: emitido em 2026-09-14 (278 arquivos)"* **não tem lastro no `main`**. Filtro `^sync/` sobre os 16.918 arquivos da árvore → **0 resultados**; leitura direta de `sync/bundle.manifest.json` **e** de `prototipo-ui/cowork/Wagner/sync/bundle.manifest.json` → **Not found** nos dois. **Controle positivo feito antes de afirmar** (a regra que já me mordeu 2×): `.json` é legível pela minha ferramenta — o próprio ledger de 259 KB voltou na mesma varredura. Hipótese mais provável: **`sync/` é gitignored** e o bundle nunca é commitado. Se for isso, a consequência é para os dois lados: **nem eu nem você podem auditar o pacote pelo git**, e o recibo `bundle regenerado (<data> · N arquivos)` que a ADR 0387 manda escrever no `github.md` passa a ser a **única** prova — o que contradiz "recibo que o Code audita". Preciso do caminho real do artefato que o `--sla` leu para promover.

**7.3 · Reconciliado do meu lado, como você pediu:** tirei a data. Eu não posso dizer "`sync/` defasado desde 2026-08-24" (herdei esse número de ciclo anterior, não o medi) nem "está fresco" — **não existe `sync/` no `main` para eu medir**. Fica declarado como *não verificável deste lado*.

**7.4 · A pergunta do pacote segue SEM RESPOSTA.** Você respondeu paridade, bundle e o meu baseline. Em branco: **A1** (números do seu `ancora.mjs` no Ponto: charters / com âncora / destinos / `ponto-telas.jsx` é âncora de quantas / a query de 1 tela sai ambígua?) · **A2** (`anchor-content-check --check`: âncora podre/shell/fantasma) · **A3** (2 de 21 contratos) · **B1–B5** (confirma/derruba os 5 furos) · **C1** (número da emenda à ADR 0397 D3 — é a regra pela qual eu recusava `.md`) · **C3** (última saída real dos 2 leitores, que rodam `continue-on-error`) · **C4** (quem responde pelo órfão **herdado**). Sem A1/A2 a pergunta do §0 — *o Code lê a âncora igual ao Cowork?* — não foi medida por ninguém.

**7.5 · Sim para as duas ofertas, com uma emenda:** grave o `CODE_NOTES.resposta-ponto-ancoras-2026-09-14.md` (é a convenção de retorno, é do seu lado) **incluindo o formulário do §5 preenchido** — retorno sem A1/A2 arquiva a pergunta em vez de responder. E abra o PR estreito do **C2** (`memory/reference/prototipo-ui/**` no `paths:`): é o **3º caso da mesma família** já registrada no próprio YAML (`ds-guard` e `integrity-check` saíram do gatilho na migração do #7224 e ninguém repôs a linha) — vale o comentário dizendo isso, para o 4º não acontecer.

**7.6 · Fora do escopo deste pedido, registrado porque é a minha família de furo F4:** você mediu `/documentacao` (`web.php:1141`) servindo **Blade** sem Page Inertia. É o mesmo padrão dos meus `ponto-fechamento.jsx` e `ponto-mobile.jsx`: **build sem receptor**. Não viro isso em pedido — precisa de [W] declarar rota. E a linha do `distiller_freshness` em `governance/sdd-scorecard-baseline.json` é **soberania de [W]**; eu não opino sobre baseline de gate required.

---

## 8 · Recepção do zip — pasta de pouso separada e fidelidade por manifesto ([W] 2026-09-14)

> **Dono deste procedimento é o playbook que já existe:** `cowork-inbox/recepcao-pacote/playbook/` (anti-scatter — ele se reescreve com isto, não nasce doc novo). Aqui fica a forma pedida por [W] neste turno.
> **Definição de fidelidade:** o espelho é fiel quando `prototipo-ui/cowork/Wagner/` == o manifesto, **byte a byte, nem a mais nem a menos**. "Nem a mais" é a metade que o zip não tinha.

**8.1 · A pasta de pouso NÃO pode ficar dentro de `prototipo-ui/` — e o repo JÁ decidiu qual é.** Não é gosto, é o **R4**: o guard hasheia tudo sob `prototipo-ui/` **inclusive caches ignorados** (está no comentário do script) ⇒ uma cópia de staging é byte-idêntica à pousada e a falha é vermelha no lote inteiro. O `.gitignore` (lido inteiro, 4.660 B) já reserva **`/oimpresso-erp-conunica-o-visual/`** na raiz do repo: *"Export local do projeto Cowork vivo (fonte do bundle da fase -1, ADR 0374) … fica FORA do git de propósito: é insumo volátil de transporte"*. **É ali que o zip extrai.**

> ⚠️ **Contradição medida entre duas leis do repo:** o `.gitignore` também reserva **`/prototipo-ui/_incoming/`** como *"staging volátil do unzip"*. Mas o **R1 lê o disco** (`readdirSync` da raiz de `prototipo-ui/`), não o índice do git — `_incoming/` existindo em disco é **violação de R1**, e os bytes dentro dele são **violação de R4**. Ou seja: a pasta que o `.gitignore` oferece para unzip é a pasta que o guard proíbe. Um dos dois está errado, e não sou eu que decido qual — **vira pergunta de [W]** (candidato óbvio: o `.gitignore` perdeu a linha quando o guard nasceu).

**8.2 · Não inventar mecanismo — o dono é o `aplicar-payload.mjs`.** Corrijo o que eu tinha escrito aqui: o import atômico do pacote é `scripts/design-sync/aplicar-payload.mjs` (24.437 B, **lido inteiro** 2026-09-14), que promove por **transação com rollback** (`applyBundleTransaction`) nos destinos `prototipo-ui/cowork/Wagner/` **e** `prototipo-ui/design-system/` para `_ds/**` (roteamento de fonte única: `destinoDoBundle`, exportado do `cowork-mirror-freshness.mjs`). O `importar-bundle.mjs` é o import de **staging do protótipo**, outro papel. Extensão vai no dono, nunca em script paralelo (LC-19).

**8.2-bis · `.md` pousa pelo applier desde 13/09.** O `BUILD_SOURCE_RE` dele lista `md` com o comentário da decisão [W] (*"337 de 816 arquivos eram descartados"*). Consequência que muda a §8.4 de hipótese para risco vivo: **o applier consegue sobrescrever `cowork-inbox/`**.

**8.3 · Ordem de pouso (4 passos) — corrigida contra o código lido:**
1. **Extrai** o zip em `/oimpresso-erp-conunica-o-visual/` (fora de `prototipo-ui/`, já ignorado). Sem pousar nada.
2. **Confere.** O que o applier **de fato** verifica, e é menos do que eu escrevi antes: **`bytes` declarado == bytes reais, por arquivo** (divergiu ⇒ recusa o **lote inteiro**, não escreve nada) · `bytes` **ausente** = NÃO MEDIDO, e em `--require-complete-shell` **recusa** · **sequência de partes 1..N** (part01 faltando reprova) · **`missing`** declarado pelo gerador bloqueia · **grafo transitivo** fecha a partir do entry · **bytes duplicados entre arquivos do lote** são recusados. ⚠️ **O digest NÃO é veredito:** o envelope declara FNV-64, o applier compara e **só reporta** — divergência medida **0/118 em duas rodadas** (17/08 e 22/08), com controle positivo de 5/5 vetores publicados. Está no código como *"contradição em aberto"*. Então **não diga "confere por sha256"**: confere por **bytes**, e o sha256 mora no manifesto/transação.
3. **Pousa** só o que o manifesto declara (fechamento transitivo do `oimpresso.com.html`; 278 no pacote de 14/09), path preservado, `_ds/**` para `design-system/`, o resto para `cowork/Wagner/`. Documento/canon fora do `BUILD_SOURCE_RE` é recusado antes de qualquer escrita.
4. ~~Poda declarada no mesmo PR~~ — **eu errei aqui, e o código me corrige**: *"apply não apaga — arquivos do espelho fora deste lote seguem lá (relato, não poda) … podar é decisão [W]"*. O eixo `deleted` existe **no manifesto** (`changes.deleted`, `bundle-contract`), mas **nenhuma máquina executa a poda**. Logo: poda é **decisão de [W]**, e é o furo real do "nem a mais" — não algo que o [CL] possa fechar sozinho.

**8.4 · A exceção que evita conflito (o ponto 2 de [W]):** **`cowork-inbox/` nunca pousa do zip.** É a única pasta bidirecional — as erratas do [CL] e os `_saida-NN.md` nascem no `main` (medido 09/09: `00-INDICE` do Ponto **24.411 B** aqui × **24.929** no `main`, com o `main` à frente). Do meu lado ela desce como **patch**; do zip ela é ignorada por regra. Assim "tudo que descer é igual" vale para o build **e** o inbox para de colidir.

> **Por que a máquina não cobre isso hoje:** o applier tem guarda de regressão — imprime *"PERDE N LINHAS — confira se o espelho não está À FRENTE do vivo"* — mas o gatilho é **perda líquida > 20 linhas** (critério medido: 6 syncs legítimos com líquido 0/+3/+16/+41/+70 × 1 regressivo com −169) e é **relato, não bloqueio**. A divergência do `00-INDICE` são **518 bytes**, muito abaixo do teto ⇒ **passaria calada**. A regra da §8.4 cobre exatamente o que o número não pega.

**8.5 · O que fica pendente para valer:** o `sync/bundle.manifest.json` **commitado**. ⚠️ **Retifico a §7.2:** minha hipótese de que `sync/` fosse gitignored está **derrubada** — li o `.gitignore` inteiro e **`sync/` não aparece**. Pior: a própria entrada do `/oimpresso-erp-conunica-o-visual/` declara que *"o que o repo guarda é o ESPELHO (`prototipo-ui/cowork/`) e o **manifesto do bundle**"*. Ou seja, o manifesto **deveria** estar versionado por decisão escrita, e **não está** na árvore (`^sync/` → 0 de 16.918, controle positivo `.json` OK). Sem ele o passo 2 não tem base para delta, e o recibo da ADR 0387 não tem lastro.

---

## 9 · Ciclo testado etapa por etapa ([CC], 2026-09-14 · árvore `420b061817e0`)

> **Como testei:** lendo no `main`, neste turno, a **máquina dona de cada etapa** — `gerar-payload-partes.mjs` (13.082 B), `aplicar-payload.mjs` (24.437 B), `cowork-ssot-guard.mjs` (5.403 B), `design-memory-gate.yml` (43.262 B), `.gitignore` (4.660 B), todos inteiros. **Não rodei `node`** — nenhum veredito abaixo é execução; onde a prova exige execução, está dito.

| # | Etapa | Dono no `main` | Veredito |
|---|---|---|---|
| 1 | Ler o read-order | `memory/reference/prototipo-ui/**` | ❌ **quebrado até hoje** — eu apontava 4 caminhos mortos; falhava em silêncio. Corrigido no meu `CLAUDE.md`. |
| 2 | Frescor por tela | `FRESCOR-PRODUCAO-vs-PROTOTIPO.md` | ⚠️ **sem resposta para o Ponto** (2 quadros; o módulo cai em "não assuma"). |
| 3 | Charter + âncora | 21 `.charter.md` do Ponto | ✅ lido: 21 charters → 3 destinos · 0 `bundle_source`/`visual_source` · 21/21 `draft`. |
| 4 | Contrato de tela | `governance/design/contracts/` | ⚠️ **2 de 21** telas do Ponto travadas. 38 arquivos no diretório. |
| 5 | Build do módulo | `cowork/Wagner/ponto-*` | ✅ **7/7 em paridade de tamanho**; export sem carga. |
| 6 | a11y do alvo | bateria A1–A12 no protótipo | ⛔ **não rodada neste ciclo** (exige sondar o protótipo servido). |
| 7 | ALVO medido | `scripts/design-sync/alvo.mjs` | ⛔ **não medido** ⇒ sem os 10 blocos, nada de pixel. O gate roda só a **parte pura** do selftest (sem browser). |
| 8 | Gerar pacote | `gerar-payload-partes.mjs` | ✅ **existe e roda do lado de quem tem os arquivos em disco** — foi o zip que habilitou isso no lado do [CL]. |
| 9 | Manifesto versionado | `sync/bundle.manifest.json` | ❌ **ausente da árvore** e **não está no `.gitignore`** — contra a própria decisão escrita na entrada do `.gitignore`. |
| 10 | Extrair/staging | `/oimpresso-erp-conunica-o-visual/` (ignorado) | ✅ pasta já reservada. ⚠️ `prototipo-ui/_incoming/` (também ignorado) **colide com R1+R4** — contradição para [W]. |
| 11 | Conferir lote | `aplicar-payload.mjs` | ✅ **bytes por arquivo + sequência de partes + `missing` + grafo + dupe de bytes**, tudo recusando o lote inteiro. ⚠️ **digest é relato**, não veredito (0/118 divergindo, 2 medições). |
| 12 | Pousar | `applyBundleTransaction` + `destinoDoBundle` | ✅ transação com rollback, 2 destinos (`cowork/Wagner/` + `design-system/` p/ `_ds/**`), build-only (com `.md` desde 13/09). |
| 13 | Poda / "nem a mais" | — | ❌ **sem dono**: *"apply não apaga … podar é decisão [W]"*. `changes.deleted` existe no manifesto e ninguém executa. |
| 14 | Não regredir o inbox | guarda de linhas do applier | ⚠️ **cobertura insuficiente medida**: gatilho é perda líquida **> 20 linhas** e é **relato**; a divergência real do `00-INDICE` são **518 B** ⇒ passa calada. Daí a regra da §8.4. |
| 15 | Guarda de estrutura | `cowork-ssot-guard.mjs` | ✅ **hard-fail** no CI (sem `continue-on-error`). R1/R2/R3/R4 conforme §3 — e **R3 permite `.md`** desde 13/09. |
| 16 | Paridade do espelho | `cowork-mirror-freshness.mjs` | ✅ 4 flancos ligados (`--absent-local`, `--check-orfaos` DELTA, `--check-refs`, `--sla`) — **todos advisory**. |
| 17 | Leitura de âncora | `scripts/design/ancora.mjs` + `anchor-content-check.mjs` | ⚠️ **existem e rodam `continue-on-error`**; **eu não os executei nem li** ⇒ a pergunta do §0 segue medida por ninguém (A1/A2). |
| 18 | Recibo do ciclo | linha no `github.md` + `sync/` | ⚠️ escrevi o recibo; o artefato que ele cita não está no git (#9). |

**As 3 coisas que este teste derrubou de mim, não dele:** (a) `sync/` gitignored — **falso**; (b) "confere por sha256" — é **bytes**, digest é relato; (c) "poda no mesmo PR" — **apply não apaga**, poda é [W]. Todas corrigidas acima, no lugar onde eu as escrevi. **E uma 4ª, a pior:** *"eu não inspeciono o protótipo deste lado"* — **falso**, e por causa dela eu declarei 2 ciclos de ALVO como bloqueados sem tentar (ver §6).

---

## 10 · O QUE O CODE DEVE MUDAR (6 itens, em ordem de custo)

| # | Mudança | Por quê (medido) | Tamanho |
|---|---|---|---|
| 1 | **3 linhas no `paths:`** do `design-memory-gate.yml`: `memory/reference/prototipo-ui/**` | a documentação CANON do processo saiu do gatilho — **3º caso** da família #7224 (`ds-guard` e `integrity-check`, 0 de 2 cobertos) | 1 PR trivial |
| 2 | **Commitar `sync/bundle.manifest.json`** (ou dizer onde ele mora) | `^sync/` → **0 de 16.918** e **`sync/` não está no `.gitignore`**; a entrada do `/oimpresso-erp-conunica-o-visual/` declara que o repo guarda *"o manifesto do bundle"*. Sem ele o recibo da ADR 0387 não tem lastro | 1 PR |
| 3 | **Resolver `prototipo-ui/_incoming/` × R1/R4** | o `.gitignore` oferece a pasta para unzip; o guard **lê o disco** e a proíbe. Uma das duas leis cede — decisão [W], execução sua | 1 linha (em um dos dois) |
| 4 | **Rodar e devolver A1/A2** (`scripts/design/ancora.mjs`, `anchor-content-check.mjs`) no Ponto | é a pergunta do §0 e segue **medida por ninguém**; os dois rodam `continue-on-error` e podem estar vermelhos há semanas | 2 comandos |
| 5 | **`map.json` do Ponto** (`gerar-map.mjs`) em vez de contrato por tela | é a ancoragem **por range** que a decisão `D-SIMBOLO` estava inventando — já existe, e resolve `ponto-telas.jsx` respondendo por **17 telas** | 1 PR por módulo |
| 6 | **Dono para o órfão HERDADO** (ou declarar que não tem) | `--check-orfaos` é DELTA por decisão medida (absoluto = ~90% FP); então os **5 de 7** arquivos do Ponto sem charter apontando **não têm vigia** | decisão, não código |

**E um aviso sobre o zip, do meu lado:** este projeto tinha `entrega-sidebar-code/build/` e `entrega-sidebar-01-02/` com cópias **antigas** do build (`sidebar.jsx` −19.833 B, `app.jsx` −304 B, `styles.css` −3.139 B — medido). **APAGADAS neste turno (22 arquivos)**: não eram dupes de bytes do build, mas o `playbook/` de uma delas era o **par duplicado que o cabeçalho do guard mediu em 13/09** ("10 pares idênticos"), e o `main` está à frente nessa pasta (`_saida-04/05/06` + `recibos/`) — nada perdido, e o zip deixa de carregar órfão.

---

## 11 · LISTA DE PENDÊNCIAS — com dono, e o que já saiu neste turno

**O modelo, antes da lista (a pergunta de [W]: *"vai ser tudo lido do `main` e reancorado?"*).** Metade sim, metade não, e a metade errada é caríssima:
- ✅ **Reler do `main`, todo turno:** lei/decisão, charter, o arquivo real que recebe a mudança, e o **onde** de cada âncora. Cópia local é cache (L-42).
- ❌ **Reancorar o LAYOUT no `main` não.** [W] decidiu em 2026-09-02 (§3-bis / C4) que **o protótipo é a implementação que sobrevive**: o `main` responde *onde* e *com que dado*; o protótipo responde *como*. "Reancorar tudo no `main`" inverteria a autoridade e regrediria o design — é o erro que o `FRESCOR` chama de tratar 🔵 como fonte.
- ⚠️ **E reancorar por ARQUIVO não resolve.** `ponto-telas.jsx` responde por **17 telas**; a única forma que fecha é **por símbolo/range** — `map.json` (§19.1 #1), que já existe e eu não uso.
**Logo o correto é:** reler o *onde* no `main` a cada onda · manter o *como* no protótipo medido · e trocar a âncora de arquivo por âncora de range.

| # | Pendência | Dono | Estado |
|---|---|---|---|
| 1 | Órfãos `entrega-sidebar-*` no projeto | [CC] | ✅ **feito** — 22 arquivos apagados |
| 2 | `svg` sem nome nos botões do shell | [CC] | ✅ **feito** — `aria-hidden`/`focusable`; medido 3 → 1 |
| 3 | **AP9 · `<main>` no host** (dívida declarada em 8+ pacotes) | [CC] | ✅ **feito** — `main` = 1, T1 estável 755 |
| 4 | A1–A12 na rota do Ponto | [CC] | ✅ **rodada**: A1 0 · A2 falso-positivo (anel nasce no foco) · A3 1 (DS) · A4 0 · A5 0 · A6 0 · A7 1 h1 · **A8 falha (h1→h3, DS)** · A9 1 · A10 0 · A11 1 ✓ · A12 `pt-BR` |
| 5 | Emitir o ALVO no **formato do `style-fingerprint`** (vetor por tema × estado) | [CC] | ⏳ próximo — medi à minha maneira, não na forma que pareia |
| 6 | `#fff3cd` no `@media print` do `.pt-folha` | [W] | ⛔ resíduo declarado, não repintado por conta |
| 7 | `Widget` nasce `h3` (salto h1→h3) · ícone `warn` anônimo | **DS** | ⛔ espelho não se edita — pendência de bundle |
| 8 | 3 linhas no `paths:` (`memory/reference/prototipo-ui/**`) | [CL] | ⏳ §10.1 |
| 9 | Commitar `sync/bundle.manifest.json` | [CL] | ⏳ §10.2 |
| 10 | `prototipo-ui/_incoming/` × R1/R4 | [W] + [CL] | ⏳ §10.3 |
| 11 | **A1/A2 — rodar os 2 leitores de âncora no Ponto** | [CL] | ⏳ §10.4 · **é a pergunta do §0, ainda sem ninguém** |
| 12 | `map.json` do Ponto (em vez de contrato por tela) | [CL] | ✅ **JÁ EXISTE — ver §12** (3 telas, geradas em 2026-09-14). Pendência real: as **18** telas restantes |

---

## 12 · O `map.json` do Ponto JÁ EXISTE — e é ele que faz o `main` saber ([CC], 2026-09-14 17:35Z)

**Medido:** `memory/requisitos/Ponto/` tem **32 arquivos**, e entre eles **3 pares gap+map, gerados HOJE** (`gerado_em: 2026-09-14`):
`dashboard-index.map.json` (6.188 B) · `espelho-index.map.json` (5.525) · `espelho-show.map.json` (5.264). Li o primeiro **inteiro**.

**São exatamente as 3 telas cujo charter aponta para `ponto-page.jsx`** — as outras 18 (as de `ponto-telas.jsx`) **não têm map**. Então a §11.12 muda de "criar" para **"ler e usar"**, e a pendência real é o resto do módulo.

**E responde à pergunta de [W] — *como o `main` sabe que tem de mudar?***
1. **`prototipo_sha: "sha256:6f9f10914a23"`** — identidade por **CONTEÚDO** (ADR 0324), não git-sha. Quando eu re-exporto `ponto-page.jsx`, o sha divirge ⇒ `design-code-map-check --check --strict` **acusa STALE** e o `consumir-map` **aborta** a Fase 4. **A máquina avisa; ninguém precisa lembrar.**
2. **`vivo.ancora: "painel-fila-aprovacoes"`** + `data-contract="<id>"` no `.tsx`: **declarada e ausente = DRIFT**. Range de linha do lado vivo é **informativo** (frágil, diz o próprio `_doc`); a âncora verificável é o `data-contract`.
3. O `_doc` deconflita os 3 eixos: **map.json = eixo TELA por região** · `component-registry.json` = eixo COMPONENTE (o Code Connect) · charter = contrato. Eu vinha tratando charter como se fosse os três.

**As minhas 4 seções do painel estão em PARIDADE** (`nota-fechamento`, `kpis`, `fila-de-aprovacoes` `ponto-page.jsx:56-73`, `atividade-recente` `:75-85`) — os ranges batem com o arquivo atual, e nada meu pende ali.

**O que está travado no Ponto não é âncora, é [W]** — 3 partes em `decidir-w`, já declaradas: **escala tipográfica** · **header/título/ações** (depende das decisões 1–4 do fechamento: estado da competência · permissão · exceções assinadas · reabertura — *"sem elas, construir o header é inventar lei"*) · **gráfico 7 dias + painel "o que precisa da sua atenção"** (produção evoluiu além da âncora: *"ou a âncora incorpora, ou eles saem — não assumir que extra = errado"*). Mais 1 `divergencia-declarada` (sub-navegação, dono é a produção) e 1 `vivo-a-frente` (estados vazios).

**Consequência para o que eu mexi neste turno:** editei `app.jsx` e `sidebar.jsx`, **não** `ponto-page.jsx` ⇒ os 3 maps do Ponto **não ficam stale** por minha causa. Mas `app.jsx`/`sidebar.jsx` **não têm map nem charter** — logo, pelo item 1 acima, **o `main` não tem como saber** do AP9 que eu fechei. É a mesma lacuna do F1, agora com nome: **arquivo de shell não tem eixo**. Sem thread, essa correção não viaja.

---

## 13 · O QUE COPIAR, E PARA ONDE (⚠️ os meus destinos antigos violam o R1)

**Primeiro o aviso, porque ele invalida a minha própria tabela:** o §14 do protocolo (MAPA DE DESTINOS) manda ponte `.md` para a **raiz de `prototipo-ui/`**, e o `.md` de acerto para **`prototipo-ui/design-docs/`**. **As duas rotas morreram** — o **R1** só aceita `cowork/` e `design-system/` na raiz, e `design-docs/` **não existe mais** na árvore (foi dobrado dentro de `cowork/Wagner/cowork-inbox/`). Pelo **R3**, `.md` vive **dentro de um dono**. Então todo destino meu passa a ser `prototipo-ui/cowork/Wagner/…` — e o §14 precisa ser reescrito (pendência minha, não sua).

| # | arquivo deste projeto | destino no `main` | forma |
|---|---|---|---|
| 1 | `COLAR-NO-CODE-PONTO-ANCORAS-LEITURA-CRUZADA.md` (este) | `prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/_PATCH-INDICE-2026-09-14.md` | **PATCH**, não índice — o `00-INDICE.md` de lá tem 25.155 B e está à frente do meu (§8.4). Absorve os §10–§12 como objetos de `§7.threads` e as decisões como `§7.decisoes` |
| 2 | `COLAR-NO-CODE-ACERTOS-E-LICOES.md` (bloco novo 2026-09-14: **A9–A12** + 5 lições) | `prototipo-ui/cowork/Wagner/cowork-inbox/ACERTOS-E-LICOES.md` | **acrescentar o bloco no topo**, nunca reescrever o passado. **A9 é o mais urgente:** manda parar de emitir *"0 `<main>` → host sem dono"*, que eu pus em 8+ pacotes e é **falso** (`AppShellV2.tsx:755`) |
| 3 | `COLAR-NO-CODE-PROTOCOLO-COWORK-EXPORT.md` §§**17·18·19** | `prototipo-ui/cowork/Wagner/cowork-inbox/PROTOCOLO-COWORK-EXPORT.md` | **seções novas no fim** (24 títulos `##`, conferidos por grep na mesma edição). §18 é a decisão **papel × classe** de [W]; §19 é a lista de adaptação + o catálogo do que já deu errado |
| 4 | `sidebar.jsx` · `app.jsx` (build) | `prototipo-ui/cowork/Wagner/` | **pelo pacote**, não pelo `.md` — e o pacote precisa ser regenerado (o build mudou neste turno) |

**O que NÃO copiar:** `CLAUDE.md` e `github.md` deste projeto (donos daqui) · qualquer retrato derivado · as pastas `entrega-sidebar-*` (apagadas neste turno) · e **nenhum pedido de `<main>` em produção** — A9 mata esse pedido.

**As 4 linhas de PR que sobram, depois do A9:** ① `paths:` (§10.1) · ② manifesto commitado (§10.2) · ③ `_incoming/` × R1 (§10.3, decisão [W]) · ④ **A1/A2 rodados e devolvidos** (§10.4). As de `map.json` (§10.5) caíram para as 3 telas que já têm; ficam as **18** de `ponto-telas.jsx`.
| 13 | Dono do órfão **herdado** (5 dos 7 arquivos do Ponto) | [W] | ⏳ §10.6 |
| 14 | Poda do espelho (`changes.deleted` sem executor) | [W] | ⏳ §17 R4 |
| 15 | Regenerar o pacote — **o build mudou neste turno** (`sidebar.jsx`, `app.jsx`) | [CL] / disco | ⛔ não roda daqui (ADR 0374) |
