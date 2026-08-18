# Memória da Jana (`/ia/memoria`) — protótipo × tela viva, por região e componente

- **Data da medição:** 2026-08-17 · **âncora:** `prototipo-ui/cowork/jana-merge.jsx` §`JmMemoria` (âncora de **símbolo** — re-localize com `grep -n "function JmMemoria"`, ref de linha apodrece)
- **Tela viva:** `resources/js/Pages/Jana/Memoria.tsx` + `components/JanaAreaHeader.tsx` + `_shared/JanaSubNav.tsx`
- **Backend:** `Modules\KB\Http\Controllers\MemoriaController` (⚠️ vive no **KB**, não no Jana) sobre `Modules\Jana\Contracts\MemoriaContrato`
- **Charter:** `resources/js/Pages/Jana/Memoria.charter.md` **v2** — `status: draft` no frontmatter × `live` no corpo (divergência registrada lá, não resolvida aqui)
- **Contrato:** `resources/js/Pages/Jana/Memoria.casos.md` — UC-MEM-01..05, lane `jana-pest.yml`
- **Gate F1.5:** esta tela **está** no manifesto `tests/Browser/visreg-screens.json` (`screen: "Jana/Memoria"`, âncora `"Memória da Jana"`) — toda mudança aqui gera diff de pixel e precisa de aprovação [W]

> **Fonte de design:** decisão [W] de 2026-08-17 (`Index.charter.md` v8) — a fonte da Jana é o **protótipo Cowork**, não o Design System. Âncora resolvida por `node prototipo-ui/ancora.mjs Jana/Memoria`, que hoje devolve **`⚠️ charter sem related_prototype`** — ver §Decisões [W] em aberto.

> **Como ler:** ✅ existe e equivale · 🟡 existe mas diverge · ❌ não existe na tela viva · 🟢 **só na viva** (o protótipo não tem — apagar seria regressão) · ⛔ existe e **não deve** ser copiado.

---

## ⚠️ Re-medido contra `origin/main` fresco antes de persistir

A 1ª redação saiu de um checkout **13 commits atrás** do `origin/main`. Ao persistir, todas as fontes
desta tela foram re-conferidas contra o main fresco (`bf3a533d0`): `Memoria.tsx`, `JanaAreaHeader.tsx`,
`MemoriaController.php`, charter, casos e `jana-merge.jsx` estão **idênticos** — nenhum veredito por
região mudou de sinal.

