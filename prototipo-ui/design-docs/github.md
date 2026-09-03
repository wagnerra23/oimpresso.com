repo: wagnerra23/oimpresso.com
branch: main
path: prototipo-ui/cowork

## Last sync
date: 2026-09-01T19:36:00Z

### Updated in this project
- Playbook do **módulo Nota fiscal** completado para **9 ondas · 28 PRs** (`COLAR-NO-CODE-fiscal-notafiscal-ondas.md`): + Onda 8 (NFS-e municipal), Onda 9 (contingência SEFAZ), PR-H2 (telemetria + Jana).
- Leitura nova (árvore `d85c003ade47`) **corrigiu meu diagnóstico anterior**: emissão NF-e **tem motor** (`NfeService::emitirParaInvoice`, `DanfeService`, listener de pagamento) — falta superfície, não motor. `Modules/NFSe` está inteiro (Service + Job + `SnNfseAdapter` + 9 exceptions + 10 Pest) **sem tela de emissão**. IBS/CBS já tem serialização testada (`SerializacaoIbsCbsTest`) — o buraco é o cálculo.
- Achado 🔴: `tpEmis` **hardcoded em 1** (`NfeService.php:1198`) — não existe contingência; só um comentário citando EPEC 110140.
- Decisões [W] pendentes: 8 → **11**.

## Last sync
date: 2026-09-01T19:24:00Z

### Updated in this project
- Playbook de ondas do **módulo Nota fiscal** escrito como PONTE (`COLAR-NO-CODE-fiscal-notafiscal-ondas.md`): 7 ondas · 22 PRs, tudo derivado de leitura do `main` neste turno (árvore `49e6e333a057`).
- Fatos medidos: 7 telas Fiscal com trio completo (7 charter + 7 casos) · 40 UC citados por teste · **8 CU sem UC** · **0 contrato `fiscal-*` em `prototipo-ui/contrato/`** · `fx-*`+`var(--fis)` fora do DS (preflight 72) · 5 GAP-SPEC ⛔ [W] · emissão/XML/DANFE inexistentes.
- Não medido, declarado como tarefa PR-F0: linha do Fiscal no `screen-coverage` (E2E/a11y/VRT).
- Divergência registrada: `US-FISCAL-022` está `todo` no SPEC, mas `CertHealthCheckCommand` + teste existem no `main`.

## Last sync
date: 2026-09-01T18:15:00Z

## Last sync
date: 2026-09-01T20:10:00Z

## Last sync
date: 2026-09-01T21:05:00Z

## Last sync
date: 2026-09-01T21:48:00Z

## Last sync
date: 2026-09-01T22:30:00Z

### O erro pior: usei a existência de uma regra como argumento, sem medir
Eu justifiquei não mexer na barra mobile do Atendimento dizendo *"`.om-mobile-tab{flex:1}`, ou seja as abas esticam"*. Mas `.om-mobile-tab` é a classe dos **botões antigos** — o TabBar do DS renderiza os dele, sem ela. Medido: `flexGrow: 0`, botões de 115px e 70px numa barra de 664px, **72% dela vazia**. `document.querySelectorAll('.om-mobile-tab').length === 0`.

**Causa-raiz:** o full-bleed do nav estava certo, mas o `flex:1` que fazia os botões dividirem a largura vivia na classe legada dos botões, que o DS não emite — barra full-bleed com botões *hugging content*.

**É a terceira vez** que deixar de emitir a classe removeu o trabalho útil dela (glyph 0×0, respiro lateral, agora o stretch). **Mas é a primeira em que o erro foi de raciocínio, não de execução:** eu li a regra na folha e a tratei como prova, quando `getComputedStyle(botão).flexGrow` responderia em uma linha. Existência de regra ≠ regra em efeito — junta-se às outras quatro da família (`textContent` não revela ellipsis, zero num stub não é evidência, nó presente não é nó visível, número absoluto não prova alinhamento).

**Uma edição, atrás de prop explícita:** `itensFull` no `CliTabs` escreve `flex: 1 1 0` + `minWidth: 0` + `justifyContent: center` nos botões, no efeito que já existia para `warn`/`off`. `justifyContent` no nav não serviria — os botões continuariam com largura de conteúdo. `inbox-extras` passa a prop.

**Medido:** Atendimento — duas abas de **332px** cada (metade exata de 664), zero espaço vazio, `flexGrow: 1`. E confirmei que **não vazou**: Clientes segue com `flexGrow: 0` e larguras por conteúdo (114/128/154), alinhado em 284.

### Três fileiras que meu script de padronização não alcançou
O script só casou call sites que **tinham `className`** no mapa — e três ficaram de fora, duas nas telas de maior tráfego.

**Clientes e OS (−24px):** esses dois call sites não passam `className` nem `pad`, e o padding lateral deles vinha de `.cli-moduletopnav{padding:0 24px}` — a classe que o adaptador **deixou de emitir**. Nav ficou com 0. Corrigido com `pad={24}` explícito. Medido: Clientes 284/284, OS 284/284.

**Orçamentos (+24px):** aqui o nav está **dentro** de `.os-toolbar`, que já paga 24 — somava. Removi o `pad`… e continuou 308, porque a folha `.os-tabs{padding:8px 24px 0}` assumiu quando o inline saiu. A declaração correta é `pad={0}`: zero inline vence a folha. Medido: 284/284, nav com `padding-left: 0px`.

**`.om-mobile-tabs` não era defeito:** conferi a folha — `.om-mobile-tabs` não tem padding lateral e `.om-mobile-tab{flex:1}`, ou seja as abas esticam. Full-bleed é intencional; `pad={0}` está certo.

### Regras órfãs que eu credarei por engano
O verificador apontou: `.mp-header` **não existe no DOM** — o `CliPageHead` não repassa `className`. Logo minhas edições de `modulo-padrao.css:5` e `crm-blade.css` (28→24 e 20→24) eram **código morto**, e o efeito visual correto vinha do `pad={24}` inline. Comentei as duas regras com o motivo, e removi a prop `className="mp-header"` morta do `ModuloPadrao` — senão a próxima leitura confia em CSS que não roda.

**Erro meu no caminho:** pus o comentário explicativo dentro do `return (` como `{/* … */}`, o que cria dois filhos e quebra o JSX. O app não subiu (`window.__go is not a function`) até eu mover o comentário para fora. E quase li o erro como stale, porque o log ainda mostrava a URL antiga — só o `typeof window.__go` resolveu a dúvida.

### Padronização do respiro lateral em 24px — decisão de [W], medida antes de aplicar
[W]: *"eu prefiro padrão, pode redesenhar. qual o padrão mais usado?"* Contei antes de escolher:
- **24px** — `.os-page-h{padding:12px 24px}` (`styles.css:2087`), usado por **~40 telas** (clientes, os, fiscal, governança, conector, inbox, hrm, usuários, cms, boletos, forja, planilhas…). O comentário na própria regra diz que o canon do git é 24/24/14.
- **28px** — só o `ModuloPadrao` (`modulo-padrao.css:5-7`), cobrindo 12 módulos.
- **20px** — `.mfg-tabs`, `.vrep-tabs`, `.pb-root>.mp-header`. **32px** — `.fin-subnav`. Casos isolados.
- **18px** — os 5 drawers (`cd-tabs`, `fin-drawer-tabs`, `cr-dwr-tabs`, `ptr-drawer-nav`, `rep-drawer-nav`).

**24px ganhou por larga margem.** Aplicado: `modulo-padrao.css` (header, abas, corpo) 28→24 e o `pad` do adaptador junto; `.mfg-tabs` e `.vrep-tabs` 20→24; `.fin-subnav` 32→24; `.pb-root>.mp-header` 20→24. E os corpos que tinham padding próprio, senão o header alinharia e o conteúdo não: `.pt-body` 28→24, `.rep-list` 28→24, `.ptr-list` 28→24, `.pb-body` 20→24.

**18px dos drawers fica** — é outro nível, consistente em 5 de 5, e recuo menor dentro de painel é correto. Padronizar não é achatar tudo num número.

**Medido depois, na mesma tela:** Ponto — eyebrow, título, abas e corpo **todos em 284**. repair 284/284, Patrimônio 284/284, Produtos 284/284, Manufatura 284/284 (era 284/280), Boletos 284/284 (era 284/292). Duas convenções de página viraram uma; os dois desalinhamentos que eu havia escalado como "pré-existentes" deixaram de existir.

