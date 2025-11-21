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
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
    exit();
}

try {
    $conn = conectarDB();
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
$id = $data['id'] ?? null;
$file_path = $data['file_path'] ?? null;

if (!is_numeric($id) || empty($file_path)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Datos de eliminación inválidos."]);
    $conn->close();
    exit();
}

$id_escaped = $conn->real_escape_string($id);

// Eliminación de archivo físico
if (file_exists($file_path)) {
    unlink($file_path);
}

// Eliminación de metadata en la DB
$sql = "DELETE FROM historiales WHERE id = '$id_escaped'";

if ($conn->query($sql) === TRUE) {
    http_response_code(200);
    echo json_encode(["success" => true, "message" => "Historial eliminado."]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error al eliminar registro: " . $conn->error]);
}

$conn->close();
?>