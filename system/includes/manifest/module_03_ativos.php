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

        'ativos:fabricantes:manage'    => 'Gerenciar fabricantes/distribuidores',
        'ativos:modelos:manage'        => 'Gerenciar modelos de ativos',
        'ativos:listas:manage'         => 'Gerenciar listas/dropdowns',
        'ativos:depositos:manage'      => 'Gerenciar depósitos',

        // Regras / automação / técnicos
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
                ],
            ],
        ],

        // por enquanto nada de front; depois podemos fazer um "Portal do Ativo"
        'front' => [],
    ],

    // === Rotas para RBAC (SCRIPT_NAME) ===
    'routes' => [
        [ 'path' => '/modules/gestao_ativos/dashboard.php',                 'requires' => ['ativos:dashboard:view'] ],
        [ 'path' => '/modules/gestao_ativos/os-listar.php',                 'requires' => ['ativos:os:read'] ],
        [ 'path' => '/modules/gestao_ativos/oc-listar.php',                 'requires' => ['ativos:oc:read'] ],
        [ 'path' => '/modules/gestao_ativos/etiquetas-qr-listar.php',       'requires' => ['ativos:etiquetas:manage'] ],
        [ 'path' => '/modules/gestao_ativos/contratos-listar.php',          'requires' => ['ativos:contratos:read'] ],
        [ 'path' => '/modules/gestao_ativos/transferencias-listar.php',     'requires' => ['ativos:transferencias:read'] ],
        [ 'path' => '/modules/gestao_ativos/ativos-listar.php',             'requires' => ['ativos:ativos:read'] ],
        [ 'path' => '/modules/gestao_ativos/licencas-listar.php',           'requires' => ['ativos:licencas:read'] ],
        [ 'path' => '/modules/gestao_ativos/fabricantes-distribuidores-listar.php', 'requires' => ['ativos:fabricantes:manage'] ],
        [ 'path' => '/modules/gestao_ativos/modelos-listar.php',            'requires' => ['ativos:modelos:manage'] ],
        [ 'path' => '/modules/gestao_ativos/listas-dropdowns-form.php',     'requires' => ['ativos:listas:manage'] ],
        [ 'path' => '/modules/gestao_ativos/depositos-listar.php',          'requires' => ['ativos:depositos:manage'] ],
    ],

    // === Perfis padrão ===
    'role_defaults' => [
        'superadmin'    => ['*'],

        'admin_ativos'  => ['ativos:*'],

        'gestor_ativos' => [
            'ativos:dashboard:view',
            'ativos:os:*',
            'ativos:oc:*',
            'ativos:etiquetas:manage',
            'ativos:contratos:*',
            'ativos:transferencias:*',
            'ativos:ativos:*',
            'ativos:licencas:*',
            'ativos:fabricantes:manage',
            'ativos:modelos:manage',
            'ativos:listas:manage',
            'ativos:depositos:manage',
        ],

        'tecnico_ativos' => [
            'ativos:dashboard:view',
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
