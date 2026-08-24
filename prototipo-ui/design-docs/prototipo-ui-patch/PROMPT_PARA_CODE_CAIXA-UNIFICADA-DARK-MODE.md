# [CC]→[CL] · Caixa Unificada — corrigir MODO ESCURO + empty-state Customer 360

> Origem: [W] abriu `/atendimento/caixa-unificada` no tema **escuro** e o painel da
> conversa ficou branco + vários elementos do Contexto perderam contraste. Diff feito
> por [CC] com leitura @main (`dd09f96`) de `ConversationThreadV4.tsx`,
> `ContextSidebarV4.tsx`, `ChannelChipsRow.tsx`, `helpers.ts`, `Index.tsx`.
>
> **Regra §10.4:** isto é PROPOSTA. Valide TUDO contra o `main` antes de codar.
> Se algo aqui contradiz o repo, o repo vence. Numeração de US/ADR é sua.
>
> **Classe de bug conhecida:** é o MESMO padrão já erradicado em Produtos, Oficina e
> Financeiro ("cor clara crua não vira no `[data-theme=dark]`"). A Caixa Unificada foi
> portada ANTES dessa auditoria de escuro e nunca recebeu o tratamento. Aplique a mesma
> disciplina (probe `qa-conformance` G6 dark + screenshot claro E escuro antes de fechar).

## Tier 0 / gates (todos os arquivos)
- **`ui:lint` R1**: nenhuma família Tailwind `-NNN` nova. `text-blue-600` (atual no
  read-receipt) **JÁ FERE R1** — trocar. Passa: utility semântica sem `-NNN`,
  `bg-[var(--x)]`, inline `style={{color:'oklch(...)'}}` (padrão que estes arquivos já usam).
- **`conformance-gate`/`foundation-guard`**: não definir token novo fora de
  `foundations.css`/`cockpit.css`. Reusar tokens semânticos existentes
  (`bg-card`/`text-foreground`/`muted`/`warning*`/`destructive*`) — confirme no
  `inertia.css @theme` quais `-soft`/`-fg` existem. Se faltar variante dark de um
  amarelo/âmbar, prefira override `[data-theme=dark]` num `.css` já na allowlist a
  cunhar token novo.
- Sem emoji em UI · PT-BR · TODOs honestos.

---

## PARTE 1 — `ConversationThreadV4.tsx` (causa-raiz do painel branco)

A raiz da thread é `bg-muted/15` (token, vira escuro OK), mas o conteúdo dentro usa
cor clara **fixa** → no escuro vira "casca escura + miolo branco". Tokenizar:

| Onde (busque a string) | Hoje | Trocar por |
|---|---|---|
| Bolha **inbound** | `'bg-white border border-border rounded-bl-[3px]'` | `'bg-card border border-border rounded-bl-[3px]'` (card flipa dark; contrasta com `bg-muted/15`) |
| Read-receipt "lida" | `className="text-blue-600"` no `<CheckCheck>` | remover a classe; `style={{ color: 'oklch(0.55 0.18 250)' }}` (mantém o azul-tick, passa R1) |
| Bolha **outbound** verde | `background:'oklch(0.85 0.10 145)'` + `color:'oklch(0.18 0.10 145)'` | **manter** (verde-WA proposital, legível nos 2 temas) — só confirme no escuro |
| **Nota interna** | `bg oklch(0.97 0.03 80)` · `dashed oklch(0.78 0.10 80)` · texto `oklch(0.22 0.10 80)` · label `oklch(0.28/0.90 …80)` | dar variante escura: ou tokens `warning-soft`/`warning-fg` (se existirem no @theme), ou um par `[data-theme=dark]` (bg `oklch(0.28 0.04 80)` / texto `oklch(0.90 0.04 80)`). Mantenha legível nos 2. |
| **Banner "em homologação"** | `bg oklch(0.97 0.013 80)` · `border oklch(0.88 0.04 80)` · texto `oklch(0.32/0.28 …80)` · link `oklch(0.40 0.13 250)` | mesmo tratamento amarelo dark-aware do item acima |
| Separador de dia | `bg-card border …text-muted-foreground` | já OK (tokens) — não mexer |

> A meta: **abrir no escuro e ler tudo**. Bolhas recebidas = superfície escura;
> enviadas = verde-WA; notas/banner = âmbar legível no escuro.

**Investigar também (pode ser maior que as bolhas):** confirme que o `[data-theme]`
do AppShellV2 realmente propaga pra esta rota. Se o `<div bg-muted/15>` da thread NÃO
estiver herdando o tema (pane inteiro branco, não só bolhas), o furo está na
propagação do atributo de tema pro conteúdo da página — corrija na raiz, não só nas folhas.

## PARTE 2 — `ContextSidebarV4.tsx` (chips de tag + revisão dark)

- **Chip de Tag aplicada**: `background:'oklch(0.94 0.03 80)'` + `border oklch(0.86 0.06 80)`
  com `text-foreground` → no escuro fica claro-no-claro. Dar variante `[data-theme=dark]`
  (bg `oklch(0.30 0.04 80)`, borda `oklch(0.45 0.05 80)`) ou tokenizar.
- O resto da sidebar usa tokens (`bg-card`/`text-foreground`/`muted`) — varrer no
  escuro com o probe e tokenizar qualquer `oklch`/`white`/hex cru que sobrar
  (os dots de fila/avatar com `oklch(...)` de HUE são decorativos, ok manter).

## PARTE 3 — empty-state do Customer 360 (`CustomerMemoryBlock`)

No topo do Contexto, quando **não há contato CRM vinculado** (sem dados), o
`CustomerMemoryBlock` (`@/Pages/Whatsapp/_components/CustomerMemoryBlock`) renderiza
um **card vazio grande** (visível claro E escuro). Ajustar o próprio componente legado:
- Sem perfil/dados → colapsar (render `null`) OU empty-state enxuto de 1 linha
  ("Sem contato CRM — vincule abaixo"), sem o card vazio.
- Como é componente compartilhado com o Inbox legacy, garanta que a mudança não
  regrida o legacy (mesma prop de estado vazio).

## PARTE 4 (config, separável) — chips de canal todos "em breve"

`ChannelChipsRow` pinta "em breve" quando `ch.status === 'em_breve'`. Hoje **todos**
os chips aparecem "em breve", inclusive o canal de onde as conversas chegam
(`whatsapp_whatsmeow` / conta "Suporte"). O catálogo do `CaixaUnificadaController`
não está marcando o canal **efetivamente ativo** como `status:'ativo'`. Corrigir o
mapeamento type→status no Controller (o canal com conta ativa do business = `ativo`,
com `count` real). Pode ir em PR separado — é backend/config, não bloqueia o dark.

---

## Pest
- Os payloads já são cobertos por `R-WA-CAIXA-UNIF-001/002`. O dark é CSS/visual —
  não exige teste de unidade novo. Se for barato, adicione asserção de que a bolha
  inbound usa `bg-card` (não `bg-white`) no snapshot/estrutura.
- Rode o probe `qa-conformance` (G6 dark) na rota nos dois temas antes de fechar.

## Fechamento
- Append em `prototipo-ui/COWORK_NOTES.md` (respostas) com placar das 4 partes
  (merged/aberto) + bump do `Index.charter.md` (Histórico + versão).
- Commits/push/merge: você executa. O [CC] não escreve no git — este arquivo é a ponte.
