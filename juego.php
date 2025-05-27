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

// Procesar apuesta realizada
if(isset($_POST['action']) && $_POST['action'] == 'apostar') {
    // Obtener datos del formulario
    $numero_apostado = isset($_POST['numero']) ? (int)$_POST['numero'] : 0;
    $cantidad_apostada = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 0;
    
    // Validar la apuesta
    if($numero_apostado < 0 || ($numero_apostado > 36 && $numero_apostado != 37)) {
        $mensaje = "Número no válido. Debe estar entre 0 y 36, o 00 (37)";
        $tipo_mensaje = "error";
    } else if($cantidad_apostada <= 0 || $cantidad_apostada > $monedas_actuales) {
        $mensaje = "Cantidad no válida. Debe ser mayor a 0 y no superar tus monedas actuales";
        $tipo_mensaje = "error";
    } else if($jugadas_restantes <= 0) {
        $mensaje = "No tienes jugadas restantes";
        $tipo_mensaje = "error";
    } else {
        // Configurar la apuesta
        $apuesta = new Apuesta($db);
        $apuesta->partida_id = $partida_id;
        $apuesta->numero_apostado = $numero_apostado;
        $apuesta->cantidad_apostada = $cantidad_apostada;
        
        // Registrar la apuesta
        if($apuesta->registrar()) {
            // Obtener el resultado de la última apuesta
            $historial_stmt = $apuesta->obtenerApuestasDePartida();
            $ultima_apuesta = null;
            
            while($row = $historial_stmt->fetch(PDO::FETCH_ASSOC)) {
                $ultima_apuesta = $row;
            }
            
            // Actualizar estado del juego
            $resultado = $ultima_apuesta['numero_resultado'];
            $ganancia = $ultima_apuesta['ganancia'];
            $monedas_actuales = $monedas_actuales - $cantidad_apostada + $ganancia;
            $jugadas_restantes--;
            
            // Actualizar variables de sesión
            $_SESSION['monedas_actuales'] = $monedas_actuales;
            $_SESSION['jugadas_restantes'] = $jugadas_restantes;
            
            // Mensaje según resultado
            if($ganancia > 0) {
                $mensaje = "¡Felicidades! Has ganado {$ganancia} monedas";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Lo siento, has perdido tu apuesta";
                $tipo_mensaje = "warning";
            }
            
            // Resetear el historial para actualizarlo
            $historial_stmt = $apuesta->obtenerApuestasDePartida();
        } else {
            $mensaje = "Error al procesar la apuesta";
            $tipo_mensaje = "error";
        }
    }
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
    <style>
        body {
            background-color: #1a1a1a;
            color: #fff;
        }
        .navbar {
            background-color: #3d0000 !important;
        }
        .card {
            background-color: #2a2a2a;
            border: none;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .btn-danger {
            background-color: #8b0000;
            border: none;
        }
        .table {
            color: #fff;
            background-color: #2a2a2a;
        }
        .table thead th {
            background-color: #3d0000;
            color: #fff;
        }
        .roulette-container {
            margin-bottom: 30px;
        }
        .roulette-wheel {
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, #8b0000 0%, #3d0000 100%);
            margin: 0 auto 30px;
            position: relative;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 3s ease-out;
        }
        .inner-wheel {
            width: 80%;
            height: 80%;
            border-radius: 50%;
            background: radial-gradient(circle, #2a2a2a 0%, #1a1a1a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 36px;
            font-weight: bold;
        }
        .number-grid {
            display: grid;
            grid-template-columns: repeat(13, 1fr);
            gap: 5px;
            margin-bottom: 20px;
        }
        .number-btn {
            width: 100%;
            aspect-ratio: 1;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .zero-btn {
            grid-column: span 3;
            aspect-ratio: 3/1;
        }
        #last-result {
            font-size: 24px;
            text-align: center;
            margin: 20px 0;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .info-panel {
            background-color: #3d0000;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .apuesta-container {
            background-color: #2a2a2a;
            padding: 20px;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <!-- Barra de navegación -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
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
                        <i class="fas fa-user me-1"></i> Hola, <?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?>
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
        
        <h1 class="text-center mb-4">Ruleta Americana</h1>
        
        <div class="row">
            <div class="col-lg-8">
                <!-- Información del juego actual -->
                <div class="info-panel d-flex justify-content-between mb-4">
                    <div>
                        <h5>Jugadas restantes: <span class="badge bg-light text-dark"><?php echo $jugadas_restantes; ?></span></h5>
                    </div>
                    <div>
                        <h5>Monedas: <span class="badge bg-warning text-dark"><?php echo $monedas_actuales; ?></span></h5>
                    </div>
                </div>
                
                <!-- Ruleta visual -->
                <div class="roulette-container">
                    <div class="roulette-wheel" id="roulette">
                        <div class="inner-wheel">
                            <span id="result-number"><?php 
                                echo isset($resultado) ? ($resultado == 37 ? '00' : $resultado) : '?'; 
                            ?></span>
                        </div>
                    </div>
                    
                    <div id="last-result">
                        <?php if(isset($resultado)): ?>
                            <?php if($ganancia > 0): ?>
                                <div class="alert alert-success w-100">
                                    ¡GANASTE! Salió el <?php echo $resultado == 37 ? '00' : $resultado; ?> 
                                    y ganaste <?php echo $ganancia; ?> monedas
                                </div>
                            <?php else: ?>
                                <div class="alert alert-danger w-100">
                                    PERDISTE. Salió el <?php echo $resultado == 37 ? '00' : $resultado; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Formulario de apuesta -->
                <div class="card">
                    <div class="card-header bg-dark">
                        <h3 class="mb-0">Realiza tu Apuesta</h3>
                    </div>
                    <div class="card-body">
                        <?php if($jugadas_restantes > 0): ?>
                            <form action="juego.php" method="post" id="apuesta-form">
                                <input type="hidden" name="action" value="apostar">
                                <input type="hidden" name="numero" id="numero-seleccionado" value="">
                                
                                <div class="mb-4">
                                    <label class="form-label">Selecciona un número:</label>
                                    
                                    <!-- Botones para los ceros -->
                                    <div class="number-grid mb-2">
                                        <button type="button" class="btn btn-success zero-btn number-select" data-numero="0">0</button>
                                        <button type="button" class="btn btn-success zero-btn number-select" data-numero="37">00</button>
                                    </div>
                                    
                                    <!-- Grid de números (1-36) -->
                                    <div class="number-grid">
                                        <?php for($i = 1; $i <= 36; $i++): ?>
                                            <button type="button" class="btn <?php echo $i % 2 == 0 ? 'btn-dark' : 'btn-danger'; ?> number-btn number-select" data-numero="<?php echo $i; ?>">
                                                <?php echo $i; ?>
                                            </button>
                                        <?php endfor; ?>
                                    </div>
                                    
                                    <div class="alert alert-info text-center" id="numero-display">
                                        Selecciona un número para apostar
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="cantidad" class="form-label">Cantidad de monedas a apostar:</label>
                                    <input type="number" class="form-control bg-dark text-light" id="cantidad" name="cantidad" min="1" max="<?php echo $monedas_actuales; ?>" value="1" required>
                                    <div class="form-text">Máximo: <?php echo $monedas_actuales; ?> monedas</div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-danger btn-lg" id="btn-apostar" disabled>
                                        <i class="fas fa-coins me-2"></i>Apostar
                                    </button>
                                </div>
                            </form>
                            
                            <div class="mt-3 text-center">
                                <form action="juego.php" method="post">
                                    <input type="hidden" name="action" value="finalizar">
                                    <button type="submit" class="btn btn-outline-light">
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
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Historial de apuestas -->
                <div class="card">
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
                            <tbody>
                                <?php 
                                $apuestas = [];
                                while($row = $historial_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $apuestas[] = $row;
                                }
                                
                                // Mostrar las apuestas en orden inverso (más reciente primero)
                                $apuestas = array_reverse($apuestas);
                                
                                foreach($apuestas as $row) {
                                    $num_apostado = $row['numero_apostado'] == 37 ? '00' : $row['numero_apostado'];
                                    $resultado = $row['numero_resultado'] == 37 ? '00' : $row['numero_resultado'];
                                    $ganancia_class = $row['ganancia'] > 0 ? 'text-success' : 'text-danger';
                                    $ganancia_texto = $row['ganancia'] > 0 ? "+{$row['ganancia']}" : "-{$row['cantidad_apostada']}";
                                    
                                    echo "<tr>";
                                    echo "<td>{$num_apostado}</td>";
                                    echo "<td>{$resultado}</td>";
                                    echo "<td class='{$ganancia_class}'>{$ganancia_texto}</td>";
                                    echo "</tr>";
                                }
                                
                                // Si no hay apuestas registradas
                                if(empty($apuestas)) {
                                    echo "<tr><td colspan='3' class='text-center'>No hay apuestas registradas</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Información del juego -->
                <div class="card mt-4">
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
                                Ganancia: x<?php echo $multiplicador; ?> la apuesta
                            </li>
                            <li class="list-group-item bg-transparent border-dark">
                                <i class="fas fa-random me-2 text-danger"></i>
                                Números: 0-36 y 00
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
        document.addEventListener('DOMContentLoaded', () => {
            // Variables para el formulario de apuesta
            const numeroSeleccionado = document.getElementById('numero-seleccionado');
            const numeroDisplay = document.getElementById('numero-display');
            const btnApostar = document.getElementById('btn-apostar');
            const botonesNumeros = document.querySelectorAll('.number-select');
            
            // Manejar clic en los botones de números
            botonesNumeros.forEach(boton => {
                boton.addEventListener('click', () => {
                    // Remover selección previa
                    botonesNumeros.forEach(b => b.classList.remove('active'));
                    
                    // Marcar como seleccionado
                    boton.classList.add('active');
                    
                    // Actualizar el número seleccionado
                    const numero = boton.getAttribute('data-numero');
                    numeroSeleccionado.value = numero;
                    
                    // Mostrar el número seleccionado
                    numeroDisplay.textContent = `Número seleccionado: ${numero == 37 ? '00' : numero}`;
                    
                    // Habilitar el botón de apostar
                    btnApostar.disabled = false;
                });
            });
            
            // Animar la ruleta si hay un resultado
            <?php if(isset($resultado)): ?>
            const roulette = document.getElementById('roulette');
            
            // Simular giro de la ruleta
            const animateRoulette = () => {
                // Número de giros completos
                const spins = 5;
                
                // Giro inicial
                roulette.style.transform = 'rotate(0deg)';
                
                // Calcular el ángulo final (giros completos + ángulo aleatorio)
                const finalAngle = spins * 360 + Math.floor(Math.random() * 360);
                
                // Aplicar la animación
                setTimeout(() => {
                    roulette.style.transition = 'transform 3s ease-out';
                    roulette.style.transform = `rotate(${finalAngle}deg)`;
                }, 100);
            };
            
            // Ejecutar animación cuando se carga por primera vez
            if (sessionStorage.getItem('animation-played') !== 'true') {
                animateRoulette();
                sessionStorage.setItem('animation-played', 'true');
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>
            