### Ritmo vertical quebrado pelo `contextoWrap` — 14px que deixaram de contar
**Causa-raiz:** com `contextoWrap` o `<p>` do eyebrow é renderizado como irmão **acima** do PageHeader, então os 6px de `padding-top` do wrapper — que eu havia calibrado para **somar** com os 14px internos do `<header>` do DS e dar os 20px do canon — passaram a ser o único espaço acima do eyebrow, e os 14px do DS viraram um **vão** entre eyebrow e título.

Medido antes: eyebrow em 6px do topo (canon: 20px) e vão de 17px até o título (legado: 2px, `chat-jana.css:45` `.jc-id p{margin:2px 0 0}`).

**Uma edição, condicional à estrutura** (mesma forma do `pad` e do `contextoWrap` — a terceira vez que a resposta é "depende de quem paga o quê"):
- `padTop = contextoWrap ? (estreito ? 16 : 20) : 6` — com eyebrow irmão o wrapper paga os 20px inteiros do canon (`modulo-padrao.css:5`, com o degrau de 16px do `:8`); sem ele, os 14px do DS cobrem e 6px completam.
- `marginBottom: -11px` no eyebrow — não é gambiarra, é compensação de um valor **não configurável**: os 14px de `padding-top` do `<header>` do DS não são prop. Os −11px devolvem os ~3px do legado.

**Medido depois, `getBoundingClientRect` relativo ao wrapper:** Ponto, Patrimônio, repair e Compras — eyebrow em **20px**, `h1` inalterado em **36px**, gap de **3px**. Jana em 6px, sem eyebrow irmão — não afetada, como esperado.

### Decisão que eu não havia declarado: o contexto mudou de lugar
No legado `.jc-id` o markup era `<h1>` e **depois** o `<p>` — contexto **abaixo** do título. Hoje é eyebrow **acima**. Isso é conformidade com o DS (o docblock do `PageHeader` prescreve "linha de contexto acima do título"), mas é **mudança de ordem de conteúdo** na Jana e nos 12 módulos, e eu não registrei como decisão em nenhum momento. Fica registrada agora, para [W] ratificar ou reverter — não é detalhe de implementação.

### O `pad` do CliTabs repetia o erro que eu já tinha corrigido no header
**Causa-raiz:** o `CliTabs` escreve `paddingInline` **inline**, e inline vence a folha. Eu vinha passando `pad` por hábito (14 / 12 / 0) em vez do padding lateral que cada fileira **tinha** na folha — então toda fileira migrada deslocou horizontalmente em relação ao conteúdo da própria tela (Conector, Governança, Manufatura, Boletos: aba em 274, título em 284).

É literalmente a lição que eu havia escrito para o `cli-pagehead` — *"o respiro lateral é de quem conhece a página, não do adaptador"* — e não apliquei ao `CliTabs`. Terceira vez que o mesmo princípio aparece: **o adaptador não inventa espaçamento.**

**Uma edição estrutural:** `pad` deixou de ter default. Sem `pad`, **nada é escrito** e a classe legada (ou o pai) paga o respiro dela; passar `pad` virou declaração explícita, com o valor medido na folha daquela fileira. Depois apliquei os 17 valores medidos:
```
cnx-tabs · gov-tabs · pf-tabs · vd-drawer-tabs · os-tabs → 24
mfg-tabs · vrep-tabs → 20        fin-subnav → 32        ofx-tabs → 4
cd-tabs · fin-drawer-tabs · cr-dwr-tabs · ptr-drawer-nav · rep-drawer-nav → 18
arq-tabs · crmf-tabs · hrm-tabs · pd-abas · ptr-subtabs · gov-subtabs · cd-subnav → sem pad
```

**Medido pelo teste certo — `left` da 1ª aba × `left` do título, na mesma tela:** Conector 284/284, Governança 284/284, Arquivos 284/284, Patrimônio 288/288, Perfil 284 (a tela não tem `h1` próprio).

**Dois casos que restaurei fielmente e continuam divergindo — pré-existentes, não mexi:** Manufatura (aba 280, título 284: a folha diz `.mfg-tabs{padding:0 20px}` e o header da tela usa 24) e Boletos (aba 292, título 284: `.fin-subnav` tem 32). O legado já era assim; corrigir para 24 seria redesenhar sem ter sido pedido. Fica registrado para [W] decidir se é intenção ou dívida.

**Efeito colateral verificado:** ao parar de emitir `cli-moduletopnav`, a regra `.jm-tabs.cli-moduletopnav{padding:0}` (jana-merge.css:11) deixou de casar — mas jana-merge passa `pad={0}` explícito, então o comportamento se mantém.

### Seis `Kebab` idênticos → um adaptador do DS
A auditoria achou **seis** `function Kebab({items})` praticamente iguais (modulos, officeimpresso, superadmin, comissionados, usuarios, connector): mesmo estado, mesmo listener de `mousedown`, mesmo SVG de três pontos, mesma pele `.cli-kebab-*`. **Nenhuma navegava por teclado.** O DS publica `Kebab` (bundle:4606), que não reimplementa menu — delega itens, teclado, clique-fora e ancoragem ao `DropdownMenu`.

`cli-kebab.jsx` traduz o vocabulário (`action`→`onSelect`, `danger`→`tone`, `sep`→`separator`) e cada tela mantém o seu título, que é conteúdo. ~6.700 chars de cópia viraram ~1.750 de adaptador + 6 delegações de 5 linhas.

**Medido renderizando:** Usuários 9 kebabs · Módulos 32 · OfficeImpresso 9 · Comissionados 5 · Conector 3 — `aria-haspopup="menu"`, itens corretos, tom `danger` aplicado, e **pele legada `.cli-kebab-*` = 0** em todas. Selecionar dispara a ação: em Usuários, "Ver detalhes" fechou o menu e abriu o drawer.

**Não verificado:** Superadmin — 0 kebabs na vista padrão (ficam numa sub-vista que não abri). O arquivo compila e a delegação é idêntica às outras cinco.

**Correção de método no caminho:** tentei provar a navegação por teclado com `KeyboardEvent` sintético e concluí que não funcionava. Errado duas vezes — o DS rastreia o item ativo por **estado**, não por foco do DOM (eu media `document.activeElement`, a propriedade errada — quinta vez nessa família), e evento sintético não exercita confiavelmente o handler. Comportamento interno do DS não é meu de provar; validei o que é meu — a tradução dos itens e o disparo da ação.

### Regra R7 nova — peça do DS reimplementada localmente
R4/R5 não pegavam os seis Kebab porque eles **não publicam em `window`** — são funções locais de módulo. R7 casa `function <Nome>` cujo nome bate com um componente do DS e cujo corpo **não delega**.

Rodada no build, achou e ficou contado com motivo: `produto-blade` (Kebab com `position: fixed` calculado — dentro de célula de tabela com overflow, qualquer menu ancorado é recortado; só migra quando o DS portalizar), **5 `Toolbar`** (estoque, crm-blade, venda-blade, catchup-shared, clientes — o local orquestra colunas+export+densidade+período+FilterChip; o `Toolbar` do DS é de 3 zonas, precisa mapeamento tela por tela) e `Timeline` de cobrança (família payment-gateway, outra conta de design).

### Texto normativo clipado — a reserva foi medida pelo elemento errado

### Texto normativo clipado — a reserva foi medida pelo elemento errado
**Causa-raiz:** a reserva de título embutida no cap (240px) foi dimensionada pelo `h1` mais longo ("Etiquetas TAG vestuário", 236px), mas a coluna tem **três** linhas e a mais longa é o **subtitle do Ponto** — 244px. Faltavam 4px, e a ellipsis cortava "Portaria MTP 671/20…". O guia do DS é explícito: *"Never paraphrase the article number"* — cortar o número da norma viola a mesma regra.

**Medi todas as rotas que passam pelo CliPageHead** em vez de estimar de novo:
```
Ponto    h1  86 · subtitle 244  ← maior
repair   h1 218 · subtitle 240
Compras  h1 117 · Patrimônio 137 · Estoque 109
```
Reserva de 240 → **264px** (cobre 244 com 20px de folga pra fallback de fonte): `cap = calc(100vw − 592px)` → 924px: 332 · 1280px: 688 · 1440px: 848.

