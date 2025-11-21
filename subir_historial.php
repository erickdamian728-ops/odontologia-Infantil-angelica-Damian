<?php
// ===============================================
// CONFIGURACIÓN DE LA BASE DE DATOS
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

// Ruta donde se guardarán los archivos
$upload_dir = 'historial_files/';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error de permisos: No se pudo crear el directorio de subida."]);
        exit();
    }
}

try {
    $conn = conectarDB();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
    exit();
}

$paciente = $_POST['paciente'] ?? '';
$descripcion = $_POST['descripcion'] ?? '';

if (empty($paciente) || empty($descripcion) || !isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Datos o archivo incompletos."]);
    $conn->close();
    exit();
}

$paciente_escaped = $conn->real_escape_string($paciente);
$descripcion_escaped = $conn->real_escape_string($descripcion);

// --- Manejo de la subida del archivo (SANITIZADO) ---
$file_info = pathinfo($_FILES['archivo']['name']);
$file_ext = $file_info['extension'];

// 1. Limpiar el nombre del paciente y reemplazar espacios/caracteres no deseados por guiones bajos
$base_paciente_name = preg_replace('/[^a-zA-Z0-9\s]/', '', $paciente_escaped); 
$base_paciente_name = str_replace(' ', '_', $base_paciente_name);

// 2. Construir el nombre final del archivo
$file_name = $base_paciente_name . '_' . time() . '.' . $file_ext; 
$file_path = $upload_dir . $file_name;

// 3. CORRECCIÓN CRÍTICA: Reemplazar la barra invertida de Windows por la barra diagonal de la web (URL)
$file_path = str_replace('\\', '/', $file_path); 

if (move_uploaded_file($_FILES['archivo']['tmp_name'], $file_path)) {
    
    // --- Guardar metadata en la DB ---
    $file_path_escaped = $conn->real_escape_string($file_path);
    $file_name_escaped = $conn->real_escape_string($file_name);
    
    $sql = "INSERT INTO historiales (paciente, descripcion, file_name, file_path) 
            VALUES (
                '$paciente_escaped', 
                '$descripcion_escaped', 
                '$file_name_escaped', 
                '$file_path_escaped'
            )";

    if ($conn->query($sql) === TRUE) {
        http_response_code(201);
        echo json_encode(["success" => true, "message" => "Historial y archivo guardados con éxito."]);
    } else {
        unlink($file_path); 
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error al guardar metadata: " . $conn->error]);
    }
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error al mover el archivo subido al directorio."]);
}

$conn->close();
?>