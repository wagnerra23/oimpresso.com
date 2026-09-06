---
id: requisitos-officeimpresso-logs-timeline-gap
tela: Officeimpresso/Logs/Timeline (/officeimpresso/licenca_log/timeline/{licenca_id})
prototipo: prototipo-ui/cowork/officeimpresso-page.jsx
tela_viva: Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Logs/Timeline.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — Officeimpresso/Logs/Timeline

> **Âncora declarada no charter** (`related_prototype: prototipo-ui/cowork/officeimpresso-page.jsx`) e confirmada pela porta. A tela viva mora dentro do módulo — ver a nota sobre as duas raízes de `Pages` no [gap da tela irmã](logs-index-gap.md).
>
> ⚠️ **No protótipo esta tela NÃO EXISTE como página.** O charter é explícito: *"No protótipo esta tela **não é uma página própria**: a timeline vive no drawer da licença dentro da view `oi-licencas` ('Timeline no log')"* — e a decisão é a mesma da irmã: **[W] 2026-08-19, "paridade agora, realinhar depois"**. A região de comparação, portanto, é o bloco `<h3>Timeline no log</h3>` dentro do `LicencaDrawer` (`officeimpresso-page.jsx:737-750`), não uma tela.
>
> **Consequência para este documento:** comparar uma **página** com um **bloco de drawer** produz "gaps" que são só diferença de chrome. Onde é isso, a Ação diz `Nada`. O único item que **de fato** decide algo é se a página continua existindo ou vira drawer — e isso é Onda 2, não esta.
>
> **Frescor do espelho (medido 2026-09-06, por HASH — rodada 38):** `officeimpresso-page.jsx` = **STALE** (`Kebab`→`window.CliKebab`, `oi-seg`→`window.CliSeg`; −20 linhas). Detalhe completo no [gap da tela irmã](logs-index-gap.md). Nenhuma das duas regiões toca o bloco da timeline.
>
> **Não duplica régua existente.** [`logs-parity.md`](logs-parity.md) (`tipo: parity`, itens 38-51 são desta tela) mede o eixo **Blade↔React**; este documento mede **protótipo↔React**. Onde se tocam, o parity vence.
>
> **Non-Goals do charter — NÃO reabertos aqui:** não mostra o log inteiro (são os últimos **200** acessos, `source=delphi_middleware`); **não** oferece ação de bloquear/desbloquear (é leitura, e quando o usuário não pode, a tela **diz** qual permissão falta em vez de esconder); não pagina nem ordena client-side; **não inventa métrica agregada** (média de latência, uptime) — *"indicador novo é decisão de produto, não de migração"*.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho da máquina | `PageHeader` "Timeline — {nome}" com subtítulo e `StatusBadge` do estado atual (ativa / máquina bloqueada / empresa bloqueada), legível sem rolar (`Timeline.tsx:89-100`, estado derivado em `:81-87`) | Nada — paridade funcional. No protótipo a identidade da máquina é o cabeçalho do drawer + a `<dl>` de ficha (`officeimpresso-page.jsx:719`, `:726-733`). Mesma informação, chrome diferente — ver a nota acima. |
| Ficha técnica da máquina | Parcial: o cabeçalho traz nome e estado; a ficha completa vive na **tela irmã** (a lista tem HD, Versão, IP, Último login) | **Decidir.** O drawer do protótipo mostra, junto da timeline, sete campos: Serial, Versão (com comparação instalada→obrigatória), Sistema, IP, Usuário, Registrada e Último acesso (`officeimpresso-page.jsx:726-733`). Como página, esta tela hoje obriga voltar à lista para ver isso. ⚠️ O charter proíbe **métrica** nova, não **campo** já existente — mas o Non-Goal irmão (*"não adiciona coluna que o Blade não tem"*) sugere conferir o Blade antes. Medir primeiro. Construir ou rejeitar por escrito. |
| Comparação de versão instalada → obrigatória | Ausente | **Decidir.** O componente `Ver` do protótipo (`officeimpresso-page.jsx:301-315`) mostra a versão instalada contra a obrigatória — é a resposta direta de *"por que o Delphi daqui não abre?"*, que é a missão declarada da tela. ⚠️ Depende de o payload trazer `versao_obrigatoria`; medir no controller antes. Construir ou rejeitar por escrito. |
| Tabela de acessos | 5 colunas — Data/hora, Status HTTP, Estado no login, IP e a 5ª — sob `<Deferred>` com skeleton (`Timeline.tsx:137-142`, `:118`) | Nada — vivo à frente. O protótipo lista eventos com tipo e horário (`officeimpresso-page.jsx:740-747`) mas **não** traz status HTTP nem o estado de bloqueio no momento do login — que é justamente o que responde a pergunta da tela. |
| "Estado no login" (tri-estado do metadata) | Presente, com parser que trata objeto **e** string JSON e **falha fechado** em JSON inválido (`Timeline.tsx:64-76`) | Nada — vivo à frente, e é **Anti-hook Tier 0 do charter**: *"Nunca assumir que `metadata` é objeto … consulta via `DB::table` foge do cast e entrega string JSON. Ler errado faz a coluna 'Estado no login' mentir sobre o bloqueio."* O protótipo não tem esse dado. |
| Recorte de 200 acessos | Fixo, vindo pronto do servidor em ordem desc | Nada — Non-Goal do charter (*"Ampliar o recorte muda a pergunta que a tela responde"*). O protótipo filtra todos os eventos do host, sem recorte (`officeimpresso-page.jsx:717`) — mas opera sobre mock de dezenas de linhas, não sobre o log real. Não importar. |
| KPIs / métricas agregadas | Ausentes | Nada — Non-Goal do charter (*"Não inventa métrica agregada (média de latência, uptime). O Blade não tem, e indicador novo é decisão de produto, não de migração"*). A `ViewLog` do protótipo tem KPIs por tipo de evento (`officeimpresso-page.jsx:1066-1070`), mas ela é **outra tela** — ver o descompasso medido no gap da irmã. Não reabrir. |
| Ações de bloqueio | Ausentes por decisão; quando falta `officeimpresso.licencas.gerenciar`, a tela **diz** qual permissão falta em vez de esconder (`Timeline.tsx:49`, charter §Permissões) | Nada — Non-Goal do charter (*"A ação é da lista; esta tela é leitura"*). O drawer do protótipo oferece ações (`officeimpresso-page.jsx:687-688`) porque lá é o mesmo chrome da lista. |
| Estado vazio | "Nenhum acesso registrado para esta máquina." + explicação de que ela está cadastrada mas o Delphi nunca chamou dali (`Timeline.tsx:129-131`) | Nada — vivo à frente. O protótipo tem um vazio genérico no lugar (`officeimpresso-page.jsx:739`: *"Nenhum evento nesta janela de retenção."*; a lista cheia começa em `:741`). |
| 403 × 404 | Distinguidos: 404 para máquina inexistente, 403 para sem permissão — os dois travados por teste (charter §Goals e §Anti-hooks) | Nada — vivo à frente. Não existe no protótipo, que não tem servidor. |
| Página × drawer | É página, porque é o que a rota faz hoje | Nada — decisão [W] registrada (*"paridade agora, realinhar depois"*). Colapsar a página no drawer da lista é a **Onda 2**, com o protótipo como fonte — e o charter da irmã manda **não** consertar o descompasso por conta própria. Não reabrir aqui. |
| CSS `oi-*` | Não atravessa — a tela usa o DS | Nada — mesmo Non-Goal da irmã. |
