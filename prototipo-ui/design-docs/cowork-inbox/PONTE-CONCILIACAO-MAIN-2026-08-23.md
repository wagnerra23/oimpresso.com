# Conciliação Cowork → main — fronteiras, arquivos e ondas

> [CC] · 2026-08-23 · pedido [W]. Base: leitura do `main` (`23498881ccf8`) neste turno + varredura do disco local.
> **Limite honesto:** o que está marcado ⚠️ NÃO foi conferido contra o `main` arquivo a arquivo — é escopo estimado, não fato.

## A fronteira (a régua que resolveu tudo)

Um arquivo só sai daqui se tiver **dono canônico no `main` E estiver lá de fato**. As duas metades importam: a primeira sozinha foi o erro que quase apagou cópia única.

| Vive aqui (esteira) | Vive no `main` (armazém) |
|---|---|
| `oimpresso.com.html` + os ~214 jsx/css que ele carrega | memória, ADRs, lições |
| `_ds/` (DS vivo, linkado) | charters e casos (`resources/js/Pages/**`) |
| `github.md`, `CLAUDE.md` | contratos de tela (`prototipo-ui/contrato/`) |
| | telas vivas (`.tsx`) |

Tudo que hoje está aqui e **não** é build está nesta lista porque é **cópia única** — não porque pertence à esteira. Sobe, e some daqui.

---

## ONDA 1 — Build da Grade Matrix (destrava o guard)

**Destino:** `prototipo-ui/cowork/`

| Arquivo | O que é |
|---|---|
| `compras-grade-matrix.jsx` | tela portada de `prototipo-ui/prototipos/compras-grade-matrix/page.jsx` |
| `compras-grade-matrix.css` | idem, com a paleta bespoke trocada por tokens `.cockpit` |

Fecha metade da allowlist do `cowork-ssot-guard`. A outra metade (`inventario-migracao`) está bloqueada por charter ausente — decisão sua, não trabalho meu.
**Efeito colateral esperado:** o protótipo antigo em `prototipo-ui/prototipos/compras-grade-matrix/` passa a ser o legado; considerar mover pro `_arquivo/` do repo.

## ONDA 2 — Contratos de tela (3 arquivos, cópia única)

**Destino:** `prototipo-ui/contrato/` (ADR 0286)

| Arquivo | Está no main? |
|---|---|
| `contrato/configuracoes.contract.json` | ❌ não |
| `contrato/patrimonio.contract.json` | ❌ não |
| `contrato/venda-menu.contract.json` | ❌ não |

Os 11 contratos que já existem no `main` são outros — não há colisão de nome. Onda mecânica.

## ONDA 2b — Charters órfãos (10 arquivos, cópia única) · NOVO 15:10Z

**Destino:** `resources/js/Pages/**` (já estão no caminho canônico aqui)

O `main` só tem `Ponto/Welcome.charter.md`. Estes 10 não existem lá:

| Arquivo |
|---|
**Inventário FECHADO** (28 arquivos locais × 33 no main, conferidos 15:15Z). 13 órfãos:

*Trio completo ausente no main (charter + casos):*

| Arquivo |
|---|
| `Ponto/Conformidade.charter.md` + `.casos.md` |
| `Ponto/Fechamento.charter.md` + `.casos.md` |
| `Ponto/Index.charter.md` + `.casos.md` |
| `Ponto/RepP.charter.md` + `.casos.md` |
| `Relatorios/Index.charter.md` + `.casos.md` (fora de Ponto) |

*Só o `.casos.md` falta no main (o charter já está lá):*

| Arquivo |
|---|
| `Ponto/Colaboradores/Index.casos.md` |
| `Ponto/Configuracoes/Index.casos.md` |
| `Ponto/Escalas/Index.casos.md` |

Charter ausente **bloqueia F3** (gate ADR 0107); casos ausente derruba o critério de pronto (trio .tsx+charter+casos com UC). Onda mecânica — mesmo path, sem conflito de nome.

