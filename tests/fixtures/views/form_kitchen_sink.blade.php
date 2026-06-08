{{-- Fixture pra smoke test do alias Form:: (resolve via config/app.php aliases) --}}
<div id="kitchen-sink">
    {!! Form::label('username', 'Usuário:', ['class' => 'control-label']) !!}
    {!! Form::text('username', $user_name ?? 'wagner', ['class' => 'form-control', 'id' => 'u']) !!}

    {!! Form::email('email', $email ?? 'a@b.com') !!}

    {!! Form::password('senha', ['class' => 'pass']) !!}

    {!! Form::hidden('user_id', $user_id ?? 42) !!}

    {!! Form::textarea('bio', $bio ?? 'linha<script>', ['rows' => 3]) !!}

    {!! Form::select('pais', $paises ?? ['BR' => 'Brasil', 'PT' => 'Portugal'], $pais_selected ?? 'PT', ['class' => 'sel']) !!}

    {!! Form::checkbox('terms', 1, $aceito ?? true, ['id' => 'chk']) !!}

    {!! Form::radio('gender', 'F', true, ['id' => 'rf']) !!}

    {!! Form::number('qty', 10, ['min' => 1, 'max' => 99]) !!}

    {!! Form::date('birth', '1985-05-15') !!}

    {!! Form::file('avatar', ['accept' => 'image/*']) !!}

    {!! Form::submit('Salvar', ['class' => 'btn']) !!}
</div>
