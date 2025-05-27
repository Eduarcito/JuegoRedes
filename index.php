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
        }
        .navbar {
            background-color: #3d0000 !important;
        }
        .card {
            background-color: #2a2a2a;
            border: none;
            border-radius: 10px;
        }
        .btn-danger {
            background-color: #8b0000;
            border: none;
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
        .roulette-wheel {
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, #8b0000 0%, #3d0000 100%);
            margin: 30px auto;
            position: relative;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
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
            font-size: 24px;
            font-weight: bold;
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
                        
                        <!-- Imagen representativa de la ruleta -->
                        <div class="roulette-wheel">
                            <div class="inner-wheel">
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
                                    echo "<td>{$position}</td>";
                                    echo "<td>" . htmlspecialchars($row['nombre_usuario']) . "</td>";
                                    echo "<td>" . number_format($row['puntaje_total']) . "</td>";
                                    echo "</tr>";
                                    $position++;
                                }
                                
                                // Si no hay datos en el ranking
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
        // Script para animación simple de la ruleta
        function simularGiro() {
            const resultElement = document.getElementById('resultNumber');
            let counter = 0;
            const interval = setInterval(() => {
                // Generar un número aleatorio (0-36 y doble cero representado como 37)
                const randomNum = Math.floor(Math.random() * 38);
                resultElement.textContent = randomNum === 37 ? '00' : randomNum;
                counter++;
                
                if (counter > 20) {
                    clearInterval(interval);
                }
            }, 100);
        }
        
        // Simular giro al cargar la página
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(simularGiro, 1000);
            
            // Código para mostrar mensajes de debug
            <?php if($mensaje): ?>
            console.log('Mensaje: <?php echo addslashes($mensaje); ?>');
            console.log('Tipo: <?php echo $tipo_mensaje; ?>');
            <?php endif; ?>
        });
    </script>
</body>
</html>