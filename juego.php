<?php
// Iniciar sesión
session_start();

// Verificar si el usuario está logueado
if(!isset($_SESSION['usuario_id'])) {
    // Redireccionar a la página principal si no está logueado
    header("Location: index.php");
    exit;
}

// Incluir archivos necesarios
require_once 'database.php';
require_once 'clases/juego.php';
require_once 'clases/ranking-implementation.php';

// Obtener conexión a la base de datos
$database = new Database();
$db = $database->getConnection();

// Variables para controlar el estado del juego
$partida_id = isset($_SESSION['partida_id']) ? $_SESSION['partida_id'] : null;
$jugadas_restantes = isset($_SESSION['jugadas_restantes']) ? $_SESSION['jugadas_restantes'] : 5;
$monedas_actuales = isset($_SESSION['monedas_actuales']) ? $_SESSION['monedas_actuales'] : 5;
$mensaje = '';
$tipo_mensaje = '';
$resultado = null;
$ganancia = 0;

// Obtener configuración del juego
$config_query = "SELECT * FROM Configuracion WHERE config_id = 1";
$config_stmt = $db->prepare($config_query);
$config_stmt->execute();
$config = $config_stmt->fetch(PDO::FETCH_ASSOC);

$max_jugadas = $config['max_jugadas_por_partida'];
$monedas_iniciales = $config['max_monedas_iniciales'];
$multiplicador = $config['multiplicador_ganancia'];

// Iniciar partida si no hay una en curso
if($partida_id === null) {
    $partida = new Partida($db);
    $partida->usuario_id = $_SESSION['usuario_id'];
    
    if($partida->iniciar()) {
        $partida_id = $partida->partida_id;
        $_SESSION['partida_id'] = $partida_id;
        $_SESSION['jugadas_restantes'] = $max_jugadas;
        $_SESSION['monedas_actuales'] = $monedas_iniciales;
        $jugadas_restantes = $max_jugadas;
        $monedas_actuales = $monedas_iniciales;
    } else {
        $mensaje = "Error al iniciar la partida";
        $tipo_mensaje = "error";
    }
}

// Historial de apuestas de la partida actual
$apuesta = new Apuesta($db);
$apuesta->partida_id = $partida_id;
$historial_stmt = $apuesta->obtenerApuestasDePartida();

// Variable para enviar datos JSON a JavaScript
$respuesta_json = null;

