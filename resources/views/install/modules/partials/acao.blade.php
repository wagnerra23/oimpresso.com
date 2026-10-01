{{--
    Botão de ação de módulo (instalar / desinstalar / atualizar) em /manage-modules.
    $post = o módulo registra POST nesta URL (ModulesController::aceitaPost, perguntado ao
    router) → <form method="POST"> + @csrf: prefetch/link/<img> não disparam a ação.
    Sem POST registrado → mantém o <a href> (GET), senão o módulo daria 405.
    Parâmetros: $url, $post, $classe, $rotulo, $is_demo, $confirmar (opcional).
--}}
@if($is_demo)
    <a class="{{ $classe }}" href="#" title="@lang('lang_v1.disabled_in_demo')" disabled>{{ $rotulo }}</a>
@elseif($post)
    <form action="{{ $url }}" method="POST" style="display: inline;"
        @isset($confirmar) onsubmit="return confirm('{{ $confirmar }}')" @endisset>
        @csrf
        <button type="submit" class="{{ $classe }}">{{ $rotulo }}</button>
    </form>
@else
    <a class="{{ $classe }}" href="{{ $url }}"
        @isset($confirmar) onclick="return confirm('{{ $confirmar }}')" @endisset>{{ $rotulo }}</a>
@endif
