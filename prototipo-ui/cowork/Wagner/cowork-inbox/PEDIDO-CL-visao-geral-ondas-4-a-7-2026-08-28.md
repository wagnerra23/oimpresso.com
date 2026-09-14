# PEDIDO [CC] → [CL]/[W] — "Visão geral": o que falta para 100% de paridade (ondas 4→7)

> [CC] 2026-08-28 · **lido no `main` NESTE turno** (árvore `01bee7581edb`): `resources/js/Pages/Home/Index.tsx`, `Index.charter.md` (v4), `Pages/Home/_components/GradesPainel.tsx`, `app/Services/Dashboard/GradesDoPainelService.php`, `resources/js/Components/shared/PeriodBar.tsx`, tree de `resources/views/home/**` (12 arquivos).
> **Não li neste turno** (e por isso não afirmo estado): `HomeController.php`, `index.blade.php` (91.541 B), `public/js/home.js`, `routes/web.php`, `permissions.php`, `AppShellV2.tsx`, `tests/Feature/Home/*`.
> Não commitei, não abri branch, não abri PR. As tools de git aqui são leitura.

---

## 1. Resposta curta

**Não falta muito — e o que falta inverteu de lado.** O vivo passou o protótipo: entre 27/08 e hoje entraram US-DASH-002 (2 gráficos deferidos), US-DASH-004 (PeriodBar + `location_id` chegando aos 3 `TransactionUtil::`) e US-DASH-005 (8 abas de grade + drawer), com charter em **v4** e 14 testes GUARD declarados. O gap de hoje é de **3 camadas**, não de tela.

Do meu pedido de 27/08, **7 dos 12 itens do mapa estão entregues** e 5 morreram por decisão registrada no charter (aba "Fluxo de caixa", `count` por aba, Exportar CSV, "Lançar pagamento" como ação, EmptyState de erro com retry).

**Feito neste turno no F1** (`dash-legacy-page.jsx`, mesmo arquivo, sem `.html` nem rota nova): reconciliei o protótipo com o vivo — 9 abas → **8**, gates reais (`perms` OR + os 3 settings do business), sem contagem por aba, sem CSV, pagamento como navegação GET, `deltas: null` no FY/intervalo livre, paginação de 10 (`POR_PAGINA`), datas ISO formatadas na UI, estados Deferido/Falha por tweak. Pendências e o painel de settings ficaram marcados **`fora do vivo`** — são proposta e instrumento, não capacidade.

---

## 2. Placar por camada (vivo × F1)

| Camada | Vivo (`main`) | F1 (Cowork) | Estado |
|---|---|---|---|
| PageHeader + stats do período | ✅ subtitle com 3 valores | ✅ `PageHeader stats` | pariado |
| Saudação "Bem-vindo, {nome}" | ✅ | ✅ | pariado |
| PeriodBar (dia/semana/mês + De/Até, default FY) | ✅ servidor resolve | ✅ | pariado |
| Filtro loja (`is_admin && locations > 1`, query string) | ✅ | ✅ | pariado |
| 4 KPI (hero Líquido) + deltas nulos sem base | ✅ | ✅ | pariado |
| Contrapartidas (4 números) | ✅ | ✅ | pariado |
| 2 gráficos deferidos + skeleton | ✅ `Deferred` | ✅ tweak "Deferido" | pariado |
| 8 abas + gates (perm OR + setting) | ✅ service | ✅ catálogo espelhado | pariado |
| Grade paginada 10/pág + drawer PT-02 | ✅ | ✅ | pariado |
| Pagamento = navegação GET | ✅ `/payments/add_payment/{id}` | ✅ | pariado |
| **Pendências** (atalho lateral) | ❌ backlog | 🟠 desenhado, `fora do vivo` | **falta** |
| **Widget registry React** (US-DASH-003) | ❌ só `?legacy=1` | ❌ declarado no Alert | **falta** |
| **EmptyState de erro na grade** | ❓ não medido | ✅ | **conferir** |
| **Contagem por aba / CSV** | ❌ Non-Goal | ❌ removido | morto por decisão |
| Aba "Fluxo de caixa" | ❌ sem fonte no Blade | ❌ removida | morto por decisão |

---

## 3. As ondas que faltam

### Onda 4 — fechar a paridade barata (sem ADR, sem decisão de [W])

