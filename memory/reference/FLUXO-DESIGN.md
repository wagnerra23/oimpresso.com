---
id: reference-fluxo-design
name: Fluxo — Design, do Cowork à tela
description: Como o design entra no repo, vira tela e é provado — E0 de ativação mais 9 etapas operacionais, com fluxos internos, decisões, armadilhas, provas existentes e testes ainda necessários.
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

# E0 de ativação + 9 etapas operacionais

## Como ler cada etapa

Cada etapa responde às mesmas sete perguntas, mesmo quando a máquina que a executa muda:

1. **Qual é a entrada confiável?** Arquivo, estado, URL, hash ou decisão humana que inicia o passo.
2. **Quem transforma?** Hook, script, workflow, navegador ou agente.
3. **Onde há decisão?** Ponto em que o fluxo continua, recusa ou volta para corrigir a fonte.
4. **O que é gravado?** Artefato durável; saída apenas no terminal não fecha o ciclo.
5. **Qual é a armadilha?** Caminho plausível que produz um resultado errado.
6. **Qual falso-verde ela gera?** Mensagem de sucesso que não prova a pergunta feita.
7. **Como provar?** Controle positivo, controle negativo e limite explícito da prova.

O vocabulário abaixo é deliberado:

- **prova existente**: teste ou selftest localizado e ligado a um workflow;
- **cobertura parcial**: a prova morde uma classe do erro, mas não a semântica inteira;
- **lacuna residual**: cenário relevante para o qual não foi localizada prova automatizada;
- **advisory/required**: nível de enforcement, nunca sinônimo de “tem teste” ou “não tem teste”.

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

### Fluxo interno e pontos de decisão

```text
prompt do usuário
  -> hooks UserPromptSubmit registrados em settings.json
  -> [há intenção de design?]
       não -> sessão segue sem injetar o protocolo
       sim -> [é comparação design x produção?]
                sim -> injeta protocolo de medição e proveniência
                não -> injeta painel do designer-agente
  -> [a ação pede escrita/publicação externa?]
       sim, sem opt-in válido -> bloqueia e explica a autorização faltante
       sim, com flag dentro do TTL -> permite uma ação no escopo autorizado
       não -> segue
  -> contador de mordidas registra qual defesa realmente apareceu na sessão
```

**Entrada:** texto do prompt + registros em `.claude/settings.json` + flags temporárias. **Saída:**
contexto injetado, bloqueio objetivo ou passagem silenciosa. E0 não modifica tela; ele decide qual
protocolo será carregado antes de alguém começar a agir.

### Armadilhas e falsos verdes

| Armadilha | Por que parece correta | Falso-verde produzido | Defesa necessária |
|---|---|---|---|
| arquivo de hook existe, mas não está registrado | o código está no disco e passa revisão visual | “o protocolo existe”, embora nunca execute | teste do wiring + manifesto órfão/fantasma |
| regex ampla demais | captura todos os prompts de design da fixture | interrompe comparações de preço, módulo e texto comum | corpus positivo **e negativo** |
| regex estreita demais | os exemplos felizes continuam verdes | linguagem real de divergência não ativa a máquina | corpus retirado de prompts reais + contador de mordidas |
| flag sem TTL ou sem escopo | o primeiro uso autorizado funciona | autorização antiga libera ação posterior diferente | expiração, consumo único e escopo explícito |
| zero mordidas interpretado como saúde | nenhum erro aparece | hook morto e hook nunca necessário têm o mesmo número | cruzar oportunidades elegíveis × ativações |

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

### Prova existente e limite residual

- `design-agente-ativa.mjs --selftest` tem corpus próprio e é invocado por
  `governance-script-tests.yml`.
- `observabilidade-tags.test.mjs` prova um caminho feliz do `design-compare-protocol`: um prompt de
  comparação emite a tag esperada.
- o manifesto e os testes de registro provam arquivo ↔ `settings.json`; isso não prova a qualidade
  semântica do matcher.
- **lacuna residual:** o protocolo de comparação não tem um corpus dedicado com positivos,
  negativos, typos e frases reais de divergência. O teste de observabilidade prova que **um**
  exemplo dispara, não que exemplos inocentes sejam ignorados nem que a linguagem real continue
  coberta.

Nenhuma dessas provas transforma E0 em required: enforcement e qualidade do teste são eixos
separados.

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

### Fluxo interno e pontos de decisão

```text
Design/Cowork vivo
  -> listagem do universo vivo
  -> gerador produz manifesto + partes + SHA por arquivo
  -> receptor reconstrói em staging
  -> [sequência completa? hashes batem? paths seguros? grafo fechado?]
       não -> recusa; destinos atuais permanecem byte-idênticos
       sim -> dry-run monta o resultado e descarta
  -> [base do delta ainda é a base ativa?]
       não -> BASE_DIVERGENTE; exigir snapshot ou novo delta
       sim -> promoção atômica dos destinos
  -> [alguma troca falhou?]
       sim -> rollback em ordem reversa
       não -> grava bundle ativo, estado e inventário
  -> detector compara universo vivo com o que desceu
       -> faltante vira pendência explícita, nunca “SYNC”
```

