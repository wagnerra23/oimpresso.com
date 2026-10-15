<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Services\AppLojas\AjustesDaEmpresa;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Modules\Arquivos\Services\ArquivosService;
use Modules\Arquivos\Services\VaultEncryptionService;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anexos da OS no app das lojas (P2, telas 03 e 22). Contrato:
 * memory/requisitos/AppMobile/api/tela-03-anexos.md.
 *
 * Sobre a tabela `arquivos` (Modules/Arquivos, ADR 0123): o mesmo morph que a web usa para as
 * fotos da OS (`arquivable_type` = FQCN do ServiceOrder), o mesmo caminho de gravação
 * (ArquivosService: dedupe, Curador, vault, trilha).
 *
 * Tier 0 (ADR 0093): business_id do usuário do token em toda consulta, explícito — os global
 * scopes do Arquivo e do ServiceOrder leem a SESSÃO, que não existe numa chamada com token.
 * OS de outro business → 404, sem dizer que existe.
 *
 * URL do conteúdo: assinada (HMAC do APP_KEY), vale 10 min, carrega o business do anexo. É a
 * única forma de um <img> do app abrir a foto: a imagem não manda o token. A rota de download da
 * web (`arquivos.download`) pede sessão, por isso não serve aqui.
 */
class OsArquivosController extends Controller
{
    /** Teto da lista (contrato): os mais novos primeiro. */
    private const MAX_ITENS = 100;

    /** Validade da URL assinada, em minutos (contrato). */
    private const URL_MINUTOS = 10;

    /** Teto do upload em bytes (contrato: 10 MB). */
    private const MAX_BYTES = 10 * 1024 * 1024;

    /** Tipos aceitos. HEIC fica fora: não há conversão no servidor (ver o contrato). */
    private const MIMES = ['image/jpeg', 'image/png', 'application/pdf'];

    private const ROTA_IDEMPOTENCIA = 'os_arquivos';

    /** GET /api/app/os/{id}/arquivos */
    public function index(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $this->oficina()->podeVerOficina($user)) {
            return $this->semPermissao();
        }

        $bizId = (int) $user->business_id;
        if (! $this->osDoNegocio($bizId, $id)) {
            return $this->naoEncontrada();
        }

        $linhas = $this->consulta($bizId)
            ->where('a.arquivable_id', $id)
            ->orderByDesc('a.created_at')->orderByDesc('a.id')
            ->limit(self::MAX_ITENS)
            ->get($this->colunas());

