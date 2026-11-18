# Modules/ConsultaOs

Portal público (sem login) onde o cliente final consulta a Ordem de Serviço (OS) dele. Lê as folhas de OS do `Modules/Repair`; o status mostrado é o que cada empresa cadastrou no Repair (é livre por empresa, não há pipeline fixo).

## Status

**Fonte real desde 2026-10-02** (US-CONSULTA-001): as folhas de OS do `Modules/Repair`. O antigo `/repair-status` redireciona (302) pra cá. Contrato vivo: `resources/js/Pages/ConsultaOs/Index.casos.md`.

Pendências que são decisão [W] (não estão feitas): identificar a empresa do cliente na busca (hoje um nº válido é procurado em todas as empresas, travado pelo UC-COS-09) e captcha.

## Arquitetura

```
Routes/web.php  GET /consulta-os  e  GET /consulta-os/buscar  (throttle:30,1, sem auth)
  → ConsultaOsController
      index()  → Inertia ConsultaOs/Index (prop buscaPorCelular)
      buscar() → ConsultaPublicaRequest (tipo em lista fechada + formato do número)
               → auditoria redigida no log (PiiRedactor, IP /24, UA 80c)
    → ConsultaOsService (found/not_found + span OTel consultaos.busca_publica)
      → ConsultaOsRepositoryInterface
        → RepairConsultaOsRepository (repair_job_sheets, payload campo a campo, até LIMITE OS)
```

## Como o cliente usa

1. A empresa passa ao cliente o nº da OS (ou ele usa o nº da venda / celular) e o link `/consulta-os`.
2. O cliente escolhe o tipo de busca e digita o número. Tipos: `job_sheet_no`, `invoice_no` e `mobile_num` (celular só quando `repair.enable_repair_check_using_mobile_num` está ligada). Formato: letras, dígitos e `/ . -`, até 20 caracteres; nº de série opcional.
3. Resposta:
   - `200 {found: true, ordens: [...]}` — cada ordem traz nº, marca, aparelho, modelo, nº de série, status (nome + cor), previsão de entrega e atividades (data, ação, quem, nota, mudança de conclusão).
   - `404 {found: false}` — não achou; não diz se o número existe em alguma empresa.
   - `422` — tipo ou formato inválido.
4. Quando a empresa muda o status da OS para um status marcado com aviso (`repair_statuses.whatsapp_template_aviso`), o cliente recebe WhatsApp (listener `Modules/Whatsapp/Listeners/NotifyRepairCustomer`, US-CONSULTA-004). A transição pela FSM do Repair não dispara esse aviso (UC-COS-14).

Nunca sai no payload: nome, CPF/CNPJ, endereço ou e-mail do cliente, `business_id`, custo estimado, senha/padrão do aparelho, defeitos, checklist e notas internas da OS.

## Conformidade

- **Segurança:** `throttle:30,1` por IP nas duas rotas públicas (e no `/repair-status`); tipo de busca em lista fechada e formato do número restrito; a consulta não roda sem tipo válido e número não vazio (revalidado no repositório).
- **LGPD:** cada busca gera uma linha de log com o número redigido (`PiiRedactor`), IP truncado (/24 IPv4, /48 IPv6), User-Agent cortado em 80 caracteres, tipo e resultado. Nenhum dado da OS encontrada vai pro log.
- **OTel:** `consultaos.busca_publica` (Service) e `consultaos.repository.lookup` (Repository), sem `business_id`.

## Testes

Rodam no CI ou no CT 100, nunca local:

- `tests/Feature/ConsultaOs/ConsultaOsIndexContratoTest.php` — contrato da tela e da busca.
- `Modules/Repair/Tests/Feature/ClienteAcompanhaStatusContratoTest.php` — aviso de status (US-CONSULTA-004).
- `Modules/ConsultaOs/Tests/Feature/` — scaffold e isolamento.

Health sob demanda: `php artisan consultaos:health --detail`.

## Refs

- `memory/requisitos/ConsultaOs/SPEC.md`
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0
- `Modules/Repair/Http/Controllers/CustomerRepairStatusController.php` — o redirect do `/repair-status`

---

Histórico: até 2026-10-02 este README descrevia o desenho com mock de 4 OS (pipeline orçado → entregue, filtro por estágio, payload `{client, stage, items[]}`). Esse desenho saiu junto com o mock; o registro está no [CHANGELOG](CHANGELOG.md).
