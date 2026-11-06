<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Fisio\Entities\AppointmentType;
use Modules\Fisio\Entities\BookingSettings;
use Modules\Fisio\Services\Agenda\AgendaDaSemana;

/**
 * Configurações › Agendamento pelo app (US-FISIO-015, etapa 04 A9 e A21). Fonte visual:
 * prototipo-ui/cowork/Wagner/prototipos/fisio/ConfigAgendamentoOnline.dc.html.
 *
 * O que o paciente pode marcar, remarcar e cancelar sozinho: ligar/desligar, quem pode, tipos
 * liberados, horários abertos e as regras (antecedência, prazo, dias à frente, faltas). Ler exige
 * `fisio.access`; salvar exige `fisio.editar`. Uma linha por empresa (BookingSettings). Sinal por
 * Pix não é editável aqui: depende do Financeiro (outro lote) e fica 0.
 */
class ConfigAgendamentoController extends Controller
{
    private const DIAS = ['1', '2', '3', '4', '5', '6'];

    public function index(Request $request, AgendaDaSemana $leitura): Response
    {
        $biz = (int) $request->session()->get('user.business_id');
        $r = BookingSettings::daEmpresa($biz);

        return Inertia::render('Fisio/Configuracoes/Agendamento', [
            'pode_editar' => (bool) $request->user()?->can('fisio.editar'),
            'regras' => [
                'online_enabled' => (bool) $r->online_enabled, 'who_can_book' => $r->who_can_book,
                'open_slots' => (object) ($r->open_slots ?? []), 'min_notice_hours' => (int) $r->min_notice_hours,
                'cancel_min_hours' => (int) $r->cancel_min_hours, 'max_days_ahead' => (int) $r->max_days_ahead,
                'no_show_block_after' => (int) $r->no_show_block_after, 'waitlist_enabled' => (bool) $r->waitlist_enabled,
                'checkin_enabled' => (bool) $r->checkin_enabled, 'policy_version' => (int) $r->policy_version,
            ],
            // Eager de propósito: o formulário nasce com os tipos liberados marcados; deferido, ele
            // nasceria vazio e salvar desligaria todos. São no máximo uma dúzia de linhas.
            'tipos' => $this->tipos($leitura, $biz),
        ]);
    }

    /** @return list<array{id:int, nome:string, duracao:int, modalidade:string, online:bool}> */
    private function tipos(AgendaDaSemana $leitura, int $biz): array
    {
        $tipos = $leitura->tipos($biz);
        $online = AppointmentType::query()->whereIn('id', array_column($tipos, 'id'))->pluck('online_bookable', 'id');

        return array_map(fn (array $t) => $t + ['online' => (bool) ($online[$t['id']] ?? false)], $tipos);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('fisio.editar'), 403);
        $biz = (int) $request->session()->get('user.business_id');
        $d = $request->validate([
            'online_enabled' => ['required', 'boolean'],
            'who_can_book' => ['required', Rule::in(['pacote', 'todos', 'ninguem'])],
            'open_slots' => ['array'], 'open_slots.*' => ['array'], 'open_slots.*.*' => ['date_format:H:i'],
            'min_notice_hours' => ['required', 'integer', 'between:0,72'],
            'cancel_min_hours' => ['required', 'integer', 'between:0,168'],
            'max_days_ahead' => ['required', 'integer', 'between:1,180'],
            'no_show_block_after' => ['required', 'integer', 'between:0,10'],
            'waitlist_enabled' => ['required', 'boolean'], 'checkin_enabled' => ['required', 'boolean'],
            'tipos_online' => ['array'], 'tipos_online.*' => ['integer'],
        ], ['open_slots.*.*.date_format' => 'Horário inválido: use HH:MM.']);

        $slots = [];
        foreach (self::DIAS as $dia) {
            $horas = array_values(array_unique($d['open_slots'][$dia] ?? []));
            sort($horas);
            if ($horas !== []) {
                $slots[$dia] = $horas;
            }
        }

        DB::transaction(function () use ($biz, $d, $slots) {
            $r = BookingSettings::daEmpresa($biz);
            $prazoMudou = (int) $r->cancel_min_hours !== (int) $d['cancel_min_hours'] || (int) $r->no_show_block_after !== (int) $d['no_show_block_after'];
            $r->forceFill([
                'online_enabled' => $d['online_enabled'], 'who_can_book' => $d['who_can_book'], 'open_slots' => $slots,
                'min_notice_hours' => $d['min_notice_hours'], 'cancel_min_hours' => $d['cancel_min_hours'],
                'max_days_ahead' => $d['max_days_ahead'], 'no_show_block_after' => $d['no_show_block_after'],
                'waitlist_enabled' => $d['waitlist_enabled'], 'checkin_enabled' => $d['checkin_enabled'],
                // A política exibida ao paciente muda de versão quando o prazo ou as faltas mudam (A8).
                'policy_version' => $prazoMudou ? (int) $r->policy_version + 1 : (int) $r->policy_version,
            ])->save();

            $liberados = array_map('intval', $d['tipos_online'] ?? []);
            // Filtro explícito além do escopo global: update em massa não depende de uma camada só (auditoria d8).
            AppointmentType::query()->where('business_id', $biz)->update(['online_bookable' => false]);
            AppointmentType::query()->where('business_id', $biz)->whereIn('id', $liberados)->update(['online_bookable' => true]);
        });

        return back()->with('success', 'Agendamento pelo app salvo.');
    }
}