As decisões de segurança acontecem **antes** de tocar o destino. O inventário acontece depois da
promoção porque precisa descrever o estado que realmente ficou ativo, não o staging que quase foi
aplicado.

### Armadilhas e falsos verdes

| Armadilha | Por que engana | Falso-verde | Como evitar |
|---|---|---|---|
| shell local antigo define o universo | todas as referências conhecidas estão presentes | `ausentes: 0` enquanto arquivos novos existem no vivo | listar o vivo primeiro; refrescar shell; registrar denominador |
| transportar só o `.jsx` | o componente chegou e o hash bate | tela quebra sem CSS, fonte ou asset transitivo | fechar grafo CSS/import/url até ponto fixo |
| comparar apenas arquivos já espelhados | tudo que foi comparado está SYNC | cobertura parcial parece cobertura total | separar `SYNC` de “vivo não inventariado” |
| aplicar delta sobre base diferente | cada parte e cada SHA são válidos isoladamente | mistura coerente de duas versões incoerentes | conferir `base_bundle_id` antes da promoção |
| copiar payload pelo chat | o conteúdo parece textual e completo | truncamento ou normalização invisível | persistência pela máquina; comparar bytes e SHA |
| promover quatro destinos sem transação | três renomes passam e o quarto falha | estado híbrido entre espelho, docs, preview e ledger | staging único + pilha de rollback reversa |
| tratar `.md` como código do espelho | arquivo foi “importado” | pedido fica no lugar errado e nunca chega ao consumidor | roteamento determinístico por extensão e destino |

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

### Prova existente e limite residual

O transporte já tem uma bateria forte; não deve ser descrito como “sem teste”:

- `gerar-payload-partes.test.mjs` cobre manifesto, reconstrução de partes, teto/piso, glob,
  exclusão, arquivo ausente e delta exato;
- `aplicar-payload.test.mjs` cobre fidelidade de bytes, idempotência, dry-run, escopo, roteamento
  de `.md`, truncamento, envelope incompleto, grafo transitivo e cancelamento atômico por
  dependência ausente;
- `bundle-transaction.test.mjs` cobre sequência, SHA, mapping, parte faltante, corrupção, path
  traversal, base divergente, promoção, rollback e invalidação da evidência quando a fonte muda;
- `cowork-mirror-freshness.test.mjs` cobre `SYNC/STALE/LIVE-ABSENT/UNCHECKED`, cobertura parcial,
  dependências CSS/fontes, arquivo live-only, SLA, preview e rollback.

Essas baterias estão ligadas aos workflows de governança/design. **Limite residual:** uma fixture
local só conhece o universo que a fixture entrega. O risco operacional que sobra é a aquisição do
universo vivo autenticado: se a listagem não rodar, estiver paginada ou usar shell velho como
denominador, todos os hashes podem passar e ainda assim faltar uma tela inteira. O teste novo deve
provar o contrato “sem listagem viva válida não existe veredito de cobertura”, não repetir hash ou
rollback já cobertos.

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

### Fluxo interno e pontos de decisão

```text
consulta por Tela/Modulo
  -> varre charters nas raízes de Pages do núcleo e dos módulos
  -> [quantos charters casam?]
       0 -> recusa: não inventar; registrar ou perguntar
       >1 -> recusa: consulta ambígua; exigir identidade completa
       1 -> lê related_prototype
  -> [valor é n/a declarado?]
       sim -> encerra como “sem protótipo por decisão”, não como pendência
       não -> normaliza o caminho declarado
  -> [arquivo abre?]
       não -> NÃO MEDIDO; não imprimir ✓
       sim -> [conteúdo é fonte de tela ou shell genérico?]
                shell/ausente -> reprova
                fonte plausível -> devolve âncora + proveniência
```

**Entrada:** identidade da tela e charter versionado. **Saída:** uma âncora única, `n/a` explícito
ou recusa. Caminho existente é condição necessária, não suficiente: a pergunta final é se aquele
arquivo representa **esta** tela.

### Armadilhas e falsos verdes

| Armadilha | Falso-verde | Por que acontece | Defesa |
|---|---|---|---|
| varrer só `resources/js/Pages` | módulo parece não ter charter | raízes modulares ficam fora do universo | usar `raizesDePages`, fonte única |
| limpar prosa do campo “no olho” | abre um arquivo diferente do declarado | normalização vira adivinhação | schema do campo + erro que mostra valor bruto e normalizado |
| arquivo existe, mas pertence a outra tela | âncora ✓ e comparação coerente da tela errada | guardas atuais provam existência/shell, não identidade semântica | vínculo Tela↔protótipo verificável ou revisão explícita |
| `n/a` contado como falta | dívida cresce artificialmente | ausência deliberada colapsa com pendência | estado separado e motivo versionado |
| erro de leitura sai exit 0 com selo verde | ausência de medição parece saúde | relatório confunde resolução com inspeção de conteúdo | estado `NÃO MEDIDO` visível e não promovível |

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

### Prova existente e limite residual

