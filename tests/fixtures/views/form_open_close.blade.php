{{-- Fixture pra smoke test de Form::open/close/token --}}
<div id="forms">
    {{-- Form POST simples --}}
    {!! Form::open(['url' => '/users', 'id' => 'post_form']) !!}
    {!! Form::text('name', 'wagner') !!}
    {!! Form::close() !!}

    {{-- Form PUT (spoofed method) --}}
    {!! Form::open(['url' => '/users/42', 'method' => 'PUT', 'id' => 'put_form']) !!}
    {!! Form::close() !!}

    {{-- Form DELETE com files --}}
    {!! Form::open(['url' => '/users/42', 'method' => 'DELETE', 'files' => true, 'id' => 'del_form']) !!}
    {!! Form::close() !!}

    {{-- Form GET (sem CSRF) --}}
    {!! Form::open(['url' => '/search', 'method' => 'GET', 'id' => 'get_form']) !!}
    {!! Form::close() !!}

    {{-- Token standalone --}}
    <div id="standalone-token">{!! Form::token() !!}</div>
</div>
