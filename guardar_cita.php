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
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

$tipo = $data['tipo'] ?? '';
$nombre = $data['nombre'] ?? '';
$telefono = $data['telefono'] ?? '';

// Variables condicionales
$responsable = $data['responsable'] ?? null;
$email = $data['email'] ?? null;
$fecha = $data['fecha'] ?? null;
$hora = $data['hora'] ?? null;
$motivo = $data['motivo'] ?? null;

if (empty($nombre) || empty($telefono) || empty($tipo)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Datos de cita incompletos."]);
    $conn->close();
    exit();
}

// Escapar y sanitizar
$tipo = $conn->real_escape_string($tipo);
$nombre = $conn->real_escape_string($nombre);
$telefono = $conn->real_escape_string($telefono);

// Lógica de validación de disponibilidad para Citas Regulares
if ($tipo === 'Cita Regular' && !empty($fecha) && !empty($hora)) {
    
    $fecha_escaped = $conn->real_escape_string($fecha);
    $hora_escaped = $conn->real_escape_string($hora);
    
    // CORRECCIÓN: Usamos SUBTIME y ADDTIME para mayor compatibilidad SQL
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM citas  
        WHERE fecha = ?  
        AND hora BETWEEN SUBTIME(?, '00:30:00') AND ADDTIME(?, '00:30:00')
    ");
    $stmt->bind_param("sss", $fecha_escaped, $hora_escaped, $hora_escaped);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count > 0) {
        http_response_code(409);
        echo json_encode(["success" => false, "message" => "❌ Lo sentimos, esa hora ya está ocupada o se superpone con otra cita existente."]);
        $conn->close();
        exit();
    }
    
    // Preparar valores para SQL (Cita Regular)
    $responsable_sql = $responsable ? "'" . $conn->real_escape_string($responsable) . "'" : "NULL";
    $email_sql = $email ? "'" . $conn->real_escape_string($email) . "'" : "NULL";
    $fecha_sql = "'" . $fecha_escaped . "'";
    $hora_sql = "'" . $hora_escaped . "'";
    $motivo_sql = "NULL"; 

// LÓGICA CORREGIDA: Verifica que el motivo NO esté vacío
} elseif ($tipo === 'Emergencia' && !empty($motivo)) { 
    // Preparar valores para SQL (Emergencia)
    $responsable_sql = "NULL";
    $email_sql = $email ? "'" . $conn->real_escape_string($email) . "'" : "NULL";
    $fecha_sql = "NULL";
    $hora_sql = "NULL";
    $motivo_sql = "'" . $conn->real_escape_string($motivo) . "'";
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Faltan campos requeridos para el tipo de cita."]);
    $conn->close();
    exit();
}


$sql = "INSERT INTO citas (tipo, nombre, responsable, email, telefono, fecha, hora, motivo) 
        VALUES (
            '$tipo', 
            '$nombre', 
            $responsable_sql, 
            $email_sql, 
            '$telefono', 
            $fecha_sql, 
            $hora_sql, 
            $motivo_sql
        )";

if ($conn->query($sql) === TRUE) {
    http_response_code(201);
    echo json_encode(["success" => true, "message" => "Cita agendada correctamente.", "id" => $conn->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error al agendar la cita: " . $conn->error]);
}

$conn->close();
?>