- `ancora.mjs --selftest` existe e é invocado em `design-memory-gate.yml`;
- `block-ancora-no-olho.test.mjs` prova o bloqueio de print semântico não declarado;
- `anchor-content-check.test.mjs` tem controles de conteúdo e alimenta também um context required;
- `settings-ancora-registration.test.mjs` cobre o wiring do hook;
- `design-code-map-check.test.mjs` cobre staleness e vínculo derivado do mapa.

**Limite residual:** essas provas detectam ausência, shell, registro e staleness; não demonstram
que um arquivo real e plausível pertence semanticamente à tela consultada. A fixture adversarial
correta é “charter de A aponta para protótipo real de B”: tudo existe, nada é shell, mas a âncora
continua errada.

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

### Fluxo interno e pontos de decisão

```text
âncora resolvida + URL de produção + viewport/tema/estado
  -> prova de frescor da fonte
  -> [fonte SYNC e âncora válida?]
       não -> recusa a comparação; não produzir lista de gaps
       sim -> estabiliza os dois renders
  -> injeta a mesma sonda em DESIGN e PROD
  -> snapshots declaram lado, URL, tela, tema, viewport e hash de origem
  -> [identidades e matriz coincidem?]
       não -> recusa antes de calcular delta
       sim -> compara propriedades computadas por papel
  -> classifica cada divergência: bug / prod à frente / ruído / não medido
  -> agrupa por região e persiste a ponte design↔código
```

Um relatório só nasce depois de provar **fonte, lado, estado e estabilidade**. Se qualquer um
desses quatro falta, a saída correta é “não medido”, nunca uma tabela parcial com aparência final.

### Armadilhas e falsos verdes

| Armadilha | Número convincente | O erro escondido | Defesa |
|---|---|---|---|
| comparar logo após reload | `552 caracteres` | página ainda carregava | sinal de pronto ou N leituras consecutivas estáveis |
| inverter os dois JSON | deltas corretos com sinais trocados | conserta o lado que estava certo | identidade embutida + validação antes do compare |
| medir card agregado | contraste `8,75:1` | textos internos têm `1,92:1` | medir o elemento que carrega o papel semântico |
| screenshot “parecido” | cards e larguras batem | alinhamento/tag/comportamento divergem | computed style + D1 comportamental |
| baseline visual já nasceu com bug | pixel diff zero | fidelidade com o design é zero | comparar com fonte de design, não só com baseline anterior |
| somar tudo em uma nota | “78% fiel” | mistura bug, prod à frente e ruído | veredito por propriedade e direção |
| comparar matriz incompleta | desktop light verde | mobile/dark não foi medido | declarar denominador viewport×tema×estado |

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

### Prova existente e limite residual

`design-diff.mjs` e `style-fingerprint.mjs` têm selftests ligados ao
`design-memory-gate.yml`; as bandas incluem controles abaixo/acima do limiar, e o portão de
proveniência tem controles nos dois sentidos. Isso prova o comparador **depois que recebeu dois
snapshots corretos**.

**Lacunas residuais:** a captura não está mecanicamente obrigada a esperar estabilidade; o canário
por rodada vive no protocolo; e os comparadores não validam semanticamente qual arquivo é design e
qual é produção. Portanto, a prova algorítmica pode estar verde enquanto a coleta alimenta o par
errado ou um estado intermediário.

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

### Fluxo interno e pontos de decisão

```text
gap medido por região
  -> portão de frescor revalida hash do protótipo
  -> [hash mudou desde a medição?]
       sim -> invalida plano e volta a E3
       não -> gera esqueleto do contrato
  -> humano/agente completa copy, ordem e critérios sem inventar regra de negócio
  -> aplica uma região no .tsx
  -> verificador procura data-contract + copy + ordem
  -> [região passa?]
       não -> corrige a mesma região; não avança
       sim -> valida comportamento e estados daquela região
  -> atualiza charter + casos + testes pelo mesmo UC-ID
  -> [fonte funcional discorda do visual?]
       sim -> preserva comportamento; registra decisão ou backend necessário
       não -> conclui a região
```

O contrato é uma **ponte verificável**, não uma especificação completa do produto. Ele ancora
estrutura e copy; permissões, estados, validações, rotas e efeitos colaterais continuam vindo do
comportamento funcional e dos casos de uso.

### Armadilhas e falsos verdes

| Armadilha | Falso-verde | Dano | Defesa |
|---|---|---|---|
| aplicar a tela inteira de uma vez | screenshot final parece próximo | não se sabe qual região quebrou | onda de no máximo duas telas e aceite região a região |
| derivar caso do `.tsx` atual | teste confirma exatamente o código | bug atual vira requisito | documentação/legado primeiro; código só confirma |
| preservar visual e remover estado “vazio/erro/sem permissão” | caminho feliz passa | regressão operacional | matriz de estados no contrato funcional |
| UC-ID só em comentário | busca manual encontra | coletor não liga resultado ao caso | ID no título do teste |
| copy existe fora da região | verificador textual encontra | região continua incompleta | busca delimitada pela âncora, não arquivo inteiro |
| ordem visual correta por CSS, DOM incorreto | desktop parece certo | teclado/leitor de tela quebram | validar ordem do DOM e navegação por teclado |
| acomodar layout mudando regra | interação fica “mais fácil” | regra de negócio é alterada sem decisão | classificar como dependência/backend e parar |

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

