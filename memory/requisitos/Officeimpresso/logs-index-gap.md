---
id: requisitos-officeimpresso-logs-index-gap
tela: Officeimpresso/Logs/Index (/officeimpresso/licenca_log)
prototipo: prototipo-ui/cowork/officeimpresso-page.jsx
tela_viva: Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Logs/Index.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — Officeimpresso/Logs/Index

> **Âncora declarada no charter** (`related_prototype: prototipo-ui/cowork/officeimpresso-page.jsx`) e confirmada pela porta.
>
> ⚠️ **A tela viva mora DENTRO do módulo** (`Modules/Officeimpresso/Resources/js/Pages/...`), não em `resources/js/Pages/`. As duas raízes são igualmente canônicas desde 2026-08-12 (`scripts/qa/page-path.mjs` §RAIZ_PAGES), e por isso `ancora.mjs` imprime `tela viva: —` para esta tela: o resolvedor dele olha só a raiz do núcleo. Não é âncora quebrada — é limite conhecido da porta.
>
> **⚠️ LEIA ANTES DE PROPOR QUALQUER COISA — o descompasso é conhecido e tem decisão [W].** O charter (`Index.charter.md` §"Sobre a âncora de design") mede um conflito de três artefatos: o Blade em produção diz que `/officeimpresso/licenca_log` é **lista de máquinas**; o protótipo Cowork chama a view `oi-log` de **log de eventos**; o RUNBOOK concorda com o Blade. **Decisão [W] 2026-08-19: "paridade agora, realinhar depois"**, e a frase seguinte é literal: *"Quem for mexer aqui **não** deve 'consertar' o descompasso por conta própria."* A view do protótipo que corresponde a **esta** tela é a **`ViewLicencas`** (`officeimpresso-page.jsx:541-713`), **não** a `ViewLog` (`:1023+`).
>
> **Frescor do espelho (medido 2026-09-06, por HASH — rodada 38):** `officeimpresso-page.jsx` = **STALE**. Espelho `0626867a4819…`, vivo `39b2cfcfba45…`. Diff decodificado: duas regiões, ambas adoção de componente do DS — `Kebab` inline (`:97-118` no espelho, 22 linhas) virou `window.CliKebab`, e o `<div className="oi-seg" role="tablist">` (`:572-575`) virou `window.CliSeg`. O comentário que o vivo ganhou é revelador: *"Eram SEIS cópias idênticas deste componente no build."* **Não muda capacidade** (−20 linhas: 1163 → 1144).
>
> **Não duplica régua existente.** [`logs-parity.md`](logs-parity.md) (`tipo: parity`) mede o eixo **Blade↔React** — os 54 itens que a migração não pode perder. Este documento mede o eixo **protótipo↔React**, que é outra pergunta. Onde os dois se tocam, o parity vence: ele é o contrato da travessia, e o charter §Goals manda preservar os 54 itens.
>
> **Non-Goals do charter — NÃO reabertos aqui.** `Index.charter.md` §Non-Goals proíbe por escrito: mudar o que a tela mostra; renomear rota ou componente; **transformar os KPIs em filtro clicável**; **adicionar coluna, exportação, seleção em massa ou bulk action que o Blade não tem**; e deixar o CSS `oi-*` do módulo atravessar.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho | `PageHeader` "Máquinas Cadastradas" com subtítulo que explica **de onde vem o dado** (a rotina `/connector/api/processa-dados-cliente`) (`Index.tsx:112-120`) | Nada — vivo à frente. O `PageHead` do protótipo traz só contagens (`officeimpresso-page.jsx:570`); o vivo explica a origem, que é o que o suporte precisa saber quando a lista está vazia. |
| KPIs | 4 `KpiCard` dentro de `<Deferred>`: Máquinas cadastradas, Máquinas bloqueadas, Empresas bloqueadas, Acessos 24h (`Index.tsx:123-130`) | Nada — decisão já registrada. O protótipo tem 4 KPIs **na view Empresas** (`officeimpresso-page.jsx:371-376`), com recorte diferente (empresas licenciadas, máquinas ativas, versão atrasada, empresas bloqueadas). O charter §Non-Goals fixa que os KPIs daqui **são globais e não seguem o filtro**, igual ao Blade — *"KPI que reage a filtro é outra tela"*. Não reabrir. |
| Filtros | Busca com debounce ≤300ms + partial reload que não repaga os KPIs, e `Select` de estado atual com o sentinela `__all__` (`Index.tsx:76-95`, `:137-149`) | **Decidir.** O protótipo tem, além da busca, um **`FilterDropdown` de Empresa** (`officeimpresso-page.jsx:585-590`) — que numa lista cross-empresa é o filtro mais óbvio que falta. ⚠️ Antes de construir, conferir contra o Blade: o charter §Non-Goals proíbe **adicionar** o que o Blade não tem, então isto só é gap legítimo se o Blade já filtrar por empresa. Medir primeiro, decidir depois. Construir ou rejeitar por escrito. |
| Chips de filtro ativo | Presentes, com remoção individual (`Index.tsx:156-160`, `removerChip` em `:107-109`) | Nada — vivo à frente. O protótipo não tem chips. |
| Tabela / colunas | 10 colunas: Empresa, Location/CNPJ, Máquina, HD, Versão, IP, Último login, Estado no último login, Estado atual, Ações (`MaquinasTable.tsx:83-92`) | Nada — vivo à frente **e** Non-Goal. O protótipo tem 7 (`officeimpresso-page.jsx:639`) e o charter proíbe adicionar coluna nova. As 10 são a paridade dos 54 itens do `logs-parity.md`. |
| Toggle "Licenças × Por empresa" | Ausente | Nada — decisão já registrada. O toggle do protótipo (`officeimpresso-page.jsx:572-575`, hoje `window.CliSeg` no vivo do Cowork) troca o agrupamento da mesma lista. É reorganização visual, e a reorganização para a estrutura desenhada (`empresas`/`licencas`/`log`) é explicitamente **Onda 2** no charter. Não reabrir agora. |
| Ações de bloqueio | Duas ações como `<Button onClick>` + `AlertDialog` de confirmação, gated por `permissions.pode_bloquear` (`Index.tsx:184`; diálogo em `MaquinasTable.tsx:256-257`) | Nada — vivo à frente, e é **Anti-hook Tier 0 do charter**: *"Nunca renderizar as ações de bloqueio como `<Link>`/`<a href>` … um href é seguível por prefetch, crawler e 'abrir em nova aba'."* O protótipo usa itens de `Kebab` (`officeimpresso-page.jsx:659`), que não carregam essa garantia. |
| Drawer da licença | Ausente — a tela não abre detalhe inline | **Decidir.** O protótipo tem o `LicencaDrawer` (`officeimpresso-page.jsx:715-753`) com ficha da máquina (serial, versão, sistema, IP, usuário, registrada, último acesso) **e a timeline embutida**. No vivo a timeline é **página própria** (`/licenca_log/timeline`), o que o charter da irmã registra como sendo o que a rota faz hoje. Decidir é escolher entre manter as duas telas ou colapsar no drawer — e isso é a Onda 2, não esta. Construir ou rejeitar por escrito. |
| Estados vazios | Dois, distintos: sem filtro (explica que a rotina do connector popula) e com filtro (+ ação "Limpar filtros") (`Index.tsx:168-180`) | Nada — vivo à frente. O `Vazio` do protótipo (`officeimpresso-page.jsx:246-255`) é genérico. |
| Tri-estado do "Estado no último login" | Tratado: `was_blocked_last === null` vira travessão, não "Liberada" (charter §Anti-hooks) | Nada — vivo à frente. O protótipo não tem esse tri-estado; é regra que nasceu do dado real. |
| Rótulo `(cadastro)` | Presente por Anti-hook do charter — quando a data vem de `dt_ultimo_acesso` e não do log, o rótulo aparece para não afirmar um acesso que nunca houve | Nada — vivo à frente. |
| Performance | `maquinas` e `kpis` em `Inertia::defer` com skeleton (`Index.tsx:123`, `:165`) | Nada — vivo à frente. Anti-hook do charter proíbe eager-load dos dois. |
| CSS `oi-*` do módulo | Não atravessa — a tela usa o DS | Nada — Non-Goal do charter (*"Não deixa o CSS `oi-*` do módulo atravessar — o módulo não tem Design System próprio"*). O protótipo usa `oi-*` livremente; **importar essas classes seria regressão declarada**, não adoção. |
