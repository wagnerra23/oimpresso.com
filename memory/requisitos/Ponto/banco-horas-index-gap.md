---
id: requisitos-ponto-banco-horas-index-gap
tela: Ponto/BancoHoras/Index (/ponto/banco-horas)
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/BancoHoras/Index.tsx
gerado_em: 2026-09-28
---

# GAP-SPEC — Ponto/BancoHoras/Index

> **Origem:** thread `21-gap-banco-horas.md` do playbook do Ponto. Decisões citadas só existem em
> `ATA-DECISOES-2026-09-14.md`: **D-BH-KPI** (EMENDA O CHARTER, ficam os 4 tiles do protótipo) e
> **D-PONTO-DETALHE** (o extrato é rota própria; absorve a D-BH-ROTA da thread).
> **Protótipo medido nesta sha:** `ponto-telas.jsx` @ `2e3f8adb4e`, símbolo `BancoHoras` (`:335-439`).
> O ramo da lista é `:409-438`; o `if (sel)` de `:352-407` é o extrato (ver `banco-horas-show-gap.md`).
> **Vivo medido nesta sha:** `resources/js/Pages/Ponto/BancoHoras/Index.tsx` @ `e4289e688` (200 linhas)
> e `BancoHorasController.php`. Toda linha abaixo saiu de `grep -n`.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Faixa de KPI | **Vivo atrás da decisão.** Vivo: 4 `KpiCard` — Crédito total, Débito total, Com crédito, Com débito (`Index.tsx:90-117`), o conjunto do charter (`Index.charter.md:31`). Protótipo: Crédito total e Débito total com as contagens na sub-linha, mais Colaboradores no banco e Multiplicadores (`ponto-telas.jsx:411-416`). | **D-BH-KPI decidida: ficam os 4 do protótipo e o charter é emendado.** A emenda é da thread 27; o `.tsx` muda depois dela, em PR próprio. |
| Saldos por colaborador | **Diverge em colunas.** Vivo: Matrícula, Colaborador, Saldo atual, Atualizado, Ações (`Index.tsx:130-136`). Protótipo: Colaborador (+ cargo), Matrícula, **Escala**, Saldo, Última movimentação, Ação (`ponto-telas.jsx:418`, `:424-429`). | Protótipo à frente na coluna Escala e no cargo; exige o dado no payload (`BancoHorasController.php:45` transforma a linha — não medido se traz escala). Decisão de forma, sem id na ata: fica para a passada de FORMA. A thread 17 grava `data-contract="bancohoras-saldos-por-colaborador"`. |
| Ação por linha | **Protótipo corrige a copy.** Vivo: link `Movimentos` para `/ponto/banco-horas/{colaborador}` (`Index.tsx:154-155`), como o charter (`Index.charter.md:33`). Protótipo: botão `Detalhes` que troca estado interno (`ponto-telas.jsx:429`) e linha clicável (`:423`). | Protótipo corrige (D-PONTO-DETALHE, R2): rótulo `Movimentos` e navegação para a rota. |
| Ordenação | **Paridade.** Vivo: `orderByDesc('saldo_minutos')` no servidor (`BancoHorasController.php:41`). Protótipo: `sort` por saldo desc (`ponto-telas.jsx:420`). | Nada. |
| Paginação | **Paridade.** Vivo: 30 por página (`BancoHorasController.php:42`), partial reload `only: ['saldos']` (`Index.tsx:165-189`). Protótipo: `usePagina(saldos.length, 30)` (`ponto-telas.jsx:343`). | Nada. |
| Estado vazio | **Vivo à frente.** Vivo: `EmptyState` "Nenhum saldo registrado" com a explicação de que a apuração diária popula o banco (`Index.tsx:122-126`). Protótipo: `Vazio` "Nenhum saldo registrado ainda." sem explicação (`ponto-telas.jsx:419`). | Protótipo corrige: leva a explicação. |
| Rodapé legal | **Ausente no vivo.** Protótipo: `Legal` com "append-only e imutáveis (Portaria MTP 671/2021)" (`ponto-telas.jsx:436`). Vivo: o subtítulo do `h1` diz "Ledger append-only" (`Index.tsx:69`), sem a Portaria (`grep -n "Portaria"` = 0). | Protótipo à frente; entra com a passada de FORMA. |