### Prova existente e limite residual

- `gerar-contrato.mjs --selftest` cobre a derivação região a região;
- `contrato-de-tela.test.mjs`, ligado a `contrato-de-tela.yml`, injeta âncora ausente, copy
  ausente, ordem trocada e remoção sem justificativa, além do controle positivo;
- `casosGuard.spec.ts` e `casosResultsCollect.spec.ts`, ligados a `guards-meta-gate.yml`, provam
  o guarda do trio e o coletor;
- `casos-gate.yml` executa o guard de cobertura e o ratchet;
- `design-code-map-check.test.mjs` cobre a ponte derivada design↔código.

**Limite residual:** a prova estrutural não substitui paridade funcional. Ela não sabe, sozinha,
se uma permissão Blade sumiu, se o filtro virou recarga completa, se o modal perdeu validação ou
se o estado de erro deixou de existir. Esse inventário precisa virar casos por tela e testes de
comportamento, não mais uma regex de contrato.

---

## E5 · Preflight local — antes de abrir o PR

O painel reúne verificações locais por classe de arquivo. A tabela abaixo mostra as famílias
principais; a lista executável e a quantidade corrente pertencem a `protocolo.config.mjs`.

### Fluxo interno e pontos de decisão

```text
diff da onda atual
  -> classifica arquivos tocados (tela, charter, casos, contrato, espelho, tokens)
  -> painel seleciona verificações aplicáveis
  -> roda guardas baratos e determinísticos primeiro
  -> [algum hard gate falhou?]
       sim -> interrompe; corrigir a causa, não baixar baseline
       não -> roda build/tipos e provas de comportamento da tela
  -> [houve alteração no espelho?]
       sim -> exige transporte verificável e carimbo compatível
       não -> segue
  -> produz recibo: comando, escopo, exit code, contagem e teto
  -> só então abre PR
```

O preflight é **diff-aware**: dívida herdada pode permanecer congelada, mas a onda não pode
aumentá-la. “Passou local” sem registrar comando, escopo e denominador não é recibo reproduzível.

### Armadilhas e falsos verdes

| Armadilha | Falso-verde | O que fazer |
|---|---|---|
| rodar só o teste do arquivo alterado | unidade passa, integração e gate de contrato não nasceram | usar o painel para derivar a bateria da classe de arquivo |
| reduzir baseline para fazer o ratchet passar | contagem volta ao teto | baseline é evidência; mudança exige fluxo próprio e antes/depois |
| `continue-on-error` confundido com “teste passou” | job verde contém step vermelho | ler o exit real e a política do context |
| regex não vê media query/nesting | zero cor crua reportada | declarar limite do analisador e complementar com parser/browser |
| espelho editado à mão passa lint | código sintaticamente perfeito | freshness/hash deve reprovar autoria local |
| build verde usada como prova funcional | bundle compila | executar casos, permissões, validações e estados da tela |
| teste skipped/fixme contado como cobertura | arquivo de teste existe | status vem do resultado coletado, não da presença do texto |

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

O campo “bloqueia merge?” precisa ser conferido contra a baseline antes de cada execução: há jobs
required diretos e verificações que chegam à proteção por um agregador. Um step vermelho dentro de
job advisory ou embrulhado não equivale a bloqueio; um context required vermelho, sim.

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

### Prova existente e limite residual

Há controles negativos ligados para casos, coletor, fundação, contrato, espelho, âncora, layout e
várias catracas do DS. O `cowork-mirror-freshness.test.mjs`, em especial, já prova edição manual,
staleness, cobertura parcial, live-only, grafo CSS/fontes e rollback — não há motivo para propor
outro teste genérico “espelho bom/ruim”.

O buraco localizado durante esta auditoria era mais específico: `tests/conformanceGate.spec.ts`
já tinha sensibilidade, especificidade e não-vacuidade, mas não tinha invocador. Ele foi fechado
em **2026-08-25 pelo PR #6232**: `guards-meta-gate.yml` passou a executar
`npm run test:conformance`, e seus filtros passaram a incluir o script, os baselines e o teste.
Não foi criado gate novo nem alterado enforcement; a prova existente deixou de ficar órfã.

Os limites auto-declarados são honestos e valem conhecer: o gate de cor avisa que *"regex não é
parser CSS"* — cor crua dentro de media query escapa; e o guarda de fonte única declara que varre
**só dentro de uma pasta**, então dupla fonte fora dali passa verde. Foi exatamente o que
aconteceu: ele saiu verde enquanto treze duplicatas existiam, sete delas defasadas.

---

## E6 · Gates de CI

O dono único da resposta é `governance/required-checks-baseline.json`. O cruzamento usa o **nome
do job** — ou o id, quando não há nome — porque branch protection enxerga contexts, não nomes de
workflow. Contagens e listas copiadas para este texto envelhecem a cada promoção; por isso devem
ser recalculadas, nunca usadas daqui como estado atual.

