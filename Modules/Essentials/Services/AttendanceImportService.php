<?php

declare(strict_types=1);

namespace Modules\Essentials\Services;

use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Essentials\Entities\EssentialsAttendance;
use Modules\Essentials\Entities\Shift;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * AttendanceImportService — validação linha a linha do import de presença (HRM-O6 / PR-6, achado A7).
 *
 * O import antigo (`AttendanceController::importAttendance`) aceitava o arquivo inteiro,
 * parava no PRIMEIRO defeito (`break`) e desfazia o lote todo por rollback. Consequência:
 * uma linha ruim na posição 900 descartava 899 marcações boas, e o operador só via
 * "User not found in row no. 900" — sem saber o que mais estava errado.
 *
 * Aqui a checagem é a MESMA de `validateClockInClockOut` (sobreposição com marcação já
 * existente), extraída para `sobrepoeMarcacaoExistente()` — que passa a ser a ÚNICA
 * implementação do predicado: o controller delega para cá em vez de repetir o SQL.
 * (Duas cópias do mesmo predicado é como o A7 nasceu: o formulário validava, o import não.)
 *
 * ── Multi-tenant Tier 0 (ADR 0093) ────────────────────────────────────────────
 * Nenhum método lê `session()`: o `$businessId` vem SEMPRE por parâmetro, porque este
 * service roda dentro de `ImportarPresencaJob` (fila não tem sessão). E-mail e turno são
 * resolvidos com `where('business_id', $businessId)` explícito — linha cujo e-mail
 * pertence a colaborador de OUTRO negócio é RECUSADA, nunca importada.
 *
 * ── Por que não usa `Util::uf_date` ───────────────────────────────────────────
 * `uf_date` lê `session('business.date_format')` — em fila isso é null e o Carbon
 * explode. O formato do arquivo é o documentado no template (`Y-m-d H:i:s`), então a
 * conversão aqui é explícita e independente de sessão.
 *
 * @see Modules\Essentials\Http\Controllers\AttendanceController::validateClockInClockOut
 * @see Modules\Essentials\Jobs\ImportarPresencaJob
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class AttendanceImportService
{
    /**
     * Colunas do arquivo, na ordem do template `import_attendance_template.xls`
     * (medido no arquivo: Email | Clock-in Time | Clock-out Time | Shift |
     * Clock-in note | Clock-out note | IP address).
     */
    public const COL_EMAIL = 0;

    public const COL_CLOCK_IN = 1;

    public const COL_CLOCK_OUT = 2;

    public const COL_SHIFT = 3;

    public const COL_CLOCK_IN_NOTE = 4;

    public const COL_CLOCK_OUT_NOTE = 5;

    public const COL_IP = 6;

    /** Tamanho do lote de INSERT — mantém a query dentro do max_allowed_packet. */
    private const CHUNK = 500;

    /**
     * Valida e importa as linhas já extraídas da planilha (sem o cabeçalho).
     *
     * Contrato: NUNCA lança por linha ruim. Toda linha é avaliada, as boas entram e as
     * ruins voltam no relatório com número da linha e motivo — o oposto do rollback total.
     *
     * @param  array<int, array<int, mixed>>  $linhas  linhas cruas (0-based, sem cabeçalho)
     * @return array{total:int, inseridas:int, recusadas:array<int, array{linha:int, motivo:string}>}
     */
    public function importar(int $businessId, array $linhas, ?string $ipPadrao = null): array
    {
        $recusadas = [];
        $aceitas = [];

        // Lookups em lote: o import antigo fazia 2 SELECT por linha (N+1) — é o que
        // obrigava o `ini_set('max_execution_time', 0)`. Aqui são 2 queries no total.
        $usuariosPorEmail = $this->mapaDeUsuarios($businessId, $linhas);
        $turnosPorNome = $this->mapaDeTurnos($businessId, $linhas);

        foreach ($linhas as $indice => $linha) {
            // +2: o índice é 0-based e o cabeçalho foi removido antes — então a linha 0
            // daqui é a linha 2 da planilha, que é o número que o operador enxerga.
            $numeroLinha = $indice + 2;
            $linha = is_array($linha) ? $linha : [];

            $resultado = $this->validarLinha($businessId, $linha, $usuariosPorEmail, $turnosPorNome, $ipPadrao);

            if (isset($resultado['motivo'])) {
                $recusadas[] = ['linha' => $numeroLinha, 'motivo' => $resultado['motivo']];

                continue;
            }

            $marcacao = $resultado['marcacao'];

            // Sobreposição contra o que JÁ existe no banco (mesma checagem do formulário)...
            if ($this->sobrepoeMarcacaoExistente(
                $businessId,
                [$marcacao['user_id']],
                $marcacao['clock_in_time'],
                $marcacao['clock_out_time']
            )) {
                $recusadas[] = [
                    'linha' => $numeroLinha,
                    'motivo' => __('essentials::lang.import_erro_sobreposicao_banco'),
                ];

                continue;
            }

            // ...e contra as linhas do PRÓPRIO arquivo já aceitas. Sem isto, duas linhas
            // sobrepostas na mesma planilha passariam as duas: nenhuma existe no banco
            // no momento da checagem.
            if ($this->sobrepoeLoteAceito($aceitas, $marcacao)) {
                $recusadas[] = [
                    'linha' => $numeroLinha,
                    'motivo' => __('essentials::lang.import_erro_sobreposicao_arquivo'),
                ];

                continue;
            }

            $aceitas[] = $marcacao;
        }

        $inseridas = $this->inserir($aceitas);

        return [
            'total' => count($linhas),
            'inseridas' => $inseridas,
            'recusadas' => $recusadas,
        ];
    }

    /**
     * Predicado de sobreposição — ÚNICA implementação, usada pelo import e pelo
     * `validateClockInClockOut` do formulário.
     *
     * Semântica preservada do original: considera sobreposto quando a entrada OU a saída
     * cai DENTRO de uma marcação fechada já existente. `$ignorarId` corresponde ao
     * `attendance_id` que o formulário manda ao editar (não conta a própria marcação).
     *
     * @param  array<int, int|string|null>  $userIds
     */
    public function sobrepoeMarcacaoExistente(
        int $businessId,
        array $userIds,
        ?string $clockIn,
        ?string $clockOut,
        int|string|null $ignorarId = null
    ): bool {
        $userIds = array_values(array_filter($userIds, static fn ($id) => $id !== null && $id !== ''));

        if ($userIds === []) {
            return false;
        }

        // SUPERADMIN: `withoutGlobalScopes` porque este predicado roda também dentro do
        // Job (fila não tem sessão, então o ScopeByBusiness não filtra nada). O
        // isolamento vem do `where('business_id', ...)` explícito logo abaixo — ADR 0093.
        $base = fn () => EssentialsAttendance::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->whereIn('user_id', $userIds)
            ->when($ignorarId !== null && $ignorarId !== '', fn ($q) => $q->where('id', '!=', $ignorarId));

        foreach ([$clockIn, $clockOut] as $instante) {
            if (empty($instante)) {
                continue;
            }

            $existe = $base()
                ->where('clock_in_time', '<', $instante)
                ->where('clock_out_time', '>', $instante)
                ->exists();

            if ($existe) {
                return true;
            }
        }

        return false;
    }

    /**
     * Valida uma linha. Devolve `['marcacao' => array]` ou `['motivo' => string]`.
     *
     * @param  array<int, mixed>  $linha
     * @param  array<string, int>  $usuariosPorEmail
     * @param  array<string, int>  $turnosPorNome
     * @return array{marcacao?:array<string, mixed>, motivo?:string}
     */
    private function validarLinha(
        int $businessId,
        array $linha,
        array $usuariosPorEmail,
        array $turnosPorNome,
        ?string $ipPadrao
    ): array {
        $email = $this->texto($linha[self::COL_EMAIL] ?? null);

        if ($email === null) {
            return ['motivo' => __('essentials::lang.import_erro_email_obrigatorio')];
        }

        $userId = $usuariosPorEmail[mb_strtolower($email)] ?? null;

        if ($userId === null) {
            // Cobre as duas causas de uma vez, e é o gate Tier 0: e-mail que existe
            // em OUTRO negócio não aparece no mapa, porque o mapa é scopado.
            return ['motivo' => __('essentials::lang.import_erro_colaborador_nao_encontrado', ['email' => $email])];
        }

        $clockInBruto = $linha[self::COL_CLOCK_IN] ?? null;

        if ($this->texto($clockInBruto) === null && ! is_numeric($clockInBruto)) {
            return ['motivo' => __('essentials::lang.import_erro_entrada_obrigatoria')];
        }

        $clockIn = $this->paraDataHora($clockInBruto);

        if ($clockIn === null) {
            return ['motivo' => __('essentials::lang.import_erro_entrada_invalida', ['valor' => $this->rotulo($clockInBruto)])];
        }

        $clockOutBruto = $linha[self::COL_CLOCK_OUT] ?? null;
        $temClockOut = $this->texto($clockOutBruto) !== null || is_numeric($clockOutBruto);
        $clockOut = null;

        if ($temClockOut) {
            $clockOut = $this->paraDataHora($clockOutBruto);

            if ($clockOut === null) {
                return ['motivo' => __('essentials::lang.import_erro_saida_invalida', ['valor' => $this->rotulo($clockOutBruto)])];
            }

            if ($clockOut <= $clockIn) {
                return ['motivo' => __('essentials::lang.import_erro_saida_antes_da_entrada')];
            }
        }

        $shiftId = null;
        $nomeTurno = $this->texto($linha[self::COL_SHIFT] ?? null);

        if ($nomeTurno !== null) {
            $shiftId = $turnosPorNome[mb_strtolower($nomeTurno)] ?? null;

            if ($shiftId === null) {
                return ['motivo' => __('essentials::lang.import_erro_turno_nao_encontrado', ['turno' => $nomeTurno])];
            }
        }

        return [
            'marcacao' => [
                'user_id' => $userId,
                'business_id' => $businessId,
                'clock_in_time' => $clockIn,
                'clock_out_time' => $clockOut,
                'essentials_shift_id' => $shiftId,
                'clock_in_note' => $this->texto($linha[self::COL_CLOCK_IN_NOTE] ?? null),
                'clock_out_note' => $this->texto($linha[self::COL_CLOCK_OUT_NOTE] ?? null),
                'ip_address' => $this->texto($linha[self::COL_IP] ?? null) ?? $ipPadrao,
            ],
        ];
    }

    /**
     * Sobreposição entre a linha candidata e as já aceitas do mesmo arquivo.
     *
     * @param  array<int, array<string, mixed>>  $aceitas
     * @param  array<string, mixed>  $candidata
     */
    private function sobrepoeLoteAceito(array $aceitas, array $candidata): bool
    {
        foreach ($aceitas as $aceita) {
            if ($aceita['user_id'] !== $candidata['user_id']) {
                continue;
            }

            // Sem saída, a marcação é um instante — só colide se for exatamente o mesmo.
            if ($aceita['clock_out_time'] === null || $candidata['clock_out_time'] === null) {
                if ($aceita['clock_in_time'] === $candidata['clock_in_time']) {
                    return true;
                }

                continue;
            }

            // Intervalos semiabertos: colidem quando um começa antes de o outro terminar.
            if ($aceita['clock_in_time'] < $candidata['clock_out_time']
                && $candidata['clock_in_time'] < $aceita['clock_out_time']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $aceitas
     */
    private function inserir(array $aceitas): int
    {
        if ($aceitas === []) {
            return 0;
        }

        $agora = Carbon::now()->toDateTimeString();

        $linhas = array_map(static function (array $marcacao) use ($agora): array {
            return $marcacao + ['created_at' => $agora, 'updated_at' => $agora];
        }, $aceitas);

        $inseridas = 0;

        // Uma transação por chunk: o lote todo já foi validado, então o rollback aqui
        // cobre falha de infraestrutura — não linha ruim (essa nem chegou até aqui).
        foreach (array_chunk($linhas, self::CHUNK) as $chunk) {
            DB::transaction(function () use ($chunk, &$inseridas): void {
                EssentialsAttendance::insert($chunk);
                $inseridas += count($chunk);
            });
        }

        return $inseridas;
    }

    /**
     * @param  array<int, array<int, mixed>>  $linhas
     * @return array<string, int>  e-mail minúsculo => user_id
     */
    private function mapaDeUsuarios(int $businessId, array $linhas): array
    {
        $emails = [];

        foreach ($linhas as $linha) {
            $email = is_array($linha) ? $this->texto($linha[self::COL_EMAIL] ?? null) : null;

            if ($email !== null) {
                // Chaveado pelo minúsculo pra deduplicar, mas guarda o valor original:
                // é ele que vai no `whereIn`, pra a query usar o índice de `email`
                // (LOWER(email) desabilitaria o índice num lote grande).
                $emails[mb_strtolower($email)] = $email;
            }
        }

        if ($emails === []) {
            return [];
        }

        $mapa = [];

        // `App\User` NÃO tem ScopeByBusiness (medido) — então o `where('business_id')`
        // aqui não é redundância defensiva, é a ÚNICA barreira Tier 0 deste lookup:
        // sem ele, um e-mail de outro negócio resolveria e viraria marcação. ADR 0093.
        $usuarios = User::query()
            ->where('business_id', $businessId)
            ->whereIn('email', array_values($emails))
            ->get(['id', 'email']);

        foreach ($usuarios as $usuario) {
            $mapa[mb_strtolower((string) $usuario->email)] = (int) $usuario->id;
        }

        return $mapa;
    }

    /**
     * @param  array<int, array<int, mixed>>  $linhas
     * @return array<string, int>  nome minúsculo => shift_id
     */
    private function mapaDeTurnos(int $businessId, array $linhas): array
    {
        $nomes = [];

        foreach ($linhas as $linha) {
            $nome = is_array($linha) ? $this->texto($linha[self::COL_SHIFT] ?? null) : null;

            if ($nome !== null) {
                // Mesma razão do mapa de e-mails: deduplica pelo minúsculo, consulta
                // pelo valor original pra não perder o índice de `name`.
                $nomes[mb_strtolower($nome)] = $nome;
            }
        }

        if ($nomes === []) {
            return [];
        }

        $mapa = [];

        // SUPERADMIN: sem sessão na fila; o isolamento é o where explícito — ADR 0093.
        $turnos = Shift::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->whereIn('name', array_values($nomes))
            ->get(['id', 'name']);

        foreach ($turnos as $turno) {
            $mapa[mb_strtolower((string) $turno->name)] = (int) $turno->id;
        }

        return $mapa;
    }

    /**
     * Normaliza célula para `Y-m-d H:i:s`, ou null quando não é data.
     *
     * Trata os três formatos que o leitor devolve na prática: string no formato do
     * template, serial numérico do Excel (célula formatada como data) e objeto DateTime.
     */
    private function paraDataHora(mixed $valor): ?string
    {
        if ($valor instanceof \DateTimeInterface) {
            return Carbon::parse($valor->format('Y-m-d H:i:s'))->format('Y-m-d H:i:s');
        }

        if (is_numeric($valor) && ! is_string($valor)) {
            try {
                return Carbon::parse(ExcelDate::excelToDateTimeObject((float) $valor)->format('Y-m-d H:i:s'))
                    ->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return null;
            }
        }

        $texto = $this->texto($valor);

        if ($texto === null) {
            return null;
        }

        // Pares [formato de PARSE, formato de COMPARAÇÃO]. O `!` inicial zera os campos
        // que o formato não informa — sem ele, `Y-m-d` herdaria a hora do relógio atual
        // e uma célula só-data viraria uma marcação no horário do import.
        $formatos = [
            ['!Y-m-d H:i:s', 'Y-m-d H:i:s'],
            ['!Y-m-d H:i', 'Y-m-d H:i'],
            ['!Y-m-d', 'Y-m-d'],
        ];

        foreach ($formatos as [$parse, $comparacao]) {
            try {
                $data = Carbon::createFromFormat($parse, $texto);
            } catch (\Throwable) {
                continue;
            }

            // `createFromFormat` é tolerante (aceita "2026-13-45" e rola pra 2027-01-14);
            // só aceita quando o round-trip devolve exatamente o texto original.
            if ($data !== false && $data->format($comparacao) === $texto) {
                return $data->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    /** Trim seguro para célula de qualquer tipo; devolve null quando vazia. */
    private function texto(mixed $valor): ?string
    {
        if ($valor === null || is_array($valor) || is_object($valor) || is_bool($valor)) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    /** Representação curta e segura da célula para a mensagem de recusa. */
    private function rotulo(mixed $valor): string
    {
        $texto = $this->texto($valor);

        if ($texto === null) {
            return is_numeric($valor) ? (string) $valor : '';
        }

        return mb_substr($texto, 0, 40);
    }
}