// MODIFICADO: Procesar apuesta CON RESULTADO PREDETERMINADO via AJAX
if(isset($_POST['action']) && $_POST['action'] == 'guardar_apuesta') {
    header('Content-Type: application/json');
    
    // Obtener datos del formulario
    $tipo_apuesta = isset($_POST['tipo_apuesta']) ? $_POST['tipo_apuesta'] : 'numero';
    $numero_apostado = isset($_POST['numero']) ? $_POST['numero'] : '';
    $cantidad_apostada = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 0;
    $numero_resultado = isset($_POST['numero_resultado']) ? $_POST['numero_resultado'] : '';
    
    // Convertir 00 a 37 para la base de datos
    if($numero_apostado === '00') {
        $numero_apostado = 37;
    } else if($numero_apostado !== '') {
        $numero_apostado = (int)$numero_apostado;
    }
    
    if($numero_resultado === '00') {
        $numero_resultado = 37;
    } else {
        $numero_resultado = (int)$numero_resultado;
    }
    
    $respuesta = [
        'success' => false,
        'mensaje' => '',
        'ganancia' => 0,
        'monedas_actuales' => $monedas_actuales,
        'jugadas_restantes' => $jugadas_restantes
    ];
    
    // Validar la apuesta según el tipo
    $apuesta_valida = true;
    if($tipo_apuesta === 'numero') {
        if($numero_apostado === '' || $numero_apostado < 0 || ($numero_apostado > 36 && $numero_apostado != 37)) {
            $respuesta['mensaje'] = "Número apostado no válido";
            $apuesta_valida = false;
        }
    } else if(!in_array($tipo_apuesta, ['par', 'impar', 'rojo', 'negro'])) {
        $respuesta['mensaje'] = "Tipo de apuesta no válido";
        $apuesta_valida = false;
    }
    
    if($apuesta_valida && ($numero_resultado < 0 || ($numero_resultado > 36 && $numero_resultado != 37))) {
        $respuesta['mensaje'] = "Número resultado no válido";
        $apuesta_valida = false;
    } else if($apuesta_valida && ($cantidad_apostada <= 0 || $cantidad_apostada > $monedas_actuales)) {
        $respuesta['mensaje'] = "Cantidad no válida";
        $apuesta_valida = false;
    } else if($apuesta_valida && $jugadas_restantes <= 0) {
        $respuesta['mensaje'] = "No tienes jugadas restantes";
        $apuesta_valida = false;
    }
    
    if($apuesta_valida) {
        // Configurar la apuesta
        $apuesta = new Apuesta($db);
        $apuesta->partida_id = $partida_id;
        $apuesta->numero_apostado = ($tipo_apuesta === 'numero') ? $numero_apostado : null;
        $apuesta->cantidad_apostada = $cantidad_apostada;
        
        // Registrar la apuesta CON RESULTADO
        if($apuesta->registrarConResultado($numero_resultado, $tipo_apuesta)) {
            // Calcular ganancia
            $ganancia = $apuesta->ganancia;
            
            // Actualizar estado del juego
            $monedas_actuales = $monedas_actuales - $cantidad_apostada + $ganancia;
            $jugadas_restantes--;
            
            // Actualizar variables de sesión
            $_SESSION['monedas_actuales'] = $monedas_actuales;
            $_SESSION['jugadas_restantes'] = $jugadas_restantes;
            
            $respuesta['success'] = true;
            $respuesta['ganancia'] = $ganancia;
            $respuesta['monedas_actuales'] = $monedas_actuales;
            $respuesta['jugadas_restantes'] = $jugadas_restantes;
            
            if($ganancia > 0) {
                $respuesta['mensaje'] = "¡Felicidades! Has ganado {$ganancia} monedas";
            } else {
                $respuesta['mensaje'] = "Lo siento, has perdido tu apuesta";
            }
        } else {
            $respuesta['mensaje'] = "Error al procesar la apuesta";
        }
    }
    
    echo json_encode($respuesta);
    exit;
}

