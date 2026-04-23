<?php

/**
 * TopNav declarativo do Essentials (ADR arq/0011).
 *
 * Espelho React do nav horizontal. Lido por LegacyMenuAdapter::buildTopNavs()
 * e exposto em `shell.topnavs.Essentials` via Inertia.
 *
 * Fonte INDEPENDENTE da sidebar (DataController::modifyAdminMenu).
 *
 * Escopo: prefixo /essentials/* (Todo/Messages/Document/Knowledge/Reminder).
 * O grupo /hrm/* (Holiday/Leave/Payroll/Attendance) é um módulo visual
 * separado e teria seu próprio topnav se/quando fizer sentido.
 *
 * Convenções:
 * - Ordem do array = ordem na barra horizontal (esquerda → direita)
 * - `can` opcional: filtra item via Spatie; sem can → visível pra todo user autenticado
 * - `icon` usa nome Lucide (resources/js/Components/Icon.tsx)
 * - `href` é path relativo (/essentials/...); backend detecta se é Inertia
 */

return [
    'label' => 'Essentials',
    'icon'  => 'Boxes',
    'items' => [
        ['label' => 'Tarefas',    'href' => '/essentials/todo',           'icon' => 'CheckCircle2',  'can' => 'essentials.add_todos'],
        ['label' => 'Mensagens',  'href' => '/essentials/messages',       'icon' => 'MessageSquare', 'can' => 'essentials.view_message'],
        ['label' => 'Documentos', 'href' => '/essentials/document',       'icon' => 'FileText'],
        ['label' => 'Knowledge',  'href' => '/essentials/knowledge-base', 'icon' => 'BookOpen'],
        ['label' => 'Lembretes',  'href' => '/essentials/reminder',       'icon' => 'Bell'],
    ],
];