**Medido depois, `scrollWidth === clientWidth` no subtitle:** Ponto **264×264**, "Ponto eletrônico · Portaria MTP 671/2021" completo. repair, Compras, Patrimônio, Estoque e Jana — nenhum subtitle nem h1 truncado. Simulando as larguras-alvo: 1280 e 1440 com ações em **uma linha** (medido por centro) e subtitle sem truncar.

**A fragilidade é o ponto, e fica registrada:** essa conta só existe porque o slot de ações do DS é `flex: 0 0 auto` e não cede. Enquanto for assim, a reserva é um número que precisa ser revisitado se aparecer um subtitle mais longo. A correção definitiva é no DS — ações que encolhem ou viram `Kebab` em largura apertada — e está na lista de pendências.

### Eu generalizei a regra da Jana para o app todo — e perdi conteúdo de domínio
Eu havia concluído que "o eyebrow truncar não é regressão", citando `jana-merge.css:17`. **Essa regra é escopada em `.jc-page` — vale pra Jana, não pros módulos.** No CSSOM existem três regras, e a do módulo é diferente:
```
.jc-page .jc-id h1, .jc-page .jc-id p { nowrap; ellipsis }   ← Jana: os DOIS
.mp-header .jc-id h1                  { white-space: nowrap } ← módulo: SÓ o h1
.cb-root .jc-id p                     { white-space: normal }
```
No módulo o `<p>` de contexto **não tinha regra** → envolvia e mostrava o texto inteiro. Em `repair` isso custava 133px: "REPAIR · Matriz + 1 filial · 14 folhas · 11 pendentes" lia "…· 14 F…" e **"14 folhas · 11 pendentes" desaparecia** — contagem operacional, conteúdo de domínio, nos 12 módulos.

**Causa-raiz:** o slot `context` do DS é um eyebrow de uma linha com ellipsis; mapear `contexto[]` para ele troca "texto completo em 2 linhas" por "texto cortado em 1".

**Uma edição, com a diferença virando prop** (mesma forma do `pad`, porque a causa é a mesma — a estrutura é por página): `contextoWrap`. Quando pedido, o `<p>` é renderizado aqui como **irmão** do PageHeader, com os mesmos tokens do slot do DS (mono 11px, uppercase, `.04em`, `--text-dim`) — não é override. `ModuloPadrao` passa; a Jana não.

Tentei antes descer o excedente pro `stats` e é pior: o DS não põe separador entre `subtitle` e `stats[0]`, e a linha de `stats` também é nowrap+ellipsis — truncava igual, com formatação pior. **O DS não tem nenhum slot que envolva** — registrado como lacuna.

**Medido, `scrollWidth × clientWidth`:** repair — eyebrow 608×608 **sem truncar**, texto completo, `white-space: normal`, irmão do header, alinhado em 288; h1 218×218. Ponto, Estoque, Compras, Patrimônio — eyebrow sem truncar em todos, com as contagens visíveis. Jana — eyebrow **truncando**, `nowrap`, como o legado dela mandava.

### Erro de aritmética no cap das ações — `min()` escolhe o menor
**Causa-raiz:** `min(300px, 34vw)` colapsa para a **constante 300px** em todo viewport ≥883px (acima disso `34vw > 300px`), então o wrapper de ações tinha largura fixa independentemente do espaço disponível no header. Eu havia trocado "título truncado sempre" por "**ações quebradas sempre**" — inclusive a 1280px (Larissa, balcão) e 1440px (Wagner, escritório), as duas larguras que o CLAUDE.md nomeia como alvo, onde havia espaço de sobra.

**Uma edição, com o cap derivado de medição em vez de chutado:** sidebar 260px + respiro ~56px ⇒ o header mede `100vw − 316px` (conferido: vw 924 → header 608). Reservando 240px pro título (o mais longo dos módulos, "Etiquetas TAG vestuário", mede 236px) + 12px de gap:
```
maxWidth: max(240px, calc(100vw - 568px))
```
→ 924px: **356** · 1280px: **712** · 1440px: **872**. O cap cresce com a tela e o piso protege telas estreitas.

**Validado nas duas larguras, como pedido.** A 924px o cap resolve a **356px** (= 100vw − 568) e as 561px de ações quebram em 3 linhas — legítimo, não cabem mesmo; o `h1` de `repair` mede **218×218**, sem truncar. Simulando 1440px (container em 1180px + cap de `calc(1440px − 568px)`): header 1124, ações 566 em **uma linha** — os tops [24, 20, 20, 20] diferem por alinhamento vertical, não por quebra (quebra dá ~39px de diferença, como medi antes), e o `h1` continua 218×218.

**Varredura final a 924px, comparando irmãos em cada tela:** Ponto 288/288, Estoque 312/312, Compras 288/288, Patrimônio 288/288, Jana 284/284 — nenhum título truncado, nenhuma tela em stub.

### O chip de frescor estava no slot errado — quinta regressão da migração
**Causa-raiz:** o `PageHeader` do DS renderiza o slot `freshness` DENTRO da flex row do `h1`, com `flex: 0 0 auto` (bundle ~5343) — desenhado para uma pílula compacta de `StatusBadge` ("recente", "fresc"), não para um botão de 115px com "Atualizado 09:42". No markup legado o chip sempre viveu em `.jc-header-r`, junto das ações. Pô-lo no `freshness` moveu 115px + 8px de gap para a coluna do título, roubando largura do `h1` permanentemente.

Em `repair` o título "Assistência técnica" truncava (218×173) numa coluna de 296px. **Frágil por comprimento de título:** "Compras" e "Patrimônio" escapavam por serem curtos — não é caso de borda, é a largura de preview do usuário.

**Uma edição:** o chip volta a ser o primeiro item de `actions` (a posição que tinha no legado), e o slot `freshness` fica livre para quem quiser a pílula curta do DS. O wrapper de ações já tem `flexWrap`, então absorve.

**Medido depois:**
- `repair` — h1 **218×218** (não trunca), linha do título com um único filho (`H1`), chip é `<button title="Reapurar agora">` na coluna direita, h1 e 1ª aba em 288.
- Jana — h1 80×80, linha do título só com `H1`, h1/aba/corpo em 284.
- **Clique testado:** "Atualizado 09:42" → **09:14**. A affordance de reapurar continua viva.

### O respiro lateral é de quem conhece a página — não do adaptador
Eu tinha cravado `pad = 28` como "convenção do shell". Errado: **duas estruturas incompatíveis convivem no app.**
- **módulo** (`.mp-page`): a página NÃO tem padding lateral e cada bloco carrega os seus 28px (`modulo-padrao.css:5`/`:6`/`:7`).
- **Jana** (`.jc-page`): a PÁGINA paga 24px e os irmãos usam 0.

Com 28 fixo, a Jana ficou com padding **duplo** — header em 312, abas e corpo em 284.

**Uma edição na causa:** default invertido para `pad = 0`; quem conhece a estrutura passa o valor — `ModuloPadrao` passa `pad={28}`, a Jana não passa nada.

**E a lição de medição, que é a terceira da mesma família:** eu medi `tituloX: 312` na Jana e aceitei, sem comparar com o X das abas e do corpo **daquela tela** — só o Ponto tinha sido comparado. **Número absoluto não prova alinhamento; comparação entre irmãos da mesma tela prova.** Junta-se a: `textContent` não revela ellipsis, zero num stub não é evidência, nó presente não é nó visível.

**Medido depois, comparando irmãos em cada tela:**
- Jana — eyebrow **284**, h1 **284**, 1ª aba **284**, `.jc-kpis` **284**.
- Ponto — eyebrow **288**, h1 **288**, 1ª aba **288**, conteúdo do `.pt-body` **288** (o bloco começa em 260 e paga os 28px). Glyph 20×20.

### Duas regressões minhas, mesma raiz — na direção oposta
Ao parar de emitir `.mp-header`/`.mp-glyph` eu removi as regras **sem auditar o que elas faziam**. A rodada anterior tinha me ensinado que repassar classe legada faz herdar regras não auditadas; a lição só ficou completa agora: **deixar de emitir também remove o trabalho útil que a classe fazia.**

