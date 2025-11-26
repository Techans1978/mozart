<?php
// Mostrar erros (opcional, só pra dev)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';

// Agora pode usar ROOT_PATH e BASE_URL para includes e links
require_once ROOT_PATH . '/system/includes/autenticacao.php';
?>

<?php
    include_once ROOT_PATH . '/system/includes/head.php';
    include_once ROOT_PATH . '/system/includes/navbar.php';
?>

            <div id="page-wrapper">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-lg-12">
                            <h1 class="page-header"><?= APP_NAME ?></h1>
                        </div>
                        <!-- /.col-lg-12 -->
                    </div>
                    <!-- /.row -->
                    <div class="row">
                        <div class="col-lg-3 col-md-6">
                            <div class="panel panel-primary">
                                <div class="panel-heading">
                                    <div class="row">
                                        <div class="col-xs-3">
                                            <i class="fa fa-comment fa-5x"></i>
                                        </div>
                                        <div class="col-xs-9 text-right">
                                            <div class="huge" id="painel-novos"></div>
                                            <div>Novos!</div>
                                        </div>
                                    </div>
                                </div>
                                <a href="<?= BASE_URL ?>/pages/listagem_chamados.php?status=novos">
                                    <div class="panel-footer">
                                        <span class="pull-left">Ver detalhes</span>
                                        <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>

                                        <div class="clearfix"></div>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="panel panel-green">
                                <div class="panel-heading">
                                    <div class="row">
                                        <div class="col-xs-3">
                                            <i class="fa fa-commenting fa-5x"></i>
                                        </div>
                                        <div class="col-xs-9 text-right">
                                            <div class="huge" id="painel-atribuidos"></div>
                                            <div>Atribuídos!</div>
                                        </div>
                                    </div>
                                </div>
                                <a href="<?= BASE_URL ?>/pages/listagem_chamados.php?status=atribuidos">
                                    <div class="panel-footer">
                                        <span class="pull-left">Ver detalhes</span>
                                        <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>

                                        <div class="clearfix"></div>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="panel panel-yellow">
                                <div class="panel-heading">
                                    <div class="row">
                                        <div class="col-xs-3">
                                            <i class="fa fa-check fa-5x"></i>
                                        </div>
                                        <div class="col-xs-9 text-right">
                                            <div class="huge" id="painel-solucionados"></div>
                                            <div>Solucionados!</div>
                                        </div>
                                    </div>
                                </div>
                                <a href="<?= BASE_URL ?>/pages/listagem_chamados.php?status=solucionados">
                                    <div class="panel-footer">
                                        <span class="pull-left">Ver detalhes</span>
                                        <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>

                                        <div class="clearfix"></div>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="panel panel-red">
                                <div class="panel-heading">
                                    <div class="row">
                                        <div class="col-xs-3">
                                            <i class="fa fa-clock-o fa-5x"></i>
                                        </div>
                                        <div class="col-xs-9 text-right">
                                            <div class="huge" id="painel-atrasados"></div>
                                            <div>Atrasados (+7d)!</div>
                                        </div>
                                    </div>
                                </div>
                                <a href="<?= BASE_URL ?>/pages/listagem_chamados.php?status=atrasados">
                                    <div class="panel-footer">
                                        <span class="pull-left">Ver detalhes</span>
                                        <span class="pull-right"><i class="fa fa-arrow-circle-right"></i></span>

                                        <div class="clearfix"></div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                    <!-- /.row -->
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <i class="fa fa-bar-chart-o fa-fw"></i> Gráfico de Chamados por Categoria
                                    <div class="pull-right">
                                    </div>
                                </div>
                                <!-- /.panel-heading -->
                                <div class="panel-body">
                                    <div id="morris-area-chart"><canvas id="graficoCategorias" width="100%" height="40"></canvas></div>
                                </div>
                                <!-- /.panel-body -->
                            </div>
                            <!-- /.panel -->
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <i class="fa fa-bar-chart-o fa-fw"></i> Chamados por Categoria
                                    <div class="pull-right">
                                    </div>
                                </div>
                                <!-- /.panel-heading -->
                                <div class="panel-body">
                                    <div class="row">
                                        <div class="col-lg-12">


                                        <div class="table-responsive">
                                            <table id="tabela-categorias" class="table table-bordered table-hover table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Categoria</th>
                                                        <th>Novos</th>
                                                        <th>Atribuídos</th>
                                                        <th>Solucionados</th>
                                                        <th>Atrasados</th>
                                                        <th>Fechados</th>
                                                        <th>Ação</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <!-- Preenchido via JS -->
                                                </tbody>
                                            </table>
                                        </div>



                                            <!-- /.table-responsive -->
                                        </div>
                                        <!-- /.col-lg-4 (nested) -->
                                        <div class="col-lg-8">
                                            <div id="morris-bar-chart"></div>
                                        </div>
                                        <!-- /.col-lg-8 (nested) -->
                                    </div>
                                    <!-- /.row -->
                                </div>
                                <!-- /.panel-body -->
                            </div>
                            <!-- /.panel -->
                        </div>
                        <!-- /.col-lg-8 -->
                        <div class="col-lg-4">
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <i class="fa fa-bell fa-fw"></i> Total Chamados
                                </div>
                                <!-- /.panel-heading -->
                                <div class="panel-body">
                                    <div class="list-group">
                                        <a href="#" class="list-group-item">
                                            <i class="fa fa-comment fa-fw"></i> Financeiro
                                            <span class="pull-right text-muted small"><em>0</em>
                                            </span>
                                        </a>
                                        <a href="#" class="list-group-item">
                                            <i class="fa fa-twitter fa-fw"></i> Fluig
                                            <span class="pull-right text-muted small"><em>0</em>
                                            </span>
                                        </a>
                                        <a href="#" class="list-group-item">
                                            <i class="fa fa-envelope fa-fw"></i> Infraestrutura
                                            <span class="pull-right text-muted small"><em>0</em>
                                            </span>
                                        </a>
                                        <a href="#" class="list-group-item">
                                            <i class="fa fa-tasks fa-fw"></i> Inteligência Comercial
                                            <span class="pull-right text-muted small"><em>0</em>
                                            </span>
                                        </a>
                                        <a href="#" class="list-group-item">
                                            <i class="fa fa-upload fa-fw"></i> Manutenção
                                            <span class="pull-right text-muted small"><em>0</em>
                                            </span>
                                        </a>
                                        <a href="#" class="list-group-item">
                                            <i class="fa fa-bolt fa-fw"></i> Marketing
                                            <span class="pull-right text-muted small"><em>0</em>
                                            </span>
                                        </a>
                                        <a href="#" class="list-group-item">
                                            <i class="fa fa-warning fa-fw"></i> Prevenção
                                            <span class="pull-right text-muted small"><em>0</em>
                                            </span>
                                        </a>
                                        <a href="#" class="list-group-item">
                                            <i class="fa fa-shopping-cart fa-fw"></i> Sistemas e digital
                                            <span class="pull-right text-muted small"><em>0</em>
                                            </span>
                                        </a>
                                        <a href="#" class="list-group-item">
                                            <i class="fa fa-money fa-fw"></i> Telefonia
                                            <span class="pull-right text-muted small"><em>0</em>
                                            </span>
                                        </a>
                                    </div>
                                    <!-- /.list-group -->
                                    <a href="#" class="btn btn-default btn-block">Filtros</a>
                                </div>
                                <!-- /.panel-body -->
                            </div>
                            <!-- /.panel -->
                        </div>
                        <!-- /.col-lg-4 -->
                    </div>
                    <!-- /.row -->
                </div>
                <!-- /.container-fluid -->
            </div>
            <!-- /#page-wrapper -->

        </div>
        <!-- /#wrapper -->


