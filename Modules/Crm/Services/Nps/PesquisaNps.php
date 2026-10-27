<?php

declare(strict_types=1);

namespace Modules\Crm\Services\Nps;

use App\Contracts\Atendimento\AvisaClienteWhatsapp;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * NPS pós-venda e pós-OS (US-CRM-097).
 *
 * Multi-tenant Tier 0 (ADR 0093): roda em fila/CLI sem sessão — `business_id` explícito em
 * toda query; a resposta pública acha a empresa pela linha do token, nunca pelo visitante.
 *
 * Envio pelo contrato do núcleo `AvisaClienteWhatsapp` (o Modules/Whatsapp implementa sobre o
 * NotificadorClienteWhatsapp, que já confere consentimento LGPD e celular). Sem a ligação, a
 * pesquisa fica `nao_enviado / sem_canal` — nada é mandado por outro caminho.
 */
class PesquisaNps
{
    /** Uma pesquisa por cliente a cada N dias (evita cansar quem compra toda semana). */
    public const INTERVALO_DIAS = 90;

    /** Só o que foi entregue nos últimos N dias entra (ligar não dispara o histórico). */
    public const JANELA_DIAS = 7;

    /** Teto por execução e por empresa. */
    public const LOTE = 20;

    /** @return array{enviadas: int, nao_enviadas: int} */
    public function enviarPendentes(int $businessId): array
    {
        $r = ['enviadas' => 0, 'nao_enviadas' => 0];
        if ($businessId <= 0 || ! Schema::hasTable('crm_nps_pesquisas')) {
            return $r;
        }
        $empresa = (string) DB::table('business')->where('id', $businessId)->value('name');

        $jaPesquisados = [];
        foreach ($this->candidatos($businessId) as $c) {
            $contato = (int) $c->contact_id;
            if (isset($jaPesquisados[$contato]) || $this->pesquisadoRecentemente($businessId, $contato)) {
                continue;
            }
            $jaPesquisados[$contato] = true;

            $token = Str::lower(Str::random(40));
            $inseridos = DB::table('crm_nps_pesquisas')->insertOrIgnore([
                'business_id' => $businessId, 'contact_id' => $contato,
                'origem_tipo' => $c->tipo, 'origem_id' => (int) $c->id, 'token' => $token,
                'status' => 'nao_enviado', 'motivo' => 'pendente',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($inseridos === 0) {
                continue; // outra execução já pegou
            }

            [$status, $motivo] = $this->enviar($businessId, $contato, $empresa, $token);
            DB::table('crm_nps_pesquisas')->where('business_id', $businessId)->where('token', $token)->update([
                'status' => $status, 'motivo' => $motivo,
                'enviada_em' => $status === 'enviado' ? now() : null, 'updated_at' => now(),
            ]);
            $r[$status === 'enviado' ? 'enviadas' : 'nao_enviadas']++;
        }

        return $r;
    }

    /** Vendas e OS entregues na janela, ainda sem pesquisa, com cliente. */
    public function candidatos(int $businessId): Collection
    {
        $desde = now()->subDays(self::JANELA_DIAS);
        $semPesquisa = fn (string $tipo, string $col) => fn ($q) => $q->from('crm_nps_pesquisas as p')
            ->where('p.business_id', $businessId)->where('p.origem_tipo', $tipo)->whereColumn('p.origem_id', $col);

        $vendas = DB::table('transactions as t')
            ->join('sale_process_stages as s', 's.id', '=', 't.current_stage_id')
            ->where('t.business_id', $businessId)->where('t.type', 'sell')
            ->whereIn('s.key', ['delivered', 'completed'])
            ->whereNotNull('t.contact_id')->where('t.updated_at', '>=', $desde)
            ->whereNotExists($semPesquisa('venda', 't.id'))
            ->selectRaw("t.id, t.contact_id, 'venda' as tipo, t.updated_at as quando")
            ->orderBy('t.id')->limit(self::LOTE)->get();

        $os = collect();
        if (Schema::hasTable('repair_job_sheets') && Schema::hasColumn('repair_job_sheets', 'current_stage_id')) {
            $os = DB::table('repair_job_sheets as j')
                ->join('sale_process_stages as s', 's.id', '=', 'j.current_stage_id')
                ->where('j.business_id', $businessId)->where('s.key', 'entregue_completo')
                ->where('j.updated_at', '>=', $desde)
                ->whereNotExists($semPesquisa('os', 'j.id'))
                ->selectRaw("j.id, j.contact_id, 'os' as tipo, j.updated_at as quando")
                ->orderBy('j.id')->limit(self::LOTE)->get();
        }

        return $vendas->concat($os)->sortBy('quando')->take(self::LOTE)->values();
    }

    private function pesquisadoRecentemente(int $businessId, int $contactId): bool
    {
        return DB::table('crm_nps_pesquisas')->where('business_id', $businessId)->where('contact_id', $contactId)
            ->where('created_at', '>=', now()->subDays(self::INTERVALO_DIAS))->exists();
    }

    /** @return array{0: string, 1: ?string} */
    private function enviar(int $businessId, int $contactId, string $empresa, string $token): array
    {
        if (! app()->bound(AvisaClienteWhatsapp::class)) {
            return ['nao_enviado', 'sem_canal'];
        }
        $texto = 'De 0 a 10, quanto você recomendaria '.($empresa !== '' ? $empresa : 'a nossa empresa')
            .' a um amigo ou colega? Responda em um toque: '.url('/nps/'.$token);
        try {
            $r = app(AvisaClienteWhatsapp::class)->avisar($businessId, $contactId, $texto);
        } catch (\Throwable $e) {
            Log::warning('[crm.nps] falha no envio', ['business_id' => $businessId, 'error' => class_basename($e)]);

            return ['nao_enviado', 'falha_envio'];
        }

        return $r['enviado'] ? ['enviado', null] : ['nao_enviado', mb_substr((string) ($r['motivo'] ?? 'nao_enviado'), 0, 40)];
    }

    /**
     * Registra a resposta pública. Só a primeira vale (a nota não é reescrita).
     *
     * @return 'ok'|'ja_respondida'|'nao_encontrada'
     */
    public function responder(string $token, int $nota, ?string $comentario): string
    {
        if (! preg_match('/^[a-z0-9]{40}$/', $token) || $nota < 0 || $nota > 10) {
            return 'nao_encontrada';
        }
        $linha = DB::table('crm_nps_pesquisas')->where('token', $token)->first(['id', 'business_id', 'nota']);
        if ($linha === null) {
            return 'nao_encontrada';
        }
        $comentario = trim((string) $comentario);
        $gravou = DB::table('crm_nps_pesquisas')
            ->where('business_id', $linha->business_id)->where('id', $linha->id)->whereNull('nota')
            ->update([
                'nota' => $nota, 'comentario' => $comentario !== '' ? mb_substr($comentario, 0, 1000) : null,
                'status' => 'respondido', 'respondida_em' => now(), 'updated_at' => now(),
            ]);

        return $gravou === 1 ? 'ok' : 'ja_respondida';
    }

    /**
     * NPS = % promotores (9-10) − % detratores (0-6), sobre as respostas do período.
     *
     * @return array{respostas: int, promotores: int, neutros: int, detratores: int, nps: ?int, enviadas: int}
     */
    public function resumo(int $businessId, \DateTimeInterface $desde): array
    {
        $base = DB::table('crm_nps_pesquisas')->where('business_id', $businessId);
        $resp = (clone $base)->whereNotNull('nota')->where('respondida_em', '>=', $desde)
            ->selectRaw('COUNT(*) as n, SUM(nota >= 9) as p, SUM(nota BETWEEN 7 AND 8) as m, SUM(nota <= 6) as d')->first();
        $n = (int) ($resp->n ?? 0);

        return [
            'respostas' => $n,
            'promotores' => (int) ($resp->p ?? 0),
            'neutros' => (int) ($resp->m ?? 0),
            'detratores' => (int) ($resp->d ?? 0),
            'nps' => $n > 0 ? (int) round(((int) $resp->p - (int) $resp->d) * 100 / $n) : null,
            'enviadas' => (clone $base)->whereNotNull('enviada_em')->where('enviada_em', '>=', $desde)->count(),
        ];
    }
}