### Fluxo interno e pontos de decisão

```text
PR aberto/atualizado
  -> cada workflow aplicável cria seus jobs
  -> job executa a máquina e preserva o exit code real
  -> GitHub publica um context pelo nome do job
  -> protection-drift cruza contexts vivos × baseline versionado
  -> [context required nasceu?]
       não -> deadlock/PENDING; falha de wiring, não do produto
       sim -> [conclusão success?]
                não -> merge bloqueado
                sim -> segue
  -> advisory vermelho informa, mas não bloqueia
  -> promoção/demissão só pelo processo versionado e autorização humana
```

Required inclui invariantes Tier-0 **e exceções explicitamente decididas em ADR**: schema,
integridade, superfície e qualidade podem estar na baseline quando houve promoção formal. Portanto
“required = somente Tier-0” não descreve corretamente a história viva; a baseline e suas emendas
são a autoridade.

### Armadilhas e falsos verdes

| Armadilha | Sintoma | Falso-verde ou deadlock | Defesa |
|---|---|---|---|
| contar workflows em vez de contexts | números não batem | um workflow com vários jobs é contado uma vez | cruzar nome/id do job |
| required com `paths:` no gatilho | PR fora do path nunca cria job | `Expected — waiting` permanente | required always-run + skip-as-pass interno |
| `cmd || echo warning` | step encontra regressão | exit vira zero e job sempre passa | capturar rc, publicar aviso e re-levantar rc quando aplicável |
| nome do job muda sem dança de proteção | job novo verde | protection espera nome antigo para sempre | rename coordenado com baseline/protection |
| advisory tratado como irrelevante | vermelho recorrente não bloqueia | dívida fica eterna | prazo, razão, mordidas e decisão explícita |
| promover sem controles negativos | gate verde no corpus atual | não se sabe se ele consegue falhar | bite test antes de enforcement |
| cachear contagem em documentação | página parece precisa | informação fica falsa no próximo flip | ponteiro para baseline + retrato datado quando necessário |

Promoção exige **mordida provada**: pelo menos dois PRs distintos em que o gate teria bloqueado
uma violação que **mergeou**. E promover não é virar uma chave — o mesmo PR tem que desembrulhar
o código de saída, anexar o registro de mordidas, atualizar os inventários, rodar verde antes, e
passar por janela e ratificação.

O critério geral de promoção exige mordida provada, mas a própria baseline registra exceções
soberanas e desvios conscientes. Antes de sugerir promoção, leia o histórico do context: não
inferir elegibilidade apenas pela cor recente.

### O advisory eterno

Gates advisory sujeitos ao calendário têm prazo e razão escrita; outros carregam isenção
grandfather registrada. A armadilha é tratar a isenção como validade técnica permanente: ela
retira o relógio, não prova que o gate continua útil nem que o exit code ainda morde.

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

### Fluxo interno e pontos de decisão

```text
resultado da onda + hashes do protótipo e da tela
  -> ds-report deriva placar do código
  -> SYNC_LOG recebe evento append-only com PR/data/veredito
  -> HANDOFF sobrescreve estado atual, próximo passo e bloqueios
  -> merge em main dispara webhook da memória
  -> Cowork consulta o conteúdo indexado
  -> [hash atual do design == hash provado? e hash atual da tela == hash provado?]
       sim -> evidência válida para aquele par
       não -> estado volta a pendente automaticamente
```

Branch local, conversa ou screenshot não atravessam esse fluxo. A unidade de retorno é um artefato
versionado e mergeado, ligado aos hashes que ele afirma representar.

### Armadilhas e falsos verdes

| Armadilha | Falso-verde | Defesa |
|---|---|---|
| marcar “aplicado” com checkbox | selo sobrevive à mudança da fonte | recalcular pelo par de hashes |
| atualizar só o handoff | estado atual parece certo | história e métrica ficam sem prova | escrever nos três canais conforme o papel |
| sobrescrever SYNC_LOG | arquivo fica limpo | auditoria do antes/depois desaparece | append-only |
| placar gerado com erro e `continue-on-error` | workflow diário fica verde | validar JSON/contagens e testar o produtor |
| PR aberto tratado como retorno | código está no remoto | Cowork ainda não recebe webhook de merge | distinguir branch, PR e main indexado |
| hash de apenas um lado | uma metade permanece igual | evidência sobrevive à mudança do outro lado | vincular design SHA **e** product SHA |

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

### Prova existente e limite residual

`bundle-transaction.test.mjs` já prova que evidência aplicada/testada é durável e se invalida
quando a fonte muda. A catraca de auditoria e os contratos dos canais também têm cobertura. Não se
deve propor novamente “teste de hash velho” como se estivesse ausente.

O ponto mais fraco é o **produtor do placar**: `ds-report.mjs` roda no metabolismo e pode escrever
o índice, mas não foi localizado um teste hermético do parser/agrupamento/`--worklist` equivalente
às baterias do transporte. A lacuna útil é injetar saída ESLint válida, malformada e parcial e
provar que erro de coleta nunca sobrescreve um placar bom com zero ou número incompleto.

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