        return response()->json([
            'itens' => $linhas->map(fn ($l) => $this->item($bizId, $l))->values(),
            'pode_enviar' => $this->podeEnviar($user),
        ]);
    }

    /** POST /api/app/os/{id}/arquivos — multipart: arquivo, legenda?, origem? (camera|galeria). */
    public function store(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $this->oficina()->podeVerOficina($user) || ! $this->podeEnviar($user)) {
            return $this->semPermissao();
        }

        $bizId = (int) $user->business_id;
        if (! $this->osDoNegocio($bizId, $id)) {
            return $this->naoEncontrada();
        }

        // 413 antes do resto: o PHP pode ter recusado o arquivo (upload_max_filesize), e aí ele
        // nem chega como "arquivo válido".
        $arquivo = $request->file('arquivo');
        if ($arquivo instanceof UploadedFile && (
            in_array($arquivo->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            || (int) $arquivo->getSize() > self::MAX_BYTES
        )) {
            return response()->json(['erro' => 'arquivo_grande', 'mensagem' => 'O arquivo passa de 10 MB.'], 413);
        }

        $chave = trim((string) $request->header('Idempotency-Key', ''));
        $v = Validator::make(array_merge($request->all(), ['idempotency_key' => $chave]), [
            'idempotency_key' => ['required', 'string', 'max:100'],
            'arquivo' => ['required', 'file', 'mimetypes:' . implode(',', self::MIMES)],
            'legenda' => ['nullable', 'string', 'max:160'],
            'origem' => ['nullable', 'in:camera,galeria'],
        ], [
            'idempotency_key.required' => 'Envie o header Idempotency-Key.',
            'arquivo.required' => 'Escolha um arquivo.',
            'arquivo.file' => 'Escolha um arquivo.',
            'arquivo.mimetypes' => 'Envie foto (JPG ou PNG) ou PDF.',
            'legenda.max' => 'A legenda tem no máximo 160 caracteres.',
            'origem.in' => 'Origem inválida.',
        ]);
        $campos = $v->fails() ? collect($v->errors()->toArray())->map(fn ($m) => $m[0])->all() : [];
        // Ajuste da empresa (app_anexos_camera): desligado, só galeria.
        if (! isset($campos['origem']) && $request->input('origem') === 'camera'
            && AjustesDaEmpresa::valor($bizId, 'app_anexos_camera') === false) {
            $campos['origem'] = 'Esta empresa só aceita anexo da galeria.';
        }
        if ($campos !== []) {
            return response()->json(['erro' => 'validacao', 'campos' => $campos], 422);
        }

        /** @var UploadedFile $arquivo */
        $legenda = trim((string) $request->input('legenda', ''));
        $legenda = $legenda === '' ? null : $legenda;
        $hash = hash('sha256', (string) json_encode([$id, md5_file($arquivo->getRealPath()), $legenda]));

        $ja = $this->idempotencia($bizId, (int) $user->id, $chave);
        if ($ja !== null) {
            return $this->repetida($bizId, $ja, $hash);
        }

        try {
            $reservaId = DB::table('app_idempotencia')->insertGetId([
                'business_id' => $bizId, 'user_id' => (int) $user->id, 'rota' => self::ROTA_IDEMPOTENCIA,
                'chave' => $chave, 'hash_corpo' => $hash, 'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            $ja = $this->idempotencia($bizId, (int) $user->id, $chave);

            return $ja !== null ? $this->repetida($bizId, $ja, $hash) : $this->emAndamento();
        }

        try {
            // Tier 0: a OS já foi conferida no business do token; o where explícito não depende
            // do global scope (que lê a sessão).
            $os = ServiceOrder::query()->where('business_id', $bizId)->whereKey($id)->firstOrFail();
            $novo = app(ArquivosService::class)->attachNoNegocio($os, $arquivo, $bizId);
            DB::table('arquivos')->where('business_id', $bizId)->where('id', $novo->id)->update([
                'legenda' => $legenda,
                'etapa' => $this->etapaAtual($bizId, $id),
            ]);
            DB::table('app_idempotencia')->where('id', $reservaId)->update([
                'resposta' => json_encode(['arquivo_id' => (int) $novo->id]),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            DB::table('app_idempotencia')->where('id', $reservaId)->delete();
            Log::error('API app os.arquivos.store: ' . $e->getMessage(), ['business_id' => $bizId]);

            return response()->json(['erro' => 'falha', 'mensagem' => 'Não foi possível enviar o anexo.'], 500);
        }

        // `origem` serve só para métrica (contrato): não vira regra nem coluna.
        Log::info('app.os_arquivos.upload', [
            'business_id' => $bizId, 'arquivo_id' => (int) $novo->id, 'origem' => $request->input('origem'),
        ]);

        return $this->respostaItem($bizId, (int) $novo->id, 201);
    }

    /**
     * GET /api/app/arquivos/{arquivo}/conteudo?biz=&expires=&signature= — sem token: a assinatura
     * (10 min) é a autorização. O business vem DENTRO da assinatura, então não dá para trocá-lo.
     */
    public function conteudo(Request $request, int $arquivo): Response
    {
        $bizId = (int) $request->query('biz');
        $a = DB::table('arquivos')->where('business_id', $bizId)->where('id', $arquivo)
            ->whereNull('deleted_at')->first(['id', 'business_id', 'disk', 'storage_path', 'original_name', 'mime_type', 'encrypted']);
        if ($a === null || trim((string) $a->storage_path) === '') {
            return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'Anexo não encontrado.'], 404);
        }

        $disco = $a->disk ?: 'arquivos';
        $disk = Storage::disk($disco);
        if (! $disk->exists($a->storage_path)) {
            return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'Anexo não encontrado.'], 404);
        }

        try {
            DB::table('arquivos_audit_log')->insert([
                'arquivo_id' => (int) $a->id, 'business_id' => (int) $a->business_id, 'user_id' => null,
                'action' => 'signed_url_consumed',
                'payload' => json_encode(['via' => 'app', 'agent' => substr((string) $request->userAgent(), 0, 200)]),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('app.os_arquivos.audit_failed', ['arquivo_id' => (int) $a->id]);
        }

        $corpo = (bool) $a->encrypted
            ? app(VaultEncryptionService::class)->getDecrypted($disco, $a->storage_path)
            : $disk->get($a->storage_path);
        if ($corpo === null) {
            return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'Anexo não encontrado.'], 404);
        }

        return response($corpo, 200, [
            'Content-Type' => $a->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . addslashes((string) $a->original_name) . '"',
            'Cache-Control' => 'private, max-age=' . (self::URL_MINUTOS * 60),
        ]);
    }

    private function respostaItem(int $bizId, int $arquivoId, int $status): JsonResponse
    {
        $l = $this->consulta($bizId)->where('a.id', $arquivoId)->first($this->colunas());

        return response()->json($this->item($bizId, $l), $status);
    }

    /** @return array<string, mixed> */
    private function item(int $bizId, object $l): array
    {
        $url = URL::temporarySignedRoute(
            'app.arquivos.conteudo',
            now()->addMinutes(self::URL_MINUTOS),
            ['arquivo' => (int) $l->id, 'biz' => $bizId],
        );
        $nome = trim(((string) $l->first_name) . ' ' . ((string) $l->last_name));

        return [
            'id' => (int) $l->id,
            'nome' => (string) $l->original_name,
            'tipo' => (string) $l->mime_type,
            'tamanho' => (int) $l->size_bytes,
            'legenda' => $l->legenda,
            'etapa' => $l->etapa,
            'enviado_em' => \Illuminate\Support\Carbon::parse($l->created_at)->utc()->format('Y-m-d\TH:i:s\Z'),
            'enviado_por' => $nome === '' ? null : $nome,
            'url' => $url,
            // Sem gerador de miniatura no ERP: a foto serve de miniatura; PDF não tem.
            'miniatura' => str_starts_with((string) $l->mime_type, 'image/') ? $url : null,
        ];
    }

    private function consulta(int $bizId): \Illuminate\Database\Query\Builder
    {
        return DB::table('arquivos as a')
            ->leftJoin('users as u', function ($j) {
                $j->on('u.id', '=', 'a.uploaded_by_user_id')->on('u.business_id', '=', 'a.business_id');
            })
            ->where('a.business_id', $bizId)
            ->where('a.arquivable_type', ServiceOrder::class)
            ->whereNull('a.deleted_at');
    }

    /** @return list<string> */
    private function colunas(): array
    {
        return ['a.id', 'a.original_name', 'a.mime_type', 'a.size_bytes', 'a.legenda', 'a.etapa',
            'a.created_at', 'u.first_name', 'u.last_name'];
    }

    private function osDoNegocio(int $bizId, int $id): bool
    {
        return DB::table('service_orders')->where('business_id', $bizId)->whereNull('deleted_at')
            ->where('id', $id)->exists();
    }

    /**
     * Chave da etapa atual da OS. OS de mecânica sem pipeline conta na etapa inicial — a mesma
     * regra do quadro web e do GET /api/app/os/{id}.
     */
    private function etapaAtual(int $bizId, int $id): ?string
    {
        $os = DB::table('service_orders')->where('business_id', $bizId)->where('id', $id)
            ->first(['order_type', 'current_stage_id']);
        $etapas = DB::table('sale_process_stages as s')
            ->join('sale_processes as p', 'p.id', '=', 's.process_id')
            ->where('p.business_id', $bizId)
            ->where('p.key', OficinaController::PROCESSO);
        if ($os === null) {
            return null;
        }
        if ($os->current_stage_id === null) {
            return $os->order_type === 'mecanica'
                ? $etapas->where('s.is_terminal', false)->orderBy('s.sort_order')->orderBy('s.id')->value('s.key')
                : null;
        }

        return $etapas->where('s.id', (int) $os->current_stage_id)->value('s.key');
    }

    private function podeEnviar(?User $user): bool
    {
        return $user !== null && ($user->can('superadmin') || $user->can('oficinaauto.service_order.update'));
    }

    private function idempotencia(int $bizId, int $userId, string $chave): ?object
    {
        return DB::table('app_idempotencia')->where('business_id', $bizId)->where('user_id', $userId)
            ->where('rota', self::ROTA_IDEMPOTENCIA)->where('chave', $chave)->first();
    }

    /** Mesma chave: mesmo corpo devolve o mesmo anexo (URL nova); corpo diferente é conflito. */
    private function repetida(int $bizId, object $ja, string $hash): JsonResponse
    {
        if (! hash_equals((string) $ja->hash_corpo, $hash)) {
            return response()->json([
                'erro' => 'idempotencia_conflito',
                'mensagem' => 'Esta chave já foi usada para outro anexo. Gere uma chave nova.',
            ], 422);
        }
        $arquivoId = (int) (json_decode((string) $ja->resposta, true)['arquivo_id'] ?? 0);
        if ($arquivoId === 0) {
            return $this->emAndamento();
        }

        return $this->respostaItem($bizId, $arquivoId, 201);
    }

    private function emAndamento(): JsonResponse
    {
        return response()->json([
            'erro' => 'em_andamento',
            'mensagem' => 'Este anexo ainda está sendo enviado. Tente de novo em instantes.',
        ], 409);
    }

    private function oficina(): OficinaController
    {
        return app(OficinaController::class);
    }

    private function naoEncontrada(): JsonResponse
    {
        return response()->json(['erro' => 'nao_encontrado', 'mensagem' => 'OS não encontrada.'], 404);
    }

    private function semPermissao(): JsonResponse
    {
        return response()->json([
            'erro' => 'sem_permissao',
            'mensagem' => 'Seu usuário não tem acesso aos anexos da OS.',
        ], 403);
    }
}
