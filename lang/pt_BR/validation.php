<?php

/*
|--------------------------------------------------------------------------
| Mensagens de validação em português
|--------------------------------------------------------------------------
| ATENÇÃO: este arquivo é obrigatório. O `.env` usa APP_LOCALE=pt_BR e
| APP_FALLBACK_LOCALE=pt_BR; sem ele o Laravel não acha tradução nenhuma
| e imprime a CHAVE crua na tela ("validation.required"), que foi
| exatamente o bug corrigido aqui.
|
| O array 'attributes' no fim do arquivo é o que faz a mensagem dizer o
| nome do campo como ele aparece no formulário ("O campo volume entregue
| (sacas) é obrigatório." em vez de "O campo volume_sacas..."). Campo novo
| em formulário => acrescentar a etiqueta dele lá.
|
| Mensagens específicas de uma regra + campo continuam nos FormRequests
| (método messages()), perto das regras que as disparam.
*/

return [

    'accepted' => 'O campo :attribute deve ser aceito.',
    'accepted_if' => 'O campo :attribute deve ser aceito quando :other for :value.',
    'active_url' => 'O campo :attribute não é uma URL válida.',
    'after' => 'O campo :attribute deve ser uma data posterior a :date.',
    'after_or_equal' => 'O campo :attribute deve ser uma data posterior ou igual a :date.',
    'alpha' => 'O campo :attribute deve conter apenas letras.',
    'alpha_dash' => 'O campo :attribute deve conter apenas letras, números, hífens e sublinhados.',
    'alpha_num' => 'O campo :attribute deve conter apenas letras e números.',
    'array' => 'O campo :attribute deve ser uma lista.',
    'ascii' => 'O campo :attribute deve conter apenas caracteres e símbolos alfanuméricos de um byte.',
    'before' => 'O campo :attribute deve ser uma data anterior a :date.',
    'before_or_equal' => 'O campo :attribute deve ser uma data anterior ou igual a :date.',
    'between' => [
        'array' => 'O campo :attribute deve ter entre :min e :max itens.',
        'file' => 'O arquivo :attribute deve ter entre :min e :max kilobytes.',
        'numeric' => 'O campo :attribute deve estar entre :min e :max.',
        'string' => 'O campo :attribute deve ter entre :min e :max caracteres.',
    ],
    'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
    'can' => 'O campo :attribute contém um valor não autorizado.',
    'confirmed' => 'A confirmação do campo :attribute não confere.',
    'contains' => 'O campo :attribute está sem um valor obrigatório.',
    'current_password' => 'A senha informada está incorreta.',
    'date' => 'O campo :attribute não é uma data válida.',
    'date_equals' => 'O campo :attribute deve ser uma data igual a :date.',
    'date_format' => 'O campo :attribute não corresponde ao formato :format.',
    'decimal' => 'O campo :attribute deve ter :decimal casas decimais.',
    'declined' => 'O campo :attribute deve ser recusado.',
    'declined_if' => 'O campo :attribute deve ser recusado quando :other for :value.',
    'different' => 'Os campos :attribute e :other devem ser diferentes.',
    'digits' => 'O campo :attribute deve ter :digits dígitos.',
    'digits_between' => 'O campo :attribute deve ter entre :min e :max dígitos.',
    'dimensions' => 'O campo :attribute tem dimensões de imagem inválidas.',
    'distinct' => 'O campo :attribute tem um valor duplicado.',
    'doesnt_end_with' => 'O campo :attribute não deve terminar com um dos seguintes: :values.',
    'doesnt_start_with' => 'O campo :attribute não deve começar com um dos seguintes: :values.',
    'email' => 'O campo :attribute deve ser um endereço de e-mail válido.',
    'ends_with' => 'O campo :attribute deve terminar com um dos seguintes: :values.',
    'enum' => 'A opção selecionada em :attribute é inválida.',
    'exists' => 'A opção selecionada em :attribute é inválida.',
    'extensions' => 'O campo :attribute deve ter uma das seguintes extensões: :values.',
    'file' => 'O campo :attribute deve ser um arquivo.',
    'filled' => 'O campo :attribute deve ter um valor.',
    'gt' => [
        'array' => 'O campo :attribute deve ter mais de :value itens.',
        'file' => 'O arquivo :attribute deve ser maior que :value kilobytes.',
        'numeric' => 'O campo :attribute deve ser maior que :value.',
        'string' => 'O campo :attribute deve ter mais de :value caracteres.',
    ],
    'gte' => [
        'array' => 'O campo :attribute deve ter :value itens ou mais.',
        'file' => 'O arquivo :attribute deve ser maior ou igual a :value kilobytes.',
        'numeric' => 'O campo :attribute deve ser maior ou igual a :value.',
        'string' => 'O campo :attribute deve ter :value caracteres ou mais.',
    ],
    'hex_color' => 'O campo :attribute deve ser uma cor hexadecimal válida.',
    'image' => 'O campo :attribute deve ser uma imagem.',
    'in' => 'A opção selecionada em :attribute é inválida.',
    'in_array' => 'O campo :attribute deve existir em :other.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'ip' => 'O campo :attribute deve ser um endereço IP válido.',
    'ipv4' => 'O campo :attribute deve ser um endereço IPv4 válido.',
    'ipv6' => 'O campo :attribute deve ser um endereço IPv6 válido.',
    'json' => 'O campo :attribute deve ser um JSON válido.',
    'list' => 'O campo :attribute deve ser uma lista.',
    'lowercase' => 'O campo :attribute deve estar em minúsculas.',
    'lt' => [
        'array' => 'O campo :attribute deve ter menos de :value itens.',
        'file' => 'O arquivo :attribute deve ser menor que :value kilobytes.',
        'numeric' => 'O campo :attribute deve ser menor que :value.',
        'string' => 'O campo :attribute deve ter menos de :value caracteres.',
    ],
    'lte' => [
        'array' => 'O campo :attribute não deve ter mais que :value itens.',
        'file' => 'O arquivo :attribute deve ser menor ou igual a :value kilobytes.',
        'numeric' => 'O campo :attribute deve ser menor ou igual a :value.',
        'string' => 'O campo :attribute deve ter :value caracteres ou menos.',
    ],
    'mac_address' => 'O campo :attribute deve ser um endereço MAC válido.',
    'max' => [
        'array' => 'O campo :attribute não deve ter mais que :max itens.',
        'file' => 'O arquivo :attribute não deve ter mais que :max kilobytes.',
        'numeric' => 'O campo :attribute não pode ser maior que :max.',
        'string' => 'O campo :attribute não deve ter mais que :max caracteres.',
    ],
    'max_digits' => 'O campo :attribute não deve ter mais que :max dígitos.',
    'mimes' => 'O campo :attribute deve ser um arquivo do tipo: :values.',
    'mimetypes' => 'O campo :attribute deve ser um arquivo do tipo: :values.',
    'min' => [
        'array' => 'O campo :attribute deve ter pelo menos :min itens.',
        'file' => 'O arquivo :attribute deve ter pelo menos :min kilobytes.',
        'numeric' => 'O campo :attribute não pode ser menor que :min.',
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
    ],
    'min_digits' => 'O campo :attribute deve ter pelo menos :min dígitos.',
    'missing' => 'O campo :attribute não deve estar presente.',
    'missing_if' => 'O campo :attribute não deve estar presente quando :other for :value.',
    'missing_unless' => 'O campo :attribute não deve estar presente a menos que :other seja :value.',
    'missing_with' => 'O campo :attribute não deve estar presente quando :values estiver presente.',
    'missing_with_all' => 'O campo :attribute não deve estar presente quando :values estiverem presentes.',
    'multiple_of' => 'O campo :attribute deve ser múltiplo de :value.',
    'not_in' => 'A opção selecionada em :attribute é inválida.',
    'not_regex' => 'O formato do campo :attribute é inválido.',
    'numeric' => 'O campo :attribute deve ser um número.',
    'password' => [
        'letters' => 'A senha deve conter pelo menos uma letra.',
        'mixed' => 'A senha deve conter pelo menos uma letra maiúscula e uma minúscula.',
        'numbers' => 'A senha deve conter pelo menos um número.',
        'symbols' => 'A senha deve conter pelo menos um símbolo (ex.: ! @ # $).',
        'uncompromised' => 'Esta senha apareceu em vazamentos de dados conhecidos. Escolha outra.',
    ],
    'present' => 'O campo :attribute deve estar presente.',
    'present_if' => 'O campo :attribute deve estar presente quando :other for :value.',
    'present_unless' => 'O campo :attribute deve estar presente a menos que :other seja :value.',
    'present_with' => 'O campo :attribute deve estar presente quando :values estiver presente.',
    'present_with_all' => 'O campo :attribute deve estar presente quando :values estiverem presentes.',
    'prohibited' => 'O campo :attribute é proibido.',
    'prohibited_if' => 'O campo :attribute é proibido quando :other for :value.',
    'prohibited_unless' => 'O campo :attribute é proibido a menos que :other seja :values.',
    'prohibits' => 'O campo :attribute proíbe que :other esteja presente.',
    'regex' => 'O formato do campo :attribute é inválido.',
    'required' => 'O campo :attribute é obrigatório.',
    'required_array_keys' => 'O campo :attribute deve conter entradas para: :values.',
    'required_if' => 'O campo :attribute é obrigatório quando :other for :value.',
    'required_if_accepted' => 'O campo :attribute é obrigatório quando :other for aceito.',
    'required_if_declined' => 'O campo :attribute é obrigatório quando :other for recusado.',
    'required_unless' => 'O campo :attribute é obrigatório a menos que :other esteja em :values.',
    'required_with' => 'O campo :attribute é obrigatório quando :values está presente.',
    'required_with_all' => 'O campo :attribute é obrigatório quando :values estão presentes.',
    'required_without' => 'O campo :attribute é obrigatório quando :values não está presente.',
    'required_without_all' => 'O campo :attribute é obrigatório quando nenhum dos :values estão presentes.',
    'same' => 'Os campos :attribute e :other devem ser iguais.',
    'size' => [
        'array' => 'O campo :attribute deve conter :size itens.',
        'file' => 'O arquivo :attribute deve ter :size kilobytes.',
        'numeric' => 'O campo :attribute deve ser :size.',
        'string' => 'O campo :attribute deve ter :size caracteres.',
    ],
    'starts_with' => 'O campo :attribute deve começar com um dos seguintes: :values.',
    'string' => 'O campo :attribute deve ser um texto.',
    'timezone' => 'O campo :attribute deve ser um fuso horário válido.',
    'unique' => 'Este :attribute já está em uso.',
    'uploaded' => 'Falha no upload do arquivo :attribute.',
    'uppercase' => 'O campo :attribute deve estar em maiúsculas.',
    'url' => 'O campo :attribute deve ser uma URL válida.',
    'ulid' => 'O campo :attribute deve ser um ULID válido.',
    'uuid' => 'O campo :attribute deve ser um UUID válido.',

    /*
    |--------------------------------------------------------------------------
    | Nomes dos campos como aparecem nos formulários
    |--------------------------------------------------------------------------
    | Mantenha igual ao <label> da tela: é assim que o usuário identifica
    | qual campo precisa corrigir.
    */

    'attributes' => [
        // Compras (o negócio)
        'uts' => 'UTS (ref. de compra)',
        'data_compra' => 'data da compra',
        'fornecedor_nome' => 'nome do vendedor',
        'fornecedor_documento' => 'CNPJ/CPF do vendedor',
        'certificacao' => 'certificação',
        'logistica' => 'logística',
        'tipo_entrada' => 'tipo de café',
        'volume_contratado' => 'volume contratado (sacas)',
        'peso_kg' => 'peso (kg)',
        'pagamento_previsto' => 'pagamento previsto',
        'pagamento_obs' => 'observação do pagamento',

        // Entregas (o que entrou no armazém)
        'data_entrega' => 'data da entrega',
        'armazem' => 'armazém',
        'volume_sacas' => 'sacas entregues',
        'numero_lote' => 'número do lote',

        // Classificação
        'padrao_final' => 'padrão final',
        'tipo_bebida' => 'tipo de bebida',
        'peneira_12up_pct' => '% da peneira 12 UP',
        'peneira_12up_sacas' => 'sacas da peneira 12 UP',
        'peneira_13up_pct' => '% da peneira 13 UP',
        'peneira_13up_sacas' => 'sacas da peneira 13 UP',
        'peneira_1718_pct' => '% da peneira 17/18',
        'peneira_1718_sacas' => 'sacas da peneira 17/18',
        'peneira_1416_pct' => '% da peneira 14/16',
        'peneira_1416_sacas' => 'sacas da peneira 14/16',
        'mercado_interno_pct' => '% do mercado interno',
        'mercado_interno_sacas' => 'sacas do mercado interno',
        'grinders_pct' => '% de grinders',
        'grinders_sacas' => 'sacas de grinders',
        'moka_pct' => '% de moka',
        'moka_sacas' => 'sacas de moka',

        // Financeiro da compra
        'valor_saca' => 'valor da saca',
        'corretor_nome' => 'nome do corretor',
        'comissao_pct' => 'comissão (%)',

        // Contratos de exportação
        'numero_ut' => 'número UT',
        'data_contrato' => 'data do contrato',
        'cliente_id' => 'cliente',
        'buyer_ref' => 'ref. do comprador',
        'qualidade_id' => 'qualidade',
        'tipo_cafe' => 'tipo de café',
        'certificado' => 'certificado',
        'quantidade_kg' => 'quantidade (kg)',
        'tipo_container' => 'tipo de container',
        'embalagem' => 'embalagem',
        'fixado' => 'contrato já fixado',
        'preco_fixado' => 'preço fixado',
        'preco_fixado_unidade' => 'unidade do preço fixado',
        'diferencial' => 'diferencial',
        'mes_fixacao' => 'mês de fixação',
        'embarque_mes' => 'mês de embarque',
        'incoterms' => 'incoterms',
        'porto' => 'porto',
        'remarks' => 'observações (remarks)',

        // Fixações (Tela NY)
        'contratos' => 'contratos a fixar',
        'contratos.*' => 'contrato',
        'corretora' => 'corretora',
        'broker_cliente' => 'broker do cliente',
        'tela' => 'tela (mês da bolsa)',
        'lotes' => 'lotes a fixar',
        'level' => 'level',
        'diferenciais' => 'diferencial dos contratos',
        'diferenciais.*' => 'diferencial',

        // Cadastros (clientes, qualidades, corretoras)
        'nome' => 'nome',
        'tipo' => 'tipo',
        'endereco' => 'endereço',
        'ref_padrao' => 'ref. padrão do comprador',
        'descricao' => 'descrição',

        // Usuários e senhas
        'name' => 'nome',
        'email' => 'e-mail',
        'role_id' => 'perfil',
        'active' => 'status da conta',
        'password' => 'senha',
        'password_confirmation' => 'confirmação da senha',
        'current_password' => 'senha atual',
        'token' => 'token de redefinição',
    ],
];