**1. O glyph de módulo colapsou para 0×0 — invisível nos 12 módulos.** Causa-raiz: o SVG do `JcIcon` não tem `width`/`height` nem classe próprios; dependia inteiramente de `modulo-padrao.css:40` → `.mp-header .mp-glyph svg{width:20px;height:20px}`. Corrigido dimensionando no próprio adaptador. Medido: span **20×20**, svg **20×20**.
- **Por que passou:** eu leí `leadingTag: "svg"` — existência do nó — e concluí sucesso. **Presença no DOM não é visibilidade; `getBoundingClientRect()` é.** Terceiro falso negativo da mesma família: `textContent` não revela ellipsis, zero num stub não é evidência, nó presente não é nó visível.
- A placa 40×40 tintada de `modulo-padrao.css:39` **não** volta, de propósito: o docblock do DS diz que o canon flat a aposentou (é do `shared/PageHeader.tsx`, CONGELADO). Perder a placa é correto; perder o ícone não era.

**2. Desalinhamento de 4px entre título, abas e corpo.** Eu havia posto `padding: 0 24px` solto; a convenção do módulo é **28px** em todos os blocos (`modulo-padrao.css:5`, `:6`, `:7`). Corrigido para 28px como prop (`pad`), com o degrau responsivo de ≤900px → 16px via `matchMedia` (padding inline não é alcançado por media query), e o respiro vertical do canon (6px aqui + os 14px próprios do DS = 20px, `margin-bottom: 14`).

**Medido depois:** Ponto — glyph 20×20, título em **288** e abas em **288** (alinhados), h1 86×86 sem truncar. Jana — avatar 24×24, h1 80×80, último botão em 857. Ambas fora de stub.

Também apaguei o comentário obsoleto que ainda dizia "maxWidth percentual", contradito pela nota seguinte.

### Regressão que eu introduzi no `cli-pagehead` — e a lição de medição
O rewrite truncava o `h1` em todas as telas que passam pelo CliPageHead (Jana + os 12 módulos do `ModuloPadrao`).

**Causa-raiz, uma frase:** eu concatenei `papel` dentro do `title`, que o `PageHeader` do DS renderiza com `nowrap` + `ellipsis`, em vez de usar o slot `subtitle` que existe pra texto secundário — e o container de ações do DS é `flex: 0 0 auto` (bundle:5369), então toda a compressão caía na coluna do título.

**Por que passou pela minha verificação:** eu leio `h1.textContent`, que devolve a string COMPLETA independentemente do truncamento visual. É a mesma família de falso negativo de "zero num stub", com outra roupa. **`textContent` nunca revela ellipsis — `scrollWidth > clientWidth` revela.**

**Três correções, cada uma numa causa medida:**
1. `papel` → `subtitle`, não concatenado. `h1` de 495×142 para **66×66**.
2. Cap das ações em `vw`, não em `%`. A primeira tentativa usou `min(420px, 45%)` e **não bindou** — porcentagem resolve contra o container de ações do DS, que tem largura automática: é circular. Com `min(300px, 34vw)` o bloco caiu de 387px para 300px.
3. **Respiro lateral**: o `PageHeader` do DS tem `padding: '14px 0'` e espera o pai dar o respiro — isso vinha das classes `.jc-header`/`.mp-header` que parei de emitir, e o último botão passou a encostar na borda (right = 924 num viewport de 924). Com 24px (convenção do shell): **900 em Ponto, 861 na Jana**.

**Uma tentativa que piorei e revertí:** descer o excedente do contexto pro `stats` produziu "Portaria MTP 671/2021Agosto/2026" — o DS não põe separador entre `subtitle` e `stats[0]` — e truncava a linha do subtitle em 486×352. Voltei tudo pro eyebrow.

**O eyebrow truncar NÃO é regressão:** conferi o legado — `jana-merge.css:17` tem `white-space:nowrap; overflow:hidden; text-overflow:ellipsis` em `.jc-id p`. Era o comportamento anterior, e é o desenho do DS pra uma linha de identidade longa.

**Medido depois, por `scrollWidth × clientWidth` (não por `textContent`):** Ponto — h1 66×66 ok, subtitle 304×304 ok, botão em 900. Jana — h1 80×80 ok, subtitle 271×271 ok, botão em 861. Ambas fora de stub.

**Nova pendência do DS:** o slot de ações deveria poder encolher, ou levar as ações a um `Kebab` em largura apertada. Hoje quem passa ações precisa se autolimitar.

### `cli-pagehead.jsx` deixou de ser dono — o DS absorveu a peça
O `PageHeader` do DS agora declara no próprio docblock que **"absorve o antigo cli-pagehead"**, com `leading` (marca na linha de base do título), `context` (linha acima do título) e `freshness`/`freshnessRel`. Era a pendência que eu havia registrado; foi atendida. O arquivo virou adaptador de ~50 linhas e só traduz vocabulário.

**Uma escolha de desenho no caminho:** o slot `leading` do DS é um dot/ícone renderizado inline no `h1` (aria-hidden, accent, `margin-right: 8px`). Passar a inicial CRUA ali dava **uma letra solta de 22px em roxo** — não é avatar. Medi, e passei a montar o `Avatar` do DS (que existe: `name`/`initials`/`size`). Resultado medido na Jana: chip 24×24, raio 8px, cor própria, texto 11px dentro do slot.

**As classes `.jc-header/.jc-avatar/.jc-id/.mp-header` não são mais emitidas** — de propósito, aplicando a lição da rodada anterior: repassar nome de classe legada faz o componente herdar regras que ninguém auditou.

### Verificado renderizando (não em stub)
- **Jana** (`__go('chat')`, sem "Ver no Blade atual"): `h1` 22px, linha de contexto "OIMPRESSO MATRIZ · biz=164 · v1404 legacy migrado", botão de frescor presente, avatar como chip do DS, `.jc-header`/`.jc-avatar` legados = 0.
- **Ponto** (header de módulo, caminho do `ModuloPadrao` que cobre 12 módulos): `h1` "Ponto · Ponto eletrônico · Portaria MTP 671/2021", `leading` é um `<svg>` (o glyph), frescor com "Reapurar" presente, `.mp-header`/`.jc-header` = 0, aba "Telas do Ponto" no TabBar do DS.

### Os dois arquivos de dono temporário morreram
`cli-seg.js` → adaptador do `Segmented` do DS (rodada anterior). `cli-pagehead.jsx` → adaptador do `PageHeader` do DS (agora). `cli-tabs.jsx` segue como adaptador fino, só pelo que o TabBar ainda não tem (`warn`/`off` por aba).

### Continua pendente
- As 6 mini-DS (~60 peças) — decisão de [W], maior bloco restante.
- `role` no `Segmented` do DS: crava `tablist`; 2 call sites são semanticamente rádio.
- `warn`/`off` por aba no `TabBar`.
- Lado git: instalar o guard em `scripts/qa/`, remover `prototipo-ui-patch/`, os 2 arquivos com `?` no nome, ordem de argumentos do `design-diff`/`style-fingerprint`, automação de emissão do bundle.

### Lição de método da rodada anterior: zero num stub não é evidência
Eu concluí que 3 das 5 migrações estavam OK lendo `bespoke = 0` em rotas que **nunca renderizaram a tela**:
- `__go('forja')` → ModuleStub. A rota real é `projects`/`teammcp` (app.jsx:907).
- `__go('metas')` → ModuleStub. A seção de metas vive dentro da Jana (rota `chat`).
- `__go('planilhas')` → renderiza a LISTA; `.pl-ed-abas` só existe depois de clicar "Abrir".

Nos três casos o seletor deu 0 porque o componente **não estava na árvore**, não porque a migração funcionou. Numa sessão em que dois edits meus (`return ({...})` em jana-merge e fiscal-page) falharam **só no render**, aceitar zero de tela não renderizada é o mesmo erro de sempre.

**Regra que fica:** antes de ler `bespoke = 0` como sucesso, confirmar que a tela renderizou — `h1` esperado e ausência de "Ver no Blade atual". **Rota errada devolve zero para qualquer pergunta.**

