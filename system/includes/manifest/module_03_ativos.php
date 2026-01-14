<?php
return [
    'slug'        => 'ativos',
    'name'        => 'Gestão de Ativos',
    'area'        => ['back' => true, 'front' => true],
    'version'     => '1.0.0',
    'description' => 'Cadastro, controle, ordens de serviço, contratos e rastreio de ativos.',

    // === Capabilities ===
    'capabilities' => [
        'ativos:dashboard:view'        => 'Ver dashboard de ativos',

        // Operação
        'ativos:desenho:read'          => 'Ver desenho de infraestrutura',
        'ativos:os:read'               => 'Ver ordens de serviço',
        'ativos:os:manage'             => 'Gerenciar ordens de serviço',
        'ativos:oc:read'               => 'Ver ordens de compra',
        'ativos:oc:manage'             => 'Gerenciar ordens de compra',
        'ativos:etiquetas:manage'      => 'Gerenciar etiquetas/QR',
        'ativos:contratos:read'        => 'Ver contratos',
        'ativos:contratos:manage'      => 'Gerenciar contratos',
        'ativos:transferencias:read'   => 'Ver transferências',
        'ativos:transferencias:manage' => 'Gerenciar transferências',

        // Cadastros principais
        'ativos:ativos:read'           => 'Ver ativos',
        'ativos:ativos:create'         => 'Criar ativos',
        'ativos:ativos:update'         => 'Editar ativos',
        'ativos:ativos:delete'         => 'Excluir ativos',
        'ativos:licencas:read'         => 'Ver licenças',
        'ativos:licencas:manage'       => 'Gerenciar licenças',

        'ativos:categorias:manage'     => 'Gerenciar categorias de ativos',
        'ativos:fabricantes:manage'    => 'Gerenciar fabricantes/distribuidores',
        'ativos:modelos:manage'        => 'Gerenciar modelos de ativos',
        'ativos:listas:manage'         => 'Gerenciar listas/dropdowns',
        'ativos:depositos:manage'      => 'Gerenciar depósitos',
        'ativos:tempos_servico:manage' => 'Gerenciar tempos de serviço',
        'ativos:margens:manage'        => 'Gerenciar margens de valor',

        // Regras / automação / técnicos
        'ativos:automacoes:manage'     => 'Gerenciar automações e regras',
        'ativos:tecnicos:manage'       => 'Gerenciar técnicos, filas e tempos',
    ],

    // === MENU lateral (backend) ===
    'menu' => [
        'back' => [
            [
                'label' => 'Gestão de Ativos',
                'icon'  => 'fa fa-wrench',
                'children' => [
                    [
                        'label'    => 'Dashboard',
                        'url'      => BASE_URL . '/modules/gestao_ativos/dashboard.php',
                        'requires' => ['ativos:dashboard:view'],
                    ],
                    [
                        'label'    => 'Desenho de Infra',
                        'url'      => BASE_URL . '/modules/gestao_ativos/desenho_listar.php',
                        'requires' => ['ativos:desenho:read'],
                    ],
                    [
                        'label'    => 'Ordem de Serviço',
                        'url'      => BASE_URL . '/modules/gestao_ativos/os-listar.php',
                        'requires' => ['ativos:os:read'],
                    ],
                    [
                        'label'    => 'Ordem de Compra',
                        'url'      => BASE_URL . '/modules/gestao_ativos/oc-listar.php',
                        'requires' => ['ativos:oc:read'],
                    ],
                    [
                        'label'    => 'Etiquetas',
                        'url'      => BASE_URL . '/modules/gestao_ativos/etiquetas-qr-listar.php',
                        'requires' => ['ativos:etiquetas:manage'],
                    ],
                    [
                        'label'    => 'Contratos',
                        'url'      => BASE_URL . '/modules/gestao_ativos/contratos-listar.php',
                        'requires' => ['ativos:contratos:read'],
                    ],
                    [
                        'label'    => 'Transferências',
                        'url'      => BASE_URL . '/modules/gestao_ativos/transferencias-listar.php',
                        'requires' => ['ativos:transferencias:read'],
                    ],
                    [
                        'label'    => 'Cadastro de Ativos',
                        'url'      => BASE_URL . '/modules/gestao_ativos/ativos-listar.php',
                        'requires' => ['ativos:ativos:read'],
                    ],
                    [
                        'label'    => 'Cadastro de Licenças',
                        'url'      => BASE_URL . '/modules/gestao_ativos/licencas-listar.php',
                        'requires' => ['ativos:licencas:read'],
                    ],
                    [
                        'label'    => 'Categorias de Ativos',
                        'url'      => BASE_URL . '/modules/gestao_ativos/categorias-listar.php',
                        'requires' => ['ativos:categorias:manage'],
                    ],
                    [
                        'label'    => 'Fabricantes / Distribuidores',
                        'url'      => BASE_URL . '/modules/gestao_ativos/fabricantes-distribuidores-listar.php',
                        'requires' => ['ativos:fabricantes:manage'],
                    ],
                    [
                        'label'    => 'Modelos',
                        'url'      => BASE_URL . '/modules/gestao_ativos/modelos-listar.php',
                        'requires' => ['ativos:modelos:manage'],
                    ],
                    [
                        'label'    => 'Listas',
                        'url'      => BASE_URL . '/modules/gestao_ativos/listas/listas-index.php',
                        'requires' => ['ativos:listas:manage'],
                    ],
                    [
                        'label'    => 'Depósitos',
                        'url'      => BASE_URL . '/modules/gestao_ativos/depositos-listar.php',
                        'requires' => ['ativos:depositos:manage'],
                    ],
                    [
                        'label'    => 'Tempo de Serviço',
                        'url'      => BASE_URL . '/modules/gestao_ativos/tempos-servico-listar.php',
                        'requires' => ['ativos:tempos_servico:manage'],
                    ],
                    [
                        'label'    => 'Margens de Valor',
                        // supondo que o arquivo correto seja .php (em vez de .hph)
                        'url'      => BASE_URL . '/modules/gestao_ativos/margens_listar.php',
                        'requires' => ['ativos:margens:manage'],
                    ],
                    [
                        'label'    => 'Automação & Regras',
                        'url'      => BASE_URL . '/modules/gestao_ativos/automacoes-listar.php',
                        'requires' => ['ativos:automacoes:manage'],
                    ],
                    [
                        'label'    => 'Técnico / Filas / Tempo',
                        'url'      => BASE_URL . '/modules/gestao_ativos/tecnicos-filas-listar.php',
                        'requires' => ['ativos:tecnicos:manage'],
                    ],
                ],
            ],
        ],

        // por enquanto nada de front; depois podemos fazer um "Portal do Ativo"
        'front' => [],
    ],

    // === Rotas para RBAC (SCRIPT_NAME) ===
    'routes' => [
        [ 'path' => '/modules/gestao_ativos/dashboard.php',                 'requires' => ['ativos:dashboard:view'] ],
        [ 'path' => '/modules/gestao_ativos/desenho_listar.php',            'requires' => ['ativos:desenho:read'] ],
        [ 'path' => '/modules/gestao_ativos/os-listar.php',                 'requires' => ['ativos:os:read'] ],
        [ 'path' => '/modules/gestao_ativos/oc-listar.php',                 'requires' => ['ativos:oc:read'] ],
        [ 'path' => '/modules/gestao_ativos/etiquetas-qr-listar.php',       'requires' => ['ativos:etiquetas:manage'] ],
        [ 'path' => '/modules/gestao_ativos/contratos-listar.php',          'requires' => ['ativos:contratos:read'] ],
        [ 'path' => '/modules/gestao_ativos/transferencias-listar.php',     'requires' => ['ativos:transferencias:read'] ],
        [ 'path' => '/modules/gestao_ativos/ativos-listar.php',             'requires' => ['ativos:ativos:read'] ],
        [ 'path' => '/modules/gestao_ativos/licencas-listar.php',           'requires' => ['ativos:licencas:read'] ],
        [ 'path' => '/modules/gestao_ativos/categorias-listar.php',         'requires' => ['ativos:categorias:manage'] ],
        [ 'path' => '/modules/gestao_ativos/fabricantes-distribuidores-listar.php', 'requires' => ['ativos:fabricantes:manage'] ],
        [ 'path' => '/modules/gestao_ativos/modelos-listar.php',            'requires' => ['ativos:modelos:manage'] ],
        [ 'path' => '/modules/gestao_ativos/listas-dropdowns-form.php',     'requires' => ['ativos:listas:manage'] ],
        [ 'path' => '/modules/gestao_ativos/depositos-listar.php',          'requires' => ['ativos:depositos:manage'] ],
        [ 'path' => '/modules/gestao_ativos/tempos-servico-listar.php',     'requires' => ['ativos:tempos_servico:manage'] ],
        [ 'path' => '/modules/gestao_ativos/margens_listar.php',            'requires' => ['ativos:margens:manage'] ],
        [ 'path' => '/modules/gestao_ativos/automacoes-listar.php',         'requires' => ['ativos:automacoes:manage'] ],
        [ 'path' => '/modules/gestao_ativos/tecnicos-filas-listar.php',     'requires' => ['ativos:tecnicos:manage'] ],
    ],

    // === Perfis padrão ===
    'role_defaults' => [
        'superadmin'    => ['*'],

        'admin_ativos'  => ['ativos:*'],

        'gestor_ativos' => [
            'ativos:dashboard:view',
            'ativos:desenho:read',
            'ativos:os:*',
            'ativos:oc:*',
            'ativos:etiquetas:manage',
            'ativos:contratos:*',
            'ativos:transferencias:*',
            'ativos:ativos:*',
            'ativos:licencas:*',
            'ativos:categorias:manage',
            'ativos:fabricantes:manage',
            'ativos:modelos:manage',
            'ativos:listas:manage',
            'ativos:depositos:manage',
            'ativos:tempos_servico:manage',
            'ativos:margens:manage',
            'ativos:automacoes:manage',
            'ativos:tecnicos:manage',
        ],

        'tecnico_ativos' => [
            'ativos:dashboard:view',
            'ativos:desenho:read',
            'ativos:os:read',
            'ativos:os:manage',
            'ativos:oc:read',
            'ativos:ativos:read',
            'ativos:ativos:create',
            'ativos:ativos:update',
            'ativos:licencas:read',
            'ativos:transferencias:read',
        ],
    ],
];
