---
id: requisitos-ponto-banco-horas-index-gap
tela: Ponto/BancoHoras/Index (/ponto/banco-horas)
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/BancoHoras/Index.tsx
gerado_em: 2026-09-29
---

# GAP-SPEC — Ponto/BancoHoras/Index

> **Origem:** thread `21-gap-banco-horas.md` do playbook do Ponto. Decisões citadas só existem em
> `ATA-DECISOES-2026-09-14.md`: **D-BH-KPI** (EMENDA O CHARTER, ficam os 4 tiles do protótipo) e
> **D-PONTO-DETALHE** (o extrato é rota própria; absorve a D-BH-ROTA da thread).
> **Protótipo medido nesta sha:** `ponto-telas.jsx` @ `2e3f8adb4e`, símbolo `BancoHoras` (então `:335-439`).
> **Re-medido em 2026-09-29** (branch `claude/reancora-maps-8194`, sobre o #8194): símbolo `:354-460`; o ramo
> da lista é `:430-459`; o `if (sel)` de `:373-428` é o extrato (ver `banco-horas-show-gap.md`).
> **Vivo medido nesta sha:** `resources/js/Pages/Ponto/BancoHoras/Index.tsx` @ `e4289e688` (200 linhas)
> e `BancoHorasController.php`; re-medido em 2026-09-29 (192 linhas). Toda linha abaixo saiu de `grep -n` nessa data.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Faixa de KPI | **Vivo atrás da decisão.** Vivo: 4 `KpiCard` — Crédito total, Débito total, Com crédito, Com débito (`Index.tsx:82-109`), o conjunto que o charter tinha antes da emenda de 2026-09-28 (#8087 — hoje `Index.charter.md:31-32` já descreve os 4 do protótipo). Protótipo: Crédito total e Débito total com as contagens na sub-linha, mais Colaboradores no banco e Multiplicadores (`ponto-telas.jsx:433-436`). | **D-BH-KPI decidida: ficam os 4 do protótipo e o charter é emendado.** A emenda da thread 27 já entrou (#8087, `Index.charter.md:31-37`, 2026-09-28); em 2026-09-29 o `.tsx` ainda renderiza os 4 KPIs antigos (`Index.tsx:82-109`) e muda em PR próprio. |
| Saldos por colaborador | **Diverge em colunas.** Vivo: Matrícula, Colaborador, Saldo atual, Atualizado, Ações (`Index.tsx:124-128`). Protótipo: Colaborador (+ cargo), Matrícula, **Escala**, Saldo, Última movimentação, Ação (`ponto-telas.jsx:439`, `:445-450`). | Protótipo à frente na coluna Escala e no cargo; exige o dado no payload — medido em 2026-09-29: o `transform` de `BancoHorasController.php:46-55` leva só id, matrícula, nome, saldo e `atualizado_em`, sem escala nem cargo. Decisão de forma, sem id na ata: fica para a passada de FORMA. O `data-contract="bancohoras-saldos-por-colaborador"` que a thread 17 prometia já está no card (`Index.tsx:111`). |
| Ação por linha | **Protótipo corrige a copy.** Vivo: link `Movimentos` para `/ponto/banco-horas/{colaborador}` (`Index.tsx:146-148`), como o charter (`Index.charter.md:39`). Protótipo: botão `Detalhes` que troca estado interno (`ponto-telas.jsx:450`) e linha clicável (`:444`). | Protótipo corrige (D-PONTO-DETALHE, R2): rótulo `Movimentos` e navegação para a rota. |
| Ordenação | **Paridade.** Vivo: `orderByDesc('saldo_minutos')` no servidor (`BancoHorasController.php:42`). Protótipo: `sort` por saldo desc (`ponto-telas.jsx:441`). | Nada. |
| Paginação | **Paridade.** Vivo: 30 por página (`BancoHorasController.php:43`), partial reload `only: ['saldos']` (`Index.tsx:157-179`). Protótipo: `usePagina(saldos.length, 30)` (`ponto-telas.jsx:363`). | Nada. |
| Estado vazio | **Vivo à frente.** Vivo: `EmptyState` "Nenhum saldo registrado" com a explicação de que a apuração diária popula o banco (`Index.tsx:114-118`). Protótipo: `Vazio` "Nenhum saldo registrado ainda." sem explicação (`ponto-telas.jsx:440`). | Protótipo corrige: leva a explicação. |
| Rodapé legal | **Ausente no vivo.** Protótipo: `Legal` com "append-only e imutáveis (Portaria MTP 671/2021)" (`ponto-telas.jsx:457`). Vivo (2026-09-29): o subtítulo "Ledger append-only" do `h1` não existe mais — o cabeçalho é `<PontoAreaHeader>` (`Index.tsx:67`) —, e `grep -n "Portaria"` e `grep -n "append"` dão 0 cada. | Protótipo à frente; entra com a passada de FORMA. |
