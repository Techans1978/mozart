<?php
return [
    'slug'        => 'ativos',
    'name'        => 'Gestão de Ativos',
    'area'        => ['back' => true, 'front' => true],
    'version'     => '1.0.0',
    'description' => 'Cadastro, controle e rastreio de ativos.',

    'capabilities' => [
        'ativos:ativos:read'       => 'Ver ativos',
        'ativos:ativos:create'     => 'Criar ativos',
        'ativos:ativos:update'     => 'Editar ativos',
        'ativos:ativos:delete'     => 'Excluir ativos',
        'ativos:ativos:import'     => 'Importar ativos',
        'ativos:marcas:read'       => 'Ver marcas',
        'ativos:marcas:create'     => 'Criar marcas',
        'ativos:marcas:update'     => 'Editar marcas',
        'ativos:marcas:delete'     => 'Excluir marcas',
        'ativos:modelos:manage'    => 'Gerenciar modelos',
        'ativos:fornecedores:read' => 'Ver fornecedores de ativos',
    ],

    'menus' => [

        /**
         * Backend – Sidebar
         */
        [
            'id'           => 'ativos-root',
            'area'         => 'back',
            'position'     => 'sidebar',
            'label'        => 'Ativos',
            'icon'         => 'ti ti-devices',
            'url'          => '#',
            'order'        => 250,
            'capabilities' => ['ativos:ativos:read'],
            'children'     => [

                [
                    'id'           => 'ativos-listar',
                    'label'        => 'Listar Ativos',
                    'icon'         => 'ti ti-list-details',
                    'url'          => BASE_URL . '/modules/gestao_ativos/ativos-listar.php',
                    'order'        => 10,
                    'capabilities' => ['ativos:ativos:read'],
                    'children'     => [],
                ],

                [
                    'id'           => 'ativos-cadastrar',
                    'label'        => 'Cadastrar Ativo',
                    'icon'         => 'ti ti-plus',
                    'url'          => BASE_URL . '/modules/gestao_ativos/ativos-form.php',
                    'order'        => 20,
                    'capabilities' => ['ativos:ativos:create'],
                    'children'     => [],
                ],

                [
                    'id'           => 'ativos-importar',
                    'label'        => 'Importar Ativos',
                    'icon'         => 'ti ti-file-import',
                    'url'          => BASE_URL . '/modules/gestao_ativos/ativos-importar.php',
                    'order'        => 30,
                    'capabilities' => ['ativos:ativos:import'],
                    'children'     => [],
                ],

                // Divider
                [
                    'id'       => 'ativos-divider-cadastros',
                    'divider'  => true,
                    'order'    => 90,
                    'children' => [],
                ],

                [
                    'id'           => 'ativos-cadastros-root',
                    'label'        => 'Cadastros',
                    'icon'         => 'ti ti-database',
                    'url'          => '#',
                    'order'        => 100,
                    'capabilities' => ['ativos:marcas:read', 'ativos:modelos:manage', 'ativos:fornecedores:read'],
                    'children'     => [

                        [
                            'id'           => 'ativos-cad-marcas',
                            'label'        => 'Marcas',
                            'icon'         => 'ti ti-tags',
                            'url'          => BASE_URL . '/modules/gestao_ativos/cadastros/marcas.php',
                            'order'        => 10,
                            'capabilities' => ['ativos:marcas:read'],
                        ],

                        [
                            'id'           => 'ativos-cad-modelos',
                            'label'        => 'Modelos',
                            'icon'         => 'ti ti-devices-pc',
                            'url'          => BASE_URL . '/modules/gestao_ativos/cadastros/modelos.php',
                            'order'        => 20,
                            'capabilities' => ['ativos:modelos:manage'],
                        ],

                        [
                            'id'           => 'ativos-cad-fornecedores',
                            'label'        => 'Fornecedores',
                            'icon'         => 'ti ti-building-store',
                            'url'          => BASE_URL . '/modules/gestao_ativos/cadastros/fornecedores.php',
                            'order'        => 30,
                            'capabilities' => ['ativos:fornecedores:read'],
                        ],

                    ],
                ],
            ],
        ],

        /**
         * Frontend – Sidebar (Portal do Ativo)
         */
        [
            'id'           => 'portal-ativo-root',
            'area'         => 'front',
            'position'     => 'sidebar',
            'label'        => 'Portal do Ativo',
            'icon'         => 'ti ti-qrcode',
            'url'          => '#',
            'order'        => 400,
            'capabilities' => ['ativos:ativos:read'],
            'children'     => [

                [
                    'id'           => 'portal-ativo-meus-ativos',
                    'label'        => 'Meus Ativos',
                    'icon'         => 'ti ti-list-check',
                    'url'          => BASE_URL . '/modules/gestao_ativos/portal/meus_ativos.php',
                    'order'        => 10,
                    'capabilities' => ['ativos:ativos:read'],
                    'children'     => [],
                ],

            ],
        ],
    ],

    'routes' => [
        [ 'path' => '/modules/gestao_ativos/ativos-listar.php',          'requires' => ['ativos:ativos:read'] ],
        [ 'path' => '/modules/gestao_ativos/ativos-form.php',            'requires' => ['ativos:ativos:create'] ],
        [ 'path' => '/modules/gestao_ativos/ativos-importar.php',        'requires' => ['ativos:ativos:import'] ],
        [ 'path' => '/modules/gestao_ativos/cadastros/marcas.php',       'requires' => ['ativos:marcas:read'] ],
        [ 'path' => '/modules/gestao_ativos/cadastros/modelos.php',      'requires' => ['ativos:modelos:manage'] ],
        [ 'path' => '/modules/gestao_ativos/cadastros/fornecedores.php', 'requires' => ['ativos:fornecedores:read'] ],
    ],

    'role_defaults' => [
        'superadmin'    => ['*'],
        'admin_ativos'  => ['ativos:*'],
        'gestor_ativos' => [
            'ativos:ativos:*',
            'ativos:marcas:*',
            'ativos:modelos:manage',
            'ativos:fornecedores:read',
        ],
        'tecnico_ativos' => [
            'ativos:ativos:read',
            'ativos:ativos:create',
            'ativos:ativos:update',
            'ativos:ativos:import',
        ],
    ],
];
