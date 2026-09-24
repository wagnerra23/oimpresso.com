---
thread: "08 · Feriados — PUXAR"
dono: "[CC]"
estado: feito
base_lida: wagnerra23/oimpresso.com@main 68e071305601 (2026-09-24)
prefixo_tocado: hrm-page.jsx (função Feriados) · oimpresso.com.html (?v=hrm11fer)
---
# _saida-08 · Feriados — o vivo puxado para o protótipo

Lidos no turno: `resources/js/Pages/Essentials/Holidays/Index.tsx` (14.768 B @ba3955181ce2) + `Index.charter.md` (3.459 B @2c49bf4b18f1). A Page usa `<Deferred>` (skeleton) — comparei o **código**, não o render; a medição T1 fica para o T7.

## Produção tem, protótipo não tinha → entrou no build
| item | produção | protótipo agora |
|---|---|---|
| filtro de período | `De` / `Até` (`type=date`), SQL | `De` / `Até` na toolbar, sobreposição de período |
| limpar filtros | botão **Limpar** quando há filtro ativo | idem; o estado vazio filtrado também limpa os 3 |
| ordem padrão | `start_date` DESC (charter) | `ini` desc |
| não-admin | botões Novo/Editar/Excluir **ocultos** (`can_manage`) | ocultos (antes: desabilitados) · coluna Ações some |
| rótulo da coluna | `Nota` | `Nota` (era "Observação") |
| nome acessível das ações | — (ícone sem rótulo: dívida da Page) | `aria-label="Editar <nome>"` / `"Excluir <nome>"` |

## Protótipo tem, produção não → não vira pedido de layout
| item | natureza | destino |
|---|---|---|
| ordenar por Feriado / Início / Dias (`button.mod-sort` no `th`) | **comportamento** | candidato a pedido de 1 arquivo, com UC novo no `casos.md` — não abri |
| 3 KPIs (no ano / só de uma localidade / maior parada) | layout | fica no protótipo |
| Início · Fim · Dias em 3 colunas (prod: "Período" + badge) | layout | fica |
| "negócio inteiro" (prod: "Todas") | copy | fica — explica o domínio (persona Iniciante) |

## Dívida da Page viva (não é desta thread)
- Botões de ação só com ícone (`<Edit/>`, `<Trash2/>`) **sem nome acessível** — falha A-nome. Pedido de 1 arquivo se [W] quiser.
- `Holidays/Index.casos.md` **não existe** no `main` (a pasta tem só `.tsx` + `.charter.md`) — trio incompleto; o `prototipo-readiness` não a conta como pronta.

## Não verificado
- Semântica exata do filtro de datas no `EssentialsHolidayController` (sobreposição × início dentro do intervalo) — não li o controller.
