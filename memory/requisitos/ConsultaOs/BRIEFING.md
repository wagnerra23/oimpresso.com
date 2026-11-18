---
id: requisitos-consulta-os-briefing
module: ConsultaOs
status: parcial
updated_at: "2026-11-18"
---

# BRIEFING — ConsultaOs

Portal público (sem login) onde o cliente final acompanha o reparo informando o nº da OS, o nº da venda ou o celular. **Desde 2026-10-02 lê as folhas de OS reais do `Modules/Repair`** (US-CONSULTA-001, decisão [W] "Ligar o ConsultaOs ao Repair") e é o portal do cliente do Repair: o antigo `/repair-status` redireciona pra cá. O mock de 4 OS fixas saiu.

**Estado:** parcial — fonte real ligada; empresa na URL e celular só com série desde 2026-11-17 (decisão [W]); desde 2026-11-18 o link da empresa nasce sozinho e chega ao cliente pela configuração, pelo aviso de WhatsApp e pela impressão da OS; falta captcha.

## Capacidades REAIS (no código)
- `GET /consulta-os/{codigo}` → `Inertia::render('ConsultaOs/Index', ['buscaPorCelular', 'empresa'])` (React client-state + `fetch` em `/consulta-os/{codigo}/buscar`). O código é da empresa (`consulta_os_empresas`) e nasce na primeira vez que o sistema pede o link (`EmpresaPublica::linkDe`/`linkSeDisponivel`, idempotente — UC-COS-63/64). `GET /consulta-os` sem código não busca, só avisa que falta o link da empresa.
- **Onde o link aparece (decisão [W] 2026-11-18):** na configuração do Repair (`/repair/repair-settings`, Blade e Inertia, com botão Copiar — UC-RSET-09); no aviso de WhatsApp de status da OS (UC-COS-65); na folha impressa da OS, com QR (`DNS2D`, UC-COS-66). Sempre o da empresa da OS/sessão. `php artisan consultaos:link {business_id}` continua para o suporte. Os atalhos "Acompanhar pedido"/"Status do reparo" do site saíram (levavam a `/consulta-os` sem empresa).
- `GET /consulta-os/{codigo}/buscar?tipo=&numero=&serie=` → JSON `{found, ordens[]}` ou `404 {found:false}`. Tipos: `job_sheet_no`, `invoice_no`, `mobile_num` (este só com `repair.enable_repair_check_using_mobile_num`). Até 20 OS.
- Payload por OS = whitelist (paridade com o `/repair-status`): nº, marca, aparelho, modelo, série, status com cor, previsão de entrega e atividades (data, ação, quem, nota). Nada de custo, senha, defeitos, notas internas, cliente ou `business_id`.
- Critério validado (`ConsultaPublicaRequest`) e revalidado no `RepairConsultaOsRepository` — sem tipo válido e número não vazio, não consulta.
- Throttle `30,1` nas rotas; auditoria com `PiiRedactor` + IP truncado; spans OTel.
- `GET /repair-status` (Repair) → 302 pro `/consulta-os`. O portal antigo do Repair (tela Blade + `POST /post-repair-status`) saiu no cutover de 2026-10-27: o ConsultaOs é a única consulta pública de OS.
- **Aviso de status por WhatsApp (US-CONSULTA-004):** quando a empresa muda o status da OS na edição (`JobSheetController::update`) para um status marcado com aviso (`repair_statuses.whatsapp_template_aviso` = `ready` ou `waiting_parts`, configurado em `/repair/status`), o `Modules/Whatsapp/Listeners/NotifyRepairCustomer` avisa o cliente e o portal já mostra o status novo. O nome do status não decide (decisão [W] 2026-10-21). O aviso filtra a empresa do cliente e respeita o opt-out de WhatsApp.
- Contrato: `tests/Feature/ConsultaOs/ConsultaOsIndexContratoTest.php` (UC-COS-01..11 e 34..64, lane sqlite do `ci.yml`), `Modules/Repair/Tests/Feature/ClienteAcompanhaStatusContratoTest.php` (UC-COS-12, 13, 31, 32, 33, 65, 66) e `Modules/Repair/Tests/Feature/RepairSettingsContratoTest.php` (UC-RSET-09).

## Capacidades PLANEJADAS (NÃO construídas)
- Captcha (resto da US-CONSULTA-001). **US-CONSULTA-002:** canary 7d.
- Transição de status pela FSM do Repair (`RepairFsmActionController::execute`) avisar o cliente e mudar o status que o portal lê — hoje não faz nenhum dos dois (limite da US-CONSULTA-004, UC-COS-14).

## Dependências
- **Reais:** `Modules\Repair\Entities\JobSheet` + `activity_log`; `Modules\Whatsapp` (aviso de status); `App\Support\Privacy\PiiRedactor`; `App\Util\OtelHelper`. Tier 0: rota pública sem sessão — a empresa vem do código na URL (`EmpresaPublica::empresaDe`) e toda busca filtra por ela.

**SPEC:** [SPEC.md](SPEC.md)
