<?php

declare(strict_types=1);

/**
 * ESQUELETO — isolamento multi-tenant Tier 0 do módulo Repair (ADR 0093, Garantia 4).
 * Nasceu da auditoria RBAC de 2026-11-14. Nenhum caso roda ainda: todos são ->todo().
 *
 * Models com business_id a cobrir (3):
 *   DeviceModel, JobSheet, RepairStatus
 *
 * Para ativar um caso: troque ->todo() por it(..., function () {...}) usando as empresas semeadas
 * 98 e 99 (`$this->seededTenant()` / `$this->seededSupportClientTenant()`) e mantenha SEMPRE o par
 * controle negativo (99 não vê / recebe 404) + controle positivo (98 vê / recebe 200). Teste só com
 * asserts negativos passa verde mesmo se a sessão der 403 em tudo.
 * Modelo: Modules/KB/Tests/Feature/CrossTenantIsolationTest.php.
 */

it('Repair · listagem da empresa 98 não traz registro da 99 (com controle positivo)')->todo();

it('Repair · leitura por id de registro da 99 com sessão da 98 dá 404 (com controle positivo)')->todo();

it('Repair · escrita com id da 99 vindo do request não altera nada na 99 (com controle positivo)')->todo();
