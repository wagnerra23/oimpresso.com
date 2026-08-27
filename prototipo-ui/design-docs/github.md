repo: wagnerra23/oimpresso.com
branch: main
path: resources/js/Pages/Produto/Unificado

## Last sync
date: 2026-08-27T17:20:43Z
tree: 8d489de20207

### Updated in this project
- **KPI "A receber vencido" alinhado ao canon** (`chat-jana.css`, CSS-only — `chat-jana.jsx` intocado): borda do `.jc-kpi.emph` recua de 35% mixada com `--border` para 22% no tom · ícone do card em alarme carrega `--neg` (era `--text-3`: alerta cinza ao lado de número vermelho) · o `"big"` do dado (`deltaCls:"red big"`, `chat-jana.jsx:93`) nunca existiu em CSS — agora o valor salta `--fs-7`→`--fs-8` (22→28px, medido) pelo RAMP, sem tamanho inventado. Fundo permanece `--neg-soft`. Nenhum token do DS foi alterado (espelho ≠ fonte, ADR 0239/0315).
- **Contraste do card em alarme corrigido**: `.jc-kpi.emph .jc-kpi-h` e `.jc-kpi-d` saem de `--text-3` para `--text` — sobre a superfície tintada o cinza media **2,40:1 (light) / 2,69:1 (dark)**, reprova AA, e o `sub` é onde está o número que decide a ação ("76% inadimplência", 11px). Token temado direto, sem `color-mix` (mistura com `--neg` puxa hue). Valor 28px em `--neg` passa AA de texto grande (3,57 / 4,30 ≥ 3,0).\n- **Armadilha registrada (achado do verificador, vale pro repo)**: `color-mix(in oklch, var(--neg) 6%, var(--surface))` no dark computa `oklch(0.3252 0.01712 248.7)` — **azul**. O caminho curto de hue 240 (`--surface` dark) → 25 (`--neg`) passa por 248.7, então tint de alarme sai frio. Mix em oklch só com segundo termo `transparent` ou de hue vizinho; para superfície tintada usar o token temado `--neg-soft` (`styles.css:6352` light / `:6460` dark, chroma calibrado nos dois modos). A regra AP7 "tint 6% / borda 22%, sem pastel" é orientação de status badge em light — aplicá-la ao fundo do card no dark apaga o sinal (L 0.3252 vs 0.30 da superfície normal).
- **Bug do DS a reportar no git (não consertar no espelho)**: `validateDOMNesting: <button> cannot appear as a descendant of <button>` — `DropdownMenu` do `_ds_bundle.js:3946` dentro do `JanaHeader`. Botão aninhado em botão; pré-existente, não regressão.
- **Cadeia de dependência do [W] refutada com leitura**: o KPI vive em `chat-jana.jsx:93` + `KPICard` (l.254, exportado em `window`) — `jana-merge.jsx` só consome `data.kpis`+`KPICard` (l.895, 1082–1092); **não havia bundle a regerar no Cowork**: o host `oimpresso.com.html` É o manifesto e carrega `chat-jana.jsx`(137), `jana-metas.jsx`(139), `jana-merge.jsx`(141), `produto-catalogo.css`(44), `produto-detalhe.jsx` lazy(186) direto — o único bundle é o `_ds_bundle.js`(124), gerado pelo push git→design fora desta esteira.
- **`ancora.mjs` não existe** — nem aqui nem no `main` (regex `ancora|anchor` em toda a árvore + `scripts/`: 0 hits de `.mjs`). A maquinaria de âncora no git é `.github/workflows/anchor-drift.yml` + `anchor-content-required.yml` + `governance/anchor-entry-baseline.json` (+ ADRs 0273/0302/0303/0326/0327/0349/0355). Se algo "confirma STALE", é um desses — e nenhum deles fala de bundle do Cowork. Corrige também a menção a `ancora.mjs` no sync de 2026-08-27T10:30:12Z, que era herdada do pacote, não lida.
- **"Visão geral"** = `/home` → `resources/js/Pages/Home/Index.tsx` (charter `status: live`, F6 Soft wrapper); o protótipo `dash-legacy-page.jsx` reconstrói o Blade `resources/views/home/index.blade.php` (`/dashboard-legacy?legacy=1`, `HomeController@indexLegacy`) e é o F1 do rewrite Cockpit V2 — destrava US-DASH-002/004/005, exige ADR + charter v3 e preserva o contrato `?legacy=1`.

