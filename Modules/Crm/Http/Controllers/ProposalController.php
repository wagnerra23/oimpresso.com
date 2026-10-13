<?php

namespace Modules\Crm\Http\Controllers;

use App\Media;
use App\Utils\ModuleUtil;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Entities\Proposal;
use Modules\Crm\Entities\ProposalTemplate;
use Modules\Crm\Http\Requests\StoreProposalRequest;
use Modules\Crm\Notifications\SendProposalNotification;
use App\Services\FeatureFlagService;
use App\Support\Privacy\PiiRedactor;
use Carbon\Carbon;
use Inertia\Inertia;
use Yajra\DataTables\Facades\DataTables;

class ProposalController extends Controller
{
    /**
     * Flag do caminho React (thread crm/playbook/14, F3). Convenção `useV2<Modulo><Tela>`.
     * Sem a chave no GrowthBook o FeatureFlagService cai no fallbackDefaults, que não a lista:
     * default OFF, a Blade segue servindo. Ligar é toggle no GrowthBook (flag:set), não deploy.
     *
     * @see memory/requisitos/Crm/RUNBOOK-propostas.md
     */
    private const FLAG_V2 = 'useV2CrmPropostas';

    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Contracts\View\View|\Inertia\Response
     */
    public function index(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        $is_admin = $this->moduleUtil->is_admin(auth()->user(), $business_id);

        // O Inertia manda `X-Requested-With` junto do `X-Inertia`: `ajax()` sozinho mandaria a
        // visita da tela nova pro DataTables (§5 2026-09-08).
        if ($request->ajax() && ! $request->header('X-Inertia')) {
            $proposal = $this->propostasQuery((int) $business_id, $is_admin);

            return Datatables::of($proposal)
                ->addColumn(
                    'action',
                    function ($row) {
                        $html = '<a href="#" data-href="'.action([\Modules\Crm\Http\Controllers\ProposalController::class, 'show'], [$row->id]).'" data-container=".view_modal" class="btn-modal btn-info btn btn-sm">
                            <i class="fa fa-eye" aria-hidden="true"></i> '
                            .__('messages.view').
                            '</a>';

                        return $html;
                    }
                )
                ->editColumn('created_at', '{{@format_datetime($created_at)}}')
                ->filterColumn('sent_by_full_name', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(users.surname, ''), ' ', COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->removeColumn('id')
                ->rawColumns(['action', 'created_at', 'sent_by_full_name'])
                ->make(true);
        }

        $proposal_template = ProposalTemplate::with(['media'])
                            ->where('business_id', $business_id)
                            ->first();

        // `?classico=1` mantém a Blade alcançável com a flag ligada (thread 14, F3).
        if (! $request->boolean('classico') && app(FeatureFlagService::class)->isOn(self::FLAG_V2, ['business_id' => $business_id])) {
            return $this->propostasInertia((int) $business_id, $is_admin, ! empty($proposal_template));
        }

        return view('crm::proposal.index')
            ->with(compact('proposal_template'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('crm::create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(StoreProposalRequest $request)
    {
        // Wave 15 D8 Security — authorize() + validate() centralizados em StoreProposalRequest.
        $business_id = request()->session()->get('user.business_id');

        try {
            $input = $request->only(['subject', 'body', 'contact_id', 'cc', 'bcc']);
            $input['business_id'] = $business_id;
            $input['sent_by'] = request()->session()->get('user.id');

            DB::beginTransaction();
            $proposal = Proposal::create($input);

            //if template media available make a copy of it for proposal
            $proposal_template = ProposalTemplate::with(['media'])
                                    ->where('business_id', $business_id)
                                    ->first();

            if ($proposal_template->media->count() > 0) {
                $file_names = [];
                foreach ($proposal_template->media as $media) {
                    $doc_name = time().'_'.$media->display_name;
                    $file_names[] = $doc_name;
                    file_put_contents(
                            public_path('uploads/media/').$doc_name, file_get_contents($media->display_url)
                        );
                }

                Media::attachMediaToModel($proposal, $business_id, $file_names);
            }

            DB::commit();

            if (! empty($proposal)) {
                $contact = CrmContact::where('business_id', $business_id)
                            ->find($proposal->contact_id);

                $proposal_with_media = Proposal::with(['media'])
                                        ->where('business_id', $business_id)
                                        ->find($proposal->id);

                $contact->notify(new SendProposalNotification($proposal_with_media));
            }

            $output = ['success' => 1,
                'msg' => __('lang_v1.success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            // D7 LGPD: redaciona PII em mensagens de erro antes de logar (proposal toca contato + valores).
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.app(PiiRedactor::class)->redact($e->getMessage()));

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()
            ->action([\Modules\Crm\Http\Controllers\ProposalController::class, 'index'])
            ->with('status', $output);
    }

    /**
     * Show the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'crm_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $proposal = Proposal::with(['media'])
                        ->join('contacts', 'crm_proposals.contact_id', '=', 'contacts.id')
                        ->join('users', 'crm_proposals.sent_by', '=', 'users.id')
                        ->where('crm_proposals.business_id', $business_id)
                        ->where('crm_proposals.id', $id)
                        ->select('contacts.name as contact', 'crm_proposals.subject as subject', 'crm_proposals.created_at as created_at', DB::raw("CONCAT(COALESCE(users.surname, ''), ' ', COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as sent_by_full_name"), 'crm_proposals.body as body', 'crm_proposals.id', 'crm_proposals.cc', 'crm_proposals.bcc')
                        ->first();

            return view('crm::proposal.show')
                ->with(compact('proposal'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        return view('crm::edit');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }

    /**
     * Propostas que o usuário pode ver: `business_id` da sessão + "só as que enviei" para quem
     * não é `Admin#` — a MESMA consulta da DataTable da Blade e da tela React (thread 14, F3).
     */
    private function propostasQuery(int $business_id, bool $is_admin)
    {
        $proposal = Proposal::join('contacts', 'crm_proposals.contact_id', '=', 'contacts.id')
                        ->join('users', 'crm_proposals.sent_by', '=', 'users.id')
                        ->where('crm_proposals.business_id', $business_id)
                        ->select('contacts.name', 'crm_proposals.subject', 'crm_proposals.created_at',
                            'crm_proposals.id', DB::raw("CONCAT(COALESCE(users.surname, ''), ' ', COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) as sent_by_full_name"));

        if (! $is_admin) {
            $proposal->where('crm_proposals.sent_by', auth()->user()->id);
        }

        return $proposal;
    }

    /**
     * Lista de propostas em Inertia. A lista e o drawer (`?proposta=ID`) saem da MESMA consulta,
     * então o detalhe herda o escopo da lista — diferente do `show()` da Blade, que não filtra
     * por quem enviou (RUNBOOK-propostas §10).
     *
     * @return \Inertia\Response
     */
    private function propostasInertia(int $business_id, bool $is_admin, bool $tem_modelo)
    {
        $busca = trim((string) request()->input('q', ''));
        $base = $this->propostasQuery($business_id, $is_admin)->withCount('media');
        if ($busca !== '') {
            $base->where(function ($w) use ($busca) {
                $w->where('contacts.name', 'like', "%{$busca}%")
                    ->orWhere('crm_proposals.subject', 'like', "%{$busca}%")
                    ->orWhereRaw("CONCAT(COALESCE(users.surname, ''), ' ', COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) like ?", ["%{$busca}%"]);
            });
        }

        $data = fn ($d) => empty($d) ? null : Carbon::parse($d)->format('d/m/Y H:i');
        $linha = fn ($p) => [
            'id' => $p->id,
            'contato' => $p->name,
            'assunto' => $p->subject,
            'enviado_por' => trim((string) $p->sent_by_full_name),
            'data' => $data($p->created_at),
            'anexos' => (int) $p->media_count,
        ];

        $propostaId = (int) request()->input('proposta');

        return Inertia::render('Crm/Propostas/Index', [
            'filtros' => request()->only(['q']),
            'pode_enviar' => $tem_modelo && auth()->user()->can('crm.access_proposal'),
            'propostas' => Inertia::defer(fn () => (clone $base)
                ->orderByDesc('crm_proposals.created_at')
                ->paginate(25)
                ->withQueryString()
                ->through($linha)),
            // Drawer: a consulta da lista filtrada pelo id. Proposta de outro negócio, ou de
            // colega para quem não é Admin#, dá `null` — o drawer não abre.
            'proposta' => fn () => $propostaId > 0
                ? $this->detalheProposta($this->propostasQuery($business_id, $is_admin)
                    ->addSelect('crm_proposals.body', 'crm_proposals.cc', 'crm_proposals.bcc')
                    ->with('media')
                    ->where('crm_proposals.id', $propostaId)
                    ->first(), $linha)
                : null,
        ]);
    }

    /**
     * Detalhe do drawer. O corpo vem do editor do modelo como HTML; a tela recebe só o texto
     * (fim de bloco vira quebra de linha) — o drawer não injeta HTML cru, ao contrário do
     * `{!! !!}` da Blade.
     */
    private function detalheProposta($p, callable $linha): ?array
    {
        if (! $p) {
            return null;
        }
        $p->media_count = $p->media->count();
        $html = preg_replace('#<\s*(br|/p|/div|/li|/h[1-6])[^>]*>#i', PHP_EOL, (string) $p->body);
        $texto = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $linha($p) + [
            'cc' => $p->cc,
            'bcc' => $p->bcc,
            'corpo' => trim($texto),
            'arquivos' => $p->media->map(fn ($m) => ['nome' => $m->display_name, 'url' => $m->display_url])->values()->all(),
        ];
    }
}
