# Teste estático dos pacotes — 01/10/2026

**Não executado:** sem aparelho, sem Expo e sem `tsc` neste ambiente. O teste foi conferir cada import, nome de ícone, prop e tipo contra o código do `main`.

## Corrigido nesta rodada
| Onde | Problema | Correção |
| --- | --- | --- |
| onda-2 OiForm | `{...rest}` depois de onFocus/onBlur: se a tela passasse onFocus, o anel de foco sumia | spread antes dos handlers |
| onda-2 oss.tsx | duas requisições (lista filtrada + contagem) | uma requisição, filtro no aparelho |
| onda-4 offline-banner | "Sincronizar agora" com 32 + 4 = 40 px | 36 + 4 = 44 px |
| onda-5 ponto | grade dos 4 tipos com `48.5%` + gap 8 quebrava em 1 coluna em tela estreita | `flexBasis: 45%` + `flexGrow` |
| onda-5 use-ponto | `globalThis.crypto` sem tipo no TS do RN | cast igual ao mutation-queue |

## Conferido e ok
- Ícones usados existem no `OiIconName` (zap, paperclip, image, trash, edit, wrench, truck, location, shield, calendar, refresh, file, chart, check-circle…).
- Hooks importados existem: `use-network-state`, `use-debounced-value`, `auth-context`, `mutation-queue` (`getQueue`, `isNetworkError`).
- `OiStatusVariant`, `ToastTone`, `useToast` são exportados pelo barrel.
- Chamadas de `erp-queries` e payloads iguais ao `main` nas 3 telas reescritas.

## Resolvido na segunda rodada
| Risco | Como |
| --- | --- |
| Tipos de erp-queries | Conferido no demo/handlers: `customer` (lite), `vehicleId`, `dataEntrada` no histórico, `customerId` no veículo — batem com o uso. |
| Ciclo de import | Nenhum arquivo em components/oi importa erp-ui ou screen-container. |
| Hermes + Intl | Data longa do relógio montada à mão (meses/dias em PT). |
| Sheet sobre sheet | `OiSelectField inline`; o adaptador Select e o formulário de Veículos usam inline. |
| Rotas da API do Ponto | Lidas em routes.php: `/ponto/api/marcar` etc. (o pacote usava `/ponto/api/mobile/…`, errado). |
| Meu espelho vazio | Usa as rotas existentes: `/saldo`, `/escala/hoje`, `/intercorrencias`. |

## Risco que só o tsc / aparelho pegam
1. **Dependência entre ondas:** OiForm usa `touch` de `oi-theme.ts` → Onda 2 não compila sem a 1.
2. **Tipos de `erp-queries`** não lidos: `os.vehicle`, `os.customer`, `h.dataEntrada`, `v.customerId` assumidos pelo uso no código antigo.
3. **Ciclo de import:** `erp-ui.tsx` (adaptador) importa `@/components/oi`; se algum Oi* importar erp-ui, vira ciclo. Hoje nenhum importa.
4. **Hermes + Intl:** `toLocaleDateString(..., { dateStyle: "full" })` no relógio do Ponto pode cair no formato curto em Android antigo.
5. **Select virou sheet** (adaptador): telas que abriam o select dentro de um ModalDialog agora empilham sheet sobre sheet — conferir em Veículos (antiga), Clientes, Estoque.
6. **Onda 5:** token Passport e caminhos da API — bloqueios do README, não testáveis sem o Wagner.