### Verificado de fato, com medição (separando do que não foi)
- **Jana `.jm-per`** — o edit de maior risco da rodada (dois controles no mesmo `<span>`, `setPer(p)` objeto → busca por chave). Aberta por `__go('chat')`, `h1` "Jana · Analista IA", sem stub. Dois `[role=tablist]`: "Período" (mai/abr/mar 2026) e "Visão das metas" (Farol/Cadastro). **Validado por CLIQUE, não por leitura:** cliquei abr/2026 → `aria-selected` migrou para o 2º; cliquei Cadastro → visão trocou e o segmented de período **desapareceu**, que é o comportamento original (`{!cadastro && …}`) preservado; voltei a Farol → período reapareceu mantendo abr/2026 selecionado. `.jm-cat` e `.jmc-view` bespoke = 0 **com a tela na frente**.
- As outras 4 (`fj-viewtabs` em `projects`>Trabalho, `fj-int-tabs` em Integrador, `cr-dwr-tabs` em `rb-assinaturas`>linha, `pl-ed-abas` em planilhas>Abrir) foram confirmadas renderizando — rótulos reais, contadores preservados, ativa em accent, e o `+`/dica de planilhas mantidos como irmãos.

### Os 26 achados, triados — 5 migrados, 7 classificados
A regra que usei pra decidir, e que ficou escrita no guard: **segmented é escolha EXCLUSIVA de 2–5 opções FIXAS.** Clicar no ativo e desmarcar → é filtro (chip). Conjunto vindo de dados → chip. Seleção múltipla, árvore ou dropdown → outro componente.

**Migrados (eram segmented/aba de verdade):**
- `.fj-viewtabs tf-subnav` (forja) — Lista · Quadro · Gantt, com ícone → `CliSeg`
- `.fj-int-tabs` (forja-integra) — 2 abas com contador → `CliTabs`
- `.cr-dwr-tabs` (cobrança recorrente) — abas de drawer → `CliTabs`
- `.jm-cat` + `.jmc-view` (jana-merge) — **dois** controles no mesmo `<span>`: período e visão → dois `CliSeg`
- `.pl-ed-abas` (planilhas) — a série de abas virou `CliTabs`; o botão `+` e a dica ficaram irmãos

**Classificados com motivo (não são segmented — waiver de mérito):**
- `.om-flt-pills` (inbox ×4) — chip de filtro; conjunto vem de dados, tem estado `muted` ("em breve") e `data-testid` por botão usado em teste de contrato
- `.cms-chips` — dois grupos de filtro + separador + botão "Limpar" heterogêneo
- `.fj-groupby` ×2 (forja-tarefas) — clicar no ativo **desmarca**, e o conjunto sai de `TK_TASKS`
- `.ess-kb-cat` — árvore aninhada (categoria → seção → artigo), não fileira
- `.fin-contas-filter` — dropdown de seleção **múltipla** com checkbox
- `.vi-salvas` — dois toggles independentes + dropdown, não grupo exclusivo
- `.os-page-h-r` (forja) — barra do header (campainha + ⌘K + nav agrupada)

### Onde o ciclo está
Fileiras de aba e segmented têm **um dono cada** (`CliTabs` → TabBar do DS, `CliSeg` → Segmented do DS), o guard tem 6 regras e um detector que não depende de vocabulário de nomes, e todo waiver tem motivo escrito. Console limpo em todas as rotas testadas.

### Continua pendente (sem mudança)
- As 6 mini-DS (~60 peças) — decisão de [W], maior bloco restante.
- `role` no `Segmented` do DS (crava `tablist`; 2 call sites são semanticamente rádio).
- `warn`/`off` por aba no `TabBar`.
- `PageHeader` do DS com `context`/`freshness` — enquanto não entrar, `cli-pagehead.jsx` segue dono.
- Lado git: instalar o guard em `scripts/qa/`, remover `prototipo-ui-patch/`, os 2 arquivos com `?` no nome, ordem de argumentos do `design-diff`/`style-fingerprint`, automação de emissão do bundle.

### Updated in this project (rodada anterior, 17:40)

### Causa-raiz única (uma frase)
**`pg-styles.css` traduz as utilitárias Tailwind stone/white para tokens do DS, mas toda regra é escopada em `.pg-shell-scope`, e a raiz de Boletos era só `.os-page bol-root` — a classe de escopo nunca estava lá, então a paleta crua renderizava contra o `--text` claro do cockpit dark.**

Não era ajuste de cor: era a camada de tradução desligada. **Uma edição** — pendurar `pg-shell-scope` na raiz — resolveu os 9 `.bg-white` de uma vez. Medido: valores de KPI passaram de `#fff` sob texto `oklch(0.94)` (contraste 1,14:1) para fundo `oklch(0.30)` sob o mesmo texto ≈ **11:1**.

### Segmented bespoke migrado (25º call site)
A fileira "Todos · Em aberto · Pagos · Vencidos · Cancelados" era utilitária Tailwind stone crua, ilha light dentro do shell dark, com **zero a11y**. Agora passa pelo `CliSeg`. Medido: `role="tablist"`, `aria-label="Situação do boleto"`, `aria-selected` `true,false,false,false,false`, e **zero** `.bg-stone-100/80` restante.

### O ponto cego do guard era o mais importante — 4ª geração do detector
As três anteriores dependiam de vocabulário de nomes, cada vez mais acima: string da classe → tag `<nav>` → sufixo `-seg` → **e agora se descobriu que também as palavras de estado** (`on|active|selected`). Controle estilizado por utilitárias não usa nenhuma: o ativo é `bg-white shadow-sm text-stone-900`, o inativo `text-stone-600`. Foi assim que Boletos passou por todas as versões.

Sinal que não depende de vocabulário: **a className diverge entre irmãos por um ternário que compara ESTADO × ITEM DO LOOP** (`tab === t.id`). Ternário genérico não serve — medi 39 achados, a maioria `cn(..., cond ? "a" : "b")` de qualquer coisa. Com a exigência de identificador×identificador: **39 → 26**.

### Inventário novo, contado e não escondido
Os 26 achados em 16 arquivos são **em boa parte reais** — controles de seleção que nenhuma versão anterior do guard via, em vocabulário de chip/pill/groupby: `.om-flt-pills` ×4 (inbox), `.fj-groupby` ×2 e `.fj-viewtabs` (forja), `.cms-chips`, `.ess-kb-cat`, `.jm-per` (período), `.pl-ed-abas`, `.vi-salvas`, `.fj-filterbar2`. Paginação, navegação de apresentação e barras de ação entraram nos vocabulários ignorados, com o motivo escrito.

**Não migrei os 26 nesta rodada** — é lote grande e cada um precisa de olho na tela. Fica contado, com nome e arquivo, não silenciado. Correção honesta do que afirmei antes: "waivers 17 → 3" valia para fileiras de aba em `<nav>`/`<div>`; **não** cobria controles estilizados por utilitárias, que eram invisíveis ao detector daquela geração.

### Updated in this project (rodada anterior, 16:52)

### A raiz: "a classe legada só tem regra de layout" era suposição, não medição
Eu vinha acrescentando `cli-moduletopnav` a todo nav com esse argumento. Medi as folhas: **as regras se dividem em duas famílias, e o TabBar do DS escreve geometria INLINE (inline vence folha)** —
- regras que **ADICIONAM** (padding, `flex-wrap`) vencem em silêncio e **quebram o DS**;
- regras que **ESCONDEM** (`display:none`) **perdem em silêncio**.

Herdei as duas sem auditar nenhuma. É a mesma raiz das rodadas anteriores: supor em vez de medir o que o nome legado declara.

**Correção na raiz, não no sintoma:** o adaptador **parou de acrescentar a classe legada**. O gancho estável passou a ser `.ds-tabbar` — a classe do próprio DS, que é o nome honesto (o nav *é* um TabBar do DS). Esconder virou **prop `hidden`** (escreve `display:none` inline), não efeito colateral de folha.

