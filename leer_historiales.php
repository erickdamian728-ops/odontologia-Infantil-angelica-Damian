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

$search = $_GET['search'] ?? '';

$sql = "SELECT id, paciente, descripcion, file_name, file_path, fecha_subida 
        FROM historiales";

if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $sql .= " WHERE paciente LIKE '%$search_escaped%'";
}

$sql .= " ORDER BY fecha_subida DESC";

$result = $conn->query($sql);
$historiales = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $historiales[] = $row;
    }
    http_response_code(200);
    echo json_encode(["success" => true, "historiales" => $historiales]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error al consultar historiales: " . $conn->error]);
}

$conn->close();
?>