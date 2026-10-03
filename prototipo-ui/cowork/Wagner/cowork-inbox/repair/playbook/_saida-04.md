---
sessao: "04"
titulo: Recibo — portal do cliente = ConsultaOs ligado ao Repair (US-CONSULTA-001)
autor: "[CL]"
data: 2026-10-02
base: origin/main 2f3829026e
---

# _saida-04 · Portal do cliente

## Decisão que guiou
[W] 2026-10-02, *"Ligar o ConsultaOs ao Repair"* (D-PORTAL em `_DECISOES-W-2026-10-02.md`). Nada
de `Pages/Repair/Portal`: o portal é o `/consulta-os`, e ele passou a ler as folhas de OS reais.

## O que foi entregue
- **US-CONSULTA-001 (parcial):** `Modules/ConsultaOs/Repositories/RepairConsultaOsRepository.php`
  troca o mock de 4 OS fixas pela consulta real em `repair_job_sheets` (+ status, marca, modelo,
  aparelho e `activity_log`). `MockConsultaOsRepository` removido; `ConsultaOsMockService` virou
  `ConsultaOsService`.
- **Busca:** `GET /consulta-os/buscar?tipo=&numero=&serie=` — `tipo` em `job_sheet_no | invoice_no |
  mobile_num` (celular só com `repair.enable_repair_check_using_mobile_num`), `numero` obrigatório,
  `serie` opcional. Resposta `{found, ordens[]}`, até 20 OS.
- **`/repair-status`:** `CustomerRepairStatusController::index` redireciona (302) pro
  `/consulta-os`. Sem o ConsultaOs registrado, cai na tela Blade de sempre. O `POST
  /post-repair-status` endurecido no #8527 não foi tocado.
- **Tela:** `Pages/ConsultaOs/Index.tsx` + `OsLookupForm` + `OsResultCard` reescritos pro dado do
  Repair; `OsPipeline`/`OsStageBadge` (estágios de gráfica do mock) removidos.
- **Trio:** charter e `Index.casos.md` atualizados — UC-COS-01..07 reescritos, UC-COS-08..11 novos.
  Teste: `tests/Feature/ConsultaOs/ConsultaOsIndexContratoTest.php`, lane sqlite do `ci.yml`,
  tenants fictícios 98/99.

## O que a página expõe — antes × depois

| | antes (`/consulta-os` mock) | antes (`/repair-status` Blade) | depois (`/consulta-os` real) |
|---|---|---|---|
| fonte | 4 OS fixas | `repair_job_sheets` | `repair_job_sheets` |
| campos por OS | id, cliente, contato, vendedor, designer, datas, estágio, itens | nº, marca, aparelho, modelo, série, status+cor, data prevista, atividades (data, ação, quem, nota, conclusão de/para) | **os mesmos do `/repair-status`**, campo a campo |
| critério | número alfanumérico | tipo (3) + número; série opcional | tipo (3, celular por config) + número; série opcional |
| teto | 1 OS | sem teto | 20 OS |

Fica fora do payload (com teste): custo estimado, senha do aparelho, defeitos, `properties` cruas do
log automático, cliente (nome, documento), `business_id`.

## Prova do índice — por que precisa de ajuste do Cowork
A prova da thread 04 é `CustomerRepairStatusController.php` **contém** `Inertia::render(`. Escolhi
**redirecionar**, não renderizar a Page do ConsultaOs dentro do controller do Repair:
1. **Uma URL só.** Renderizar no `/repair-status` faria a mesma tela existir em duas URLs, e a busca
   dela chama `/consulta-os/buscar` de qualquer jeito — o `/repair-status` viraria fachada.
2. **Sem rota nova** (o pedido proibia): o redirect usa a rota `consulta-os.index` que já existe.
3. **Links antigos e QR impressos seguem valendo.** 302, não 301, pra o navegador não guardar o
   desvio se o ConsultaOs for desligado.

Logo a prova do json não se aplica. **Pedido ao Cowork** (não editei o `00-INDICE.md`): trocar por
`{"tipo": "contem", "path": "${MOD}/Http/Controllers/CustomerRepairStatusController.php", "padrao": "redirect()->route('consulta-os.index')"}`
e, de quebra, o prefixo (ver `_DECISOES-W-2026-10-02.md`).

## Pendente — decisão do dono
1. **[T0] A busca cruza empresas.** O ConsultaOs **não tem** como saber a empresa do cliente (nem
   subdomínio, nem slug, nem parâmetro — conferido nas rotas e no controller). Um nº válido é
   procurado em todas as empresas, como o `/repair-status` já fazia. A numeração de OS é sequencial
   **por empresa**, então o mesmo `JS2026/0001` casa OS de várias empresas — o cliente pode ver o
   status, a marca/modelo e as notas de atividade da OS de outra loja. Não inventei rota nova; o
   UC-COS-09 trava o comportamento atual e o assert dele inverte quando [W] escolher o jeito de
   identificar a empresa (subdomínio, slug na URL, ou exigir nº + celular juntos).
2. **Captcha** — resto da US-CONSULTA-001.
3. **"Quem" na atividade** é o nome do funcionário (paridade com o `/repair-status`). É PII de
   funcionário; manter ou tirar é decisão [W].

## O que foi removido e por quê
10 arquivos de teste em `Modules/ConsultaOs/Tests/Feature/` (Wave18/23/25/26/27/28,
CustomerJourney, SmokeRoutes, PublicTokenSecurity, LgpdCompliance). Todos verificavam o mock (OS
`4821`, `MockConsultaOsRepository`, nomes de arquivo) e **nenhum roda em lane de CI** (só pelo
`<testsuite>` no CT 100). Ficou o `ScaffoldTest`. O contrato que roda é o de `tests/Feature/ConsultaOs/`.

## Não feito nesta thread
- Thread 05 (abas do `JobSheet/Index`, D-RECORTE) — outro PR.
- Nada subiu ao Cowork.
