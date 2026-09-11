---
id: requisitos-superadmin-pacotes-gap
tela: superadmin/Pacotes/Index (/superadmin/packages)
prototipo: prototipo-ui/cowork/superadmin-page.jsx
tela_viva: Modules/Superadmin/Resources/js/Pages/superadmin/Pacotes/Index.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — superadmin/Pacotes/Index

> Protótipo = porte do Blade legado (superadmin-page.jsx:1-9), anterior às telas React (SA-O1..O3, 2026-08). Charter: `Modules/Superadmin/Resources/js/Pages/superadmin/Pacotes/Index.charter.md` (Non-Goals/Anti-hooks respeitados, nunca reabertos).

**Veredito:** PARIDADE na leitura (grid de cards, card inteiro, "0 = ilimitado", tags) · toda a ESCRITA do mockup (form, kebab, aviso de exclusão) já está decidida como SA-O4d · vivo à frente em skeleton/vazio · **1 item a decidir** (contagens no subtítulo do header).

| Parte | Estado no vivo | Ação |
|---|---|---|
| PageHeader — título | `PageHeader title="Pacotes de assinatura"` (Pacotes/Index.tsx:98); mockup mesmo título (superadmin-page.jsx:1186) | Nada — paridade |
| PageHeader — subtítulo com contagens | `description="A grade comercial da plataforma"` fixa (Pacotes/Index.tsx:98); nenhuma contagem de total/ativos no header — o contrato (`prototipo-ui/contrato/superadmin-pacotes.contract.json`) só trava a seção `superadmin.pacotes.grid`, não a copy do header | **Decidir.** O mockup (superadmin-page.jsx:1186) escreve no subtítulo `N pacotes · N ativos` computados da lista (o `· 1 privado` ali é literal fixo = mock); o vivo (Pacotes/Index.tsx:98) tem descrição estática. Construir ou rejeitar por escrito. |
| PageHeader — ação "Novo pacote" | Sem botão nem drawer de criação (Pacotes/Index.tsx:95-107); comentário :20-21 declara a onda como LEITURA | Nada — decisão já registrada (charter §Divergências declaradas contra o F1 "FormDrawer novo/editar/duplicar → SA-O4d"; RUNBOOK-pacotes §8; contrato `_nota_recorte`) |
| Grid de cards (`superadmin.pacotes.grid`) | `data-contract="superadmin.pacotes.grid"` + `<Deferred>` (Pacotes/Index.tsx:100-104); grid 1/2/3 colunas (:138); mockup `sa-pkgs` (superadmin-page.jsx:1192-1193) | Nada — paridade |
| Card — header (nome + tags privado/avulso/ativo/inativo + inativo recuado) | Nome `truncate` + 3 badges (Pacotes/Index.tsx:150-158); inativo `opacity-60` (:148); mockup :1195-1203 e classe `off` (:1194) | Nada — paridade |
| Card — kebab por card (Editar · Duplicar · Ativar/Desativar · Excluir) | Nenhum menu por card (Pacotes/Index.tsx:146-201) | Nada — decisão já registrada (charter §Divergências "kebab com 4 ações — ausente — todas escrevem, SA-O4d"; RUNBOOK-pacotes §8) |
| Card — aviso "migre antes de excluir" | Ausente; a contagem de assinantes que o aviso usaria já chega no payload (`PackagesController.php:90-141`) | Nada — decisão já registrada (charter §Divergências "depende do RUNBOOK §5.3, decisão [W]"; RUNBOOK-pacotes §5.3 e §8) |
| Card — preço + ciclo (Grátis · valor formatado · "/ N meses") | `moeda.format` via `Intl.NumberFormat` + `ciclo()` com mapa de plural (Pacotes/Index.tsx:59-82, :161-168); mockup :1213-1217 com `BRL()` mock (:13) e `per()` (:1183) | Nada — paridade (o formatador do mockup é helper mock; o vivo formata o número do payload — charter Anti-hook "não escrever literal monetário") |
| Card — 4 limites + dias de teste ("0 = ilimitado") | `limite()` (Pacotes/Index.tsx:91-93) + `<ul>` (:174-180), `plural()` de `_components/assinatura.tsx:25`; mockup `lim()` (:1180) + :1219-1225 | Nada — paridade |
| Card — módulos liberados (chips) | Badges por módulo, seção some quando vazia (Pacotes/Index.tsx:183-191); rótulos resolvidos no controller (`PackagesController.php:110`); mockup :1228-1231 com `PERM_LABEL` mock | Nada — paridade |
| Card — footer (descrição + assinantes) | Descrição condicional + `plural(assinantes)` (Pacotes/Index.tsx:193-198); contagem em 1 query agregada (`PackagesController.php:90`); mockup :1233-1236 | Nada — paridade |
| Form de criação/edição/duplicação (`PacoteForm`, 4 seções) | Não existe form nem `useForm` na tela (Pacotes/Index.tsx inteiro, 206 ln) | Nada — decisão já registrada (SA-O4d: escreve `price`, REGRA MESTRE de proibicoes + RUNBOOK-pacotes §5.1; SPEC US-SUPER-002 "Tela: … LEITURA. O FormDrawer … é a SA-O4d") |
| Feedback (toast) das ações de escrita | Sem toast (a tela não escreve) | Nada — decisão já registrada (só existe no mockup como retorno das ações do kebab/form, todas SA-O4d — superadmin-page.jsx:1178, :1204-1209) |
| Estado carregando | `GridEsqueleto` com 6 skeletons (Pacotes/Index.tsx:111-119) sob `<Deferred>` (:101); contrato lista estado `carregando` | Nada — vivo à frente (mockup renderiza `PACOTES` mock direto, sem skeleton — superadmin-page.jsx:1176-1249) |
| Estado vazio | `EmptyState "Nenhum pacote cadastrado"` (Pacotes/Index.tsx:124-135); contrato lista estado `vazio` | Nada — vivo à frente (mockup não tem `Vazio` nesta view — superadmin-page.jsx:1176-1249) |
| Catálogo completo, sem paginação/filtro | Lista inteira do controller, sem `paginate`/filtro (Pacotes/Index.tsx:121-144); mockup também renderiza tudo (:1193) | Nada — paridade (charter Non-Goal "Não pagina" + Anti-hook "Não paginar nem cortar a lista") |