### Sync anterior (2026-08-27T14:05:00Z · Jana telas novas)
tree: 0859644c1152

### Updated in this project
- **Telas novas da Jana construídas** — `jana-telas-novas.jsx` + `.css`: **Alertas** (`/ia/alertas` + `/ia/alertas/config`), **Ações** (fila HITL `/ia/acoes`) e **Plataforma** (`/ia/superadmin/metas` + `/ia/install`), como abas de área da tela única (sem rota nem `.html` novo). Campos, limites e mensagens de erro dos FormRequests reais; as 5 chaves/rótulos de `AcaoHitlService::ACOES` byte-idênticos; severidade por múltiplo do corte como no `AlertaService`.
- **Permissão virou camada de tela**: tweak "Papel de quem entrou" (funcionaria · dona · superadmin) — aba Plataforma só com `jana.superadmin`, config de alertas só com `jana.metas.manage`, ausência como `no-perm`.
- **P0 levantado**: `SuperadminController` confere `can('jana.superadmin')` **e** roda `withoutGlobalScope`; `Gate::before` passa qualquer ability pra `Admin#{business_id}` → dona de empresa veria meta de outro cliente. Decisão de tenancy, não de design.
- **Errata dos meus números**: `permissions.php` hoje é `group: 'Jana'` com **22** permissões e labels já "Jana: …" — o "Copiloto" vivo é `lang/pt/copiloto.php`, `topnav.php`, as 8 views `copiloto::…`, `ChatCopilotoAgent`/`CopilotoDesvioDetectado`/seeder/migrations e o botão do `Cliente/Index.tsx`. O PR-0 de nome encolhe.
- Pedido pro Code (camadas · telas novas · divergências · ordem de 6 PRs): `cowork-inbox/JANA-CAMADAS-TELAS-NOVAS-2026-08-27.md`.

