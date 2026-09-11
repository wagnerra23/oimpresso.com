---
sessao: "_saida-03"
thread: "03 · Seção TOPO — paridade CompanyPicker + slot de alerta pós-picker"
dono: "[CC]"
data: 2026-09-10
prefixo_tocado: prototipo-ui/cowork/sidebar.jsx (topo) · data.jsx · styles.css · (host: bump de ?v=)
base_lida: Components/cockpit/NfeCertBadge.tsx (inteiro, main 2026-09-10)
residuo: RESÍDUO-4 respondido por [W] — importar o slot real
---
# _saida-03

## Feito
`NfeCertBadge` + `NfeCertBadgeRail` no protótipo, entre `.sb-top` e `.sb-body` — a posição que o vivo documenta em `NfeCertBadge.tsx:25` ("após CompanyPicker, antes do SidebarMenu"). Estado vem de `MOCK.NFE_CERT` (`data.jsx`), espelhando `shell.nfe_cert_status` do backend.

Portado do vivo, não inventado: os 4 estados (`sem_cert`/`ok`/`vencendo`/`vencido`), a regra de silêncio (só os dois críticos renderizam), as cores exatas (exceção R-DS-002 — status fixo de alerta), os textos literais ("Cert vence em breve" / "Certificado vencido", "N dias restantes" / "há N dias"), a singularização de "dia/dias", o destino `/fiscal/config` e o `title` completo.

## Medição — os 4 estados
| `MOCK.NFE_CERT` | expandido | rail |
|---|---|---|
| `ok` | silencioso | silencioso |
| `vencendo · 12` | "Cert vence em breve / 12 dias restantes" · fg `oklch(0.82 0.1 80)` · borda `oklch(0.78 0.15 80)` | ícone 40×40 |
| `vencido · -3` | "Certificado vencido / há 3 dias" · fg `oklch(0.78 0.1 25)` · borda `oklch(0.55 0.2 25)` | ícone 40×40, `aria-label` completo |
| `sem_cert` | silencioso (mesmo ramo do `ok`) | silencioso |

Ordem no DOM medida: `sb-top → sb-cert → sb-body → sb-user`. Caixa: 243×46px, 11px, raio 8px — igual ao inline do vivo (`padding 8px 10px`, `margin 6px 8px`, `borderRadius 8`).

## Decisões que o vivo não tomou por mim
1. **O rail não existe no vivo.** O `AppShellV2` monta o badge sem variante estreita — em 56px o texto não cabe. Fiz `NfeCertBadgeRail`: só o ícone, com o texto inteiro no `aria-label` e no `data-tip`. **Esta é invenção minha** — se o vivo adotar, tem de decidir o mesmo.
2. **`<button>`, não `<a href>`.** O vivo navega por URL; o protótipo roteia por `onSelectRoute`. Mantive o alvo `fiscal-config`.
3. **Ícones:** `I.shield`/`I.alert` do `icons.jsx` no lugar de `ShieldAlert`/`AlertTriangle` do Lucide — mesma leitura, sem dependência nova.

## Não feito
- **O diff bidirecional do `CompanyPicker` não foi feito** — o playbook pedia a tabela linha a linha nos dois sentidos, e eu fui direto ao slot, que era o que [W] pediu. A divergência estrutural do topo está fechada; **a de detalhe do picker continua não medida.**
- `light-dark()` do vivo virou só o ramo dark: a sidebar é preta nos dois modos (UI-0023), então o ramo claro nunca se aplica aqui.
- A11y do slot: `title` + `aria-label` conferidos; **contraste do fg sobre o bg tintado não medido em número.**