## Recibos de ausência
- `grep -nE 'ativos|\.filter\(|privado ·' Modules/Superadmin/Resources/js/Pages/superadmin/Pacotes/Index.tsx` → 0   (subtítulo sem contagens)
- `grep -nE 'Novo pacote|<Sheet|useForm|onSalvar' Modules/Superadmin/Resources/js/Pages/superadmin/Pacotes/Index.tsx` → 0   (sem botão/form de criação; `FormDrawer` só aparece em comentário :20)
- `grep -nE 'Kebab|DropdownMenu|kebab' Modules/Superadmin/Resources/js/Pages/superadmin/Pacotes/Index.tsx` → 0   (sem kebab por card)
- `grep -nE 'Editar|Duplicar|Excluir|Desativar|router\.' Modules/Superadmin/Resources/js/Pages/superadmin/Pacotes/Index.tsx` → 0   (sem ações de escrita)
- `grep -nE 'migre|antes de excluir' Modules/Superadmin/Resources/js/Pages/superadmin/Pacotes/Index.tsx` → 0   (sem aviso de exclusão)
- `grep -nE 'toast|Toast|sonner' Modules/Superadmin/Resources/js/Pages/superadmin/Pacotes/Index.tsx` → 0   (sem toast)
- `grep -nE 'Confirm|AlertDialog' Modules/Superadmin/Resources/js/Pages/superadmin/Pacotes/Index.tsx` → 0   (sem confirmação — não há ação que a exija)
- `sed -n '1176,1249p' prototipo-ui/cowork/superadmin-page.jsx | grep -cE 'SkelTable|Vazio|Skeleton|EmptyState'` → 0   (mockup sem skeleton/vazio nesta view)
