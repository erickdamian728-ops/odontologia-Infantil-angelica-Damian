<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Método no permitido."]);
    exit();
}

// ===============================================
// CONFIGURACIÓN DE LA BASE DE DATOS
// ===============================================
$servername = "localhost"; 
$username = "root"; 
$password = ""; 
$dbname = "odontologia_db"; 
$port = 3307; 

// ===============================================
// FUNCIÓN PARA CONECTAR Y RETORNAR EL OBJETO $conn
// ===============================================
function conectarDB() {
    global $servername, $username, $password, $dbname, $port;
    
    $conn = new mysqli($servername, $username, $password, $dbname, $port); 

    if ($conn->connect_error) {
        throw new Exception("Fallo de conexión a MySQL en el host/puerto: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");

    return $conn;
}

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

try {
    $conn = conectarDB();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

$id = $data['id'] ?? null;
$tipo = $data['tipo'] ?? '';
$nombre = $data['nombre'] ?? '';
$responsable = $data['responsable'] ?? null;
$email = $data['email'] ?? null;
$telefono = $data['telefono'] ?? '';
$fecha = $data['fecha'] ?? null;
$hora = $data['hora'] ?? null;
$motivo = $data['motivo'] ?? null;

if (!is_numeric($id) || empty($nombre) || empty($telefono) || empty($tipo)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Datos de cita incompletos o ID inválido."]);
    $conn->close();
    exit();
}

$id = $conn->real_escape_string($id);
$tipo_escaped = $conn->real_escape_string($tipo);
$nombre_escaped = $conn->real_escape_string($nombre);
$telefono_escaped = $conn->real_escape_string($telefono);

// --- PREPARACIÓN DE VALORES (Garantizando NULL o STRING) ---

$responsable_sql = "NULL";
$email_sql = "NULL";
$fecha_sql = "NULL";
$hora_sql = "NULL";
$motivo_sql = "NULL";

if ($responsable) $responsable_sql = "'" . $conn->real_escape_string($responsable) . "'";
if ($email) $email_sql = "'" . $conn->real_escape_string($email) . "'";
if ($motivo) $motivo_sql = "'" . $conn->real_escape_string($motivo) . "'";


// Lógica de validación para Cita Regular
if ($tipo === 'Cita Regular' && !empty($fecha) && !empty($hora)) {
    
    $fecha_escaped = $conn->real_escape_string($fecha);
    $hora_escaped = $conn->real_escape_string($hora);

    // Validación de superposición (CORREGIDA LA SINTAXIS SQL)
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM citas 
        WHERE fecha = ? 
        AND hora BETWEEN SUBTIME(?, '00:30:00') AND ADDTIME(?, '00:30:00')
        AND id != ?
    ");
    $stmt->bind_param("sssi", $fecha_escaped, $hora_escaped, $hora_escaped, $id);
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
    
    // Asignamos valores de fecha y hora solo si es Cita Regular
    $fecha_sql = "'" . $fecha_escaped . "'";
    $hora_sql = "'" . $hora_escaped . "'";
    $motivo_sql = "NULL"; // Se fuerza a NULL si es Cita Regular
    $responsable_sql = $responsable ? "'" . $conn->real_escape_string($responsable) . "'" : "NULL"; 
    $email_sql = $email ? "'" . $conn->real_escape_string($email) . "'" : "NULL"; 

} else if ($tipo === 'Emergencia' && !empty($motivo)) {
    // Si es emergencia, las variables de fecha/hora/responsable se quedan como NULL
    $fecha_sql = "NULL";
    $hora_sql = "NULL";
    $responsable_sql = "NULL";
    $email_sql = $email ? "'" . $conn->real_escape_string($email) . "'" : "NULL";
    $motivo_sql = "'" . $conn->real_escape_string($motivo) . "'";
    
} else {
    // En caso de que se intente actualizar a Cita Regular sin fecha/hora o Emergencia sin motivo
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Faltan campos requeridos para la actualización."]);
    $conn->close();
    exit();
}


// --- SENTENCIA SQL FINAL (USANDO LOS VALORES PREPARADOS) ---
$sql = "UPDATE citas SET 
        tipo = '$tipo_escaped', 
        nombre = '$nombre_escaped', 
        responsable = " . $responsable_sql . ", 
        email = " . $email_sql . ", 
        telefono = '$telefono_escaped', 
        fecha = " . $fecha_sql . ", 
        hora = " . $hora_sql . ", 
        motivo = " . $motivo_sql . "
        WHERE id = $id";


if ($conn->query($sql) === TRUE) {
    http_response_code(200);
    echo json_encode(["success" => true, "message" => "Cita actualizada correctamente."]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error al actualizar la cita: " . $conn->error]);
}

$conn->close();
?>