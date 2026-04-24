<?php

namespace Modules\MemCofre\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\MemCofre\Entities\DocChatMessage;
use Modules\MemCofre\Services\ChatAssistant;
use Modules\MemCofre\Services\RequirementsFileReader;

class ChatController extends Controller
{
    public function index(Request $request, RequirementsFileReader $reader): Response
    {
        $businessId = (int) (session('business.id') ?: $request->session()->get('user.business_id'));
        $userId = (int) (auth()->id() ?? 0);

        $sessionId = $request->query('session') ?: ('sess_' . Str::random(16));

        $history = DocChatMessage::where('business_id', $businessId)
            ->where('user_id', $userId)
            ->where('session_id', $sessionId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (DocChatMessage $m) => [
                'id'             => $m->id,
                'role'           => $m->role,
                'content'        => $m->content,
                'module_context' => $m->module_context,
                'sources'        => $m->sources,
                'mode'           => $m->mode,
                'created_at'     => optional($m->created_at)->format('H:i'),
            ]);

        // Lista de sessões recentes do usuário
        $recent = DocChatMessage::where('business_id', $businessId)
            ->where('user_id', $userId)
            ->selectRaw('session_id, max(created_at) as last_at, count(*) as msg_count, min(content) as first_msg')
            ->groupBy('session_id')
            ->orderByDesc('last_at')
            ->limit(10)
            ->get()
            ->map(fn ($s) => [
                'session_id' => $s->session_id,
                'last_at'    => $s->last_at,
                'msg_count'  => $s->msg_count,
                'preview'    => Str::limit($s->first_msg, 60),
            ]);

        $modules = array_map(fn ($m) => $m['name'], $reader->listModules());

        return Inertia::render('MemCofre/Chat', [
            'session_id'  => $sessionId,
            'history'     => $history,
            'recent'      => $recent,
            'modules'     => $modules,
            'ai_enabled'  => (bool) config('memcofre.ai.enabled', false),
        ]);
    }

    public function ask(Request $request, ChatAssistant $assistant): JsonResponse
    {
        $validated = $request->validate([
            'session_id'     => 'required|string|max:64',
            'question'       => 'required|string|max:2000',
            'module_context' => 'nullable|string|max:64',
        ]);

        $businessId = (int) (session('business.id') ?: $request->session()->get('user.business_id'));
        $userId = (int) (auth()->id() ?? 0);

        // Persiste a pergunta do usuário
        DocChatMessage::create([
            'business_id'    => $businessId,
            'user_id'        => $userId,
            'session_id'     => $validated['session_id'],
            'role'           => 'user',
            'content'        => $validated['question'],
            'module_context' => $validated['module_context'] ?? null,
            'mode'           => 'offline',
        ]);

        // Gera resposta
        $answer = $assistant->ask($validated['question'], $validated['module_context'] ?? null);

        $reply = DocChatMessage::create([
            'business_id'    => $businessId,
            'user_id'        => $userId,
            'session_id'     => $validated['session_id'],
            'role'           => 'assistant',
            'content'        => $answer['reply'],
            'module_context' => $validated['module_context'] ?? null,
            'sources'        => $answer['sources'],
            'mode'           => $answer['mode'],
            'tokens_used'    => $answer['tokens_used'],
        ]);

        return response()->json([
            'reply' => [
                'id'             => $reply->id,
                'role'           => 'assistant',
                'content'        => $reply->content,
                'module_context' => $reply->module_context,
                'sources'        => $reply->sources,
                'mode'           => $reply->mode,
                'created_at'     => $reply->created_at->format('H:i'),
            ],
        ]);
    }

    public function newSession(): JsonResponse
    {
        return response()->json([
            'session_id' => 'sess_' . Str::random(16),
        ]);
    }
}
