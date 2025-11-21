<?php
// ===============================================
// CONFIGURACIÓN DE LA BASE DE DATOS (INCLUIDA AQUÍ)
// ===============================================
$servername = "localhost"; 
$username = "root"; 
$password = ""; 
$dbname = "odontologia_db"; 
$port = 3307; 

// ===============================================
// FUNCIÓN PARA CONECTAR Y RETORNAR EL OBJETO $conn (INCLUIDA AQUÍ)
// ===============================================
function conectarDB() {
    global $servername, $username, $password, $dbname, $port;
    
    // Conexión a MySQL
    $conn = new mysqli($servername, $username, $password, $dbname, $port); 

    // Verificar la conexión
    if ($conn->connect_error) {
        throw new Exception("Fallo de conexión a MySQL en el host/puerto: " . $conn->connect_error);
    }
    
    // Asegurar el juego de caracteres
    $conn->set_charset("utf8mb4");

    return $conn;
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
    exit();
}

try {
    $conn = conectarDB();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
    exit();
}

$sql = "SELECT id, tipo, nombre, responsable, email, telefono, fecha, hora, motivo, fecha_registro 
        FROM citas 
        ORDER BY fecha DESC, hora ASC, tipo ASC";

$result = $conn->query($sql);
$citas = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $citas[] = $row;
    }
    http_response_code(200);
    echo json_encode(["success" => true, "citas" => $citas]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error al consultar citas: " . $conn->error]);
}

$conn->close();
?>