### Consertado, com medição
- **Impressão** (regressão sistêmica): `@media print{…{display:none}}` não vencia o inline do DS — toda fileira de abas passaria a sair no papel. Regra reescopada pra incluir `.ds-tabbar` com `!important`. Medido no CSSOM: `.cli-pageheader-r, .cli-moduletopnav, .ds-tabbar { display: none !important; }`.
- **`flex-wrap` vazando** (`.os-tabs`, `.cb-nav`, `.cd-subnav`, `.ptr-subtabs`, `.arq-tabs`): o CliTabs agora escreve `flexWrap` **sempre explícito**. Sem isso a fileira empilhava em 2 linhas onde o DS quer rolar com fade — e o `syncOverflow` do DS nunca disparava, porque com wrap `scrollWidth == clientWidth` sempre. Medido em Orçamentos: `flexWrap: "nowrap"`, altura 44px (era 71px em duas linhas).
- **Onde o wrap era intencional**, virou prop: `.cd-subnav` (chips curtos em drawer estreito) e `produto-cadastros`. A declaração morta em `.ptr-subtabs` saiu da folha, com o motivo escrito.
- **CSS morto pela remoção do wrapper**: `.ptr-subtabs nav, .ptr-drawer-nav nav{overflow-y:hidden}` mirava um nav DENTRO da classe; depois da migração o nav É a classe, então o clamp se perdia. Reescrito sem o descendente.
- **`.om-mobile-tabs`**: a ocultação vinha de `display:none` + `@media(max-width:1100px){display:flex !important}`. Virou estado explícito (`hidden={!mobile}` com `matchMedia`).

### Medido e NÃO confirmado como regressão
`.fin-root .fin-subnav{display:none}` (boletos): medido no DOM vivo — **`.fin-root` não existe nessa página**, então a regra nunca se aplicou. O mecanismo era o mesmo; o caso, não.

### Não medível neste viewport — declarado, não afirmado
O iframe está em 924px, abaixo do corte de 1100px, então a barra mobile aparecer aqui é o comportamento **correto**. A ocultação acima de 1100px depende do `hidden` que acabei de introduzir e **não pude medir nesta largura** — vale conferir em janela larga.

### O guard estava errado do mesmo jeito, duas vezes — agora casa forma, não nome
O verificador achou dois pontos cegos estruturais, cada um com instância VIVA no app. O padrão do erro é o que importa: **as três gerações do guard dependiam de um nome, só cada vez mais acima na hierarquia.**
- 1ª geração casava a string `cli-moduletopnav` → não via `.pd-abas`, `.hrm-tabs`, `.gov-tabs`.
- 2ª casava `<nav` → não via `.os-tabs`, que é fileira de aba montada em `<div>` (vendas-page, orc-page).
- 2ª casava sufixo `-seg` → não via `.rel-dens`, `.fin-density`, `.vd-vista`.

Nas duas vezes a instância que escapou foi achada varrendo o **DOM**, não o código. A lição não é "melhorar o regex": é que casamento por nome só encontra o que já foi catalogado.

**R1 e R2 unificados por forma, sem tag e sem sufixo:** qualquer elemento com 2+ `<button>` irmãos + alternância de estado ativo. Aba vs segmented pelo `role` quando existe, senão por contador/quantidade.

**O discriminador foi medido, não intuído.** A primeira versão por forma deu 26 achados com ~20 falsos positivos (Cancelar/Salvar em rodapé de drawer, ações de linha, bulkbar). Guard que grita demais é guard ignorado. Adicionei a exigência de **série** — um `.map()` sobre as opções, ou a mesma variável de estado comparada 2+ vezes — e mais os cortes de `role="listbox|option|menu"`/`aria-haspopup`. Resultado: **26 → 8 achados, todos reais ou classificáveis.**

### Migrado nesta rodada
- `.os-tabs` ×2 (vendas-page, orc-page) — a pele em `<div>` que o R1 não via. Tinha drift real: ativa com sublinhado de 1px e inativas reservando 2px transparente, então trocar de aba deslocava a linha em 1px.
- `.rel-dens` (relatorios), `.fin-density` (financeiro, com `iconOnly` do DS), `.vd-vista` (vendas — achado na mesma leitura, não estava no relatório).
- `.fx-chips` de abas do manifesto DF-e (fiscal-subpages), `.om-mobile-tabs` (inbox-extras), `.ofx-tabs` (oficina-os) — os 3 reais dos 8 achados do guard novo.
- `cli-seg.js` ganhou passagem de `iconOnly`.
- **Layout:** `.rel-cont` estava sendo esmagado por `.rel-acoes` (482px nowrap que não cede) — "7 linhas apuradas · página 1 de 1" comprimia em 55×69px, 4 linhas. Com `flex:none` + `nowrap`: 228×17, uma linha. Medido antes e depois.

### Classificado, não silenciado
`.fx-chips` de visões salvas (fiscal-page) e de tipo de evento (fiscal-subpages) ficam como chip, não segmented: quantidade variável, `data-tone`, filtro manual entra e sai — é vocabulário de `FilterChip`/`TagChip` do DS, não de Segmented com 2–5 opções fixas.

### Medido depois (DOM vivo, rota por rota)
Vendas, Orçamentos, Relatórios, Financeiro, Fiscal, Atendimento: todas respondem, `aria-label` real em cada barra e segmented (Lente · Campo de data · Densidade em Financeiro), `.fin-density` e `.os-tabs` bespoke não existem mais. Console limpo.

### Updated in this project (rodada anterior, 15:18)
- **22 fileiras de aba migradas** (as que estavam em waiver na rodada anterior): arquivos, connector, crm-ficha, governance ×2 (tabs + subtabs), hrm, essenciais, configuracoes, cliente-drawer760 ×2 (cd-tabs + cd-subnav), financeiro (drawer), repair (drawer), vendas (drawer), vendas-extras (vrep), manufacturing, perfil, boletos, patrimonio ×3, estoque ×2, relatorios.
- **Waivers do R1: de 17 para 3.** Os 3 que ficam são de mérito, não de dívida: `app.jsx` (topbar/ph-nav é chrome do shell, vocabulário do AppSidebar), `vendas-extras` (vd-modnav mistura salto pra módulos irmãos + PDV — é barra de navegação), `superadmin` (sa-cfg-nav é rail vertical de 2 linhas por item). Encolher a lista É a métrica.

### Terceira categoria descoberta — regra R6 nova
- 14 call sites usavam `<TabBar>`/`<Segmented>` do DS **direto**, por fora do dono. Não é pele paralela (o componente é o certo), mas perde o que o adaptador garante: **nenhum passava `ariaLabel`**, então toda fileira do app se anunciava como "Sub-navegação" pro leitor de tela; e vários embrulhavam a barra num `<div>` só pra dar padding — o anti-padrão que o DS acabou de eliminar com `inset`.
- 7 migrados (patrimonio ×3, repair, estoque ×2, relatorios). Onde o wrapper tinha irmãos de verdade (spacer + botão em patrimonio:455 e repair:210), o wrapper FICOU — colapsar ali quebraria a linha.
- 5 em waiver com motivo: `venda-v3*` ×4 (vêm de outra conta de design, [W] 2026-08-13) e `dash-legacy` (tela em substituição).

### Erro meu que a medição pegou
- Em `arquivos-page` havia **dois caminhos no mesmo ponto de render**: `TabBar` do DS no ramo vivo, `<nav>` bespoke no fallback. Migrei o fallback — código morto — e deixei o vivo intacto. Só apareceu ao medir `aria-label` no DOM: a barra vinha sem rótulo e sem as classes que eu tinha passado. Corrigido pra um só caminho, com `data-contract` descendo do `<div>` pro próprio nav.
- Lição: **um dono por peça vale por ponto de render, não por arquivo.** A R6 agora pega esse formato.

### Medido depois (DOM vivo, sem hover)
Arquivos, Estoque, Assistência, Perfil, Manufatura, Patrimônio: `aria-label` real em cada barra (não mais "Sub-navegação"), aba ativa em `oklch(0.7 0.15 295)` = accent roxo, inativas em `--text-dim`. Nenhum erro de console.

### Continua pendente
- `role` no `Segmented` do DS (crava `tablist`; 2 call sites são semanticamente rádio).
- `warn`/`off` por aba no `TabBar` (hoje por efeito no `cli-tabs.jsx`).
- `PageHeader` do DS com `context`/`freshness` — enquanto não entrar, `cli-pagehead.jsx` segue dono.
- Lado git, inalterado: instalar o guard em `scripts/qa/` (agora com 6 regras), remover `prototipo-ui-patch/`, os 2 arquivos com `?`, ordem de argumentos do `design-diff`/`style-fingerprint`, automação de emissão do bundle.