### Fluxo interno e pontos de decisão

```text
comando `gh pr merge --admin <PR>`
  -> hook consulta arquivos do PR
  -> [tocou .tsx/.css/.blade.php e não há escape no corpo?]
       não -> não cria flag
       sim -> grava `timestamp|PR` com TTL de 5 minutos
  -> agente usa uma ferramenta reconhecida de navegador
       -> implementação atual apaga a flag imediatamente
  -> agente tenta declarar “pronto” via comando
       flag fresca -> bloqueia e pede navegação + screenshot
       flag ausente/expirada -> deixa seguir
```

Esse é o fluxo **implementado**. URL, rota, tenant, screenshot e SHA implantado aparecem na regra
operacional, mas não são gravados nem conferidos pela flag atual. Portanto, a ação de navegador é
um sinal de que alguém olhou; ainda não é um recibo que prove **o que** foi olhado.

### Armadilhas e falsos verdes

| Armadilha | Falso-verde | Defesa |
|---|---|---|
| visitar `/login` e chamar de smoke da tela | servidor responde 200 | abrir rota-alvo autenticada e executar o caso |
| screenshot antigo | imagem parece correta | recibo com SHA, URL, horário e hash da captura |
| navegador antes do merge | interação ocorreu | evento precisa ser posterior à flag do merge |
| tenant errado | tela funciona com outro conjunto de permissões/dados | declarar business/usuário de teste sem expor PII |
| olhar só o caminho feliz | tela abre | testar permissão negada, vazio, erro e ação mutável aplicável |
| terminal “simulando navegador” | comando retorna 200 | hook só aceita classe real de ferramenta de navegador |
| produção ainda não contém o SHA | UI antiga passa | confirmar versão implantada antes do veredito; a flag atual não faz essa conferência |

### Prova existente e limite residual

`post-merge-ui-smoke-required.test.mjs` cobre mordida, liberação, TTL e escapes; o teste de
registro confirma os pontos de wiring. Ele prova a **máquina de cobrança**, não a fidelidade do
smoke executado. A lacuna residual é identidade da evidência: ação de navegador em URL/tenant/SHA
errado pode satisfazer o evento técnico sem provar a tela modificada.

---

## E9 · Meta — quem vigia os vigias

O vigia roda cada catraca contra uma fixture boa e uma ruim e exige que a ruim **falhe pelo
motivo certo** — erro de execução não conta como morder. Em 25/08/2026: **80/80, 40 catracas
distintas**. Ele não é decorativo: pegou a introdução de uma dependência nova **antes do CI**.

Uma catraca vai além e testa o **terceiro estado**: referência ausente tem que sair "não medi",
nunca "regrediu" — porque colapsar "não consegui medir" em um estado do objeto medido é proibido.

### Fluxo interno e pontos de decisão

```text
registro de catracas
  -> para cada máquina, monta fixture boa e ruim
  -> roda boa -> deve sair 0
  -> roda ruim -> deve sair não-zero
  -> [falhou pelo motivo/assinatura esperada?]
       não -> erro de execução; não conta como mordida
       sim -> bite comprovado na fixture
  -> quando aplicável, roda terceiro estado “não medido”
  -> compara inventário registrado × scripts vivos
  -> publica total e lacunas; required só passa com todos os bites esperados
```

### Armadilhas e falsos verdes

| Armadilha | Falso-verde | Defesa |
|---|---|---|
| fixture ruim falha porque o script não iniciou | exit 1 parece mordida | conferir mensagem/código do motivo esperado |
| fixture boa nunca roda | só se prova sensibilidade | exigir especificidade: inocente passa |
| teste chama função reimplementada | teste verde, CLI real quebrada | invocar entrypoint produtivo sempre que possível |
| máquina viva fora do registro | placar 100% | comparar inventário com descoberta do disco/workflows |
| current-tree-only | repositório atual passa | não prova que uma corrupção seria detectada | sandbox boa/ruim |
| bite na fixture interpretado como uso real | 80/80 | hook pode ter zero ativações no mundo | telemetria de oportunidades × mordidas |

### O limite que o próprio E9 declara sobre si

*"O vigia prova que a defesa morde **na fixture**. Não prova que ela mordeu **no mundo**."*

O caso: um hook tinha vinte e seis asserções verdes — uma delas afirmando o próprio bug —
enquanto ficava silencioso em **116 de 116** oportunidades reais. Descoberto por acaso numa
conversa. Nenhum gate poderia ter pegado: o oráculo são os transcripts locais, fora do
repositório e invisíveis ao CI.

### Prova existente e limite residual

`design-memory-gate.test.mjs` prova que o workflow invoca `integrity-check.mjs` e que a árvore
corrente sai verde. Isso é prova de wiring + controle positivo, não um controle negativo isolado.
O teste que falta deve montar uma espinha temporária quebrada — arquivo canônico ausente, charter
sem `.tsx`, L-NN duplicado ou link-alvo morto — e exigir o IT exato e exit não-zero.

