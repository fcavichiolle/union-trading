@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if (session('linkGerado'))
    <div class="alert alert-success">
        Link temporário (válido por 7 dias): <code>{{ session('linkGerado') }}</code>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-error">
        <strong>Verifique os campos abaixo:</strong>
        <ul style="margin:6px 0 0; padding-left:18px;">
            @foreach ($errors->all() as $erro)
                <li>{{ $erro }}</li>
            @endforeach
        </ul>
    </div>
@endif
