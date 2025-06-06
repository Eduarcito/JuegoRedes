<?php
// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once 'database.php';
require_once 'clases/juego.php';
require_once 'clases/usuario_corregido.php';
require_once 'clases/ranking-implementation.php';

// Verificar si el usuario está logueado
$logged_in = isset($_SESSION['usuario_id']) ? true : false;

// Obtener conexión a la base de datos
$database = new Database();
$db = $database->getConnection();

// Obtener ranking para mostrar
$ranking = new Ranking($db);
$stmt_ranking = $ranking->obtenerRankingGlobal(10);

// Mensaje de error o éxito
$mensaje = '';
$tipo_mensaje = '';

// Debugging - Verificar si se están recibiendo los datos de los formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Guardar en un archivo de log para debug
    file_put_contents('form_debug.log', print_r($_POST, true), FILE_APPEND);
}

// Procesar inicio de sesión
if(isset($_POST['action']) && $_POST['action'] == 'login') {
    // Crear objeto usuario
    $usuario = new Usuario($db);
    
    // Configurar propiedades
    $usuario->nombre_usuario = $_POST['username'];
    $usuario->contraseña = $_POST['password'];
    
    // Intentar login
    if($usuario->login()) {
        // Guardar datos en sesión
        $_SESSION['usuario_id'] = $usuario->usuario_id;
        $_SESSION['nombre_usuario'] = $usuario->nombre_usuario;
        
        // Redireccionar a la misma página para evitar reenvío de formulario
        header("Location: index.php");
        exit;
    } else {
        $mensaje = "Usuario o contraseña incorrectos";
        $tipo_mensaje = "error";
    }
}

// Procesar registro
if(isset($_POST['action']) && $_POST['action'] == 'register') {
    // Crear objeto usuario
    $usuario = new Usuario($db);
    
    // Configurar propiedades
    $usuario->nombre_usuario = $_POST['username'];
    $usuario->email = $_POST['email'];
    $usuario->contraseña = $_POST['password'];
    
    // Verificar si el usuario ya existe
    if($usuario->usuarioExiste()) {
        $mensaje = "El nombre de usuario o email ya está en uso";
        $tipo_mensaje = "error";
    } else {
        // Intentar registro
        if($usuario->registrar()) {
            $mensaje = "Registro exitoso. Por favor inicia sesión";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "Error al registrar. Inténtalo de nuevo";
            $tipo_mensaje = "error";
        }
    }
}