### Updated in this project (rodada anterior, 14:32)
- **Espelho `_ds/` estava DESATUALIZADO — era o risco T6 acontecendo.** O bundle local exportava 43 componentes; o DS vivo tem 54. Faltavam exatamente as "7 lacunas verdadeiras" que eu vinha tratando como ausentes: `Segmented`, `Widget`, `Toolbar`, `Kebab`, `Timeline`, `DataGrid`, `PresenterMode`. Copiei bundle + `colors_and_type.css` + `styles.css` + `cockpit_domains.css` do DS vivo. Lição: a ausência de automação de emissão não é risco teórico — eu afirmei "o DS não publica Segmented" com base num espelho velho, e estava errado.
- **`TabBar` agora aceita o contrato no próprio `<nav>`** (`...rest` + `className`/`ariaLabel`/`pad`/`size`/`off`/`icon`/`inset`). Era o pedido registrado como 10ª pendência; o DS atendeu (ver `HANDOFF-2026-08-31-tabbar-pageheader.md` no projeto do DS).
- **`cli-tabs.jsx` encolheu de 151 → ~90 linhas**: caiu o wrapper `display:contents`, caíram os `setAttr`/`setStyle` por render. Sobrou tradução (`n`→`count`, `icon:string`→nó, classe legada, pad responsivo) + `warn`/`off` por aba, que o TabBar ainda não tem.
- **`cli-seg.js` deixou de ser dono do desenho** — virou adaptador de 45 linhas sobre o `Segmented` do DS; `cli-seg.css` apagada. O desenho do segmented agora é do DS em 24 call sites.
- **As 3 telas de Produto foram migradas** — o bloqueio caiu junto com o wrapper. `produto-blade` (2 fileiras), `produto-analises`, `produto-cadastros` (com `off` para aba sem permissão, que mantém o "—" visível).

### O guard achou o que eu não achava — e o R1 estava fraco
- `.pd-abas` (abas do catálogo de Produtos, `produtos-page.jsx:667`) **passou por mim, pelo guard e pelo verificador**. Só apareceu porque a barra de rolagem dela ficou visível numa screenshot. Migrada.
- Causa: o R1 casava a string `cli-moduletopnav`. **Guard que depende de nome de classe só pega o que já foi catalogado.** Reescrito para detectar por FORMA (`<nav>` + `<button>` irmãos com alternância de ativo).
- Medição nova com o R1 por forma: **38 fileiras de aba em 31 dialetos de classe**. Dessas, ~16 não são aba (stepper, breadcrumb, rail vertical, barra de filtro — vocabulário diferente, TabBar não serve) e estão classificadas por sufixo; ~22 são fileiras reais em 19 dialetos (`hrm-tabs` ×3, `gov-tabs`+`gov-subtabs`, `cd-tabs`+`cd-subnav`, `vd-modnav`, `cnx-tabs`, `crmf-tabs`, `arq-tabs`, `mfg-tabs`, `pf-tabs`, `sa-cfg-nav`, `vrep-tabs`, drawers…).
- Essas 22 entraram como **waiver nomeado com motivo**, não silenciadas: o guard fica verde no CI hoje e a dívida fica contada. Apagar a linha do waiver ao migrar é a métrica.
- R5 ganhou os 11 nomes novos do DS — sem isso, publicar `window.Segmented` passaria batido.

### Continua pendente
- 22 fileiras de aba em waiver (lote grande, cada uma precisa de olho na tela).
- `role` no `Segmented` do DS: ele crava `tablist`. Dois call sites são semanticamente rádio ("Pessoa física/jurídica" em cliente-form e cliente-drawer760) — perdi a nuance de propósito, pra não manter pele paralela viva. Pedido ao DS, não decisão fechada.
- `warn`/`off` por aba no `TabBar` (hoje ainda por efeito no `cli-tabs.jsx`).
- `PageHeader` do DS com `context`/`freshness`: o handoff do DS diz que `leading` já existe e que `context` exige ADR proposta. Enquanto não entrar, `cli-pagehead.jsx` segue dono.
- Lado git, inalterado: instalar o guard em `scripts/qa/`, remover `prototipo-ui-patch/`, os 2 arquivos com `?`, ordem de argumentos do `design-diff`/`style-fingerprint`, automação de emissão do bundle.

### Regressão corrigida no mesmo dia — e a lição de método
- `cli-tabs.jsx` gravava `b.style.color = ""` para "limpar" a cor de aba não-`warn`. O TabBar do DS escreve `color` INLINE em cada botão (accent na ativa, `--text-dim` nas outras); a string vazia DELETAVA esse valor, e o React nunca o reescrevia — a prop dele não mudou. **Toda aba não-warn nascia preta sobre fundo escuro** (contraste 2,26:1), em ~20 arquivos, porque o adaptador é compartilhado.
- Passou por screenshot porque os handlers de hover do DS regravam a cor: qualquer aba que recebesse o mouse ficava certa dali em diante, e a captura pegava o estado pós-hover. Só `getComputedStyle` na carga mostrava.
- Regra que fica: **efeito que pisa em estilo de terceiro RESTAURA o valor dele, não limpa.** E validação de cor é por medição no DOM, nunca por imagem.
- Medido depois da correção, rota OS, sem hover: ativa `oklch(0.7 0.15 295)` (accent), inativas `oklch(0.72 0.005 90)` (`--text-dim`), `Atrasadas` `oklch(0.8 0.13 75)` (`--warn`).

### Para reportar ao DS (não é do app)
- O bundle novo registra 1 erro interno próprio: `Norte/norte-app.jsx` — `Cannot destructure property 'SCENES' of 'window.NORTE'`. Arquivo de demo do DS; nada no `oimpresso.com.html` o consome.

### Updated in this project (rodada anterior, 20:44)
- **Guard escrito e rodado**: `cowork-pele-paralela.mjs` (destino `scripts/qa/`). 5 regras — R1 pele de aba fora do dono · R2 segmented próprio · R3 mini-DS nova · R4 nome publicado por 2 arquivos · R5 nome que colide com componente do DS. Waivers com motivo escrito (Produto ×3, inbox-page por `data-testid`, essenciais por ser paginador de mês). Simulei as regras sobre os 190 arquivos do build antes de entregar.
- **O guard achou 3 coisas que a auditoria manual não achou**: (1) `cli-seg.js` + `cli-seg.css` JÁ EXISTIAM de uma sessão anterior, melhor documentados que o `cli-seg.jsx` que eu escrevi hoje — eu tinha criado um SEGUNDO dono do segmented. Mantive o pré-existente (desenho trilho, `n`/`icon`/`full`, regra de print), registrei no host, apaguei o meu e reapontei os 23 call sites. (2) Existem **SEIS** mini-DS, não quatro: `CatchupUI` e `PontoUI` também. (3) 10 segmented que eu tinha perdido na varredura manual — connector, financeiro-page ×2, financeiro-relatorios, notificações ×2, prefs, venda-blade, venda-index.
- Fallbacks legados removidos dos 4 adaptadores (hrm-ui, funcoes, oficina, modulo-padrao): a ordem de carga garante a peça, e o fallback só reintroduzia a pele velha — além de acender o guard.
- Verificado com o app rodando: Financeiro, Vendas, Notificações, Preferências, Conector, Governança, Essenciais, Ponto e Jana — segmented com a MESMA pele em todas, pela primeira vez.

### Lição desta rodada (vale mais que o diff)
Auditoria manual encurta a lista; a tela seguinte alonga. O guard achou em 1 execução três coisas que eu não achei lendo — inclusive um erro MEU cometido 40 minutos antes (dono duplicado do segmento). Máquina no CI > lista no chat.

### Updated in this project (rodada anterior, 20:12)
- **Segmented consolidado**: `cli-seg.jsx` (`window.CliSeg`) é agora o dono único do desenho, e 23 fileiras em 20 arquivos passaram a usá-lo — as 14 peles paralelas (`.seg .pb-seg .hrm-seg .gov-seg .fnc-seg .cd-seg .cf-seg .oi-seg .pt-seg .fin-seg .sa-seg .cms-ed-seg .ptm-seg .vt-msg-abas`) viraram só classe de LAYOUT. Inclui os 3 `Seg` compartilhados (HrmUI, funcoes, oficina), que cobrem outros call sites por tabela.
- **Header de página unificado**: `cli-pagehead.jsx` (`window.CliPageHead`). `JanaHeader` (chat-jana) e o `Header` do ModuloPadrao eram a MESMA anatomia com dois markups; agora ambos delegam. Verificado: Jana, Ponto e Estoque idênticos ao antes.
- Nenhum CSS foi apagado nesta rodada — de propósito. O desenho vem inline (vence a classe) e as regras legadas seguem valendo pelo que são boas: posição, margem, alvo de 44px em `pointer:coarse`. Foi regex de CSS que causou o estrago da rodada anterior; aqui só houve substituição literal, com contagem de ocorrências antes de cada troca.
- Os três `cli-*.jsx` subiram no host pra antes de `chat-jana` e `modulo-padrao`, que os consomem.