**Nada a fazer** com os arquivos que só existem no main (Welcome, Dashboard/Index, BancoHoras/Show, Colaboradores/Edit, Configuracoes/Reps, Escalas/Form, Importacoes/Create+Show, Intercorrencias/Create+Show) — produção à frente, correto.

## ONDA 3 — Intake do Cowork (24 arquivos, cópia única)

**Destino:** decidir por item — `cowork-inbox/` do repo, ou destilar no charter da tela.

`cowork-inbox/` **não existe no `main`** (0 arquivos). Tudo aqui é original. Agrupado por natureza:

- **Pedidos pro [CL]** (4): `PEDIDO-CL-applier-digest`, `PEDIDO-CL-programa-doc`, `PEDIDO-CL-programa-doc-react`, `MODULOS-F3-ONDAS-PARA-CODE`, `SUPERADMIN-F3-ONDAS-PARA-CODE`
- **F1 entregues** (6): `ACESSOS-F1`, `CMS-F1`, `MODULOS-F1`, `NOTIFICACOES-F1`, `SUPERADMIN-F1`, `CATCHUP-F1`
- **Jana** (5): `JANA-CICLO-COMPLETO-PRODUCAO`, `JANA-FASE2`, `JANA-FUSAO`, `JANA-MODULO-ONDAS-PR`, `JANA-PAINEL-DARK-PARIDADE`
- **Forja/planos** (5): `FORJA-COCKPIT-CHARTER-V2-PROPOSTA`, `FORJA-TOPNAV-3GRUPOS-LEVA1`, `PLANO-BLADE-PARA-REACT`, `PLANO-MESTRE-trilha-d-ciclo-completo`, `INVENTARIO-L1-VENDAS-PDV`
- **Outros** (2): `FICHA-BL-home-index`, + subpastas (`acessos/`, `casos-financeiro-2026-08-17/`, `cms/`, `connector/`, `essenciais/`, `hrm/`, `modulos/`, `notificacoes/`, `produto/`, `produto-telas-novas/`, `programa-doc/`, `venda-menu/`) ⚠️ conteúdo não inventariado

Não é onda mecânica: cada item ou vira Issue, ou é destilado num charter, ou morre. Precisa de você.

## ONDA 4 — Resíduo de patch ⚠️ (escopo não conferido)

`prototipo-ui-patch/` — 4 prompts restantes (`ONDAS-FINANCEIRO-APLICAR`, `ERRADICA-LOCACAO-ACTIONS`, `FORJA-ABSORCAO-TEAMMCP`, `PROMPT_MESTRE_SESSAO_2026-06-29`) + 10 subpastas espelho (`Modules/`, `Pages/`, `app/`, `memory/`, `resources/`, `routes/`, `scripts/`, `prototipos/`, `prototipo-ui/`, `pageheader-canon-v4/`).

As subpastas são espelho de caminhos do repo — **provavelmente** duplicata stale, que é exatamente a causa-raiz do L-42. Mas "provavelmente" não é fato: precisa de um diff por arquivo contra o `main` antes de qualquer delete. Mesma coisa para `resources/` e `scripts/` na raiz daqui.

---

## Resumo

| Onda | Arquivos | Natureza | Bloqueio |
|---|---|---|---|
| 1 · Build Grade Matrix | 2 | mecânica | nenhum — pronta |
| 2 · Contratos | 3 | mecânica | nenhum — pronta |
| 3 · Intake | ~24 + subpastas | curadoria | precisa de [W] item a item |
| 4 · Resíduo de patch | ⚠️ ~centenas | diff antes de decidir | precisa do diff |

**Ondas 1 e 2 são 5 arquivos e podem subir hoje.** As ondas 3 e 4 não são trabalho de export — são decisão e verificação.

> Eu não escrevo no git. Isto é o pedido; a ponte é você colar 1× ou abrir Issue → PR.