// Procesar cierre de sesión
if(isset($_GET['action']) && $_GET['action'] == 'logout') {
    // Eliminar todas las variables de sesión
    session_unset();
    
    // Destruir la sesión
    session_destroy();
    
    // Redireccionar a la página principal
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ruleta Americana - Sistema de Apuestas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #1a1a1a;
            color: #fff;
            overflow-x: hidden;
        }
        .navbar {
            background-color: #3d0000 !important;
            animation: slideDown 0.5s ease-out;
        }
        @keyframes slideDown {
            from { transform: translateY(-100%); }
            to { transform: translateY(0); }
        }
        .card {
            background-color: #2a2a2a;
            border: none;
            border-radius: 10px;
            animation: fadeInUp 0.6s ease-out;
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(139, 0, 0, 0.3);
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .btn-danger {
            background-color: #8b0000;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-danger:hover {
            background-color: #a50000;
            transform: scale(1.05);
            box-shadow: 0 5px 20px rgba(139, 0, 0, 0.5);
        }
        .btn-success {
            background-color: #006400;
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
        
        /* Ruleta animada en el home */
        .roulette-home-container {
            position: relative;
            width: 300px;
            height: 300px;
            margin: 30px auto;
        }
        
        .roulette-wheel-home {
            width: 100%;
            height: 100%;
            animation: continuousSpin 20s linear infinite;
        }
        
        @keyframes continuousSpin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .roulette-indicator-home {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 15px solid transparent;
            border-right: 15px solid transparent;
            border-top: 30px solid #FFD700;
            z-index: 10;
            filter: drop-shadow(0 2px 5px rgba(0,0,0,0.5));
        }
        
        .roulette-center-home {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60px;
            height: 60px;
            background: radial-gradient(circle, #FFD700 0%, #B8860B 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            color: #000;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
            z-index: 5;
        }
        
        /* Animación de título */
        h2.card-title {
            animation: titleGlow 2s ease-in-out infinite alternate;
        }
        
        @keyframes titleGlow {
            from { text-shadow: 0 0 10px rgba(255, 215, 0, 0.5); }
            to { text-shadow: 0 0 20px rgba(255, 215, 0, 0.8), 0 0 30px rgba(255, 215, 0, 0.6); }
        }
        
        /* Animación para los números del ranking */
        .table tbody tr {
            animation: fadeIn 0.5s ease-out;
            animation-fill-mode: both;
        }
        
        .table tbody tr:nth-child(1) { animation-delay: 0.1s; }
        .table tbody tr:nth-child(2) { animation-delay: 0.2s; }
        .table tbody tr:nth-child(3) { animation-delay: 0.3s; }
        .table tbody tr:nth-child(4) { animation-delay: 0.4s; }
        .table tbody tr:nth-child(5) { animation-delay: 0.5s; }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* Medallas para el top 3 */
        .medal {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 5px;
            font-size: 12px;
            text-align: center;
            line-height: 20px;
        }
        
        .gold { background: linear-gradient(45deg, #FFD700, #FFA500); }
        .silver { background: linear-gradient(45deg, #C0C0C0, #808080); }
        .bronze { background: linear-gradient(45deg, #CD7F32, #8B4513); }
        
        /* Partículas de fondo */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }
        
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: #FFD700;
            border-radius: 50%;
            opacity: 0.3;
            animation: float 10s infinite linear;
        }
        
        @keyframes float {
            from {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.3;
            }
            90% {
                opacity: 0.3;
            }
            to {
                transform: translateY(-100vh) rotate(360deg);
                opacity: 0;
            }
        }
        
        /* Hover effects para los modales */
        .modal-content {
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                transform: scale(0.9);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body>
    <!-- Partículas de fondo -->
    <div class="particles" id="particles"></div>
    
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
                        <a class="nav-link active" href="index.php">Inicio</a>
                    </li>
                    <?php if($logged_in): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="juego.php">Jugar</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="ranking.php">Ranking</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <?php if($logged_in): ?>
                        <span class="navbar-text me-3">
                            <i class="fas fa-user me-1"></i> Hola, <?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?>
                        </span>
                        <a href="index.php?action=logout" class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-sign-out-alt me-1"></i> Cerrar Sesión
                        </a>
                    <?php else: ?>
                        <button class="btn btn-outline-light btn-sm me-2" data-bs-toggle="modal" data-bs-target="#loginModal">
                            <i class="fas fa-sign-in-alt me-1"></i> Iniciar Sesión
                        </button>
                        <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#registerModal">
                            <i class="fas fa-user-plus me-1"></i> Registrarse
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Contenido principal -->
    <div class="container">
        <?php if($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <h2 class="card-title mb-4">¡Bienvenido a la Ruleta Americana!</h2>
                        
                        <!-- Ruleta animada mejorada -->
                        <div class="roulette-home-container">
                            <div class="roulette-indicator-home"></div>
                            <svg class="roulette-wheel-home" viewBox="0 0 300 300">
                                <?php
                                // Números de la ruleta americana
                                $numeros = [0, 28, 9, 26, 30, 11, 7, 20, 32, 17, 5, 22, 34, 15, 3, 24, 36, 13, 1, 37, 27, 10, 25, 29, 12, 8, 19, 31, 18, 6, 21, 33, 16, 4, 23, 35, 14, 2];
                                $colores = [
                                    0 => '#006400', 37 => '#006400',
                                    1 => '#DC143C', 3 => '#DC143C', 5 => '#DC143C', 7 => '#DC143C', 9 => '#DC143C',
                                    12 => '#DC143C', 14 => '#DC143C', 16 => '#DC143C', 18 => '#DC143C', 19 => '#DC143C',
                                    21 => '#DC143C', 23 => '#DC143C', 25 => '#DC143C', 27 => '#DC143C', 30 => '#DC143C',
                                    32 => '#DC143C', 34 => '#DC143C', 36 => '#DC143C'
                                ];
                                
                                $totalNumeros = count($numeros);
                                $anguloPorNumero = 360 / $totalNumeros;
                                
                                for($i = 0; $i < $totalNumeros; $i++) {
                                    $numero = $numeros[$i];
                                    $anguloInicio = $i * $anguloPorNumero;
                                    $anguloFin = ($i + 1) * $anguloPorNumero;
                                    $color = isset($colores[$numero]) ? $colores[$numero] : '#000';
                                    
                                    $x1 = 150 + 130 * cos(deg2rad($anguloInicio));
                                    $y1 = 150 + 130 * sin(deg2rad($anguloInicio));
                                    $x2 = 150 + 130 * cos(deg2rad($anguloFin));
                                    $y2 = 150 + 130 * sin(deg2rad($anguloFin));
                                    
                                    $anguloMedio = ($anguloInicio + $anguloFin) / 2;
                                    $textoX = 150 + 100 * cos(deg2rad($anguloMedio));
                                    $textoY = 150 + 100 * sin(deg2rad($anguloMedio));
                                    
                                    echo "<g>";
                                    echo "<path d='M 150 150 L $x1 $y1 A 130 130 0 0 1 $x2 $y2 Z' fill='$color' stroke='white' stroke-width='2'/>";
                                    
                                    $numeroMostrar = $numero == 37 ? '00' : $numero;
                                    echo "<text x='$textoX' y='$textoY' fill='white' font-size='12' font-weight='bold' text-anchor='middle' dominant-baseline='middle' transform='rotate(" . ($anguloMedio + 90) . " $textoX $textoY)'>$numeroMostrar</text>";
                                    echo "</g>";
                                }
                                ?>
                            </svg>
                            <div class="roulette-center-home">
                                <span id="resultNumber">?</span>
                            </div>
                        </div>
                        
                        <p class="card-text mt-4">
                            Juega a la ruleta americana y pon a prueba tu suerte. 
                            Cada jugador inicia con 5 monedas y tiene 5 jugadas.
                        </p>
                        
                        <?php if($logged_in): ?>
                            <a href="juego.php" class="btn btn-danger btn-lg mt-3">
                                <i class="fas fa-play-circle me-2"></i>¡Jugar Ahora!
                            </a>
                        <?php else: ?>
                            <button class="btn btn-danger btn-lg mt-3" data-bs-toggle="modal" data-bs-target="#loginModal">
                                <i class="fas fa-user me-2"></i>Inicia Sesión para Jugar
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-dark">
                        <h3 class="mb-0">¿Cómo jugar?</h3>
                    </div>
                    <div class="card-body">
                        <ol>
                            <li class="mb-2">Inicia sesión o regístrate para comenzar.</li>
                            <li class="mb-2">Cada jugador comienza con 5 monedas y 5 jugadas.</li>
                            <li class="mb-2">Selecciona un número del 0 al 36 (o el doble cero "00").</li>
                            <li class="mb-2">Elige la cantidad de monedas a apostar en cada jugada.</li>
                            <li class="mb-2">Si el número que salió en la ruleta coincide con tu apuesta, ¡ganas 35 veces lo apostado!</li>
                            <li class="mb-2">Al finalizar tus 5 jugadas, tu puntuación se registrará en el ranking.</li>
                        </ol>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-dark d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">Top 10 Jugadores</h3>
                        <a href="ranking.php" class="btn btn-sm btn-outline-light">Ver Todos</a>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Jugador</th>
                                    <th>Puntos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $position = 1;
                                while ($row = $stmt_ranking->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<tr>";
                                    echo "<td>";
                                    if($position == 1) echo '<span class="medal gold">🥇</span>';
                                    else if($position == 2) echo '<span class="medal silver">🥈</span>';
                                    else if($position == 3) echo '<span class="medal bronze">🥉</span>';
                                    else echo $position;
                                    echo "</td>";
                                    echo "<td>" . htmlspecialchars($row['nombre_usuario']) . "</td>";
                                    echo "<td>" . number_format($row['puntaje_total']) . "</td>";
                                    echo "</tr>";
                                    $position++;
                                }
                                
                                if($position == 1) {
                                    echo "<tr><td colspan='3' class='text-center'>No hay datos de ranking aún</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Iniciar Sesión -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark">
                <div class="modal-header border-bottom-0">
                    <h5 class="modal-title">Iniciar Sesión</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="">
                        <input type="hidden" name="action" value="login">
                        <div class="mb-3">
                            <label for="username" class="form-label">Nombre de Usuario</label>
                            <input type="text" class="form-control bg-dark text-light" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control bg-dark text-light" id="password" name="password" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer justify-content-center border-top-0">
                    <span>¿No tienes cuenta? </span>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#registerModal" data-bs-dismiss="modal">Regístrate</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Registrarse -->
    <div class="modal fade" id="registerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark">
                <div class="modal-header border-bottom-0">
                    <h5 class="modal-title">Registrarse</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="">
                        <input type="hidden" name="action" value="register">
                        <div class="mb-3">
                            <label for="reg-username" class="form-label">Nombre de Usuario</label>
                            <input type="text" class="form-control bg-dark text-light" id="reg-username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control bg-dark text-light" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="reg-password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control bg-dark text-light" id="reg-password" name="password" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-user-plus me-2"></i>Registrarse
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer justify-content-center border-top-0">
                    <span>¿Ya tienes cuenta? </span>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" data-bs-dismiss="modal">Inicia Sesión</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts de Bootstrap y JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Crear partículas de fondo
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 50;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 10 + 's';
                particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
                particlesContainer.appendChild(particle);
            }
        }
        
        // Script para animación de la ruleta
        function animarNumeroAleatorio() {
            const resultElement = document.getElementById('resultNumber');
            let counter = 0;
            const interval = setInterval(() => {
                // Generar un número aleatorio (0-36 y doble cero representado como 37)
                const randomNum = Math.floor(Math.random() * 38);
                resultElement.textContent = randomNum === 37 ? '00' : randomNum;
                counter++;
                
                if (counter > 20) {
                    clearInterval(interval);
                    // Después de parar, hacer una animación de pulso
                    resultElement.style.animation = 'pulse 0.5s ease-in-out';
                    setTimeout(() => {
                        resultElement.style.animation = '';
                    }, 500);
                }
            }, 100);
        }
        
        // Ejecutar cuando se carga la página
        document.addEventListener('DOMContentLoaded', () => {
            // Crear partículas
            createParticles();
            
            // Animar número aleatorio cada 5 segundos
            animarNumeroAleatorio();
            setInterval(animarNumeroAleatorio, 5000);
            
            // Animación de hover para las cartas
            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transition = 'all 0.3s ease';
                });
            });
            
            // Validación de formularios con animación
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const button = this.querySelector('button[type="submit"]');
                    button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando...';
                    button.disabled = true;
                });
            });
            
            // Efecto de brillo en el botón principal
            const mainButton = document.querySelector('.btn-danger.btn-lg');
            if (mainButton) {
                setInterval(() => {
                    mainButton.style.boxShadow = '0 0 30px rgba(139, 0, 0, 0.8)';
                    setTimeout(() => {
                        mainButton.style.boxShadow = '0 0 15px rgba(139, 0, 0, 0.5)';
                    }, 1000);
                }, 2000);
            }
            
            // Código para mostrar mensajes de debug
            <?php if($mensaje): ?>
            console.log('Mensaje: <?php echo addslashes($mensaje); ?>');
            console.log('Tipo: <?php echo $tipo_mensaje; ?>');
            <?php endif; ?>
        });
    </script>
</body>
</html>