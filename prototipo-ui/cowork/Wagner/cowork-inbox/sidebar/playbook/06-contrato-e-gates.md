---
sessao: "06"
titulo: Contrato de tela do shell + gates — travar a Sidebar no CI
dono: "[CL]"
base: af09f7c3a0fd
prefixo: prototipo-ui/contrato/cockpit-sidebar.contract.json · tests/Feature/Sidebar/
nao_toca: Components/cockpit/** · Layouts/AppShellV2.tsx (esta thread não muda comportamento, só o trava)
depende: 01 · 03 · 04 (o contrato descreve o estado final, não o intermediário)
---
# 06 · Contrato de tela + gates

## A · O que é
`prototipo-ui/contrato/*.contract.json` (ADR 0286) declara **seções + copy literal + estados** e trava o comportamento no CI. A Sidebar nunca teve um: é o shell de **todas** as telas, e hoje o que impede regressão nela é teste de PHP mais screenshot de humano.

## B · Seções a declarar (o `alvo` é o shell depois das threads 01·03·04)
| seção | conteúdo travado | estados |
|---|---|---|
| `topo` | `CompanyPicker` (nome do business + iniciais + lista) · slot de alerta pós-picker | expandido · rail · alerta silencioso/crítico |
| `corpo` | `<nav aria-label="Navegação principal">` · grupos do `SIDEBAR_GROUPS` (vender · operar · financas · pessoas · sistema) · item single-link (AP19) · contadores (chat · atendimento · tarefas) · `.sb-kbd` | grupo aberto/fechado · item ativo · ghosts (**conforme a saída da 05**) |
| `rodape` | `SidebarFooter`: usuário + cascata Superadmin | menu aberto/fechado |
| `modos` | expanded · rail · **hidden** · drawer mobile ≤768px | + auto-rail sem escolha persistida (UI-0030) |
| `alcas` | `.sb-collapse-handle` (⌘\\) · `.sb-reopen-handle` (⌘⇧\\) | hover · escondidas no mobile |

Copy literal a travar (PT-BR, exatamente como está no vivo): `"Navegação principal"` · `"Expandir sidebar (⌘\\)"` · `"Recolher sidebar (⌘\\)"` · `"Mostrar sidebar"` · `"Abrir menu"` / `"Fechar menu"`.

## C · Gates
```
npm run contrato:check
php artisan test --filter=Sidebar          # tests/Feature/Sidebar/* + SidebarConsolidacaoTest + SidebarCountsTest
php artisan test --filter=Cockpit          # PatternConformance · Typography · AccentCanon
php artisan test --filter=AppShellUsageGate
node scripts/governance/cowork-ssot-guard.mjs
node scripts/qa/prototipo-readiness.mjs
```
Regras que o contrato **não** substitui e que continuam sendo lei: sidebar PRETA nos dois modos (UI-0023) · `.sb-item.is-open` não clareia · item single-link (AP19) · nenhum grupo cross-módulo em `AdminSidebarMenu.php` (agrupamento é frontend, `SIDEBAR_GROUPS`) · sem cor crua.

## D · Não inventar
- Reusar o `contract.schema.json` existente e a forma dos contratos já vigentes (`essentials-metas` é a irmã mais recente) — não criar schema novo pra shell.
- Se o schema não descrever "shell sem rota", **parar e reportar**: é emenda de ADR 0286, não improviso de campo.

## Execução
```
PASSO A PASSO : 1) confirmar que 01·03·04 fecharam (senão o contrato nasce descrevendo estado que vai mudar)
                2) ler contract.schema.json + um contrato vigente  3) declarar as 5 seções  4) contrato:check
                5) rodar os 5 gates  6) placar no corpo do PR  7) _saida-06.md
PARAR SE      : a 05 continuar bloqueada → declarar `corpo` SEM a linha de ghosts e marcar o buraco no _saida
                (contrato que chuta o lado do conflito vira lei por acidente)
```

## Prova
- `prototipo-ui/contrato/cockpit-sidebar.contract.json` válido no schema, com `alvo` e `secoes`.
- Os 5 gates verdes, placar no PR, `_saida-06.md`.
- Não verificável daqui: T7 · screenshot prod ([W2]).
