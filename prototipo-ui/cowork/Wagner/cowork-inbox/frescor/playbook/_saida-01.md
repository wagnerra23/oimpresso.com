# Saída 01 · FRESCOR: 8 linhas + caminho da Caixa + regra de validade

- **Executado por:** [CL], 2026-09-23, branch `claude/frescor-thread-01` a partir de `origin/main` `1061dbf2e0f6`.
- **Tocado:** só o prefixo — `memory/reference/prototipo-ui/FRESCOR-PRODUCAO-vs-PROTOTIPO.md` (+ este recibo).

## O que mudou no quadro (cada linha com data + sha)
| Tela | Antes | Agora |
|---|---|---|
| Atendimento/CaixaUnificada | 🔵, caminho `resources/js/Pages/Atendimento/…` (não existe) | ✅ paridade, caminho `Modules/Whatsapp/Resources/js/Pages/Atendimento/CaixaUnificada/`; resta h1 14→22px (decidido [W]) |
| Cliente — drawer 760 | 🟠 revisado 08-26 | fato de 08-26 preservado + drawer 760 ✅ (EnderecosEntregaList já no protótipo) |
| PageHeader | ⚪ ~85% | ⚪ decidido [W] 2026-09-23, execução no pedido de pageheader |
| Sidebar/Shell | ⚪ empate | ⚪ sem pendência de puxar (UI-0023 resolveu o tema) |
| Compras grade-matrix | 🟠 órfão, caminho `Compras/components/…` (não existe) | ⏳ implementado em `Purchase/_components/`, aguarda smoke |
| Financeiro/Dre | 🟠 atrás, pouco | 🟠 quase ✅ — resta 1ª coluna mono |
| Financeiro/Fluxo | 🟠 atrás | ✅ paridade de forma (FIN-2) |
| OficinaAuto/Vehicles/* | 🟠 sem fonte | 🟠 sem mudança (nota de revisão abaixo da tabela) |

+ item 4 em "Como o design usa isto": veredito com data+sha; linha com mais de 14 dias é pista.

## Conferência das evidências (relidas em `1061dbf2e0f6`)
Todas bateram com o pedido, com uma correção: as linhas do ⌘K citadas no pedido (`AppShellV2.tsx:89/735`) andaram — hoje são `:398` (atalho) e `:734` (palette); o quadro cita as atuais. O `resultado.json` da Caixa dá IGUAL em D2/D6/D8/D9, mas D4 e SHELL saíram SEM-DADO — o quadro diz isso, não "igual em tudo".

## Provas do índice
- `contem` `Modules/Whatsapp/Resources/js/Pages/Atendimento/CaixaUnificada` → 1 ocorrência.
- `nao_contem` a linha antiga da Caixa (`inbox-page.jsx` (15/mai…) → 0; caminho morto `resources/js/Pages/Atendimento/CaixaUnificada` → 0.
- `contem` `14 dias` → 1.

## O que NÃO foi feito
Nenhuma medição de runtime (T7). Não cobre Ponto nem o "RESTO DO WORKSPACE". Não edita `00-INDICE.md` nem `_SESSAO-FRIA.md`.