### Por que o DS não é o dono destas duas peças (medido)
- **Segmented**: o DS não publica nenhum. Tem `TabBar` e `PeriodBar` (que usa um segmented interno, não exportado). Lacuna registrada no cabeçalho do `cli-seg.jsx`.
- **PageHeader**: o do DS é header de índice (título + stats + ações). Não tem glyph/avatar, linha de contexto (tenant · código · versão) nem chip de frescor com reapuração — que é exatamente o que estas telas usam. Trocar hoje perderia função. Quando o DS aceitar `glyph`/`contexto`/`frescor`, o `CliPageHead` vira adaptador e morre.

### Continua pendente
- As 3 telas de Produto (`produto-blade`, `produto-analises`, `produto-cadastros`): seguem com nav nativo. Bloqueio inalterado — o `TabBar` do DS exige wrapper e o travamento da rota não foi isolado.
- Do lado do git: remover `cowork/prototipo-ui-patch/` (47 arquivos), os 2 arquivos com `?` no nome, a ordem de argumentos de `design-diff`/`style-fingerprint`, e a automação de emissão do bundle.

### Updated in this project (rodada anterior, 19:52)
- Colis\u00e3o de nome resolvida: o stepper local virou `window.OiFsmStepper` (era `FsmStepper`, mesmo nome do componente do DS — quem ganhava dependia da ordem de carga). 9 refs em `fsm-stepper.jsx`, `os-page.jsx`, `repair-page.jsx`, `financeiro-page.jsx` + o coment\u00e1rio do host. Verificado: pipeline de OS, Assist\u00eancia t\u00e9cnica e Financeiro pintam.
- `CliTabs` ganhou as duas afordâncias perdidas na convers\u00e3o: padding lateral responsivo (16px em ≤900px, via matchMedia — o padding \u00e9 inline e a media query n\u00e3o alcan\u00e7a) e o estado `warn` das abas (texto no tom de aten\u00e7\u00e3o; usado em "Atrasadas" nas etapas de OS).
- Arquivos de nome duplicado: `app.jsxv=eb21` e `modulo-padrao.jsxv=mp1` apagados. `app.jsx?v=eb2` e `clientes-page.jsx?v=ph3` N\u00c3O sa\u00edram — o `?` no nome \u00e9 lido como query pela ferramenta de arquivo daqui. S\u00e3o inertes (o host aponta pra `eb25`/`ph5`), mas precisam de remo\u00e7\u00e3o do lado do Code.

### Updated in this project (rodada anterior, 19:39)
- Medição de duplicação feita no `main` (árvore `84b62eb785e8`): as 4 mini-DS (`AcessosDS` 20 consumidores · `PBUI` 15 · `ModuloPadrao` 12 · `HrmUI` 7) e a colisão `FsmStepper` seguem intactas no git; `CliTabs` tem 0 ocorrências lá. Achado novo: `prototipo-ui/cowork/prototipo-ui-patch/` tem 47 arquivos que não são build (cópias velhas de os-page/fsm-stepper/inbox-page/clientes-page.css, 13 CSS `_dark-tier*`, 6 `.php`, 8 `.tsx`, `inertia.css`, tokens parcial).
- Migração de abas para o `TabBar` do DS APLICADA aqui (2ª tentativa, agora sem tocar em Produtos): `CliTabs` registrado no host antes de `modulo-padrao`, wrapper renomeado pra `.cli-tabs-slot` (o nome `.cli-tabs` colide com o DS). 14 fileiras convertidas em 13 arquivos — modulo-padrao (cobre 12 módulos), os, clientes, fiscal, crm-blade ×2, crm-blade-forms, crm-portal, venda-blade, venda-blade-caixa, financeiro-legado, compras-extras, integra-extras ×2, jana-merge.
- EXCLUÍDAS de propósito, por decisão de [W] nesta rodada: `produto-blade.jsx`, `produto-analises.jsx`, `produto-cadastros.jsx` — seguem com nav nativo (o travamento da rota Produtos não foi isolado; ver `## Padronização — por que não entrou`).
- Verificado com o app rodando: Estoque, Compras, OS, Clientes, Fiscal, Vendas, CRM e Financeiro pintam com o TabBar do DS; Produtos continua vivo com nav nativo. Dois erros de sintaxe pegos e corrigidos no caminho (`return ({...})` inválido em jana-merge e fiscal).

### Não verificado
- Rota da Jana (jana-merge) e o caixa do venda-blade: não achei a chave de rota via `__go` pra confirmar visualmente. A conversão é a mesma forma já validada em modulo-padrao e o arquivo compila.
- Perdas conhecidas da conversão: o modificador `warn` das abas de OS e o padding responsivo (`0 16px` em ≤900px) das abas de módulo — o CliTabs escreve padding inline fixo.

### Updated in this project (rodada anterior, 19:05)
- Build restaurado integralmente do `main` (330 arquivos) depois que a padronização de abas/segmented travava a thread no boot da rota Produtos. O `main` é a fonte; o espelho local voltou a ele.
- Reaplicado sobre o build restaurado: correção do TDZ em `inbox-page.jsx` — `useInboxKeyboard` estava declarado ACIMA do `useMemo` de `filteredConvs`, e o array `deps` é avaliado na chamada do hook, então a tela de Atendimento quebrava em todo render ("Cannot access 'filteredConvs' before initialization"). Verificado: Atendimento abre com 8 conversas, sem erro.
- NÃO reaplicado (fica como proposta, não como código): a migração de 31 fileiras de aba para o `TabBar` do DS e de 19 segmented para um componente único. Motivo e medições em `## Padronização — por que não entrou`.

## Screen map
| Tela | Arquivos do build |
|---|---|
| Atendimento | inbox-page.jsx (única alteração local sobre o `main`) |

## Padronização — por que não entrou
Diagnóstico medido, para quem retomar:
- O host compila cada `text/babel` em série no boot e re-renderiza o app a cada tick da fila de módulos. Qualquer custo por render é multiplicado nessa janela.
- Folha de estilo nova precisa entrar DIFERIDA (`media="print" onload="this.media='all'"`), como todas as outras. Um `<link>` render-blocking derruba o boot em qualquer rota.
- `.cli-tabs` JÁ EXISTE no DS (`styles.css`: `margin: 8px 0 14px`). Não batizar wrapper com esse nome.
- Em Produtos o `<nav>` das abas é item direto do grid do page-head (`produto-catalogo.css`: `.pd-abas{grid-column:1/-1;overflow-x:auto}`). Envolver o nav num wrapper TRAVA a thread na carga dessa rota: React congela (contadores de `createElement` param), nada aparece no console, a página nunca pinta. Testado e reprovado: sem a classe legada no nav, com `display:contents` no wrapper, com a folha nova fora, com o CSS restaurado do git. A causa exata entre o `TabBar` do DS e o layout dessa página NÃO foi isolada.
- Saída definitiva: o `TabBar` do DS precisa aceitar `className`/`aria-label`/`pad`/`data-contract`/`off`/`size`/`icon:string` NO PRÓPRIO `<nav>`, sem wrapper. Enquanto exigir wrapper, a migração é insegura em telas cujo CSS foi escrito para o nav ser o item de layout.
- Erro meu a não repetir: regex de CSS com `[^{]*` atravessa a fronteira da regra. Apagou vizinhos inocentes, incluindo a regra que estilava `.os-btn` (compartilhada no app) em `produto-blade.css`. Deleção de CSS tem de ser regra por regra, com o texto conferido.

## Sync history
### 2026-08-31 (anterior, revertido)
Migração de abas/segmented para o DS exportada neste projeto e depois desfeita pelo restore acima. Nada disso chegou ao git.
