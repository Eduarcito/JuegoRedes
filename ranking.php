<?php
// Iniciar sesión
session_start();

// Incluir archivos necesarios
require_once 'database.php';
require_once 'clases/ranking-implementation.php';

// Obtener conexión a la base de datos
$database = new Database();
$db = $database->getConnection();

// Verificar si el usuario está logueado
$logged_in = isset($_SESSION['usuario_id']) ? true : false;

// Obtener ranking completo
$ranking = new Ranking($db);
$stmt_ranking = $ranking->obtenerRankingGlobal(100); // Obtener hasta 100 jugadores

// Obtener posición del usuario actual
$posicion_usuario = 0;
if($logged_in) {
    $posicion_usuario = $ranking->obtenerPosicionUsuario($_SESSION['usuario_id']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ruleta Americana - Ranking</title>
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
        .table {
            color: #fff;
            background-color: #2a2a2a;
        }
        .table thead th {
            background-color: #3d0000;
            color: #fff;
        }
        .trophy-gold {
            color: #FFD700;
        }
        .trophy-silver {
            color: #C0C0C0;
        }
        .trophy-bronze {
            color: #CD7F32;
        }
        .table tr.current-user {
            background-color: rgba(139, 0, 0, 0.3);
            font-weight: bold;
        }
        .ranking-title {
            background: linear-gradient(45deg, #8b0000, #3d0000);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
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
                    <?php if($logged_in): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="juego.php">Jugar</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="ranking.php">Ranking</a>
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
                        <a href="index.php" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-sign-in-alt me-1"></i> Iniciar Sesión
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Contenido principal -->
    <div class="container">
        <div class="ranking-title">
            <h1><i class="fas fa-trophy me-3"></i>Ranking de Jugadores<i class="fas fa-trophy ms-3"></i></h1>
            <p class="lead mb-0">Los mejores jugadores de la Ruleta Americana</p>
        </div>
        
        <?php if($logged_in): ?>
        <div class="alert alert-info mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="alert-heading">¡Hola, <?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?>!</h4>
                    <p class="mb-0">Tu posición actual en el ranking: <strong>#<?php echo $posicion_usuario; ?></strong></p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <a href="juego.php" class="btn btn-danger">
                        <i class="fas fa-play-circle me-2"></i>¡Jugar Ahora!
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Jugador</th>
                            <th>Puntaje Total</th>
                            <th>Partidas Jugadas</th>
                            <th>Promedio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $position = 1;
                        while ($row = $stmt_ranking->fetch(PDO::FETCH_ASSOC)) {
                            // Determinar si es el usuario actual
                            $is_current_user = $logged_in && $row['usuario_id'] == $_SESSION['usuario_id'];
                            
                            // Calcular promedio
                            $promedio = $row['partidas_jugadas'] > 0 ? 
                                        round($row['puntaje_total'] / $row['partidas_jugadas'], 2) : 0;
                            
                            echo "<tr" . ($is_current_user ? ' class="current-user"' : '') . ">";
                            
                            // Columna de posición
                            echo "<td>";
                            if($position == 1) {
                                echo '<i class="fas fa-trophy trophy-gold me-2"></i>';
                            } else if($position == 2) {
                                echo '<i class="fas fa-trophy trophy-silver me-2"></i>';
                            } else if($position == 3) {
                                echo '<i class="fas fa-trophy trophy-bronze me-2"></i>';
                            }
                            echo $position . "</td>";
                            
                            // Nombre de usuario
                            echo "<td>" . htmlspecialchars($row['nombre_usuario']) . "</td>";
                            
                            // Puntaje total
                            echo "<td>" . number_format($row['puntaje_total']) . "</td>";
                            
                            // Partidas jugadas
                            echo "<td>" . $row['partidas_jugadas'] . "</td>";
                            
                            // Promedio por partida
                            echo "<td>" . $promedio . "</td>";
                            
                            echo "</tr>";
                            $position++;
                        }
                        
                        // Si no hay datos en el ranking
                        if($position == 1) {
                            echo "<tr><td colspan='5' class='text-center'>No hay datos de ranking aún</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="mt-4 text-center">
            <a href="index.php" class="btn btn-outline-light">
                <i class="fas fa-home me-2"></i>Volver al Inicio
            </a>
            <?php if($logged_in): ?>
            <a href="juego.php" class="btn btn-danger ms-2">
                <i class="fas fa-play-circle me-2"></i>¡Jugar Ahora!
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Scripts de Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>