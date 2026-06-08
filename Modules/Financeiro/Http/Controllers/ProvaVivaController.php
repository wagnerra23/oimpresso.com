<?php

namespace Modules\Financeiro\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tela /financeiro/prova-viva — Prova viva dos primitivos de layout (ADR 0253).
 *
 * Fecha o "critério de pronto" da ADR 0253: a tela Financeiro composta 100%
 * pelos primitivos de layout (Box/Stack/Inline/Grid/Container/Text). Tradução
 * fiel do gabarito Cowork "Financeiro - Prova Viva (primitivos).html" (chat46
 * 2026-06-07) aprovado no loop de design.
 *
 * Read-only e SEM dado de tenant: a tela é prova de LAYOUT — os lançamentos são
 * mock no próprio .tsx (não consulta DB). Por isso não há query business_id aqui;
 * o isolamento multi-tenant Tier 0 (ADR 0093) é trivialmente preservado por
 * construção (nada de negócio é lido/escrito). NÃO substitui a Visão Unificada
 * (Financeiro/Unificado/Index), que continua sendo a landing de produção.
 *
 * Gate de permissão `financeiro.dashboard.view` (mesmo da Fluxo/DRE) só pra
 * manter a tela atrás do mesmo guard de leitura do módulo.
 */
class ProvaVivaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:financeiro.dashboard.view');
    }

    public function index(): Response
    {
        return Inertia::render('Financeiro/ProvaViva');
    }
}
