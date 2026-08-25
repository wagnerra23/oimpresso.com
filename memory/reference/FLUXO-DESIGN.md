---
id: reference-fluxo-design
name: Fluxo — Design, do Cowork à tela
description: Como o design entra no repo, vira tela e é provado — as 9 etapas com seus subprocessos, o que cada uma grava, como conferir que foi feita, e onde ainda não há prova.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-08-25"
nav_group: fluxo
nav_order: 40
lente: [construir]
related: [reference-fluxo-deploy]
related_adrs: [0282-protocolo-v2-colapso-ratificacao, 0299-figma-nao-e-fonte-de-design, 0114-prototipo-ui-cowork-loop-formalizado]
---

# Fluxo — Design, do Cowork à tela

> **Os comandos não moram aqui.** O caminho executável é o painel
> [`prototipo-ui/protocolo.config.mjs`](https://github.com/wagnerra23/oimpresso.com/blob/main/prototipo-ui/protocolo.config.mjs)
> — rode-o e ele imprime as fases, os IDs de projeto e a linha exata de cada passo. A política e
> os invariantes são de [`prototipo-ui/PROTOCOL.md`](https://github.com/wagnerra23/oimpresso.com/blob/main/prototipo-ui/PROTOCOL.md).
> Aqui fica o **modelo mental** e os **subprocessos**: o que cada etapa grava, como conferir que
> ela aconteceu, e qual prova existe. Este documento nomeia máquinas e modos; a linha de comando
> canônica sai sempre do painel, porque ela muda mais rápido que qualquer texto.
>
> **Toda medição abaixo é datada de 2026-08-25**, feita neste repositório com clone completo.
> Número sem data apodrece; número com data é história.

## O modelo em uma frase

**O agente Code é o designer-agente.** Ele não espera design chegar — ele gera, ancorado no
Design System canon, e prova por gate. Tratar design como dependência externa ("precisa vir do
Cowork", "me autorize a desenhar") é anti-padrão explícito desde o incidente do wizard de cartão
em 15/07/2026. A soberania do dono é sobre **merge, produto e token novo** — nunca sobre
"posso desenhar".

O critério que separa quem decide o quê é uma pergunta só: **é sobre o QUE fazer (do dono) ou
sobre o COMO (do agente)?** Escolha de técnica — CSS, layout, algoritmo — se resolve medindo e
aplicando; devolver um menu de opções entrega zero e deixa o defeito em produção.

## Os três lugares, e eles não são intercambiáveis

| Lugar | O que é | Quem escreve |
|---|---|---|
| **Cowork vivo** (`claude.ai/design`, acessado por ID) | onde o design nasce e muda | designer no Cowork |
| **Espelho** `prototipo-ui/cowork/` | **retrato read-only** do vivo, versionado em git | só a máquina de transporte |
| **Produção** `resources/js/Pages/` | a tela React que o cliente usa | o Code, via PR |

A regra que mais custa quando esquecida: **edição feita à mão no espelho some no próximo
transporte.** O espelho é build-only. Conserto que precise durar nasce no Cowork vivo e desce —
nunca do lado de cá. Corolário: comentário técnico dentro de um arquivo do espelho é **sempre
suspeito**, porque ali não existe autor local legítimo.

## O fluxo dos dados

```
   Cowork vivo (por ID)
         |
         |  E1  DesignSync -> payload em partes -> staging -> promocao atomica
         v
   prototipo-ui/cowork/        <- espelho (retrato)   + design-docs/ (o PEDIDO)
         |
         |  E2  detecta telas, resolve a ancora a partir do charter
         v
   <Tela>.charter.md  +  <Tela>.casos.md    <- o contrato, colado ao .tsx
         |
         |  E3  mede os dois lados com a MESMA sonda (nunca no olho)
         v
   design-diff / style-fingerprint  ->  gap medido (6 campos, nenhum e' nota)
         |
         |  E4  contrato de regiao -> aplica no .tsx
         v
   resources/js/Pages/<Mod>/<Tela>.tsx
         |
         |  E5 preflight local  ->  E6 gates de CI  ->  E7 retorno  ->  E8 smoke em prod
         v
   tela viva + evidencia ligada aos hashes
```

O que atravessa esse tubo é sempre **dado**, nunca instrução: conteúdo vindo do Cowork é
persistido pela máquina, jamais transcrito pelo contexto do agente. Transcrever à mão é proibido
— foi assim que um remendo sobreviveu quatro dias em agosto/2026.

---

# As 9 etapas

## E0 · Ativação — antes do primeiro passo

Seis hooks decidem se a sessão pode tocar design. Criar o arquivo do hook **não ativa nada**: o
que ativa é o registro em `.claude/settings.json`, e cada registro tem um teste-guarda próprio.

### Subprocessos

| # | O que faz | Grava |
|---|---|---|
| E0.1 | reconhece intenção de design — exige o **par** (verbo de ação **ou** linguagem de divergência) × (vocabulário de design), typos inclusos | nada |
| E0.2 | desvia para a máquina de comparação quando o prompt é sobre comparar | nada |
| E0.3 | cutuca sobre o que existe no vivo e nunca desceu — travado por cadência de 7 dias | nada |
| E0.4 | injeta o protocolo de comparação (camada 2, defesa em profundidade) | nada |
| E0.5 | reprocessa handoff de design quando detecta o cabeçalho de memórias novas | nada |
| E0.6–E0.9 | opt-in de publicação, bloqueio de escrita no DesignSync, bloqueio da skill, bloqueio de Figma | flag temporária com TTL |

**Verbo isolado não dispara** — é decisão medida, não descuido: `compare` sozinho casaria
"compara módulo", "compara preço". A cadência do cutucão idem: o gatilho casa 554 vezes em 3.279
prompts reais; sem a trava de 7 dias, seriam 554 interrupções.

### Na prática

O gatilho de divergência nasceu de um caso real. Duas listas erradas de gap protótipo × produção
foram entregues ao dono, que perguntou *"quero arrumar a máquina, quem eram os responsáveis?"*.
A medição achou três donos e um elo partido: a skill de comparação existia e **não disparou**; a
máquina de medição rodou uma vez e **não foi usada** na hora de listar; e o inventário por tela
**não foi aberto**. As frases que precederam o erro — *"Ainda não chegou no protótipo? Tá
diferente ainda sem pageheader"* — não continham verbo de ação nenhum, e por isso não
disparavam. Hoje elas são corpus versionado do autoteste.

### Como conferir

Quatro portas, em ordem de custo: os testes de registro provam que o wiring persistiu; o
manifesto de hooks acusa órfão (arquivo sem registro) e fantasma (registro sem arquivo); o
autoteste prova que o gatilho ainda casa; e o **contador de mordidas** é a única que responde se
a defesa mordeu **no mundo**, não na fixture.

Medido em 30 dias, sobre 868 sessões locais: ativação de design **350 entregas**, protocolo de
comparação **116**, bloqueio de DesignSync **6**, âncora **1**, Figma **0**. O próprio contador
avisa que zero é ambíguo — pode ser "ninguém usou Figma" (legítimo) ou "o hook não morde mais".

### Prova hoje

Dos seis hooks, **dois não têm teste ao lado**: o de ativação (tem autoteste, 27/27) e o
**protocolo de comparação, que não tem prova nenhuma e nenhum invocador em CI** — entregou 116
vezes em 30 dias e nada garante que o regex ainda casa o que deveria. **Nenhum hook de E0 roda
em job required.**

---

## E1 · Acesso e importação — trazer o design pro repo

A etapa mais densa, e a única que já regrediu de verdade.

### Subprocessos

| # | Subprocesso | Entrada → Saída | Grava |
|---|---|---|---|
| E1.1 | **gerar o payload** | diretório do design vivo → partes numeradas + manifesto | do lado de quem tem os arquivos em disco — nunca do lado do agente, senão é transcrição |
| E1.2 | **validar em staging** (dry-run) | partes → veredito | nada: monta tudo e joga fora |
| E1.3 | **promover atomicamente** | staging → quatro destinos de uma vez | espelho · design-docs · cache de preview · estado |
| E1.4 | **inventariar** | estado → tela por tela: pendente / bloqueada / a criar | relatório de aplicação |
| E1.5 | **rota pontual** (1–3 arquivos) | JSON do arquivo → escrita pela máquina | espelho ou design-docs, roteado **por extensão** |
| E1.6 | **o pedido** (intake) | `cowork-inbox/` → o que fazer com o design | design-docs |

O transporte é **em partes** porque a leitura corta em 256 KiB: um payload único de ~3,5 MB volta
inútil. A primeira recepção é **snapshot** (tudo); as seguintes são **delta** — só adicionados e
modificados carregam bytes.

**Receber não é aplicar.** Antes de qualquer troca são conferidos: a sequência completa das
partes (subconjunto é recusado), **bytes e hash de cada arquivo** contra o manifesto, e o **grafo
fechado** — arquivo de entrada presente, nada faltando, nada com path traversal, nada duplicado.

**Falha no meio reverte tudo.** A promoção renomeia destino para backup e staging para destino,
empilhando cada troca; no erro, percorre a pilha **em ordem reversa** e devolve os backups. Falha
*antes* da promoção não tocou em nada.

### Os três níveis de dependência

Esta é a parte que mais engana, porque cada nível é invisível ao anterior **por construção**.

| Nível | O que enxerga | O que **não** enxerga |
|---|---|---|
| **(a)** deps diretas declaradas pelo shell | `src=`/`href=` do HTML | o que o CSS pede |
| **(b)** grafo CSS recursivo | CSS que importa CSS, fonte puxada por `url()` | o que ninguém declara |
| **(c)** o pedido | `cowork-inbox/`, que desce por rota própria | — |

O nível (b) existe porque o shell **não menciona fonte alguma**: quem as pede é o CSS de
tipografia. Antes do conserto, o preview dava sete `@font-face` com erro e a tipografia caía no
fallback do sistema — 404 silencioso, com o comando dizendo "2 repostos" e dando impressão de
plano completo. Medido hoje: o plano tem 10 arquivos, sendo **7 fontes** que o nível (a) jamais
veria.

O nível (c) é o mais fácil de esquecer porque não é código: o pedido não é carregado por nenhum
`<script>` nem por nenhum `url()`. O tamanho do ponto cego está medido: **229 documentos no vivo,
174 fora de arquivo morto, zero no espelho** — incluindo os briefings de tela e mais de trinta
prompts de construção.

### Na prática — a tela Arquivos, 24/08/2026

Três PRs no mesmo dia, cada um consertando o furo que o anterior deixou.

| Hora | PR | O que aconteceu |
|---|---|---|
| 17:49 | #6198 | a fonte desce (646 linhas) — os dois arquivos existiam vivos e o espelho **não tinha nenhum**; nada acusava, porque o manifesto é cego pro que nunca desceu |
| 18:56 | #6208 | o `.jsx` viera **sem a folha de estilo** e a tela não renderizava — 72 classes usadas, **zero** definidas em qualquer CSS do espelho |
| 19:22 | #6212 | o **detector estava cego**: o shell do espelho era de 20/08 e não citava os arquivos novos, então respondia "ausentes: 0" |

O antes/depois do detector é a lição inteira:

```
ANTES:  20 âncoras + 221 deps · ausentes: 0
DEPOIS: 20 âncoras + 222 deps · ausentes: 6
```

E aí a máquina enumerou sozinha o que ninguém tinha pedido: não era problema da Arquivos, era a
**leva inteira de cinco telas** que nunca havia descido.

Duas lições que esse caso ensina melhor que qualquer regra escrita: **transportar o `.jsx` não é
transportar a tela**, e **um detector que lê fonte velha responde zero com confiança**.

### Como conferir

A ordem importa e o painel a enumera. O ponto não-óbvio: **refresque o shell antes de confiar no
detector**, porque o universo dele sai do shell. Shell velho = detector cego, e o cego responde
zero com confiança.

**A distinção que mais engana:** um comparador verde prova que *o que está no espelho* acompanha
o vivo. **Não** prova que o espelho *cobre* o vivo — arquivo que existe no vivo e nunca foi
exportado é invisível por construção, porque o manifesto monta o universo lendo o **lado do
espelho**. Quem responde a outra pergunta é o modo que lista o vivo.

Generalizando, e vale para muito além do design: **régua cujo universo vem do lado que você
controla mede a sua diligência, não a realidade.**

### Prova hoje

O transporte tem teste (aplicação, geração de partes, transação). **Não têm**: o contrato do
bundle, o grafo de dependências, a detecção de colisão, o construtor do espelho e o inventário de
estado. O vigia-dos-vigias tem **uma única** entrada neste eixo, e ela cobre só a checagem de
referências.

---

## E2 · Detecção e âncora — qual protótipo é a fonte desta tela

### Subprocessos

| # | O que faz | Nota |
|---|---|---|
| E2.1 | enumera os charters nas **duas** raízes (núcleo e módulos) | varrer só o núcleo cegava três máquinas que derivam daqui |
| E2.2 | casa a consulta com **um** charter | sem charter: recusa, e a mensagem é *"NÃO invente âncora; registre ou pergunte"* |
| E2.3 | **computa** a âncora do que o charter declara | é o campo do charter, nunca a string do caminho |
| E2.4 | trata `n/a` como **declaração**, não como pendência | 135 de 158 charters declaram `n/a` legitimamente |
| E2.5 | classifica o conteúdo da âncora | ausente ou apontando pro shell reprova; sem módulo é aviso |

Medido: **217 charters**, âncoras no lugar fixo e vivas.

### Na prática

```
ÂNCORA da tela: Arquivos/Index
  charter:    resources/js/Pages/Arquivos/Index.charter.md
  tela viva:  resources/js/Pages/Arquivos/Index.tsx
  âncora ✓:   [related_prototype (charter)] prototipo-ui/cowork/arquivos-page.jsx
```

E o caso que **não mede**, que é o mais instrutivo — o campo tem prosa entre parênteses, o
parêntese entra no path e o arquivo não abre:

```
  âncora ⚠️:  ... (formalizado 2026-07-09 — o visual_source já declarava ...)
              ⚠️ NÃO MEDIDO — o arquivo da âncora não pôde ser LIDO neste path.
                 Zero fantasma aqui é AUSÊNCIA DE MEDIÇÃO, não saúde.
```

Repare: **exit 0**. A âncora *resolve* — o que não aconteceu foi a checagem de conteúdo. Antes de
2026-08-17 o relatório estampava `✓` aqui, verde por ausência de medição.

### Por que a âncora não se escolhe no olho

Um hook bloqueia a leitura de **print semântico** (nome contendo `audit`, `critique`, `tribunal`,
`adversari`…) que **nenhum charter declarou** como âncora. Imagem de design legítima passa.

Guardas por nome de pasta foram tentados **duas vezes e reprovados**. A allowlist de pasta
backfirou de três maneiras ao mesmo tempo: os charters reais declaravam a fonte em `.jsx`/`.html`
e **zero** em imagem, então ela bloqueava todo design real, deixava passar o print velho que
estivesse na pasta certa, e **nunca lia charter embora a mensagem prometesse**. Proveniência é o
que o charter declara — não a string do caminho.

### Estado hoje

`⛔ podre: 0 · 🟡 sem módulo: 3 · ✓ ok: 51`

### Prova hoje

O detector de telas está no vigia-dos-vigias. **O resolvedor de âncora — do qual três outras
máquinas derivam — não tem teste, está fora do registro, e seu único passo de CI é
`continue-on-error`.** Se ele regredir, nada fica vermelho.

---

## E3 · Comparação — medir, nunca olhar

A etapa com a lição mais cara do ciclo.

### Subprocessos

| # | O que faz |
|---|---|
| E3.0 | **provar a fonte** — pré-condição, não passo opcional |
| E3.1 | resolver a âncora (fail-closed: se ela quebra, a comparação **recusa**) |
| E3.2 | injetar a **mesma sonda** nos dois renders |
| E3.3 | extrair estilo **computado** — o que o browser resolveu, nunca a classe |
| E3.4 | comparar os dois JSON |
| E3.5 | emitir veredito de vocabulário fechado |
| E3.6 | persistir a ponte design↔código |
| E3.7 | rotear a análise por **região**, não pela tela inteira |

A sonda mapeia **papel**, não classe, porque as classes diferem entre os lados. E grava `url` e
`theme` dentro do próprio snapshot — não é decoração: são os campos que alimentam os portões de
proveniência e de tema.

### As oito dimensões

| # | Mede | Mecanizada? |
|---|---|---|
| **D1** | comportamento de rede: filtro e navegação são recarga parcial, nunca página inteira | não — passo do agente |
| **D2** | layout: número de linhas visuais, ordem das zonas, quebra | sim |
| **D3** | ícones: SVG × glyph × emoji | não |
| **D4** | tipografia das âncoras: título, valor do indicador, linha de tabela | sim |
| **D5** | rodapé e somatórios: conteúdo **e formato** | não |
| **D6** | cor e token: matiz, saturação, estados | sim |
| **D7** | densidade: altura de linha, espaçamentos | parcial |
| **D8** | alinhamento **e a tag** — porque a tag explica a causa | sim |

**D1 é a mais importante e a mais barata de esquecer**: um print igual pode esconder uma recarga
de página inteira. Comportamento antes de pixel.

### Na prática — o que é um gap medido

Um gap medido tem **seis campos, e nenhum deles é uma nota**: a dimensão, **qual propriedade**
exata, o valor computado de cada lado, o veredito de vocabulário fechado, e o **delta com a banda
nomeada ao lado**. Do caso real:

```
D8 · kpi.text-align  · prod "center" × design "start"  · DIVERGE (bug) · 5/5 desalinhados
D8 · kpi.tag         · prod "BUTTON" × design "DIV"    · DIVERGE (bug)
D6 · primary lightness · 0.55 × 0.72 · DIVERGE (bug) · Δ 0.17 · banda luminancia ±0.1
```

**A segunda linha explica a primeira**: `<button>` herda `text-align:center` do navegador;
`<div>` é `left`. Sem CSS resetando, o card centraliza sem ninguém pedir. O print mostraria os
dois lados "com 5 cards" — só a medida mostra a causa.

As bandas de tolerância são **medidas contra o corpus, não palpitadas**. A mais contra-intuitiva:
a banda de tipografia é **zero**, porque no corpus o menor passo real é 0,5px — ou seja, "0,5px é
ruído" é falso aqui, é passo de design. E cada banda tem um par de teste abaixo e acima do
limite; sem esse par, o número seria decorativo.

### Por que screenshot não é prova

O caso, em uma frase: cinco indicadores estavam centralizados na produção e à esquerda no design,
e o agente olhou o print e disse *"estruturalmente igual"* — e estava, estruturalmente. **Um print
de dois cards de largura idêntica com texto curto não distingue centralizado de alinhado à
esquerda.** O dono usou o alinhamento como canário e pegou.

Isso aconteceu **duas vezes**. Na segunda, o diagnóstico foi: *"o protocolo existia e nada o
ativava no momento da comparação"* — daí o hook. **Conhecimento sem gatilho não dispara.**

E há um segundo strike, mais traiçoeiro: **medir a coisa errada**. Contraste medido pelo
*agregado* do card deu 8,75:1 → "passa, é só estética". Por elemento: 1,92:1 e 2,04:1 —
reprovado. *"O strike 1 é olhar; o strike 2 é medir a coisa errada — e esse passa despercebido
porque vem com número."*

### Três camadas que não se substituem

| Camada | Pergunta | O que **só** ela pega |
|---|---|---|
| D1 comportamento | a interação é parcial? | recarga de página inteira — invisível em qualquer print |
| D2–D7 fidelidade | a produção bate com o **design**? | defeito que **nasceu** com a tela |
| pixel no CI | o PR mudou a tela **sem querer**? | refactor de CSS que desloca algo |

O caso que prova: um botão renderizava fantasma porque o estilo escopado nunca casou com ele. O
bug **nasceu com a tela**, e a baseline de pixel foi capturada **já com o bug**. *"O gate de pixel
estava verde e sempre estaria: regressão zero, fidelidade zero."*

### A nota única de fidelidade foi rejeitada

Uma divergência pode ser três coisas **com sinal oposto**: produção fora do canon (Δ negativo),
protótipo atrasado porque a produção evoluiu com aprovação (Δ **positivo**), ou ruído de dado
(Δ zero). Somar as três produz um número que **cai quando a produção melhora**. Uma "fidelidade
78%" não diz se você deve consertar código, re-exportar design ou ignorar.

No lugar dela: histograma de qual propriedade diverge mais (com marca de sistemático), uma linha
em linguagem natural, e a matriz por célula — que acusa **regressão de recorte**: quebra só no
mobile, ou só num tema.

### A armadilha de tempo

Medir logo depois de um reload lê a página **no meio do carregamento**. O caso: leitura de 552
caracteres levou a *"a tela não renderiza"*; estabilizado, o real era **2218**, com a tela inteira
no screenshot. **Número que ainda está subindo não é medida, é retrato de meio-caminho.** A app
publica o sinal de fim; a defesa é esperá-lo, ou ler duas vezes e só concluir se não mudou.

O pior detalhe: a prova estava no próprio output do agente enquanto ele afirmava o contrário —
a sonda já devolvia conteúdo real da tela. *Não havia bug: havia pressa.*

### Prova hoje

**Seis de seis máquinas têm autoteste; zero têm teste unitário; nenhuma está no
vigia-dos-vigias.** O portão de proveniência é sólido e tem controles nos dois sentidos. Mas
**o canário por rodada não é máquina** — vive só em skill e hook, ambos advisory — e a armadilha
de tempo também não. São as duas pré-condições mais frágeis.

⚠️ **Armadilha ativa:** os dois comparadores desta fase têm **ordem de argumentos invertida** —
um espera produção-depois-design, o outro protótipo-depois-produção — e **nenhum valida
semanticamente qual JSON é qual**. Trocar a ordem produz um relatório plausível com os lados
espelhados.

---

## E4 · Contrato e aplicação — a tela nasce

Onde o agente novo mais erra.

### Subprocessos

| # | O que faz | Nota |
|---|---|---|
| E4.1 | **portão de frescor** + plano de leitura | aborta se o protótipo re-exportou; a sessão abre **só** os trechos mapeados |
| E4.2 | gap medido vira **contrato de região** | uma seção por parte acionável |
| E4.3 | preencher a copy literal e ancorar no `.tsx` | |
| E4.4 | **verificar** o contrato | âncora presente, copy literal, **ordem** das âncoras |
| E4.5 | aceite **por região**, não pela tela toda | região sem âncora no DOM vira ausente explícito — nunca recorta a tela inteira em silêncio |
| E4.6 | o **trio** | charter, casos, teste |

Separação de papéis, e ela é deliberada: uma máquina **deriva** o esqueleto, outra **verifica** —
um papel por script.

### O trio, mostrado de verdade

A ponte entre as três pernas é o **id do caso de uso**, não prosa:

```
Index.charter.md ──"- Casos: [Index.casos.md]"──▶ Index.casos.md
                                                       │
                                    UC-INDEX-01 (a chave)
                                                       ▼
   ArquivosAdminControllerTest.php   it('UC-INDEX-01 · …')
   e2e/arquivos-index.spec.ts        test.fixme('UC-INDEX-01: …')
```

**Detalhe que decide se o caso vira verde:** o id tem de estar no **título** do teste, não em
comentário. Medido: 546 de 716 casos têm o id no título; **158 citados só em comentário nunca
viram verde como estão** — o conserto é reescrever o título, não rodar mais teste.

Um caso de uso tem persona, aceite em Dado/Quando/Então, o teste que o defende, **a regressão que
ele defende**, e um status que vem do **veredito**, não da leitura de quem escreveu.

O que **não** vira caso de uso: cenário sem id fica como backlog visível — declarar os catorze
cenários do protótipo como casos criaria treze órfãos e quebraria o gate.

### As três pernas e a regra dura de cada uma

| Perna | Regra dura |
|---|---|
| **Charter** (lei) | linka os casos; seção que **promete teste inexistente = revogar** |
| **Casos** (contrato) | derivado da documentação canônica, **nunca do `.tsx`** — senão é tautológico |
| **Teste** (defesa) | roda na lane real; o nome cita o caso; status vem do veredito |

**Ordem de fonte para escrever um caso**, e ela é fixa: documentação canônica → código (só para
*confirmar*, nunca para derivar) → legado Delphi (paridade) → concorrentes (traduzir premissa,
não copiar solução) → **perguntar**. *Anti-padrão inventado no charter é pior que ausente, porque
parece canônico.*

### Precedência quando os artefatos discordam

**teste verde citando o caso > casos > charter > SPEC** — e a regra de ação: **corrigir o
perdedor no mesmo PR**.

O charter pode estar **errado** e ainda ser "lei": lei significa *autoridade de intenção*, não
*garantia de correção*. Um anti-hook de charter que contradiz o código correto é instrução ativa
para regressão — corrija o charter, não o código. O caso que fixou isso: um charter proibia
cachear por business enquanto o código correto cacheava por business; obedecer criaria vazamento
entre clientes.

### O hook sem escape

Editar uma tela sem o runbook da migração é bloqueado, e **não há override** — nem flag, nem
variável de ambiente. Durante um tempo a documentação anunciava um; nunca houve handler, e não
poderia haver: aquele override é registro **humano** em PR, que vira decisão arquivada — nunca
comando de máquina.

⚠️ **Duas skills ainda apresentam esse override como autorização.** Uma delas traz a ressalva
correta; a outra não. O código é a fonte: sem escape.

### Como conferir

Duas portas vivas, e as duas declaram **escopo idêntico** — se divergirem, uma está mentindo
sobre o universo. Medido: **214 telas**, charter em 214, casos em 117, casos órfãos 12, status
mentindo **0**. Cobertura de teste ponta-a-ponta **13,6%**, acessibilidade **1,9%**.

**Responder isso por busca manual é a classe de erro que mais reincide neste projeto.** O mapa é
comando, não `grep`.

### Prova hoje

**Nenhuma das treze máquinas de E4 está no vigia-dos-vigias** — o núcleo do contrato de região
não é vigiado. E o guarda do trio, com mais de setecentas linhas, **não tem par bom/ruim
provando que morde**; o registro cobre os vizinhos dele, não ele. Buraco puro: o lint que define
o campo canônico de vínculo com a história de usuário **não tem prova de tipo nenhum**.

---

## E5 · Preflight local — antes de abrir o PR

Doze verificações locais.

### O que cada uma reprova

| Máquina | Reprova | Bloqueia merge? |
|---|---|---|
| primitivas de layout | arquivo **ganhar** flex/grid solto vs baseline (o legado é grandfathered) | **sim** |
| cobertura de casos | trio ausente, caso sem teste, metadata morta, status mentindo | **sim** |
| lint e tipos | contagem por par arquivo/regra subindo | **sim** (lint) |
| cor crua | regra de tela ganhando cor literal; accent fora da faixa; papel de token trocado | **sim**, via agregador |
| fundação | **definição** de token fora da fundação; arquivo CSS novo fora da allowlist | **sim**, via agregador |
| lint de UI | ratchet da Constituição visual | **sim**, via agregador |
| espelho | arquivo mexido depois do carimbo de verificação | **sim** |
| âncora | apontando pro shell ou pro nada | **sim** |
| charter vivo | declarado vivo sem sinal de produção | **sim** |
| guarda do DS · fonte única · tipos | paleta inventada, dupla fonte, erro de tipo | **não** — só local |

**Nove dos doze bloqueiam merge**, não quatro: seis são required diretos e três chegam lá pelo
**agregador**, que depende dos jobs de cor e de lint de UI. Um vermelho em qualquer um deles
derruba o agregador, que é required.

### Na prática

Um verde tem esta forma — repare que cada arquivo mostra **contagem e teto**, não um "ok":

```
[conformance-gate] sells-cowork.css: cor-crua(regras de tela)=332 · teto=337 · ✅ PASS
[conformance-gate] --accent: todos em roxo 250–330 ✅
✅ 28 arquivo(s) conformes.
```

E um vermelho, da fixture versionada:

```
❌ REGRESSÃO — 1 arquivo(s) com MAIS flex/grid solto vs baseline:
   Fixture.tsx · 1 → 2 (Δ+1)
        L6: <div className="flex items-center gap-2">
```

### Prova hoje

**Duas de nove** máquinas sem prova efetiva — não quatro de sete, como uma medição estreita
sugeriria: os meta-testes não moram ao lado do script, moram no diretório de testes, e vários
estão ligados ao CI.

Mas há um buraco grave, e ele é do tipo pior: **o meta-teste do gate de cor crua existe e não é
invocado em lugar nenhum.** Ele é lei do agregador required, o script está sem commit desde
**10/06/2026**, e a prova de que ainda morde **não roda desde que foi escrita**. Os dois irmãos
dele estão ligados; esse ficou de fora. Ele passa verde todo PR, e ninguém sabe se é porque o CSS
está limpo ou porque o comparador apodreceu.

Os limites auto-declarados são honestos e valem conhecer: o gate de cor avisa que *"regex não é
parser CSS"* — cor crua dentro de media query escapa; e o guarda de fonte única declara que varre
**só dentro de uma pasta**, então dupla fonte fora dali passa verde. Foi exatamente o que
aconteceu: ele saiu verde enquanto treze duplicatas existiam, sete delas defasadas.

---

## E6 · Gates de CI

Medido em 25/08/2026, cruzando o **nome do job** (ou o id do job, quando ele não tem nome) com
`governance/required-checks-baseline.json`, que é o dono único da resposta. O nome do *workflow*
não serve — cruzar por ele dá resposta errada.

**33 workflows no eixo · 8 com pelo menos um context required · 25 advisory.** Em *contexts* são
**11**, porque um workflow carrega quatro sozinho.

### Os required

Regressão visual (isolamento entre clientes no render) · cobertura de tela · casos · agregador do
DS · primitivas de layout · nota de tela que não desce · âncora não-shell · e o pacote de âncora
entre especificação e código. Mais um context de schema do charter, servido por outro workflow.

**Os oito passam no teste do gate mudo**: nenhum tem filtro de caminho no gatilho de PR, logo
todos nascem em **todo** PR. Isso é vigiado por um required próprio — criado depois que uma
promoção mexeu no filtro e travou o repositório inteiro por dois dias com checks que nunca
nasciam.

### Por que required é só oito

A política é deliberada: **required = só o que evita catástrofe de primeira ordem** — dinheiro,
dados pessoais, isolamento entre clientes, fiscal. O resto continua rodando e mostrando vermelho,
sem bloquear. Demover não é apagar.

Promoção exige **mordida provada**: pelo menos dois PRs distintos em que o gate teria bloqueado
uma violação que **mergeou**. E promover não é virar uma chave — o mesmo PR tem que desembrulhar
o código de saída, anexar o registro de mordidas, atualizar os inventários, rodar verde antes, e
passar por janela e ratificação.

**Estado hoje: nenhum gate de design tem as duas mordidas.** O registro tem uma única entrada, e
ela sem PR associado. Por dado, nenhum é candidato.

### O advisory eterno

Todo gate advisory tem prazo com razão escrita. **Um vencido no repositório hoje, e ele não é do
eixo design.** Mas a armadilha real é outra: **nove advisory do eixo estão isentos por
grandfather** — sem prazo, sem relógio nenhum. Advisory eterno por isenção, não por vencimento.

E os prazos que existem são **renovações**: cada uma exige razão escrita e preserva o prazo
anterior. O mecanismo não matou o advisory eterno — tornou-o caro e datado.

### ⚠️ Um gate que não pode ficar vermelho

O gate de identidade visual tem um único passo substantivo, e ele engole o código de saída:
o `|| echo` transforma qualquer falha em aviso, que sai zero. **Sessenta runs: 53 sucessos, 7
cancelados, zero falhas.** Não é sorte — não existe caminho de falha no job.

O contraste prova que isso é conserto conhecido: cinco irmãos já foram **desembrulhados** e hoje
re-levantam o código de saída, com o comentário citando a regra nominalmente — *"advisory =
não bloqueia, nunca = não pode ficar vermelho"*. Este ficou para trás, e ainda por cima está
isento de prazo.

---

## E7 · Retorno — fechar o loop

O lado do design não enxerga o repositório direto; enxerga por um servidor de memória alimentado
por webhook **no merge**. Logo: **status que não está commitado é invisível do outro lado.**
Handoff fora da pasta do protocolo não fecha o loop; mensagem em chat e screenshot também não;
nem branch com PR aberto, porque o webhook é no merge.

### Os três canais, e por que são três

| Canal | Pergunta que responde | Modo | Por que não funde |
|---|---|---|---|
| placar de adoção | **quanto falta, por módulo, agora?** | derivado por máquina | é o único **medido do código**; virando prosa, volta a ser opinião |
| log de sincronização | **o que aconteceu, quando, em qual PR?** | append, imutável | é o único com **história**; sobrescrever mata a auditoria |
| handoff | **onde estamos agora e o que trava?** | sobrescrito | é o único de **uma página**; se fosse append ninguém acharia o estado |

Quantidade × história × estado. Os três modos de escrita são incompatíveis num arquivo só.

O log preserva até o que envelheceu: uma previsão errada de julho está lá **riscada**, com o
ponteiro corrigido embaixo. Append-only de verdade, sem apagar.

### A evidência que expira sozinha

Não existe marca de "aplicado". O estado é **recalculado a cada leitura**, comparando dois hashes
— o do arquivo no design e o do arquivo no produto. Qualquer re-exportação do protótipo **ou**
qualquer edição na tela derruba a evidência, e a tela volta a pendente. É o oposto de um checkbox,
e é fail-closed: na dúvida, pendente.

**Estado hoje: zero de 68 telas com evidência; o ledger nem existe; 21 telas bloqueadas.** O
mecanismo é sólido e o uso é zero.

### Prova hoje

O ponto de *auditoria* tem contrato codificado, teste e catraca. O ponto de *produção do dado*
não tem prova nenhuma: o placar roda diariamente com `continue-on-error`, sem teste e sem
autoteste — se o parse quebrar, ele mente e nada morde. Já ficou congelado dois meses servindo
número velho; ao religar, saltou de 148 para 1604. Foi pego por auditoria humana, não por gate.

E a lógica de hash acima **não tem teste**: uma inversão de comparação passaria no CI e
transformaria evidência velha em "aplicado".

---

## E8 · Pós-merge — a prova em produção

Nenhuma declaração de "pronto" vale sem smoke real. Um hook grava uma marca quando um PR de UI é
mergeado e **bloqueia** frases de conclusão nos cinco minutos seguintes até que a marca seja
limpa — e **nenhum comando de terminal a limpa**: só o uso real de um navegador. Não há como
narrar a saída; só olhar.

Os dois escapes que ele anuncia **existem no código** e são cobertos por teste — inclusive um
teste que exige que a mensagem de bloqueio continue nomeando os escapes, para que ela não anuncie
o que sumiu.

O smoke de CI que capturaria a tela em produção depende de uma variável de repositório e tem um
residual declarado: sem uma conta de teste dedicada, os screenshots capturariam dados reais.
Enquanto isso, E8 depende do hook local.

---

## E9 · Meta — quem vigia os vigias

O vigia roda cada catraca contra uma fixture boa e uma ruim e exige que a ruim **falhe pelo
motivo certo** — erro de execução não conta como morder. Em 25/08/2026: **80/80, 40 catracas
distintas**. Ele não é decorativo: pegou a introdução de uma dependência nova **antes do CI**.

Uma catraca vai além e testa o **terceiro estado**: referência ausente tem que sair "não medi",
nunca "regrediu" — porque colapsar "não consegui medir" em um estado do objeto medido é proibido.

### O limite que o próprio E9 declara sobre si

*"O vigia prova que a defesa morde **na fixture**. Não prova que ela mordeu **no mundo**."*

O caso: um hook tinha vinte e seis asserções verdes — uma delas afirmando o próprio bug —
enquanto ficava silencioso em **116 de 116** oportunidades reais. Descoberto por acaso numa
conversa. Nenhum gate poderia ter pegado: o oráculo são os transcripts locais, fora do
repositório e invisíveis ao CI.

### Prova hoje

O verificador de integridade da espinha — que afirma que os 217 charters têm tela viva — **não
tem prova de tipo nenhum** e roda com `continue-on-error`: se parar de morder, o sinal é
indistinguível de "está tudo bem". E o próprio vigia-dos-vigias, que é required, **não tem
meta-vigia**: a prova de que ele pega uma catraca que parou de morder é um procedimento manual.

---

# Estado medido em 2026-08-25 — o que está vermelho

Isto é retrato datado, não veredito permanente. Re-rode as portas antes de citar.

| O quê | Medida |
|---|---|
| **Cinco telas existem no vivo e nunca desceram** | comunicação visual · voz do cliente · suporte · vestuário · catálogo QR |
| **O espelho quase não está sendo medido** | última rodada mediu **1 de 242**; 241 seguem sem veredito |
| **A base do próximo delta não conhece as correções de 24/08** | gerada **21 minutos antes** do merge que desceu a fonte da Arquivos; não contém nenhum dos três arquivos |
| **O portão de proveniência está mordendo** | com o ledger parcial, uma comparação contra o espelho local sai "não medi" — corretamente |
| **O censo de máquinas está desatualizado** | três máquinas no disco fora do índice |
| **O charter da Arquivos afirma que a tela não existe** | e ela existe desde 24/08 — divergência que a precedência manda corrigir no mesmo PR |

A frase da própria máquina resume a família inteira: *"'0 stale' só cobre o que foi medido."*

---

# Testes propostos

**Nada abaixo está armado.** São propostas de **prova de máquina existente** (fixture boa passa,
fixture ruim falha pelo motivo certo) — não gates novos. Gate novo exige medir falso-positivo no
corpus real antes de instalar, e este projeto já enterrou quatro tentativas de guarda sintático
que reprovava o legítimo.

| # | Alvo | Teste proposto | Por que |
|---|---|---|---|
| T1 | meta-teste do gate de cor crua (E5) | **ligá-lo** ao CI, ao lado dos dois irmãos que já estão | ele existe, é lei de um required, e não roda desde que foi escrito |
| T2 | detector de faltantes (E1) | **controle negativo**: shell velho com arquivo novo no vivo tem de acusar, não responder zero | é a regressão real de 24/08, e nada impede que volte |
| T3 | guarda do espelho (E5) | espelho editado à mão falha; intacto passa | é a defesa contra a edição que some no próximo transporte |
| T4 | contrato do bundle (E1) | parte faltando, base divergente e hash torto — cada uma falha por motivo distinto | o rollback é a promessa central do transporte |
| T5 | lógica de hash da evidência (E7) | evidência velha não pode virar "aplicado" | uma inversão de comparação passaria no CI |
| T6 | resolvedor de âncora (E2) | par bom/ruim no vigia-dos-vigias | três máquinas derivam dele e seu passo de CI não morde |
| T7 | verificador de integridade (E9) | espinha quebrada tem de reprovar | zero prova, e afirma que 217 charters têm tela viva |
| T8 | ordem dos comparadores (E3) | recusar snapshot cujo `url` não corresponde ao lado declarado | hoje trocar a ordem produz relatório plausível espelhado |

T1 e T2 são os de maior retorno: fecham falhas **já observadas**, e T1 é uma linha de
configuração.

---

# Onde este documento não é dono

- **Comandos e IDs** — painel `protocolo.config.mjs`.
- **Política, papéis e invariantes do loop** — `prototipo-ui/PROTOCOL.md`.
- **O que é required** — `governance/required-checks-baseline.json`.
- **Inventário completo de máquinas** — [`MAQUINAS-INVENTARIO.md`](MAQUINAS-INVENTARIO.md).
- **Artefatos de uma tela específica** — os relatórios de cobertura de tela e de casos, que
  recalculam da árvore. Um mapa escrito à mão apodrece no mesmo dia.
- **O gap de uma tela específica** — o comparativo visual dela, que já traz veredito e data por
  item. Listar gap por busca de texto produz lista errada.
