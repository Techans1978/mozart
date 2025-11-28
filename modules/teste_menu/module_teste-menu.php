<?php
return [
    'slug'        => 'teste_menu',
    'name'        => 'Módulo de Teste de Menu',
    'area'        => ['back' => true, 'front' => false],
    'version'     => '0.1.0',
    'description' => 'Apenas para testar o novo sistema de menus.',

    'capabilities' => [
        'teste:menu:ver' => 'Ver menu de teste',
    ],

    'menus' => [
        [
            'id'           => 'teste-root',
            'area'         => 'back',
            'position'     => 'sidebar',
            'label'        => 'Menu Teste',
            'icon'         => 'fa fa-flask',
            'url'          => '#',
            'order'        => 900,
            'capabilities' => ['teste:menu:ver'],
            'children'     => [
                [
                    'id'           => 'teste-item-1',
                    'label'        => 'Item de Teste 1',
                    'icon'         => 'fa fa-star',
                    'url'          => BASE_URL . '/pages/pagina_teste.php',
                    'order'        => 10,
                    'capabilities' => ['teste:menu:ver'],
                    'children'     => [],
                ],
                [
                    'id'           => 'teste-item-2',
                    'label'        => 'Item de Teste 2',
                    'icon'         => 'fa fa-star-o',
                    'url'          => BASE_URL . '/pages/pagina_teste2.php',
                    'order'        => 20,
                    'capabilities' => ['teste:menu:ver'],
                    'children'     => [],
                ],
            ],
        ],
    ],

    'routes' => [],
    'role_defaults' => [],
];