<?php include_once ROOT_PATH . '/system/includes/code_footer.php'; ?>
    <script>
        function carregarContadoresCategorias() {
    console.log("Chamando o PHP...");

    $.getJSON("<?= BASE_URL ?>/config/contadores_por_categoria.php")
        .done(function (data) {
            console.log("Dados recebidos:", data);
            const tbody = $("#tabela-categorias tbody");
            tbody.empty();

            data.forEach(function (item) {
                const linha = `
                    <tr>
                        <td>${item.categoria}</td>
                        <td>${item.novos}</td>
                        <td>${item.atribuidos}</td>
                        <td>${item.solucionados}</td>
                        <td>${item.atrasados}</td>
                        <td>${item.fechados}</td>
                        <td><a href="<?= BASE_URL ?>/pages/listagem_categorias.php?categoria=${encodeURIComponent(item.categoria)}" class="btn btn-primary btn-xs">Ver</a></td>
                    </tr>
                `;
                tbody.append(linha);
            });
        })
        .fail(function (jqxhr, textStatus, error) {
            console.error("Erro ao carregar categorias:", textStatus, error);
            console.error("Resposta do servidor:", jqxhr.responseText);
            alert("Erro ao carregar categorias.");
        });
}

   </script>

    <script>
        $(document).ready(function () {
            carregarContadoresCategorias();
        });
    </script>
<?php include_once ROOT_PATH . '/system/includes/footer.php'; ?>