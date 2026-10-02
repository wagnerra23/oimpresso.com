---
id: requisitos-consulta-os-briefing
module: ConsultaOs
status: parcial
updated_at: "2026-10-02"
---

# BRIEFING — ConsultaOs

Portal público (sem login) onde o cliente final acompanha o reparo informando o nº da OS, o nº da venda ou o celular. **Desde 2026-10-02 lê as folhas de OS reais do `Modules/Repair`** (US-CONSULTA-001, decisão [W] "Ligar o ConsultaOs ao Repair") e é o portal do cliente do Repair: o antigo `/repair-status` redireciona pra cá. O mock de 4 OS fixas saiu.

**Estado:** parcial — fonte real ligada; faltam captcha e identificar a empresa do cliente (decisão [W]).

## Capacidades REAIS (no código)
- `GET /consulta-os` → `Inertia::render('ConsultaOs/Index', ['buscaPorCelular'])` (React client-state + `fetch`).
- `GET /consulta-os/buscar?tipo=&numero=&serie=` → JSON `{found, ordens[]}` ou `404 {found:false}`. Tipos: `job_sheet_no`, `invoice_no`, `mobile_num` (este só com `repair.enable_repair_check_using_mobile_num`). Até 20 OS.
- Payload por OS = whitelist (paridade com o `/repair-status`): nº, marca, aparelho, modelo, série, status com cor, previsão de entrega e atividades (data, ação, quem, nota). Nada de custo, senha, defeitos, notas internas, cliente ou `business_id`.
- Critério validado (`ConsultaPublicaRequest`) e revalidado no `RepairConsultaOsRepository` — sem tipo válido e número não vazio, não consulta.
- Throttle `30,1` nas rotas; auditoria com `PiiRedactor` + IP truncado; spans OTel.
- `GET /repair-status` (Repair) → 302 pro `/consulta-os`; o `POST /post-repair-status` endurecido (#8527) segue no lugar.
- Contrato: `tests/Feature/ConsultaOs/ConsultaOsIndexContratoTest.php` (UC-COS-01..11, lane sqlite do `ci.yml`).

## Capacidades PLANEJADAS (NÃO construídas)
- Identificar a empresa do cliente (hoje um nº válido é procurado em todas as empresas) — decisão [W].
- Captcha (resto da US-CONSULTA-001). **US-CONSULTA-002:** canary 7d.

## Dependências
- **Reais:** `Modules\Repair\Entities\JobSheet` + `activity_log`; `App\Support\Privacy\PiiRedactor`; `App\Util\OtelHelper`. Tier 0: rota pública **não** scopa por `business_id` (sem sessão) — escape comentado no código.

**SPEC:** [SPEC.md](SPEC.md)
