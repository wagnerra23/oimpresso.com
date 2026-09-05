---
sessao: "08"
titulo: Feriados — PUXAR (produção à frente, conteúdo não lido)
dono: "[CC] read-only"
base: 159e572dd448
prefixo: nenhum na 1ª fase. Se gap: hrm-extras.jsx (`Feriados`) do build — puxar o vivo pro protótipo, nunca o contrário
nao_toca: resources/js/Pages/Essentials/Holidays/Index.tsx · EssentialsHolidayController · DS
depende: — (vaga 1)
---
# 08 · Feriados — puxar o vivo

## Estado
`/hrm/holiday` **já é Inertia**: `resources/js/Pages/Essentials/Holidays/Index.tsx` (14.768 B) + `Index.charter.md` existem no `main`. Em 04/09 tratei Feriados como 🔵 **pela existência**, não por paridade medida — o conteúdo **não foi lido**. Esta thread fecha isso.

## O que esta thread faz
1. Ler `Holidays/Index.tsx` + `Index.charter.md` no `main` (no turno).
2. Medir o protótipo (`hrm-extras.jsx`, `Feriados`): 882 nós · `os-table` 7 col · `th` ordenável por nome/ini/dias com `button.mod-sort` dentro do `th` (o padrão a11y **certo** — o `DataTablePro` do DS não faz isso, RESÍDUO 4).
3. Diff nos dois sentidos: o que a Page viva tem que o protótipo não tem (átomos, aria, `data-testid`, colunas reais) → **entra no build**; o que o protótipo tem que a Page não tem → só vira pedido se for **comportamento** (ordenar, filtrar), nunca layout.
4. Se a Page viva tiver `TH` ordenável sem semântica → é dívida do DS (RESÍDUO 4), não desta tela.
5. `_saida-08.md` com o diff em tabela.

## PARAR SE
- A Page viva tiver skeleton/lazy → T1 (duas leituras iguais) antes de contar.
- O diff exigir mudar a Page viva → não é desta thread; vira pedido de 1 arquivo, com o UC do `casos.md` da Holidays.

## Prova
- `_saida-08.md` com o diff nos dois sentidos e a sha lida
- Se entrou no build: `hrm-extras.jsx` no espelho `prototipo-ui/cowork/` com os átomos puxados