// Finalizar partida si no quedan jugadas o por solicitud del usuario
if(($jugadas_restantes <= 0 || (isset($_POST['action']) && $_POST['action'] == 'finalizar')) && $partida_id !== null) {
    $partida = new Partida($db);
    $partida->partida_id = $partida_id;
    
    if($partida->finalizar()) {
        $mensaje = "Partida finalizada. Tu puntaje final es: {$monedas_actuales}";
        $tipo_mensaje = "info";
        
        // Limpiar variables de sesión de la partida
        unset($_SESSION['partida_id']);
        unset($_SESSION['jugadas_restantes']);
        unset($_SESSION['monedas_actuales']);
        
        // Redireccionar a la página de ranking
        header("Location: ranking.php");
        exit;
    } else {
        $mensaje = "Error al finalizar la partida";
        $tipo_mensaje = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ruleta Americana - Juego</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/style_ruleta.css" type="text/css">
    <link rel="stylesheet" href="css/style_juegoruleta.css" type="text/css">
</head>

<body>
    <!-- Barra de navegación -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-1">
        <div class="container-ruleta">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-dice me-2"></i>Ruleta Americana
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="juego.php">Jugar</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ranking.php">Ranking</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        <i class="fas fa-user me-1"></i> Hola,
                        <?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?>
                    </span>
                    <a href="index.php?action=logout" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-sign-out-alt me-1"></i> Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Contenido principal -->
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <!-- Información del juego actual -->
                <div class="info-panel d-flex justify-content-between mb-1">
                    <div>
                        <h5>Jugadas restantes: <span class="badge bg-light text-dark"
                                id="jugadas-restantes"><?php echo $jugadas_restantes; ?></span></h5>
                    </div>
                    <div>
                        <h5>Monedas: <span class="badge bg-warning text-dark"
                                id="monedas-display"><?php echo $monedas_actuales; ?></span></h5>
                    </div>
                </div>
                <!-- Contenedor para mensajes dinámicos -->
                <div id="mensaje-container"></div>

                <?php if($mensaje): ?>
                <div class="alert alert-<?php 
                        echo $tipo_mensaje === 'error' ? 'danger' : 
                            ($tipo_mensaje === 'success' ? 'success' : 
                            ($tipo_mensaje === 'warning' ? 'warning' : 'info')); 
                    ?> alert-dismissible fade show" role="alert">
                    <?php echo $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if($jugadas_restantes > 0): ?>
                <div class="container-ruleta col-12 col-lg-5 ">
                    <div class="ruleta-section">
                        <h1>RULETA AMERICANA</h1>
                        <div class="ruleta-container">
                            <div class="indicador"></div>
                            <div class="ruleta" id="ruleta">
                                <div class="centro"></div>
                            </div>
                        </div>
                    </div>

                    <div class="controles col-12 col-lg-7">
                        <div class="apuesta-actual">
                            <p><strong>Apuesta seleccionada:</strong> <span id="apuestaTexto">Ninguna</span></p>
                        </div>

                        <div class="contenedor-flex d-flex flex-column flex-lg-row w-100">
                            <div class="panel-numeros col-12 col-lg-6">
                                <h3>Selecciona un número:</h3>

                                <div class="numeros-especiales">
                                    <button class="btn-numero verde" data-numero="0" data-color="verde">0</button>
                                    <button class="btn-numero verde" data-numero="00" data-color="verde">00</button>
                                </div>

                                <div class="numeros-grid" id="numerosGrid">
                                    <!-- Los números se generarán con JavaScript -->
                                </div>
                            </div>

                            <div class="apuestas col-12 col-lg-6">
                                <h3 class="col-12">O apuesta a:</h3>
                                <button class="btn-apuesta" data-tipo="par">PAR</button>
                                <button class="btn-apuesta" data-tipo="impar">IMPAR</button>
                                <button class="btn-apuesta color-rojo" data-tipo="rojo">ROJO</button>
                                <button class="btn-apuesta color-negro" data-tipo="negro">NEGRO</button>

                                <div class="apuesta-form">
                                    <h3>Cantidad a apostar:</h3>
                                    <input type="number" class="form-control monedas-input" id="cantidad"
                                        name="cantidad" min="1" max="<?php echo $monedas_actuales; ?>" value="1"
                                        required>
                                    <div class="form-text" style="color: #aaa;">Máximo: <span
                                            id="max-monedas"><?php echo $monedas_actuales; ?></span> monedas</div>
                                </div>

                                <div style="padding: 0.5rem;" class="col-12">
                                    <button type="button" class="btn-girar" id="btnGirar">APOSTAR</button>
                                </div>

                                <div class="resultado" id="resultado" style="display: none;">
                                    <p id="numeroGanador"></p>
                                    <p id="resultadoApuesta"></p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="text-center finalizar-btn mt-4">
                    <form action="juego.php" method="post">
                        <input type="hidden" name="action" value="finalizar">
                        <button type="submit" class="btn btn-outline-light py-3 px-5">
                            <i class="fas fa-flag-checkered me-2"></i>Finalizar Partida
                        </button>
                    </form>
                </div>

                <?php else: ?>
                <div class="alert alert-warning">
                    <h4 class="alert-heading">¡Partida terminada!</h4>
                    <p>Has completado todas tus jugadas. Puntaje final: <?php echo $monedas_actuales; ?> monedas.</p>
                    <hr>
                    <form action="juego.php" method="post">
                        <input type="hidden" name="action" value="finalizar">
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-trophy me-2"></i>Ver Ranking
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

            </div>

            <div class="d-flex flex-column flex-lg-row col-12">
                <!-- Historial de apuestas -->
                <div class="card mt-4 col-12 col-lg-6 p-2">
                    <div class="card-header bg-dark">
                        <h3 class="mb-0">Historial de Apuestas</h3>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Apuesta</th>
                                    <th>Resultado</th>
                                    <th>Monedas</th>
                                </tr>
                            </thead>
                            <tbody id="historial-tbody">
                                <?php 
                    $apuestas = [];
                    while($row = $historial_stmt->fetch(PDO::FETCH_ASSOC)) {
                        $apuestas[] = $row;
                    }
                    
                    $apuestas = array_reverse($apuestas);
                    
                    foreach($apuestas as $row) {
                        $tipo_apuesta = isset($row['tipo_apuesta']) ? $row['tipo_apuesta'] : 'numero';
                        $apuesta_texto = $tipo_apuesta === 'numero'
                            ? ($row['numero_apostado'] == 37 ? '00' : $row['numero_apostado'])
                            : strtoupper($tipo_apuesta);
                        $resultado = $row['numero_resultado'] == 37 ? '00' : $row['numero_resultado'];
                        $ganancia_class = $row['ganancia'] > 0 ? 'text-success' : 'text-danger';
                        $ganancia_texto = $row['ganancia'] > 0 ? "+{$row['ganancia']}" : "-{$row['cantidad_apostada']}";
                        
                        echo "<tr><td>{$apuesta_texto}</td><td>{$resultado}</td><td class='{$ganancia_class}'>{$ganancia_texto}</td></tr>";
                    }

                    if(empty($apuestas)) {
                        echo "<tr><td colspan='3' class='text-center'>No hay apuestas registradas</td></tr>";
                    }
                    ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Información del juego -->
                <div class="card mt-4 col-12 col-lg-6 p-2">
                    <div class="card-header bg-dark">
                        <h3 class="mb-0">Información</h3>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush bg-transparent">
                            <li class="list-group-item bg-transparent border-dark">
                                <i class="fas fa-info-circle me-2 text-info"></i>
                                Tienes <?php echo $max_jugadas; ?> jugadas por partida
                            </li>
                            <li class="list-group-item bg-transparent border-dark">
                                <i class="fas fa-coins me-2 text-warning"></i>
                                Ganancia número específico: x<?php echo $multiplicador; ?>
                            </li>
                            <li class="list-group-item bg-transparent border-dark">
                                <i class="fas fa-palette me-2 text-danger"></i>
                                Ganancia color (rojo/negro): x2
                            </li>
                            <li class="list-group-item bg-transparent border-dark">
                                <i class="fas fa-divide me-2 text-success"></i>
                                Ganancia par/impar: x2
                            </li>
                            <li class="list-group-item bg-transparent border-dark">
                                <i class="fas fa-random me-2 text-primary"></i>
                                Números: 0-36 y 00 (0 y 00 no cuentan para color/paridad)
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Scripts de Bootstrap y JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Configuración de la ruleta con los números y sus posiciones
    const numerosRuleta = [{
            numero: '0',
            grado: 0,
            color: 'verde'
        },
        {
            numero: '28',
            grado: 9.47,
            color: 'negro'
        },
        {
            numero: '9',
            grado: 18.95,
            color: 'rojo'
        },
        {
            numero: '26',
            grado: 28.42,
            color: 'negro'
        },
        {
            numero: '30',
            grado: 37.89,
            color: 'rojo'
        },
        {
            numero: '11',
            grado: 47.37,
            color: 'negro'
        },
        {
            numero: '7',
            grado: 56.84,
            color: 'rojo'
        },
        {
            numero: '20',
            grado: 66.32,
            color: 'negro'
        },
        {
            numero: '32',
            grado: 75.79,
            color: 'rojo'
        },
        {
            numero: '17',
            grado: 85.26,
            color: 'negro'
        },
        {
            numero: '5',
            grado: 94.74,
            color: 'rojo'
        },
        {
            numero: '22',
            grado: 104.21,
            color: 'negro'
        },
        {
            numero: '34',
            grado: 113.68,
            color: 'rojo'
        },
        {
            numero: '15',
            grado: 123.16,
            color: 'negro'
        },
        {
            numero: '3',
            grado: 132.63,
            color: 'rojo'
        },
        {
            numero: '24',
            grado: 142.11,
            color: 'negro'
        },
        {
            numero: '36',
            grado: 151.58,
            color: 'rojo'
        },
        {
            numero: '13',
            grado: 161.05,
            color: 'negro'
        },
        {
            numero: '1',
            grado: 170.53,
            color: 'rojo'
        },
        {
            numero: '00',
            grado: 180.00,
            color: 'verde'
        },
        {
            numero: '27',
            grado: 189.47,
            color: 'rojo'
        },
        {
            numero: '10',
            grado: 198.95,
            color: 'negro'
        },
        {
            numero: '25',
            grado: 208.42,
            color: 'rojo'
        },
        {
            numero: '29',
            grado: 217.89,
            color: 'negro'
        },
        {
            numero: '12',
            grado: 227.37,
            color: 'rojo'
        },
        {
            numero: '8',
            grado: 236.84,
            color: 'negro'
        },
        {
            numero: '19',
            grado: 246.32,
            color: 'rojo'
        },
        {
            numero: '31',
            grado: 255.79,
            color: 'negro'
        },
        {
            numero: '18',
            grado: 265.26,
            color: 'rojo'
        },
        {
            numero: '6',
            grado: 274.74,
            color: 'negro'
        },
        {
            numero: '21',
            grado: 284.21,
            color: 'rojo'
        },
        {
            numero: '33',
            grado: 293.68,
            color: 'negro'
        },
        {
            numero: '16',
            grado: 303.16,
            color: 'rojo'
        },
        {
            numero: '4',
            grado: 312.63,
            color: 'negro'
        },
        {
            numero: '23',
            grado: 322.11,
            color: 'rojo'
        },
        {
            numero: '35',
            grado: 331.58,
            color: 'negro'
        },
        {
            numero: '14',
            grado: 341.05,
            color: 'rojo'
        },
        {
            numero: '2',
            grado: 350.53,
            color: 'negro'
        }
    ];

    // Números rojos y negros
    const numerosRojos = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
    const numerosNegros = [2, 4, 6, 8, 10, 11, 13, 15, 17, 20, 22, 24, 26, 28, 29, 31, 33, 35];

    let apuestaSeleccionada = null;
    let girando = false;

    // Variables globales para el estado del juego
    let monedasActuales = <?php echo $monedas_actuales; ?>;
    let jugadasRestantes = <?php echo $jugadas_restantes; ?>;
    const multiplicadorNumero = <?php echo $multiplicador; ?>;
    const multiplicadorColor = 1.5;
    const multiplicadorParidad = 1.5;

    // Crear los números en la ruleta (solo visualización)
    function crearRuleta() {
        const ruleta = document.getElementById('ruleta');

        numerosRuleta.forEach((item) => {
            const numeroDiv = document.createElement('div');
            numeroDiv.className = `numero-ruleta ${item.color}`;
            numeroDiv.textContent = item.numero;

            // Calcular posición
            const angulo = item.grado * Math.PI / 180;
            const radio = 220;
            const x = Math.cos(angulo - Math.PI / 2) * radio + 220;
            const y = Math.sin(angulo - Math.PI / 2) * radio + 220;

            numeroDiv.style.left = `${x}px`;
            numeroDiv.style.top = `${y}px`;
            numeroDiv.style.transform = `translate(-50%, -50%) rotate(${item.grado}deg)`;

            ruleta.appendChild(numeroDiv);
        });
    }

    // Crear panel de números para apostar
    function crearPanelNumeros() {
        const grid = document.getElementById('numerosGrid');

        for (let i = 1; i <= 36; i++) {
            const btn = document.createElement('button');
            btn.className = 'btn-numero';
            btn.textContent = i;
            btn.dataset.numero = i;

            if (numerosRojos.includes(i)) {
                btn.classList.add('rojo');
                btn.dataset.color = 'rojo';
            } else {
                btn.classList.add('negro');
                btn.dataset.color = 'negro';
            }

            btn.addEventListener('click', seleccionarNumero);
            grid.appendChild(btn);
        }
    }

    // Seleccionar número
    function seleccionarNumero(e) {
        document.querySelectorAll('.btn-numero').forEach(b => b.classList.remove('selected'));
        document.querySelectorAll('.btn-apuesta').forEach(b => b.classList.remove('selected'));

        e.target.classList.add('selected');
        const numero = e.target.dataset.numero;
        const color = e.target.dataset.color;
        apuestaSeleccionada = {
            tipo: 'numero',
            valor: numero,
            color: color
        };
        actualizarApuestaTexto();
    }

    // Seleccionar tipo de apuesta
    function seleccionarTipoApuesta(e) {
        document.querySelectorAll('.btn-numero').forEach(b => b.classList.remove('selected'));
        document.querySelectorAll('.btn-apuesta').forEach(b => b.classList.remove('selected'));

        e.target.classList.add('selected');
        apuestaSeleccionada = {
            tipo: e.target.dataset.tipo
        };
        actualizarApuestaTexto();
    }

    // Actualizar texto de apuesta
    function actualizarApuestaTexto() {
        const texto = document.getElementById('apuestaTexto');
        if (apuestaSeleccionada) {
            if (apuestaSeleccionada.tipo === 'numero') {
                texto.textContent = `Número ${apuestaSeleccionada.valor} (${apuestaSeleccionada.color})`;
            } else {
                texto.textContent = apuestaSeleccionada.tipo.toUpperCase();
            }
        }
    }

    // Función para mostrar mensajes
    function mostrarMensaje(mensaje, tipo) {
        const container = document.getElementById('mensaje-container');
        const alertClass = tipo === 'error' ? 'danger' : (tipo === 'success' ? 'success' : 'warning');

        const alertHtml = `
            <div class="alert alert-${alertClass} alert-dismissible fade show" role="alert">
                ${mensaje}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;

        container.innerHTML = alertHtml;

        // Auto-ocultar después de 5 segundos
        setTimeout(() => {
            const alert = container.querySelector('.alert');
            if (alert) {
                alert.remove();
            }
        }, 5000);
    }

    // NUEVA FUNCIÓN: Generar número aleatorio de la ruleta
    function generarNumeroAleatorio() {
        // Generar índice aleatorio entre 0 y 37 (38 números en total)
        const indiceAleatorio = Math.floor(Math.random() * numerosRuleta.length);
        return numerosRuleta[indiceAleatorio];
    }

    // MODIFICADA: Función para guardar apuesta DESPUÉS de la animación
    function guardarApuesta(tipoApuesta, numeroApostado, cantidadApostada, numeroResultado) {
        // Construir el body de la petición
        let bodyParams =
            `action=guardar_apuesta&tipo_apuesta=${tipoApuesta}&cantidad=${cantidadApostada}&numero_resultado=${numeroResultado}`;

        // Solo incluir numero si es apuesta a número específico
        if (tipoApuesta === 'numero') {
            bodyParams += `&numero=${numeroApostado}`;
        }

        return fetch('juego.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: bodyParams
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Actualizar estado del juego
                    monedasActuales = data.monedas_actuales;
                    jugadasRestantes = data.jugadas_restantes;

                    // Actualizar interfaz
                    document.getElementById('monedas-display').textContent = monedasActuales;
                    document.getElementById('jugadas-restantes').textContent = jugadasRestantes;
                    document.getElementById('cantidad').max = monedasActuales;
                    document.getElementById('max-monedas').textContent = monedasActuales;

                    // Actualizar historial
                    let textoApuesta = tipoApuesta === 'numero' ? numeroApostado : tipoApuesta.toUpperCase();
                    actualizarHistorial(textoApuesta, numeroResultado, data.ganancia, cantidadApostada);

                    // Mostrar mensaje
                    mostrarMensaje(data.mensaje, data.ganancia > 0 ? 'success' : 'warning');

                    // Verificar si se acabaron las jugadas
                    //if (jugadasRestantes <= 0) {
                    //    setTimeout(() => {
                    //        window.location.href = 'ranking.php';
                    //    }, 3000);
                    //}
                }
                return data;
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarMensaje('Error de conexión. Inténtalo de nuevo.', 'error');
                return {
                    success: false,
                    mensaje: 'Error de conexión'
                };
            });
    }

    // Función para actualizar el historial de apuestas
    function actualizarHistorial(numeroApostado, numeroResultado, ganancia, cantidadApostada) {
        const tbody = document.getElementById('historial-tbody');
        const primeraFila = tbody.querySelector('tr');

        // Si la primera fila dice "No hay apuestas registradas", eliminarla
        if (primeraFila && primeraFila.textContent.includes('No hay apuestas registradas')) {
            primeraFila.remove();
        }

        // Crear nueva fila
        const nuevaFila = document.createElement('tr');
        const gananciaClass = ganancia > 0 ? 'text-success' : 'text-danger';
        const gananciaTexto = ganancia > 0 ? `+${ganancia}` : `-${cantidadApostada}`;

        // Mostrar números correctamente (37 -> 00)
        const numApostadoDisplay = numeroApostado === '37' ? '00' : numeroApostado;
        const numResultadoDisplay = numeroResultado === '37' ? '00' : numeroResultado;

        nuevaFila.innerHTML = `
            <td>${numApostadoDisplay}</td>
            <td>${numResultadoDisplay}</td>
            <td class="${gananciaClass}">${gananciaTexto}</td>
        `;

        // Insertar al principio del tbody
        tbody.insertBefore(nuevaFila, tbody.firstChild);
    }


    function girarRuleta() {
        if (girando || !apuestaSeleccionada) {
            if (!apuestaSeleccionada) {
                mostrarMensaje('Por favor, selecciona una apuesta primero', 'error');
            }
            return;
        }

        // Validar cantidad
        const cantidadInput = document.getElementById('cantidad');
        const cantidad = parseInt(cantidadInput.value);

        if (!cantidad || cantidad <= 0) {
            mostrarMensaje('Por favor, ingresa una cantidad válida', 'error');
            return;
        }

        if (cantidad > monedasActuales) {
            mostrarMensaje('No tienes suficientes monedas para esta apuesta', 'error');
            return;
        }

        girando = true;
        document.getElementById('btnGirar').disabled = true;
        document.getElementById('resultado').style.display = 'none';

        // NUEVO: Generar número ganador ANTES de la animación
        const numeroGanador = generarNumeroAleatorio();

        // Determinar si ganó según el tipo de apuesta
        let gano = false;
        let ganancia = 0;
        const numeroResultado = numeroGanador.numero;
        const colorResultado = numeroGanador.color;

        if (apuestaSeleccionada.tipo === 'numero') {
            // Apuesta a número específico
            gano = apuestaSeleccionada.valor === numeroResultado;
            if (gano) {
                ganancia = cantidad * multiplicadorNumero;
            }
        } else if (apuestaSeleccionada.tipo === 'par' || apuestaSeleccionada.tipo === 'impar') {
            // Apuesta a par/impar (0 y 00 no cuentan)
            if (numeroResultado !== '0' && numeroResultado !== '00') {
                const numResultado = parseInt(numeroResultado);
                const esPar = numResultado % 2 === 0;

                if (apuestaSeleccionada.tipo === 'par') {
                    gano = esPar;
                } else {
                    gano = !esPar;
                }

                if (gano) {
                    ganancia = Math.floor(cantidad * multiplicadorParidad);
                }
            }
        } else if (apuestaSeleccionada.tipo === 'rojo' || apuestaSeleccionada.tipo === 'negro') {
            // Apuesta a color (0 y 00 no cuentan)
            if (numeroResultado !== '0' && numeroResultado !== '00') {
                gano = colorResultado === apuestaSeleccionada.tipo;

                if (gano) {
                    ganancia = Math.floor(cantidad * multiplicadorColor);
                }
            }
        }

        // Resetear rotación previa
        const ruleta = document.getElementById('ruleta');
        ruleta.style.transition = 'none';
        ruleta.style.transform = 'rotate(0deg)';

        // Hacer la animación
        setTimeout(() => {
            ruleta.style.transition = 'transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99)';

            // Calcular rotación (5 vueltas + posición final)
            const vueltasCompletas = 5;
            const gradosBase = vueltasCompletas * 360;
            const gradoFinal = 360 - numeroGanador.grado;
            const rotacionTotal = gradosBase + gradoFinal;

            // Aplicar rotación
            ruleta.style.transform = `rotate(${rotacionTotal}deg)`;

            // Mostrar resultado después de la animación
            setTimeout(() => {
                // Mostrar resultado visual
                mostrarResultadoVisual(numeroGanador, gano);

                // AHORA guardar la apuesta en el servidor con el resultado predeterminado
                guardarApuesta(
                    apuestaSeleccionada.tipo,
                    apuestaSeleccionada.valor || '',
                    cantidad,
                    numeroGanador.numero
                ).then(respuesta => {
                    if (!respuesta.success) {
                        mostrarMensaje(respuesta.mensaje || 'Error al guardar la apuesta',
                            'error');
                    }

                    // Rehabilitar el botón
                    girando = false;
                    document.getElementById('btnGirar').disabled = false;

                    // Limpiar selección
                    document.querySelectorAll('.btn-numero, .btn-apuesta').forEach(b => b
                        .classList.remove('selected'));
                    apuestaSeleccionada = null;
                    document.getElementById('apuestaTexto').textContent = 'Ninguna';
                });
            }, 4000);
        }, 50);
    }

    // Mostrar resultado visual
    function mostrarResultadoVisual(numeroGanador, ganaste) {
        const resultadoDiv = document.getElementById('resultado');
        const numeroGanadorP = document.getElementById('numeroGanador');
        const resultadoApuestaP = document.getElementById('resultadoApuesta');

        numeroGanadorP.textContent = `Número ganador: ${numeroGanador.numero} (${numeroGanador.color.toUpperCase()})`;

        if (ganaste) {
            resultadoApuestaP.textContent = '¡GANASTE!';
            resultadoApuestaP.className = 'ganador';
        } else {
            resultadoApuestaP.textContent = 'Perdiste';
            resultadoApuestaP.className = 'perdedor';
        }

        resultadoDiv.style.display = 'block';
    }

    // Inicializar
    document.addEventListener('DOMContentLoaded', function() {
        // Crear ruleta visual
        crearRuleta();

        // Crear panel de números
        crearPanelNumeros();

        // Agregar eventos a números especiales (0 y 00) después de que el DOM esté listo
        document.querySelectorAll('.numeros-especiales .btn-numero').forEach(btn => {
            btn.addEventListener('click', seleccionarNumero);
        });

        // Agregar eventos a botones de tipo de apuesta
        document.querySelectorAll('.btn-apuesta').forEach(btn => {
            btn.addEventListener('click', seleccionarTipoApuesta);
        });

        // Event listener para el botón girar
        document.getElementById('btnGirar').addEventListener('click', girarRuleta);

        // Validar cantidad en tiempo real
        document.getElementById('cantidad').addEventListener('input', function() {
            const valor = parseInt(this.value);
            if (valor > monedasActuales) {
                this.value = monedasActuales;
            }
            if (valor < 1 && this.value !== '') {
                this.value = 1;
            }
        });
    });
    </script>


</body>

</html>