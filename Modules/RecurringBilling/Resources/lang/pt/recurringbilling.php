<?php

/**
 * Alias do locale `pt` para o `pt-BR` deste modulo.
 *
 * MEDIDO EM PRODUCAO (2026-08-26): `config('app.locale')` = `pt` e o fallback e `en`.
 * Este modulo so tinha `Resources/lang/pt-BR/`, entao NENHUMA chave dele resolvia: caia no
 * fallback `en`, que tambem nao existe aqui, e o Laravel devolvia a CHAVE CRUA.
 *
 *   __('recurringbilling::recurringbilling.module_label')  =>  'recurringbilling::recurringbilling.module_label'   (antes)
 *   __('recurringbilling::recurringbilling.module_label')  =>  'Cobranca Recorrente'   (depois)
 *
 * Sintoma que [W] via: em `/superadmin/packages/{{id}}/edit` tres dos 27 checkboxes
 * apareciam como `recurringbilling::recurringbilling.module_label` em vez do nome do modulo. O mesmo valia para
 * as permissoes deste modulo em `/roles/{{id}}/edit`.
 *
 * `require` em vez de copiar: UMA fonte de verdade. Renomear a pasta `pt-BR` consertaria o
 * locale `pt` e quebraria quem estivesse em `pt-BR`; o alias atende os dois.
 *
 * @see Modules/RecurringBilling/Resources/lang/pt-BR/recurringbilling.php  (a fonte)
 */

return require __DIR__.'/../pt-BR/recurringbilling.php';