### Sync anterior (2026-08-27T11:37:38Z · Jana metas)
- **Metas (camada Blade) construída no protótipo** — `jana-metas.jsx` + `jana-metas.css`, plugados na seção Metas da Jana (`jana-merge.jsx`), sem rota nem `.html` novo. Absorve `Modules/Jana/Resources/views/metas/{index,create,edit,show}.blade.php` e `fontes/show.blade.php`: index → view "Cadastro" (DataTable + busca + filtros ativas/inativas/plataforma + Switch de ativo + menu de ações) · create/edit → drawer de formulário (slug derivado do nome e imutável depois de criada, unidade, tipo_agregacao, alvo, janela, alvo por período do `PeriodosController`) · show → seção "Apurações gravadas" no drawer de detalhe + Modal de "Forçar reapuração" · fontes/show → drawer de Fonte só leitura (US-COPI-040).
- Alternador **Farol | Cadastro** no cabeçalho da seção Metas (leituras do mesmo dado, não tela nova). Os avisos "abre a tela própria (/ia/metas/…) — fora deste protótipo" morreram: agora abrem o formulário.
- **Inventário do que faltou na rodada 1 da Jana** (lido no `main` hoje): 8 views Blade de tela — `metas/*` (4) + `alertas/{index,config}` (config **não grava**) + `fontes/show` (controller é do `Modules\KB`) + `superadmin/metas` (gate `jana.superadmin`) — mais 3 closures `/ia/admin/{custos,qualidade,roadmap}` em `routes.php` a conferir (Qualidade/Governança já repontadas pro `Modules/Governance`). `emails/weekly-digest.blade.php` fica fora (o teste `JanaViewsSemAndaimeTest` exclui `emails/`). `MetasController` tem `STUB spec-ready` no docblock. Todas as views ainda dizem "Copiloto" → casa com o PR-0 de nome.
### Sync anterior (2026-08-27T10:30:12Z · Jana errata da rodada 1)
- **Jana — errata da rodada 1** (`cowork-inbox/JANA-ERRATA-CAMADA-ESQUECIDA-2026-08-27.md`): a rodada 1 não pode ir pro Code. Camada esquecida = **permissão/entitlement + contrato e lei que já existem no git**. Lido hoje: `Pages/Jana/**` (24 arquivos), `Modules/Jana/Http/routes.php`, `Resources/permissions.php`, `contrato/jana-painel.contract.json`, controllers, workflows, testes.
- Achados: a rota é **`/ia`** (não `/jana`) com `can:jana.access` no grupo e controllers reais · 24 permissões no grupo "Copiloto" que o pacote não citou · **Memória/Fontes são do `Modules\KB`**, não do Jana · charter+casos somam **172 KB de UC citados por contrato e scorecards** ("reescrever junto" apaga lei) · `jana-painel.contract.json` **já existe e está ativo no CI** com 3 `_pendente_w` · 6 workflows + 2 testes `.tsx` que o rewrite quebra + seeder de visreg + `ancora.mjs` + 3 skills do Code · 5 docs de pedido Jana já no git (plano de ondas).
- **Espelho local estava defasado**: `main` tem `cowork/jana-merge.jsx` 58.381 B vs 56.535 B local (idem chat-jana e jana-pro). Apaguei `prototipo-ui/cowork/jana/` que eu havia criado ontem — era duplicata de versão mais velha (L-42).
- **"Copiloto" ainda vivo na UI** (apontado por [W]): rename Copiloto→Jana (ADR 0088/0092) foi PHP-only e a fachada nunca veio. `permissions.php` tem `group: 'Copiloto'` + as 24 labels "Copiloto: …" (o que a tela de permissões mostra), `Cliente/Index.tsx` tem o botão "Falar com Copiloto →", e o cabeçalho do `Jana/Chat.tsx` declara `tela: /copiloto` / `module: Copiloto` — foi daí que saiu o `/jana` errado do meu pacote. Chaves `jana.*` NÃO se tocam (#4853). Sugerido PR-0 só de nome.
- **Conferência do [CL] aceita (2026-08-27)**: 4 números meus corrigidos — charter+casos = **154,5 KiB** (não 172; erro de soma), contrato = **5 seções / 7 strings** (não 5 copies), `cowork/jana/` **nunca esteve no git** (era pasta local minha, já apagada), e o delta do espelho eu medi **caracteres contra bytes** — real: só os dois `.jsx` divergem (−1.196 B e −1.514 B), os 3 `.css` e o `jana-pro.jsx` são idênticos; a worktree do [CL] está em sinc com o `main`. Caem a acusação de L-42 e a tabela de 56.535 B. Conserto por `--export-from`, nunca cópia à mão.
- **Casos escritos** (`cowork-inbox/JANA-CASOS-EMENDA-PERMISSAO-2026-08-27.md`): 9 UCs de emenda no formato do repo — UC-JPERM-01..08 (gate do grupo `/ia`, chat não vem de graça, meta em leitura sem `metas.manage`, custos fora do payload, Tier 0, conversa alheia, esquecer `critical`, Pro cliente ≠ preview admin) + UC-JNAME-01 (grupo/labels "Jana" com as 24 `key` byte-idênticas). Todos nascem ⬜ e entram **no mesmo PR do seu teste** (G-2).
- Aceitas as 2 recomendações técnicas do [CL]: emendar (não reescrever) e não puxar espelho por cima.
- Aguardando 3 decisões de [W]: emendar vs reescrever charter/casos · os 3 `_pendente_w` · puxar espelho do `main` sobre o build local.

### Sync anterior (2026-08-26T21:55:00Z · Jana rodada 1)
- **Jana — refazer o módulo** (pedido de [W]): rodada 1 = pacote zero-toque em `cowork-inbox/JANA-REFAZER-ZERO-TOQUE-2026-08-26.md` — inventário fechado das 62 unidades do protótipo → arquivo do repo (estender/criar/matar), contrato de dados por tela com nomes fechados, 10 regras duras e a ordem dos 5 PRs. O protótipo Cowork manda; o vivo só dá rota/props/gates.
- Build da fonte exportado pra `prototipo-ui/cowork/jana/` (6 arquivos: `jana-merge`, `chat-jana`, `jana-pro` · jsx+css); cópias velhas de 24/08 na raiz de `cowork/` apagadas (duplicata reprova no `cowork-ssot-guard`).
- Decisões fechadas no pacote: `JcIcon` morre (repo usa lucide) · pasta `Pages/Jana/components/` morre, tudo em `_components/` · aba própria de Metas não vai pro vivo (é Tweak, canon é seção do Painel) · `JanaCockpit.tsx` (44 KB) sai quebrado · `AssistantUiChat` a conferir.
- Rodadas 2–5 = os `.tsx` completos por tela + charter/casos reescritos + 4 contratos de tela.

### Sync anterior (2026-08-26T21:41:00Z · Arquivos)
- Refinos de produção da tela **Arquivos** (4 screenshots do vivo + `Pages/Arquivos/Index.tsx` lido no `main`): 10 achados + bônus em `cowork-inbox/ARQUIVOS-REFINOS-PRODUCAO-2026-08-26.md`. Tudo copy/format/estado — nada escreve nem dispara job.
- P1: lupa da busca sobre o placeholder (defeito do `shared/DataTable`, atinge toda tela com busca) · datas ISO na tela (`2026-09-07`, `2026-06-09 18:08`) · inglês em UI (`No grace period`/`Grace period`) e `hard_delete` como número-herói · coluna Disco mostrando `arquivos` (o `rotuloDisco` do Cofre não chegou na tabela) · badges Vencendo/Envio ainda em fill sólido (AP7, resto do #6268).
- P2: sub-linha do arquivo com duas ausências ("sem contexto · sem classificação humana") — reusar `CONTEXTO_PT`, que hoje só a Retenção usa · `size=207560` como Detalhe da Trilha · "1 anos" · Cofre saudável gastando a dobra em 3 ensaios · `Storage::url` como nota de card.
- A3/A5/A6 mudam copy literal → `contrato/arquivos.contract.json` no mesmo PR.

### Refino anterior deste turno (Comunicação Visual)
- Revisão de refino em produção da tela Comunicação Visual, lendo no `main`: `Pages/ComunicacaoVisual/Index.tsx` + charter, `Routes/web.php`, `OrcamentoController`, `OrcamentoCalculator`, `CalcularOrcamentoRequest`, `Entities/Material`.
- 7 refinos + 1 bônus em `cowork-inbox/COMVIS-REFINOS-PRODUCAO-2026-08-26.md` (diffs prontos, todos no vivo, nenhum depende de tela/API nova). P0: rota não passa `materiais`/`podeCriar` (catálogo sempre vazio, aviso mentiroso) e `crypto.randomUUID()` sem fallback (tela branca em `http://` no balcão).
- Divergências de contrato: `temItemValido` com `some` libera 422; total da tela clampa em 0 e não espelha os rounds HALF_UP do Service; `CalcularOrcamentoRequest` valida `largura_mm`/`preco_m2` (contrato divergente do controller, aparentemente órfã).
- Não escrevo no git — o Code aplica pelo pedido.

### Sync anterior (2026-08-26T18:18:46Z · tree c82e60e8ced7)
- Frota checada no `main`: **não existe** — zero em `Modules/`; veículo é entidade da OficinaAuto exposta via cliente (`Pages/Cliente/_show/VehiclesTab.tsx`, `Show.tsx:108/121`, `StatusBadge.tsx:73`). Confirma o P-2 de `cowork-inbox/JANA-MODULO-ONDAS-PR-2026-08-09.md` ([W] matou Frota em 2026-08-07).
- Frota removida do módulo Jana no protótipo: KPI "Frota utilização", análise Frota (donut), ação `a3` outbound, prompt/sugestão de caçambas paradas e o branch de resposta `/frota|caçamba/` em `chat-jana.jsx`; meta `m3`, conversa `t4`, toggle Frota, `JM_KPI_DRILL.truck`, prévia `a3` e o texto "6 análises" → 5 em `jana-merge.jsx`.
- Jana no `main` tem 4 telas (`Index`/`Chat`/`Pro`/`Memoria` + charter/casos), `_components/` (JanaCockpit, drawers meta/drill/config, `useJanaConfig`), `_shared/JanaSubNav`.

### Sync anterior (2026-08-26T17:40:00Z · tree e9027ee7be7b)
- Conferência Arquivos protótipo × `main` (2ª rodada, `Pages/Arquivos/Index.tsx` lido inteiro): as 4 vistas convergiram; o gap real é natureza (vivo é leitura pura, protótipo escreve) + 3 achados NO VIVO — chips da Trilha com enum cru, coluna Contexto da política em jargão de banco, bucket Sensível como fill sólido (AP7).
- Pedido pro Code em `cowork-inbox/ARQUIVOS-PARIDADE-3-ACHADOS-2026-08-26.md` (diffs prontos + gates). Não escrevo no git.
- Conferência Comunicação Visual protótipo × `main`: protótipo tem PCP + salvar/WhatsApp que o vivo não tem; vivo tem ícone printer, breadcrumb, preço no `<option>` e o caso servidor≠prévia que o protótipo perdeu. `Index.charter.md` do vivo está defasado (diz stub, descreve 3 widgets que o `Index.tsx` não tem).

### Sync anterior (2026-08-26T13:16:50Z · tree 1e34f251a9a4)
- Conferência Arquivos protótipo × `main` (`Pages/Arquivos/Index.tsx`): os 3 erros do acervo (buckets `common`/`public` inexistentes, contagem "em 1824 dias" estourando a célula, ações com texto encavalando a coluna) já estão corrigidos nos dois lados.
- Protótipo acertado nas 4 divergências que sobravam: `VIS` ganhou a chave `business` ("Equipe"), "Escopo do job" saiu de `business_id` para "Uma empresa por vez" (`ds/no-db-jargon-in-ui`), barra de progresso do Cofre removida (denominador era 5 GB de mock, sem quota configurada) e contador da aba Retenção retirado (aba de retrato não leva número).

### Sync anterior (2026-08-26T11:40:26Z · tree 3ca7e27e85c3)
- Leitura de verificação (sem export): `Pages/Jana/Pro.tsx` (AppShellV2 + PageHeader, 13 blocos `style` inline) vs `Pages/Arquivos/Index.tsx` (10 componentes do DS, zero inline) — confere o diagnóstico do [W].
- Perf do host Cowork: loader de módulos deixou de pagar 1 macrotask por arquivo e o tick de `oi:lazy-*` passou a ser dirigido (família da rota + a cada 24); `app.jsx` coalesce o tick em 300ms e não faz mais setState de rota no commit — troca de tela renderiza 1x (era 2x).

### Sync anterior (2026-08-25T18:32:25Z · tree 4de9d46bcbb1)
- Consulta de Produtos (rota `produtos`) reescrita a partir do `main`: abas por tipo, KPI-filtros, toolbar em uma linha, grid sem raio com thead sticky, rodapé de paginação, BulkBar e drawer com esteira.
- Custo/margem passaram a ser autorização (coluna montada ou não montada), não preferência de layout.
- Matéria-prima entrou como tipo de linha; as 4 sub-telas saíram desta tela (handoff V6 #11) e seguem nas rotas `prod-*`.
- Painel de detalhe reaterrado em `_components/DetalheProduto.tsx`: ordem das seções do handoff 21/08 §5, largura 420/480, esteira ‹ N de M ›, código e referência copiáveis, e o aviso "custo e margem são restritos" quando falta permissão.
- Pedido de charter/casos/contrato deixado em `cowork-inbox/PRODUTO-UNIFICADO-CHARTER-CASOS-CONTRATO-2026-08-25.md` (não escrevo no git).

## Screen map
| Tela do protótipo | Arquivos do repo |
| --- | --- |
| `chat` / Jana (jana-merge.jsx + chat-jana.jsx + jana-pro.jsx + jana-metas.jsx + jana-telas-novas.jsx) | `Pages/Jana/{Index,Chat,Memoria,Pro}.tsx`, `_components/*`, `_shared/JanaSubNav.tsx`, `Modules/Jana/Http/routes.php`, `Resources/views/{metas,alertas,superadmin,fontes}/*.blade.php`, `Http/Controllers/{Alertas,AcaoHitl,Superadmin,Metas}Controller.php`, `Services/{AlertaService,AcaoHitlService}.php`, `Resources/permissions.php` |
| `arquivos` (arquivos-page.jsx + arquivos-data.jsx + modulos-faltantes.css) | `Pages/Arquivos/Index.tsx`, `Index.charter.md`, `Index.casos.md` |
| `cv` / `comunicacao-visual` (comunicacao-visual-page.jsx + modulos-faltantes.css) | `Pages/ComunicacaoVisual/Index.tsx`, `Index.charter.md`, `Index.casos.md`, `Modules/ComunicacaoVisual/{Routes/web.php,Http/Controllers/OrcamentoController.php,Services/OrcamentoCalculator.php}` |
| `produtos` (produtos-page.jsx + produto-detalhe.jsx + produto-catalogo.css) | `Pages/Produto/Unificado/Index.tsx`, `_components/{catalogo.ts,Colunas.tsx,KpiFiltros.tsx,FiltroTrigger.tsx,BulkBar.tsx,Disponibilidade.tsx,MiniaturaProduto.tsx,Mono.tsx,Observacao.tsx}` |