O vigia required continua tendo um limite inevitável: ele prova fixtures registradas. Cobertura do
mundo exige o contador de mordidas e auditoria das oportunidades elegíveis; nenhum meta-teste
local deduz sozinho que um hook deveria ter aparecido numa conversa real.

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

As propostas abaixo foram filtradas contra os testes já existentes. Não repetem bundle, rollback,
grafo, live-only, mirror freshness, contrato de região, casos ou invalidação de hash — esses já têm
controles positivos e negativos. O T6 aparece como **fechado durante a elaboração**, porque o
`main` avançou com a correção antes da publicação deste playbook. **“Proposto” também não significa
“novo required”**: primeiro se prova a máquina localmente; promoção de enforcement é outra decisão.

## Anatomia obrigatória de um teste deste fluxo

Todo teste novo deve declarar:

```text
fixture boa        -> o inocente passa                         (especificidade)
fixture ruim       -> o bug injetado falha                     (sensibilidade)
assinatura esperada-> falha pelo motivo certo, não por crash   (mordida)
terceiro estado    -> quando não mediu, não inventa veredito   (honestidade)
entrypoint real    -> testa a CLI/hook usado, não uma cópia    (fidelidade)
```

### T1 · Matcher de comparação: corpus positivo e negativo — E0 · prioridade P1

- **Alvo:** `.claude/hooks/design-compare-protocol.mjs` e seu registro em `settings.json`.
- **Fixture boa:** prompts como “compare o design com a produção”, frases reais de divergência sem
  verbo explícito e typos já observados.
- **Fixture inocente:** “compare preços”, “compare módulos”, “design do relatório” sem intenção de
  comparar interfaces.
- **Bug injetado:** estreitar o matcher retirando linguagem de divergência; em outro caso, ampliar
  para aceitar `compare` isolado.
- **Oráculo:** positivos começam com a tag do protocolo; inocentes têm stdout vazio; o teste de
  registro confirma o comando exatamente uma vez no evento correto.
- **Falso-verde evitado:** o teste atual prova um único caminho feliz; não mede falso-negativo nem
  falso-positivo.
- **Limite:** fixture ainda não prova uso real; contador de oportunidades × mordidas continua
  necessário.

### T2 · Cobertura viva fail-closed — E1 · prioridade P0

- **Alvo:** fronteira entre listagem autenticada do Cowork e `cowork-mirror-freshness`.
- **Fixture boa:** inventário vivo completo, paginado, com denominador e cursor final; espelho
  contém todos os itens.
- **Fixture ruim A:** listagem falha antes da última página. **Ruim B:** shell velho conhece 20
  entradas, mas a listagem viva contém uma 21ª. **Ruim C:** resposta vazia sem prova de que o
  projeto realmente está vazio.
- **Oráculo:** A/C saem `UNCHECKED`/não-zero para cobertura; B enumera `LIVE-ABSENT`. Nenhuma pode
  imprimir “ausentes: 0” ou `SYNC` global.
- **Falso-verde evitado:** hashes, grafo e rollback perfeitos sobre um universo incompleto.
- **Armadilha do próprio teste:** não mockar a listagem com dados derivados do espelho; isso faria
  os dois lados compartilharem o mesmo ponto cego.

### T3 · Âncora existente, porém da tela errada — E2 · prioridade P1

- **Alvo:** resolvedor/proveniência da âncora.
- **Fixture boa:** charter `TelaA` aponta para protótipo `TelaA`, com identidade verificável.
- **Fixture ruim:** charter `TelaA` aponta para um arquivo real, legível e não-shell de `TelaB`.
- **Controle:** um `n/a` legítimo permanece declaração válida e não é acusado.
- **Oráculo:** ruim resulta `ANCHOR_MISMATCH` ou `NÃO MEDIDO`, nunca `✓`; mensagem mostra as duas
  identidades sem tentar escolher por semelhança de nome.
- **Falso-verde evitado:** todos os guards de existência passam enquanto E3 mede a fonte errada.
- **Pré-requisito:** definir um sinal determinístico de identidade. Se ele não existir, manter a
  decisão humana explícita; não criar heurística por pasta, abordagem já reprovada.

### T4 · Identidade e ordem dos snapshots — E3 · prioridade P0

- **Alvo:** `style-fingerprint.mjs` e `design-diff.mjs`.
- **Fixture boa:** snapshot declara `side`, `screen`, `url`, `theme`, `viewport` e `sourceSha`
  coerentes com a posição esperada pelo comparador.
- **Fixtures ruins:** arquivos invertidos; `side=prod` com URL do preview; temas diferentes;
  telas diferentes; SHA ausente.
- **Oráculo:** recusa antes de calcular qualquer delta, com erro específico por campo. O stdout
  não pode conter tabela de divergências parcial.
- **Falso-verde evitado:** relatório matematicamente correto e semanticamente espelhado.
- **Compatibilidade:** se snapshots legados não têm os campos, classificá-los `NÃO MEDIDO` ou
  migrá-los explicitamente; inferir o lado pelo nome do arquivo recria a armadilha.

### T5 · Estabilização do render — E3 · prioridade P1

