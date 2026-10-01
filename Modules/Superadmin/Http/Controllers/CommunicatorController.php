<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Business;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Superadmin\Entities\SuperadminCommunicatorLog;
use Modules\Superadmin\Notifications\SuperadminCommunicator;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Routing\Controller;

class CommunicatorController extends Controller
{
    /**
     * Comunicador — era `superadmin::communicator.index` (Blade + DataTables) e passa a Inertia
     * (thread Superadmin/05). RUNBOOK: memory/requisitos/Superadmin/RUNBOOK-comunicador.md.
     *
     * Superadmin enxerga TODOS os negócios por definição (ADR 0093 §exceções Superadmin):
     * a lista de destinatários é cross-tenant de propósito.
     */
    public function index(): InertiaResponse
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        return Inertia::render('superadmin/Comunicador/Index', [
            'negocios' => Inertia::defer(fn () => Business::orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($b) => ['id' => (int) $b->id, 'nome' => (string) $b->name])
                ->all()),
            // Os 50 envios mais recentes. O corpo vai SEM tag: histórico é leitura, e o legado
            // gravava HTML do TinyMCE — renderizar isso cru seria injetar HTML de log na tela.
            'historico' => Inertia::defer(fn () => SuperadminCommunicatorLog::latest()
                ->limit(50)
                ->get(['id', 'subject', 'message', 'business_ids', 'created_at'])
                ->map(fn ($l) => [
                    'id' => (int) $l->id,
                    'assunto' => (string) $l->subject,
                    'resumo' => mb_substr(trim(strip_tags((string) $l->message)), 0, 240),
                    'destinatarios' => count((array) $l->business_ids),
                    'enviado_em' => optional($l->created_at)->toIso8601String(),
                ])
                ->all()),
        ]);
    }

    /**
     * Sends notification to the required business owners.
     *
     * @param  Request  $request
     * @return Response
     */
    public function send(Request $request)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        //Disable in demo
        if (config('app.env') == 'demo') {
            $output = ['success' => 0,
                'msg' => 'Feature disabled in demo!!',
            ];

            return back()->with('status', $output);
        }

        $request->validate([
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*' => ['integer'],
            'subject' => ['required', 'string', 'max:191'],
            'message' => ['required', 'string'],
        ]);

        // A tela manda TEXTO puro. O e-mail (`emails.plain_html`) imprime o corpo como HTML,
        // então o texto é ESCAPADO e só as quebras de linha viram <br>: o que o superadmin
        // digita chega como texto, nunca como marcação executável no e-mail do cliente.
        $input = $request->only(['recipients', 'subject']);
        $input['message'] = nl2br(e((string) $request->input('message')), false);

        //Get business owners
        $business_owners = User::join('business as B', 'users.id', '=', 'B.owner_id')
                        ->whereIn('B.id', $input['recipients'])
                        ->select('users.*')
                        ->groupBy('users.id')
                        ->get();

        //Send notifications
        \Notification::send($business_owners, new SuperadminCommunicator($input));

        //Create Log
        SuperadminCommunicatorLog::create([
            'business_ids' => $input['recipients'],
            'subject' => $input['subject'],
            'message' => $input['message'],
        ]);

        $output = ['success' => 1,
            'msg' => __('lang_v1.success'),
        ];

        return back()->with('status', $output);
    }

    public function getHistory()
    {
        $history = SuperadminCommunicatorLog::select('subject', 'message', 'created_at');

        return Datatables::of($history)
                         ->editColumn(
                             'created_at',
                             '{{@format_date($created_at)}} {{@format_time($created_at)}}'
                         )
                         ->rawColumns([1])
                         ->make(false);
    }
}