**O que mudou foi o TAMANHO de três ❌**, e mudou por causa de PRs vizinhos do Painel: `JanaConfigDrawer`,
`useJanaConfig` e `JanaCockpitSkeleton` **passaram a existir** em `_components/` (#5862/#5878, 2026-08-17)
e são consumidos **só pelo `Index.tsx`**. A Memória segue sem os três — mas agora é **plugar**, não construir.
As linhas afetadas dizem isso explicitamente.

Registro porque o próprio `Index-visual-comparison` (§Correções) já cataloga a classe: *"um documento de
comparação é derivado, e derivado citado depois do prazo vira afirmação"*.

---

## ⚠️ O que esta comparação É e o que ela NÃO É

Isto é comparação **estrutural**, por leitura de código dos dois lados. Ela responde *"existe / não existe / diverge"*.

**Ela não mede fidelidade visual.** Fidelidade exige, cumulativamente:

1. `node scripts/governance/cowork-mirror-freshness.mjs --compare --check` = **SYNC** (prova que o espelho não é design velho);
2. sonda `node prototipo-ui/design-diff.mjs --probe` nos **dois** renders + `--compare a.json b.json --check` (skill `comparar-design-prod`).

**Nenhum dos dois rodou nesta medição.** O `--compare` foi invocado e recusou: *"exige um snapshot.json existente (do DesignSync.get_file)"*. Logo, **nada neste documento afirma "fiel"** — só "existe/não existe/diverge estruturalmente". Cor, espaçamento, tipografia e tokens (`bg-page-cream`, `bg-card/80`) estão **fora** do que foi medido.

---

## R1 · Shell e header da área

| componente | protótipo (`JanaPage`, `tab === "memoria"`) | tela viva | veredito |
|---|---|---|---|
| container | `.jc-page` com `data-screen-label="Jana — Memória"` | `AppShellV2` + `max-w-4xl mx-auto p-6` | ✅ equivalente |
| barra de identidade | `JanaHeader` — empresa, `biz=`, "Atualizado HH:MM" | `JanaAreaHeader` (PageHeader canon) | ✅ |
| empresa + `biz=` no subtitle | sempre presente (`company`, `data.biz`) | **ausente** — o `MemoriaController` não passa `janaContext`, e `<JanaAreaHeader active="memoria" />` é chamado sem `businessName`/`businessId` | 🟡 o subtitle fica só com "Atualizado HH:MM" |
| reapuração | `onRefresh={atualizar}` no header | botão "Atualizado HH:MM" → `router.reload()` | ✅ |
| selo de plano | `jm-plano` — "plano Pro/Grátis", clicável → abre Configurar | — | ❌ |
| ação Configurar | `onConfig` → `JmConfigDrawer` | — (o `actions` do header não é passado por esta tela) | ❌ **mas é wiring, não construção** — ver nota |
| ação Exportar | dropdown com **"Fatos da memória (LGPD)"** entre os itens | — | ❌ — ver nota |
| primary | — | `PageHeaderPrimary "Conversar"` → `/ia/conversa` | 🟢 só na viva |
| título próprio da tela | não tem (abre direto no alerta LGPD) | removido em 2026-08-08 (US-COPI-148) justamente por isso | ✅ convergiram |

> **Nota sobre o Exportar.** O item do protótipo avisa *"Export de fatos exige log de auditoria — fora deste protótipo"*, e o charter tem o Non-Goal ⛔ *"Export CSV de fatos PII sem audit log"*. Os dois **concordam**: o que falta é a versão **com** log, não a proibição. Construir exige decidir o formato do audit.

## R2 · Navegação da área

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| abas | `JmTabs` — Painel · Metas* · Conversa · **Memória** (ícone `database`) | `JanaSubNav` via `JanaAreaHeader active="memoria"` → ghost `memorias` | ✅ equivalente |
| **troca de aba** | estado local (`tab`), **mesma página, sem reload** | **rota própria** `/ia/memoria` — navegação Inertia entre páginas | 🟡 divergência estrutural — ver nota |
| contador na aba | `n` por aba (nº de conversas / metas) | — | ❌ **precisa de backend** — o `JanaSubNav` lê `shell.menu` do `DataController`, e é **compartilhado pelas 4 telas** da área (R2 do `Index-visual-comparison`) |

> **Nota sobre aba × rota.** No protótipo a Memória é uma **aba** de `JanaPage`: o estado (`cfg`, `threads`, `carregando`) sobrevive à troca. Na viva é **rota separada** — cada entrada é um render novo, e é por isso que R8 (skeleton/toast) fica vazio: não há estado de área pra carregar. Não é "errado", é outra arquitetura; mas explica metade dos ❌ abaixo. Reconciliar é decisão [W].

## R3 · Aviso LGPD

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| bloco | `<Alert tone="info" title="Memória da Jana — LGPD Art. 18">` | `<Alert>` + `<AlertTitle>` do DS | ✅ |
| copy do título | "Memória da Jana — LGPD Art. 18" | idêntica | ✅ literal |
| copy do corpo | "Você vê, corrige e apaga qualquer fato que a Jana aprendeu sobre o seu negócio. Toda alteração registra autor e motivo no log de auditoria." | idêntica | ✅ literal |
| fallback sem DS | `.jm-mem-lgpd` (texto curto) | n/a — o DS é garantido | ⛔ não copiar (artefato de protótipo) |

## R4 · Busca e filtro por categoria

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| barra | `.jm-mem-bar` — busca + chips + contador | `<Inline gap={3} wrap>` — mesma tríade | ✅ |
| campo de busca | `placeholder="Buscar em fatos…"` + ícone `search` | idêntico (`Search` lucide, `aria-label`) | ✅ literal |
| algoritmo da busca | `f.fato.toLowerCase().includes(termo)` | `m.fato.toLowerCase().includes(termo)` | ✅ idêntico (ambos **sem** normalizar acento) |
| chips de categoria | lista **fixa** `JM_CATS` = todas · preferência · operação · financeiro · cliente · sazonalidade · equipe | **derivados do dado** (`Set` sobre `metadata.categoria`), rotulados por `CATEGORIA_LABELS` | 🟡 divergência **decidida** — ver nota |
| chip "todas" | primeiro da lista fixa | sentinela `__todas__` sempre presente | ✅ |
| contador | "N de M fatos" (singular/plural) | idêntico | ✅ literal |
| barra escondida quando vazio | sempre visível | escondida quando `memorias.length === 0` | 🟢 só na viva |
| a11y dos chips | nenhum atributo ARIA | `role="group"` + `aria-label` + `aria-pressed` por chip | 🟢 só na viva |

> **Nota sobre os chips.** A lista do protótipo é a taxonomia do **mock do Martinho**; a de produção é outra (`meta`/`preferencia`/`restricao`/`contexto`/`acao_pendente`). Traduziu-se o **comportamento** (filtrar), não a lista (§5 2026-07-16). Já registrado no `Memoria.casos.md` — **não re-propor** copiar a lista literal.

## R5 · Lista de fatos — o card

| componente | protótipo (`.jm-fato`) | tela viva (`FatoCard`) | veredito |
|---|---|---|---|
| texto do fato | `<p>{f.fato}</p>` | `<p className="text-sm">` | ✅ |
| categoria | `.jm-tag` (texto cru) | `<Badge variant>` soft, 5 variantes mapeadas por **semântica** | 🟡 diverge na forma, equivale na função |
| origem | `origem: {f.origem}` | `· origem: {origem}` | ✅ |
| data | `desde {f.desde}` | `· desde {formatData(valid_from)}` (pt-BR) | ✅ |
| relevância | **5 bolinhas** (`.jm-rel`, `title="Relevância N de 5"`) | **texto** `relevância N/10` | 🟡 divergência **decidida** [W] 2026-08-07: a **produção é a fonte**, o protótipo é que se adapta. Não re-propor |
| **rastro da última edição** | `f.editado` → *"editado por você · agora · <motivo>"* **na própria linha** | — grava em `activity_log`, mas **não renderiza** | ❌ — ver nota |
| ordenação | ordem do array mock | `MemoriaContrato::listar($businessId, $userId)` — a interface **não declara** ordem | ⚠️ não medido (o charter pede `valid_from DESC`; a interface é silenciosa) |

> **Nota sobre o rastro.** É a assimetria mais interessante da tela: a viva **grava** mais que o protótipo (autor + motivo + PII redigida, UC-MEM-03/04) e **mostra** menos. O titular não vê que corrigiu, nem por quê — o Art. 18 é sobre transparência pro titular, e o dado já existe em `activity_log`. Candidato natural a próxima entrega.

## R6 · Edição inline

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| modo edição | inline no card (`editando === f.id`) | inline no card (`useState`) | ✅ |
| campo texto | `<textarea rows={2} aria-label="Texto do fato">` | `<textarea aria-label="Texto do fato">` | ✅ |
| **campo categoria** | `<select>` com `JM_CATS` | — | ❌ |
| **campo relevância** | `<select>` 1–5 | — | ❌ |
| campo motivo | `<input placeholder="fica no log de auditoria">` | idêntico, com `aria-label` | ✅ literal |
| rodapé da edição | "Toda correção registra autor, horário e motivo." | idêntico | ✅ literal |
| Salvar desabilitado | `disabled={!fato.trim() \|\| !motivo.trim()}` | `podeSalvar` idêntico + `title` explicando o porquê | ✅ + 🟢 (o `title`) |
| **validação no servidor** | nenhuma (é mock) | `required\|min:3\|max:255` no `motivo`, com mensagens PT-BR | 🟢 só na viva — **UC-MEM-01/02** |
| erros de validação | — | `errors.fato` / `errors.motivo` renderizados | 🟢 só na viva |
| forma dos botões | texto ("Salvar" / "Cancelar") | ícones (`Save` / `X`) com `title` | 🟡 |
| **PII do motivo** | — | `PiiRedactor` antes de persistir | 🟢 só na viva — **UC-MEM-04** |

## R7 · Apagar (esquecer)

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| confirmação | **inline na linha** (`apagando === f.id`) | inline na linha (`confirmandoApagar`) | ✅ equivalente |
| copy | "Apagar é irreversível." + "Apagar" / "Manter" | idêntica | ✅ literal |
| forma do botão | texto "Apagar" | ícone `Trash2` + `title="Esquecer"` | 🟡 |
| efeito | remove do array local | `DELETE /ia/memoria/{id}` → soft delete via `MemoriaContrato::esquecer` | ✅ |
| **trilha do esquecer** | — | `activity_log` `jana_memoria_fato_esquecido` | 🟢 só na viva — **UC-MEM-05** |
| expurgo dos embeddings | — | contrato diz *"índice removido"*; o charter pede **job async** — não localizei job dedicado | ⚠️ não medido |

> ⚠️ O charter pede `AlertDialog`. A viva usa **confirmação inline** — e isso é **decisão deliberada**, documentada no próprio `Memoria.tsx` (o `confirm()`/dialog sai do fluxo e não diz *qual* fato). Pela regra de precedência (`teste > casos > charter > SPEC`), o `casos.md` já registrou a inline como o comportamento. **O charter é o perdedor aqui e deveria ser corrigido** — fica registrado, não mexido nesta leva.

## R8 · Estados e feedback

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| empty "nada aprendido" | título + descrição + ícone `database` | idêntico, ícone `Brain` | ✅ copy literal, 🟡 ícone |
| empty "nenhum fato com esse filtro" | título + descrição + botão "Limpar filtro" | idêntico, ícone `Search` | ✅ copy literal |
| **dois** empty states distintos | sim | sim | ✅ (produção tinha **um** antes de 2026-08-07) |
| carregando | `JmPainelSkeleton compacto` | — props são **eager**, sem `Inertia::defer`, sem skeleton | ❌ — o gargalo é o **controller**, não o componente (ver nota do R9) |
| toast | `jm-toast` / `<Toast>` do DS | `sonner` disponível, sem uso nesta tela | ❌ |
| estado de erro | `EmptyState variant="error"` + "Tentar de novo" | — | ❌ |
| aviso mobile | "O painel foi desenhado pro escritório (1280px)…" | — | ❌ (é do Painel; aqui o charter pede **accordion por categoria**, também ausente) |

## R9 · Plano, configuração e persistência

| componente | protótipo | tela viva | veredito |
|---|---|---|---|
| drawer Configurar | `JmConfigDrawer` — toggles das análises, brief on/off + hora, áudio, **retenção** | — nesta tela | ❌ **o componente existe** — `_components/JanaConfigDrawer.tsx` (#5878, 2026-08-17); só o Painel o pluga |
| retenção declarada | item do drawer ("12 meses") | existe em `Modules/Jana/Config/retention.php`, **sem UI** | ❌ na tela |
| persistência da config | `localStorage` `oimpresso.jana.cfg` | — nesta tela | ❌ **o hook existe** — `_components/useJanaConfig.ts`, `JANA_CFG_KEY = 'oimpresso.jana.cfg'`, **a mesma chave do protótipo**; só o Painel o consome |
| gate por plano | `pro` condiciona blocos | — | ❌ |

> **Nota — os três primeiros são wiring.** `JanaConfigDrawer`, `useJanaConfig` (chave `oimpresso.jana.cfg`,
> idêntica à do protótipo) e `JanaCockpitSkeleton` existem no repo e funcionam no Painel. Para a Memória
> falta (a) passar `actions` ao `JanaAreaHeader` e (b) decidir **quais** toggles fazem sentido aqui — o
> drawer do Painel governa análises e brief, não fatos. O skeleton é o único que não se reaproveita direto:
> o `JanaCockpitSkeleton` desenha o cockpit, e o gargalo desta tela é o `MemoriaController` entregar
> `memorias` **eager** — sem `Inertia::defer` não há o que um skeleton cubra.

## R10 · Backend e contrato — o que **só a viva** tem

> Esta região não existe no protótipo por construção (ele é mock client-side). Está aqui porque **paridade não é via de mão única**: apagar qualquer linha abaixo para "ficar igual ao protótipo" seria regressão de LGPD.

| item | onde | veredito |
|---|---|---|
| `motivo` obrigatório **no servidor** | `MemoriaController::update` — a UI é conveniência, o servidor é a garantia | 🟢 UC-MEM-01 |
| motivo vazio/curto reprovado (`'   '`, `'ok'`) | mesma validação | 🟢 UC-MEM-02 |
| trilha com `causer_id` + `properties.motivo` | `activity()` spatie | 🟢 UC-MEM-03 |
| PII do motivo redigida antes de persistir | `PiiRedactor` | 🟢 UC-MEM-04 |
| trilha também no esquecer | `registrarNaTrilha` | 🟢 UC-MEM-05 |
| texto do fato **nunca** entra no log | `MemoriaFato::getActivitylogOptions()` — `logOnly` restrito | 🟢 |
| escopo Tier 0 | `HasBusinessScope` — id de outro business resolve `null`, e a trilha sai mesmo assim | 🟢 |

---

## Inventário de cobertura — Goal do charter × implementado × tem contrato

> Formato copiado do `Chat.casos.md` (2026-08-17). Responde a pergunta que a tabela por região não responde: *a lei da tela está coberta por teste?*

| # | Goal / Anti-hook do charter | implementado | tem UC citado por teste |
|---|---|---|---|
| G1 | Listar fatos com **filtro por categoria** | ✅ chips derivados | ❌ (⬜ no casos.md — client-side) |
| G1b | **Busca fulltext** em `fato` | ✅ client-side `includes` | ❌ (⬜) |
| G1c | **Sort por `valid_from DESC`** | ⚠️ não medido — a interface `listar()` não declara ordem | ❌ |
| G2 | Editar inline **texto** + `activitylog` autor/quando/motivo | ✅ | ✅ UC-MEM-01/02/03 |
| G2b | Editar inline **categoria** | ❌ | ❌ |
| G2c | Editar inline **relevância** | ❌ | ❌ |
| G3 | Apagar com soft delete + confirmação | ✅ (inline, não `AlertDialog` — ver R7) | ✅ UC-MEM-05 |
| G3b | Apagar **embeddings Meilisearch async via job** | ⚠️ não medido | ❌ |
| G4 | Mostrar `origem` do fato | ✅ | ❌ (⬜) |
| G5 | Superadmin vê cross-business via `?escopo=plataforma` | ❌ **0 hits** de `escopo` no controller e no contrato | ❌ |
| A1 | `PiiRedactor` em texto com CPF/CNPJ | 🟡 aplicado ao **motivo**; o **fato** não passa por redação no render | ✅ UC-MEM-04 (só o motivo) |
| A2 | Nunca update sem `activitylog` | ✅ | ✅ UC-MEM-01/03 |
| A3 | Sem `forceDelete()` sem job async | ✅ (é soft delete) | ✅ UC-MEM-05 (parcial) |
| A4 | Bloquear edit sem `copiloto.memoria.manage` | ⚠️ não medido — não localizei o gate na rota nem no controller | ❌ |
| U1 | Render < 250ms p95 com `Inertia::defer()` | ❌ props eager, sem paginação | ❌ |
| U2 | Mobile — accordion por categoria | ❌ | ❌ |

**Placar:** 16 itens · **6 cobertos por UC** · 5 implementados-sem-contrato · 4 não implementados · 4 não medidos (as categorias se sobrepõem em G3/A1).

**Status dos 5 UCs:** todos `🧪` no `casos.md` (Pest escrito, aguardando run verde). A porta viva `screen-coverage --screen Jana/Memoria` confirma o vínculo UC↔teste dos cinco (`✓ UC-MEM-01..05`), o que prova **linkagem**, não **veredito verde** — o status vem do run, não da leitura.

---

## Resumo — o que falta, por tamanho

| ordem | entrega | região | por que primeiro |
|---|---|---|---|
| 1 | **Rastro da edição visível no card** (`editado por … · motivo`) | R5 | o dado **já existe** em `activity_log`; é render, e fecha o Art. 18 pro titular |
| 2 | Editar **categoria** e **relevância** | R6 | 2 dos 3 campos que o protótipo edita estão ausentes; o charter os promete |
| 3 | `businessName` + `businessId` no header | R1 | 1 linha no controller + 2 props; hoje o operador não vê o escopo Tier 0 |
| 4 | Skeleton + `Inertia::defer` nos fatos | R8 | é o UX target U1 do charter, hoje a zero |
| 5 | Estado de erro + toast | R8 | camada de feedback inteira ausente |
| 6 | Exportar "Fatos da memória (LGPD)" **com** audit log | R1 | protótipo e charter concordam; falta decidir o formato do log |
| 7 | Plugar `JanaConfigDrawer` + `useJanaConfig` nesta tela | R9 | **o componente já existe** (#5878) — falta `actions` no header e decidir os toggles de fatos |
| 8 | `?escopo=plataforma` superadmin | R10 | Goal do charter com **zero** código |

---

## Decisões [W] em aberto

- **`related_prototype` ausente** — `ancora.mjs Jana/Memoria` devolve `⚠️ charter sem related_prototype`. A âncora real é `jana-merge.jsx` §`JmMemoria`, mas o charter não a declara, então nenhuma máquina a enxerga. Mesmo buraco registrado no `Index-visual-comparison` §Fora de escopo — e lá com a ressalva de que o check `pt_declarado` só casa `PT-0X`.
- **Charter `draft` × corpo `live`** — o frontmatter diz `draft`, o corpo diz *"em uso prod biz=1 desde 2026-04"*. `screen-coverage` responde **"✗ NÃO pode ligar (charter `draft` · zero sinal de prod)"**. Promover é ato [W].
- **`AlertDialog` × confirmação inline** — o charter pede um, a tela faz o outro **por decisão registrada**. Pela precedência, o charter é o perdedor e deveria ser corrigido no mesmo PR que alguém tocar esta região.
- **Aba × rota** (R2) — reconciliar a Memória como aba de `JanaPage` (protótipo) ou manter rota própria (viva) muda a arquitetura da área inteira, não só desta tela.
- **`RUNBOOK-memoria.md`** — declarado no charter e resolvido como **autoritativo** pela porta viva. Nada a fazer; registrado por contraste com o Pro, onde o mesmo campo está ambíguo.