- **Alvo:** harness de captura, não o comparador puro.
- **Fixture boa:** página publica sinal de pronta e mantém assinatura igual por leituras
  consecutivas.
- **Fixture ruim A:** contagem de caracteres/âncoras cresce entre leituras. **Ruim B:** timeout sem
  sinal de pronta. **Ruim C:** DOM estabiliza, mas fonte ainda carrega e altera métricas.
- **Oráculo:** só captura após estabilidade de DOM **e** fontes; timeout sai `NÃO MEDIDO`, nunca
  “tela vazia” ou “divergente”.
- **Falso-verde evitado:** snapshot legítimo de um estado intermediário.
- **Armadilha do teste:** `sleep` fixo não é oráculo; máquina rápida passa e lenta flaka. Esperar
  condição observável.

### T6 · Meta-teste de conformidade ligado ao CI — E5 · FECHADO em 2026-08-25

- **Alvo:** `npm run test:conformance` já existente.
- **Implementação:** o PR #6232 o adicionou ao `guards-meta-gate.yml`, ao lado dos meta-testes de
  casos, domínio e fundação, reutilizando o mesmo `npm ci`.
- **Wiring:** os filtros de PR/push passaram a incluir `conformance-gate.mjs`,
  `conformanceGate.spec.ts` e seus baselines; tocar a máquina ou sua prova cria o job.
- **Prova:** a suíte executa os controles de sensibilidade, especificidade e não-vacuidade já
  versionados.
- **Falso-verde evitado:** suíte excelente no disco que nunca roda.
- **Escopo preservado:** ligar a prova não mudou o status required de nenhum context.

### T7 · Produtor do placar não sobrescreve saúde com coleta inválida — E7 · prioridade P1

- **Alvo:** `scripts/ds-report.mjs`, especialmente JSON, agrupamento, `--worklist` e `--write`.
- **Fixture boa:** saída ESLint conhecida com violações em dois módulos e duas regras; totals e
  agrupamentos esperados são assertados.
- **Fixture ruim A:** JSON truncado. **Ruim B:** arquivo referenciado desaparece durante a leitura.
  **Ruim C:** subprocesso termina não-zero após produzir stdout parcial.
- **Oráculo:** a execução ruim sai não-zero e preserva byte a byte o índice anterior; nunca grava
  zero, total parcial ou data nova.
- **Controle de não-vacuidade:** fixture com uma violação deve gerar total maior que zero e item na
  worklist.
- **Falso-verde evitado:** rotina diária verde servindo número congelado ou parcial.

### T8 · Integridade com árvore temporária quebrada — E9 · prioridade P1

- **Alvo:** `prototipo-ui/integrity-check.mjs`.
- **Pré-requisito técnico:** aceitar `--root` ou separar as funções puras; o teste não deve editar a
  árvore real.
- **Fixtures ruins independentes:** IT1 sem uma peça da espinha; IT2 charter sem `.tsx`; IT4 L-NN
  duplicado/com buraco; IT7 alvo documentado inexistente.
- **Fixture boa:** miniárvore completa e mínima.
- **Oráculo:** cada ruim sai não-zero e cita **somente o IT esperado**; a boa sai zero. IT6 advisory
  continua aviso e não vira hard por acidente.
- **Falso-verde evitado:** wiring verde + árvore atual saudável sendo confundidos com prova de que
  a máquina detecta corrupção.

### T9 · Recibo de smoke pertence ao merge e à tela — E8 · prioridade P2

- **Alvo:** evidência consumida pelo hook pós-merge.
- **Fixture boa:** evento de navegador posterior ao merge contém URL-alvo, screen id, SHA
  implantado, horário, tenant de teste e hash da captura.
- **Fixtures ruins:** navegador antes do merge; URL `/login`; SHA anterior; tela diferente; recibo
  fora do TTL.
- **Oráculo:** só a fixture boa limpa a flag. A ruim mantém o bloqueio e informa qual identidade não
  confere.
- **Falso-verde evitado:** “usei o navegador” em qualquer página ser aceito como prova da mudança.
- **Risco:** recibo não deve armazenar cookie, token, PII ou dados reais; apenas identificadores
  técnicos e hashes.

## Ordem sugerida de implementação dos testes

| Onda | Testes | Motivo | Critério de pronto |
|---|---|---|---|
| **A** | T2 + T4 | fecham os falsos-verdes de maior dano: universo incompleto e lados invertidos | controles bons/ruins verdes no CI advisory, sem mudar produção |
| **B** | T1 + T5 | endurecem ativação e aquisição da medida | corpus positivo/negativo + timeout `NÃO MEDIDO` |
| **C** | T7 + T8 | protegem memória derivada e o meta-verificador | escrita atômica preservada + ITs mordendo isoladamente |
| **D** | T3 + T9 | exigem contrato novo de identidade, portanto mais decisão | identidade definida sem heurística de pasta e sem persistir segredo |

Cada onda deve nascer advisory, provar a mordida e ser apresentada separadamente. Nenhuma promoção
a required, mudança de branch protection ou ação de merge está implícita nesta ordem.

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
