---
sessao: "00"
titulo: SINCRONIZAR Sidebar — índice do playbook (fonte da máquina embutida em §7)
autor: "[CC]"
criado: 2026-09-10
base: wagnerra23/oimpresso.com@main (tree af09f7c3a0fd · lida 2026-09-10 10:37–10:38 UTC)
destino_no_main: prototipo-ui/design-docs/cowork-inbox/sidebar/playbook/
regra: este índice é PEDIDO (lista de threads com prefixo e prova), não inventário. Estado é derivado (§2-bis), nunca escrito. Nunca em prototipo-ui/cowork/ (guard R1).
---

# SINCRONIZAR Sidebar — playbook

> **Absorve, não duplica:** `handoff-sidebar/PEDIDO-CODE.md` (Cowork, 2026-08-28, árvore `7d7c8fd14310`). Aquele pedido **envelheceu**: 3 dos 4 deltas que ele pedia já foram resolvidos no `main` — e um deles foi resolvido **ao contrário** do que ele supunha. Este playbook o substitui; não abrir doc novo pra Sidebar (anti-scatter).
> A Sidebar é **página única** → **onda = seção** (corpo/menu · modos · topo), nunca "o sidebar inteiro".

## 0 · Landing — como esta pasta desce
- **A unidade é a PASTA inteira** (`00-INDICE.md` + 6 `NN-*.md`). Rota: DesignSync `get_file` de cada `.md` → `--export-from <dir>`; `.md` roteia pra `prototipo-ui/design-docs/cowork-inbox/sidebar/playbook/`.
- **Só `.md` roteia.** Por isso a fonte da máquina não é arquivo: é o primeiro bloco ```json deste índice (§7). Nada `.json`/`.mjs` solto no pacote.
- `.md` em `prototipo-ui/cowork/` é **proibido** (guard R1) — se o roteador mandar pra lá é erro de rota, não exceção.

## 1 · LEVANTAR — o pedido de 2026-08-28 relido contra o `main` de hoje

> **Correção 2026-09-10 (leitura de `shared.ts` posterior à 1ª emissão do índice):** `AUTO_RAIL_MAX_W = 1280` e `AUTO_RAIL_MQ = "(max-width: 1280px)"` — **inclusive**. O build daqui usa `innerWidth < 1280`: **a 1280px exatos o vivo nasce em rail e o protótipo nasce expandido** (e 1280 é a largura do monitor do [W]). Além disso a chave diverge: `LS.SB_MODE = "oimpresso.sb.mode"` no vivo × `"oimpresso.sidebar.mode"` no build. Detalhe na thread 02. Confirmado na mesma leitura: `SidebarMode = 'expanded' | 'rail'` — sem `hidden`, como a thread 04 previu.

Denominadores usados (Sidebar não tem rota própria — o denominador é **componente**, não rota): **D1** `resources/js/Components/cockpit/Sidebar.tsx` · **D2** `resources/js/Layouts/AppShellV2.tsx` (quem monta e o que passa) · **D3** o build daqui (`sidebar.jsx` 622 linhas · `app.jsx` shell) · **D4 (runtime)** o contrato backend `app/Sidebar/*.php` (4 arquivos: `SidebarGhost` · `SidebarGroup` · `SidebarMenuItem` · `SidebarPrimaryAction`).

| delta do PEDIDO-CODE (28/08) | o que o `main` diz **hoje** (medido nesta sha) | veredito | thread |
|---|---|---|---|
| **A · aba Chat órfã do TSX?** | `Sidebar.tsx:5-7` e `:519`: *"SidebarChat e SidebarTabs removidos — conv switcher migrou pra `Pages/Copiloto/Chat.tsx`"*, `UI-0011 single-pane`, 2026-05-05. `AppShellV2` aceita `conversas` só por compat (`_conversasIgnored`). | **resolvido ao contrário**: não é órfão no vivo — é **legado no protótipo**. Quem está atrasado é o build daqui | 01 |
| **B · atalho "G X"** | existe: `useSidebarShortcut` importado em `AppShellV2.tsx` (hook próprio, instalado no shell e não no menu, por causa do retorno antecipado) · `.sb-kbd` em `Sidebar.tsx:537` | **feito no vivo** — nada a pedir | — |
| **C · ghosts na sidebar × ADR 0180** | `Sidebar.tsx:542-544` **implementa** `GHOST_TETO = 5` citando o protótipo, e `:569` fatia a lista | **o código foi na frente da ADR** — o conflito não sumiu, mudou de lado: ou emenda a 0180, ou reverte o vivo | 05 (bloqueada [W]) |
| **D · promover `SidebarReopenHandle`** | sem ocorrência em `Components/cockpit/`; `SidebarMode` do shell é só `'rail' \| 'expanded'`; o único uso vivo segue em `Pages/Financeiro/_cowork-bundle/shell-app.jsx:509-510` | **o delta real, ainda de pé** | 04 |

**Produção à frente do protótipo (🔵 puxar, não refazer)** — medido em `AppShellV2.tsx` nesta sha:
- **auto-rail (ADR UI-0030):** sem escolha persistida, o modo vem da largura (`matchMedia(AUTO_RAIL_MQ)`), e segue a largura ao vivo por listener. O build daqui decide **só no mount** e por `innerWidth < 1280` (`app.jsx:629-630`) — tem auto-rail, faltam o **listener** e o **inclusive** (ver a correção acima e a thread 02).
- **persistência só da escolha manual** (`chooseSidebarMode`): o comentário do vivo diz literalmente que o protótipo grava TODO valor, inclusive o automático, e que isso foi **medido no espelho em 2026-09-02** (chave `rail` de um run a 1279px sobrevivendo a 1728px). É defeito do nosso build, com dono nomeado.
- **`<nav className="sb-body" aria-label="Navegação principal">`** no vivo × `<div className="sb-body">` no protótipo.
- **`NfeCertBadge`** entre `CompanyPicker` e o menu (slot documentado em `NfeCertBadge.tsx:25`) — não existe no build.

Já paritário (não mexer): drawer mobile ≤768px + hambúrguer + backdrop (`app.jsx:637-648, 963-968` × `AppShellV2`), alça `.sb-collapse-handle` com ⌘\\, modos rail/expanded, 3 contadores de `shell.sidebar_counts`.
Instrumentos do protótipo que **nunca** vão pro vivo: `WipMark` · `podeVer(papel)`/`MOCK.SIDEBAR_PAPEIS` · `countOf`/`MOCK.SIDEBAR_COUNTS`.

## 2 · Threads — ordem · dono · prefixo (Lei 1) · dependência

| # | thread | dono | prefixo que escreve | depende de | vaga |
|---|---|---|---|---|---|
| 01 | Seção CORPO: `nav` + a11y A1–A12 + aposentar Tabs/Chat/ConvRow (UI-0011) | [CC] | `sidebar.jsx` · `styles.css` (bloco Sidebar) | RESÍDUO-2 (**só** a parte "aposentar"; `nav`+a11y andam sozinhos) | 1 |
| 02 | Seção MODOS: auto-rail UI-0030 + persistir **só** escolha manual | [CC] | `app.jsx` | — | 1 |
| 03 | Seção TOPO: paridade `CompanyPicker` + slot de alerta pós-picker | [CC] | `sidebar.jsx` (topo) · `data.jsx` | 01 (mesmo arquivo) · RESÍDUO-4 | 2 |
| 04 | Modo `hidden` + `SidebarReopenHandle` → promover pro vivo | [CL] | `Components/cockpit/{Sidebar.tsx,shared.ts}` · `Layouts/AppShellV2.tsx` · `resources/css/cockpit.css` | 01 (a11y do alvo) · RESÍDUO-3 | 2 |
| 05 | Ghosts × ADR 0180 — emenda ou reversão | [W] → [CL] | `memory/decisions/0180-*.md` | RESÍDUO-1 (despacho [W]) | 2 |
| 06 | Contrato de tela do shell + gates | [CL] | `prototipo-ui/contrato/cockpit-sidebar.contract.json` · `tests/Feature/Sidebar/` | 01 · 03 · 04 | 3 |

> **Vaga 1 na prática:** só a **02** está 100% destravada. A **01** roda parcial sem [W] (faz `nav`+a11y, deixa a remoção do Chat fora) e a **04** está presa ao RESÍDUO-3. O `--proximo` do placar lista as três; a leitura correta é "3 abertas, 1 integral".

**Vaga 1:** 01 ∥ 02 · **Vaga 2:** 03 ∥ 04 ∥ 05 · **Vaga 3:** 06. Entre vagas, S0 consolida.
**Ancoragem dupla:** alvo de layout = o protótipo medido (`prototipo-ui/cowork/sidebar.jsx`); **âncora de implementação = `Components/cockpit/Sidebar.tsx` + `cockpit.css`** — reusar `.sb-*`, `SidebarMode`, `LS.SB_MODE`, `AUTO_RAIL_MQ` que já existem lá. O `main` responde *onde e com que dado*; o protótipo responde *como*.

## 2-bis · ESTADO — derivado, nunca escrito
> **Fonte = bloco ```json do §7 + o repo.** `node prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.mjs --indice prototipo-ui/design-docs/cowork-inbox/sidebar/playbook/00-INDICE.md --root . --proximo`. Regra: `_saida-NN.md` presente **e** provas verdes = `feito`; sem `_saida` = não feito, mesmo com PR mergeado; `bloqueada` é fila de [W].
> Render **medido** na aterrissagem (2026-09-10, sha `71109f60d3`): `Sidebar: entregue 0 de 6 · próximo 1 · em curso 0 · pendente 4 · bloqueada 1` — **PRÓXIMO: 02.** Só a 02 é executável hoje: 01/03/04 estão presas em decisão pendente ([W]) e a 04 ainda depende da 01. Respondidas RESÍDUO-2 e -3, o placar passa a `PRÓXIMO: 01 · 02` (controle positivo rodado).

### Fluxo (6 passos, iguais para toda thread)
```
1 ABRIR    sessão limpa · gh pr list --state open × arquivos a tocar · colar §3 · ler NN-*.md + âncora no main (sha no _saida)
2 MEDIR    tema dark · após __oiLazyDone e DUAS leituras iguais de querySelectorAll('*').length · getComputedStyle (nunca a classe declarada) · sonda nova roda caso de sanidade antes de qualquer veredito
3 APLICAR  1–3 arquivos do prefixo · reusar átomos existentes · PARAR SE vale mais que terminar
4 PROVAR   provas do NN verdes · placar no corpo do PR
5 FECHAR   _saida-NN.md (feito · não feito e por quê · pedido literal · descobertas · prefixo tocado) → parar
6 SAÍDA    regenerar o pacote (gerar-payload-partes.mjs) e anotar `bundle regenerado` no github.md — não roda do Cowork
```

## 3 · Abertura de thread (colar como 1ª mensagem — sessão limpa)
```
Sessão fresca. ANTES de abrir: `gh pr list --state open` cruzado com os arquivos do seu prefixo.
Leia nesta ordem, do main, nunca de cópia local:
1. prototipo-ui/design-docs/cowork-inbox/ponte/03-REGRAS-DE-PARALELISMO.md      ← Leis 1–4
2. prototipo-ui/design-docs/cowork-inbox/sidebar/playbook/00-INDICE.md          ← §1 medição · §2 seu prefixo · §7 fonte
3. prototipo-ui/design-docs/cowork-inbox/sidebar/playbook/NN-<sua-thread>.md    ← escopo · alvo · dado · prova
4. resources/js/Components/cockpit/Sidebar.tsx + Layouts/AppShellV2.tsx         ← a âncora (não recriar nada que já esteja lá)
5. prototipo-ui/PRE-FLIGHT-TELA.md · memory/proibicoes.md · memory/LICOES_CC.md
AVISO: o handoff-sidebar/PEDIDO-CODE.md de 2026-08-28 está SUPERADO por este playbook — os deltas A e B já estão no main, e o C mudou de lado. Não o execute.
Regras duras: sidebar PRETA nos dois modos (UI-0023) · `.sb-item.is-open` não clareia · item de sidebar é single-link (AP19) · nenhum grupo cross-módulo em AdminSidebarMenu.php · sem cor crua (só --sb-*, hue por grupo via --gh) · PT-BR · sem emoji · um <main> por documento (AP9) · chain de overflow (AP10).
Você escreve SOMENTE no seu prefixo e no seu _saida-NN.md. Não edita este índice, github.md nem memory/**.
Terminou: escreva _saida-NN.md e pare.
```

## 4 · VERIFICAR — placar
Thread `feito` = `_saida-NN.md` com os 5 itens **e** provas verdes lendo o `main`. Gates das threads do vivo (04 · 06):
```
php artisan optimize:clear && composer dump-autoload
php artisan test --filter=Sidebar     # tests/Feature/Sidebar/* + Modules/Fiscal/…/SidebarConsolidacaoTest + Modules/Whatsapp/…/SidebarCountsTest
php artisan test --filter=Cockpit     # CockpitPatternConformance · Typography · AccentCanon
php artisan test --filter=AppShellUsageGate
node scripts/governance/cowork-ssot-guard.mjs && node scripts/qa/prototipo-readiness.mjs
```
`tests/Feature/Sidebar/SidebarMenuItemContractTest.php` trava o shape do item — se o delta mexer nele, atualizar junto, nunca contornar. **T7** (`design-diff --compare --check` nos dois renders, prod deployada) não é visível daqui: o placar afirma "arquivos verdes", nunca "paridade".

## 5 · Revisão 3× por passo
| passo | R1 · fonte | R2 · falsificação | R3 · frescor |
|---|---|---|---|
| LEVANTAR | denominador de componente (não de rota): `Sidebar.tsx` + `AppShellV2` + build + `app/Sidebar/*.php` | reli os 4 deltas do pedido velho **contra o código**, não contra o pedido: 3 caíram | pedido de 28/08 tinha 13 dias; a sha mudou (`7d7c8fd1` → `af09f7c3`) |
| PUXAR | 4 melhorias do vivo entram no build (auto-rail · persistência · `nav` · slot de alerta) | puxar às cegas reintroduziria a aba Chat que o vivo **removeu de propósito** | `Sidebar.tsx:5-7` datado e assinado (UI-0011) |
| REACT | um só delta sobra pro vivo (modo `hidden`) — o resto é build ou decisão | busca de `SidebarReopenHandle` veio **bounded**; confirmei por `AppShellV2` (o tipo `SidebarMode` não tem `hidden`), não pelo zero-match | — |
| PLAYBOOK | Lei 1: 01 e 03 tocam o mesmo `sidebar.jsx` → vagas diferentes, não paralelas | teste do estranho na 04 (tem o caminho, o nome do tipo e o CSS) | ADR 0180 não relida nesta sha — a 05 abre **lendo-a** |

## 6 · RESÍDUO Sidebar — fila de decisão [W]
1. **Ghosts (thread 05):** o vivo implementou `GHOST_TETO = 5`; a ADR 0180 (AP19) diz que ghost vira tab na Zona C do PageHeader. Emenda a ADR (código venceu) ou reverte o vivo?
2. **Aba Chat no protótipo:** confirmar a aposentadoria (UI-0011). Remover `SidebarTabs`/`SidebarChat`/`ConvRow` do build **ou** mantê-los com selo "fora do canon — demo"?
3. **`hidden` é canon?** o modo existe no protótipo e no bundle do Financeiro, mas nunca virou `SidebarMode` do shell. Vira canon (thread 04) ou morre nos dois lados?
4. **`NfeCertBadge` no protótipo:** puxar o slot de alerta real ou representar como placeholder genérico (o build não tem estado de certificado)?

## 7 · Fonte da máquina (playbook.json embutido — primeiro bloco json deste arquivo)
```json
{
  "modulo": "Sidebar",
  "sha": "af09f7c3a0fd",
  "gerado": "2026-09-10",
  "absorve": ["prototipo-ui/design-docs/handoff-sidebar/PEDIDO-CODE.md"],
  "variaveis": { "CKPT": "resources/js/Components/cockpit", "BUILD": "prototipo-ui/cowork" },
  "decisoes": [
    { "id": "RESIDUO-1", "pergunta": "Ghosts: emendar ADR 0180 (código venceu) ou reverter GHOST_TETO do vivo?", "respondida": false, "destrava": ["05"] },
    { "id": "RESIDUO-2", "pergunta": "Aposentar SidebarTabs/SidebarChat/ConvRow do protótipo (UI-0011) ou selar como demo?", "respondida": false, "destrava": ["01"] },
    { "id": "RESIDUO-3", "pergunta": "Modo hidden vira canon do shell (SidebarMode) ou morre nos dois lados?", "respondida": false, "destrava": ["04"] },
    { "id": "RESIDUO-4", "pergunta": "Slot de alerta pós-CompanyPicker no protótipo: NfeCertBadge real ou placeholder?", "respondida": false, "destrava": ["03"] }
  ],
  "threads": [
    { "id": "01", "titulo": "Seção CORPO: nav + a11y A1–A12 + aposentar Tabs/Chat/ConvRow", "dono": "CC", "vaga": 1, "arquivo": "01-corpo-a11y.md",
      "prefixo": ["${BUILD}/sidebar.jsx", "${BUILD}/styles.css"],
      "nao_toca": ["${BUILD}/app.jsx", "${BUILD}/data.jsx", "${CKPT}/"],
      "depende_decisoes": ["RESIDUO-2"],
      "provas": [
        { "tipo": "nao_contem", "path": "${BUILD}/sidebar.jsx", "padrao": "role=\"link\"", "nota": "os 2 div[role=link] (ItemRow:165 e atalho:278) viram <a>/<button> reais; a prova antiga (contem aria-label) JÁ passava — 3 ocorrências" },
        { "tipo": "nao_contem", "path": "${BUILD}/sidebar.jsx", "padrao": "function SidebarChat", "nota": "só com RESIDUO-2 respondida por remover" }
      ] },
    { "id": "02", "titulo": "Seção MODOS: auto-rail UI-0030 + persistir só escolha manual", "dono": "CC", "vaga": 1, "arquivo": "02-modos-auto-rail.md",
      "prefixo": ["${BUILD}/app.jsx"],
      "nao_toca": ["${BUILD}/sidebar.jsx", "${BUILD}/styles.css"],
      "provas": [
        { "tipo": "contem", "path": "${BUILD}/app.jsx", "padrao": "(max-width: 1280px)", "nota": "o MESMO AUTO_RAIL_MQ do vivo (inclusive); 0 ocorrências no build hoje — prova trabalho novo" },
        { "tipo": "nao_contem", "path": "${BUILD}/app.jsx", "padrao": "localStorage.setItem(\"oimpresso.sidebar.mode\", sbMode)", "nota": "grava só na escolha manual; sem indentação no padrão (§5 2026-07-29)" }
      ] },
    { "id": "03", "titulo": "Seção TOPO: paridade CompanyPicker + slot de alerta", "dono": "CC", "vaga": 2, "arquivo": "03-topo-picker.md",
      "prefixo": ["${BUILD}/sidebar.jsx", "${BUILD}/data.jsx"],
      "nao_toca": ["${BUILD}/app.jsx"],
      "depende_threads": ["01"], "depende_decisoes": ["RESIDUO-4"],
      "provas": [], "nota_provas": "medição primeiro: _saida-03.md com o diff CompanyPicker protótipo × vivo nos dois sentidos" },
    { "id": "04", "titulo": "Modo hidden + SidebarReopenHandle → promover pro vivo", "dono": "CL", "vaga": 2, "arquivo": "04-hidden-reopen.md",
      "prefixo": ["${CKPT}/Sidebar.tsx", "${CKPT}/shared.ts", "resources/js/Layouts/AppShellV2.tsx", "resources/css/cockpit.css"],
      "nao_toca": ["resources/js/Pages/Financeiro/_cowork-bundle/", "${CKPT}/useSidebarShortcut.ts", "app/Sidebar/"],
      "depende_threads": ["01"], "depende_decisoes": ["RESIDUO-3"],
      "provas": [
        { "tipo": "contem", "path": "${CKPT}/shared.ts", "padrao": "| 'hidden'", "nota": "o 3º membro do type SidebarMode; 0 ocorrências hoje" },
        { "tipo": "contem", "path": "resources/js/Layouts/AppShellV2.tsx", "padrao": "SidebarReopenHandle" },
        { "tipo": "contem", "path": "resources/css/cockpit.css", "padrao": ".sb-reopen-handle" }
      ] },
    { "id": "05", "titulo": "Ghosts × ADR 0180 — emenda ou reversão", "dono": "W", "vaga": 2, "arquivo": "05-ghosts-adr-0180.md",
      "prefixo": ["memory/decisions/0180-sidebar-v3-5-grupos-ghosts-header.md"],
      "nao_toca": ["${CKPT}/Sidebar.tsx"],
      "bloqueio": "RESIDUO-1: o vivo implementou GHOST_TETO contra a letra da ADR; nenhuma linha de código antes do despacho",
      "depende_decisoes": ["RESIDUO-1"],
      "provas": [], "nota_provas": "quando despachado: {tipo:contem, path:<ADR 0180>, padrao:'2026-09'} — emenda datada, nunca ADR paralela (LC-19)" },
    { "id": "06", "titulo": "Contrato de tela do shell + gates", "dono": "CL", "vaga": 3, "arquivo": "06-contrato-e-gates.md",
      "prefixo": ["prototipo-ui/contrato/cockpit-sidebar.contract.json", "tests/Feature/Sidebar/"],
      "nao_toca": ["${CKPT}/", "resources/js/Layouts/AppShellV2.tsx"],
      "depende_threads": ["01", "03", "04"],
      "provas": [
        { "tipo": "json_com_chaves", "path": "prototipo-ui/contrato/cockpit-sidebar.contract.json", "chaves": ["alvo", "secoes"] }
      ] }
  ]
}
```

---

## 8 · Aterrissagem — o que o [CL] corrigiu no índice em 2026-09-10 (fato datado)

Os 6 `NN-*.md` desceram **como vieram** (byte a byte, conferido). As 15 âncoras de linha que eles citam
foram medidas uma a uma contra `origin/main` e **todas conferem** (`sidebar.jsx` 25/31/42/160/180/208/256/390/590/561/566/177 ·
`shared.ts` 182/197/198 · `NfeCertBadge.tsx:25` · `styles.css` 5219/5267 · `shell-app.jsx` 509-510).
Os 6 gates da §4 e da thread 06 existem — `SidebarConsolidacaoTest` e `SidebarCountsTest` vivem em
`Modules/Fiscal/` e `Modules/Whatsapp/`, e o `--filter=Sidebar` os pega pelo nome.

O `00-INDICE.md` recebeu **13 consertos**, todos medidos na sha `71109f60d3`:

| # | era | virou | por quê |
|---|---|---|---|
| C1 | `"granularidade": "secao"` no §7 | removido (a prosa do §0 já diz "onda = seção") | `_schema/playbook.schema.json` tem `additionalProperties: false` — com o campo, o placar saía `Playbook inválido: data must NOT have additional properties`, ou seja **não rodava** |
| C2 | `node scripts/qa/placar-indice.mjs` | `node prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.mjs` | aquele path é o **destino sugerido** no docblock do script, não onde ele está |
| C3 | render *esperado* (`próximo 3 · pendente 2`) | render **medido** (`próximo 1 · pendente 4`) | o placar nunca produziria o número anterior; agora é saída colada, não previsão |
| C4 | §2 "Vaga 1: 01 ∥ 02 ∥ 04" | "Vaga 1: 01 ∥ 02 · Vaga 2: 03 ∥ 04 ∥ 05" (+ `vaga` da 04 no §7) | a prosa contradizia o próprio §7: a 04 declara `depende_threads: ["01"]`, e a thread 04 dá a razão ("a11y do alvo corrigida antes de exportar"). A dependência é intencional — quem estava errado era o §2 |
| C5 | prova t02 `contem "matchMedia"` | `contem "(max-width: 1280px)"` | `matchMedia` já casava **2×** no `app.jsx` (linhas 641/644, do drawer mobile ≤768px): a prova nascia verde sem trabalho algum |
| C6 | prova t02 `nao_contem` com indentação e `\n` embutidos | `nao_contem "localStorage.setItem(\"oimpresso.sidebar.mode\", sbMode)"` | âncora dita estrutural comparando literal de formatação (§5 2026-07-29) — um reindent a satisfaria |
| C7 | prova t04 `contem "hidden"` | `contem "\| 'hidden'"` | `hidden` é genérico (22× em `cockpit.css`); um `aria-hidden` futuro em `shared.ts` satisfaria a prova sem o modo existir |
| C8 | `memory/decisions/0180-sidebar-contrato-v2.md` | `memory/decisions/0180-sidebar-v3-5-grupos-ghosts-header.md` | o arquivo anterior não existe; como estava, a thread 05 criaria **ADR paralela** — o LC-19 que a própria thread quer evitar. (Há colisão de número: existe também `0180-drift-numero-adr-0178-conflito-paralelo.md`) |
| C9 | `sidebar.jsx` 623 linhas | 622 | contagem |
| C10 | "auto-rail … O build daqui **não tem**" | "decide só no mount, por `innerWidth < 1280` (`app.jsx:629-630`) — faltam o listener e o inclusive" | contradizia a correção que este mesmo arquivo já trazia acima, e mandaria a thread 02 reimplementar o que existe |
| C11 | comentário do §4 sugerindo os 3 testes sob `tests/Feature/Sidebar/` | caminhos reais | 2 dos 3 vivem em `Modules/` |

Verificação: o placar foi rodado antes e depois, e o controle positivo (responder RESIDUO-2 e -3 e ver
`PRÓXIMO` virar `01 · 02`) confirmou que a fila deriva das decisões, não do texto.

### 8.1 · Errata de 2026-09-10 — a âncora da thread 01 era mentirosa (e passou pela minha revisão)

Quatro horas depois de aterrissar este playbook, [W] apontou que o desenho que eu apresentava como
"o protótipo" não era o protótipo. Estava certo, e a causa é minha: **nunca resolvi a âncora**.
Rodado agora, `node prototipo-ui/ancora.mjs cockpit/Sidebar` responde
`✗ sem charter pra essa tela — NÃO invente âncora; registre ou pergunte`. A Sidebar não tem charter,
logo não tem âncora computável — e eu escolhi uma no olho, que é o anti-padrão da lápide §5 2026-06-30.

Medido depois, com `DesignSync.get_file` no projeto Cowork por ID e conferido contra o espelho:

| afirmação | estado |
|---|---|
| "o protótipo usa `<div className="sb-body">` — o vivo está certo, copiar dele" (01, §A) | **enganosa por omissão** — o div **contém** `<nav aria-label="Navegação principal">` (`:260`, `:268`, `:451`). A divergência é de **nível**, não de ausência |
| prova `contem "aria-label=\"Navegação principal\""` (§7, thread 01) | **nascia verde** — 3 ocorrências antes da thread começar. Mesmo defeito do `matchMedia` que corrigi na aterrissagem, e que deixei passar neste |
| passo 1 da thread 01: "`sb-body` vira `<nav>`" | **manda criar o que já existe** — virou verificação + decisão de nível |

Corrigido acima (A1–A4). A prova da 01 passa a ser `nao_contem "role=\"link\""`: os 2 clicáveis que
hoje são `div[role="link"][tabIndex]` (`ItemRow:165` e o atalho de topo `:278`) precisam virar
`<a>`/`<button>` reais — que é o trabalho de a11y que a thread de fato tem.

⚠️ **O que NÃO mudou, e é o resto da thread 01:** a bateria A1–A12, o `aria-expanded` do grupo,
o nome acessível do "⋯ mais N", o contraste medido por `getComputedStyle`. Tudo isso segue de pé.
