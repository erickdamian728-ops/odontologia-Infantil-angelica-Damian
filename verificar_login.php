<?php
// ===============================================
// CONFIGURACIÓN DE LA BASE DE DATOS (INCLUIDA AQUÍ)
// ===============================================
$servername = "localhost"; 
$username = "root"; 
$password = ""; 
$dbname = "odontologia_db"; 
$port = 3307; 

function conectarDB() {
    global $servername, $username, $password, $dbname, $port;
    $conn = new mysqli($servername, $username, $password, $dbname, $port); 
    if ($conn->connect_error) {
        // En caso de fallo de conexión a la DB
        http_response_code(500);
        die(json_encode(["success" => false, "message" => "Fallo de conexión a la DB: " . $conn->connect_error]));
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? '';
$password_ingresada = $data['password'] ?? '';

if (empty($email) || empty($password_ingresada)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Ingrese usuario y contraseña."]);
    exit();
}

try {
    $conn = conectarDB();
    
    // 1. Buscar usuario por email
    $stmt = $conn->prepare("SELECT password, nombre FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $hash_almacenado = $user['password'];
        
        // 2. Verificar la contraseña ingresada contra el hash
        if (password_verify($password_ingresada, $hash_almacenado)) {
            // Éxito en el login
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Acceso concedido.", "user" => $user['nombre']]);
        } else {
            // Contraseña incorrecta
            http_response_code(401);
            echo json_encode(["success" => false, "message" => "Credenciales inválidas."]);
        }
    } else {
        // Usuario no encontrado
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Credenciales inválidas."]);
    }
    
    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error del servidor: " . $e->getMessage()]);
}
?>