1. **Reconciliar a âncora** — o charter v4 aponta `related_prototype: prototipo-ui/cowork/dash-legacy-page.jsx`, e o arquivo apontado descrevia 9 abas com CSV. Trocar pelo build desta entrega (é o único artefato desta onda que sai daqui).
   *Nota do charter a corrigir no mesmo PR:* a v4 diz `PT-04 Dashboard`; o arquétipo do Dashboard é **PT-05** (PT-04 é o Modal). O gate `pt_declarado` casa `/PT-0[1-5]/` e não pega a troca.
2. **Estado de erro da grade dito** — se a consulta da aba falhar, a tela precisa dizer (`EmptyState` de erro + "tentar de novo"), não ficar no skeleton. É a herança que motivou o rewrite (no Blade o loader girava pra sempre). **[CL]: conferir se `Deferred` já tem `onError`; se não, é caso de teste novo.**
3. **`aria-label` + `data-screen-label`** no bloco de grades e nos painéis (o F1 já leva).
4. **Contrato de tela** — `prototipo-ui/contrato/visao-geral.contract.json` (ADR 0286) com as seções na ordem (header → período → KPI → contrapartidas → gráficos → grades → banner) e a copy literal dos 8 rótulos de aba, dos 4 rótulos de contrapartida e das 2 mensagens de vazio. Sem isso a copy anda sozinha entre PRs.

### Onda 5 — Pendências (precisa de palavra de [W], não de ADR)

O charter deixou no backlog com motivo bom: cada linha é um `COUNT` por render. **Caminho que respeita o motivo:** os números que Pendências mostra são derivados das **mesmas queries das abas** — 1 `COUNT` agregado por aba permitida, num único `Inertia::defer('pendencias')`, depois do first paint, com cache de request. Se [W] quiser o atalho, é 1 query agregada, não 8 round-trips.
Se não quiser: **remover Pendências do F1** em vez de deixar desenhado — protótipo que mostra o que não existe vira pedido implícito.

### Onda 6 — US-DASH-003, widget registry React (exige ADR nova)

Último motivo pelo qual o `?legacy=1` ainda precisa existir para além do canário. Sequência: ADR do registry (contrato do slot, quem registra, ordem, permissão) → 1 módulo piloto → banner do rodapé muda de "Abrir versão completa" para o que sobrar. **Enquanto não houver ADR, o Alert do rodapé fica** — é dívida declarada, não escondida.

### Onda 7 — aposentar o Blade (a pergunta que fecha "100%")

Paridade de tela ≠ Blade poder sair. Falta medir, e eu **não medi**:
- `resources/views/home/index.blade.php` (91.541 B) + `calendar.blade.php` + `notification_modal` + `todays_profit_modal` + os **8 partials** (`net`, `expense`, `invoice_due`, `purchase_due`, `total_sell`, `total_purchase`, `total_sell_return`, `total_purchase_return`) — o React cobre os 8 partials como números; **`calendar`, `notification_modal` e `todays_profit_modal` não têm equivalente na tela nova** e o charter só declara Non-Goal para `/calendar`.
- Os 4 endpoints AJAX seguem intactos por Non-Goal — quando o Blade sair, eles ficam órfãos ou viram API pública? É decisão, não limpeza.
- Telemetria de uso do `?legacy=1` antes de qualquer data de desligamento.

---

## 4. Decisões que são de [W]

1. **Pendências entra** (onda 5, com o `defer` agregado) **ou sai do protótipo?**
2. **Widget registry** (onda 6): abre ADR agora ou o `?legacy=1` fica indefinidamente como a porta dos widgets?
3. **`todays_profit_modal` e `notification_modal`**: portar, matar, ou declarar Non-Goal explícito no charter v5?

## 5. Gates a rodar (cada PR)

`npm run contrato:check -- prototipo-ui/contrato/visao-geral.contract.json` · `node scripts/qa/prototipo-readiness.mjs` · `casos:check` · Pest `HomeIndexInertiaTest` + `GradesDoPainelTest` · anchor gates (`anchor-content-required`, `anchor-drift`) · `cowork-ssot-guard.mjs`.

## 6. Limites desta entrega

Escrevi, não executei: nenhum gate rodou aqui. O que digo do vivo vem da leitura de hoje listada no cabeçalho; o que está marcado "não li" segue não afirmado.
