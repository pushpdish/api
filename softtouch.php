<?php
$host = "localhost";
$username = "pushpdish";
$password = "pushpdish";
$dbname = "softtouch";

// Create a new connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection errors
if ($conn->connect_error) {
    $inputResult = ["success" => false, "message" => "Oops! The stars aren't aligned: " . $conn->connect_error];
    echo json_encode($inputResult);
    exit();
}

$inputResult = [];

$requestMethod = $_SERVER["REQUEST_METHOD"];
$data = json_decode(file_get_contents("php://input"), true);

// Handle requests based on method
switch ($requestMethod) {
    case "GET":
        if (isset($_GET["endpoint"])) {
            $endpoint = $_GET["endpoint"];
            $inputResult = fetchEntities($endpoint, $conn);
        } else {
            $inputResult = ["success" => false, "message" => "Endpoint missing! It's like trying to find a needle in a haystack without a magnet"];
        }
        break;
    case "POST":
        if (isset($data["endpoint"])) {
            $endpoint = $data["endpoint"];
            unset($data["endpoint"]); 
            $inputResult = insertDataEntity($endpoint, $data, $conn); 
        } else {
            $inputResult = ["success" => false, "message" => "Houston, we have a problem! Missing endpoint in your spaceship... erm, request body"];
        }
        break;
    case "PUT":
        if (isset($data["endpoint"]) && isset($data["id"])) {
            $endpoint = $data["endpoint"];
            $id = $data["id"];
            unset($data["endpoint"]); 
            $inputResult = updateDataEntity($endpoint, $data, $id, $conn);
        } else {
            $inputResult = ["success" => false, "message" => "It's a bit cloudy today! Can't find your endpoint or id in the sky of your request body"];
        }
        break;
    case "DELETE":
        if (isset($data["endpoint"]) && isset($data["id"])) {
            $endpoint = $data["endpoint"];
            $id = $data["id"];
            $inputResult = deleteDataEntity($endpoint, $id, $conn);
        } else {
            $inputResult = ["success" => false, "message" => "Looks like we're missing a map! Can't find endpoint or id in your treasure chest of a request body"];
        }
        break;
    default:
        $inputResult = ["success" => false, "message" => "Unsupported request method: " . $requestMethod];
}


$conn->close();

// Output the result
echo json_encode($inputResult);

function insertDataEntity($tableName, $data, $conn) {
    
    $columns = implode(", ", array_keys($data));
    $placeholders = implode(", ", array_fill(0, count($data), '?'));
    $types = getTypesString($data);
    $values = array_values($data);

    $stmt = $conn->prepare("INSERT INTO $tableName ($columns) VALUES ($placeholders)");
    if (!$stmt) {
        return ["success" => false, "message" => "Prepare failed: " . $conn->error];
    }

    $stmt->bind_param($types, ...$values);
    if ($stmt->execute()) {
        return ["success" => true, "message" => "Data inserted successfully"];
    } else {
        return ["success" => false, "message" => "Execute failed: " . $stmt->error];
    }
}
function updateDataEntity($tableName, $data, $id, $conn) {
    $assignments = [];
    foreach ($data as $column => $value) {
        $assignments[] = "$column = ?";
    }
    $setClause = implode(", ", $assignments);
    $types = getTypesString($data) . 'i'; 
    $values = array_merge(array_values($data), [$id]);

    $stmt = $conn->prepare("UPDATE $tableName SET $setClause WHERE id = ?");
    if (!$stmt) {
        return ["success" => false, "message" => "Prepare failed: " . $conn->error];
    }

    $stmt->bind_param($types, ...$values);
    if ($stmt->execute()) {
        return ["success" => true, "message" => "Data updated successfully"];
    } else {
        return ["success" => false, "message" => "Execute failed: " . $stmt->error];
    }
}

function deleteDataEntity($tableName, $id, $conn) {
    
    $stmt = $conn->prepare("DELETE FROM $tableName WHERE id = ?");
    if (!$stmt) {
        return ["success" => false, "message" => "Prepare failed: " . $conn->error];
    }

    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        return ["success" => true, "message" => "Data deleted successfully"];
    } else {
        return ["success" => false, "message" => "Execute failed: " . $stmt->error];
    }
}


// Function to fetch entities from the database
function fetchEntities($tableName, $conn) {
    $inputResult = [];
    $entities = [];

    
    $validTableNames = ['users', 'products', 'cart', 'comments', 'order', ]; 
    if (!in_array($tableName, $validTableNames)) {
        return ["success" => false, "message" => "Invalid table name"];
    }

    $stmt = $conn->prepare("SELECT * FROM $tableName");
    if ($stmt && $stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $entities[] = $row;
        }
        $inputResult = ["success" => true, "data" => $entities];
    } else {
        $inputResult = ["success" => false, "message" => "Error fetching $tableName: " . $conn->error];
    }
    $stmt->close();
    return $inputResult;
}
function getTypesString($data) {
    $types = '';
    foreach ($data as $value) {
        switch (gettype($value)) {
            case 'integer':
                $types .= 'i'; // integer
                break;
            case 'double':
                $types .= 'd'; // double
                break;
            case 'string':
                $types .= 's'; // string
                break;
            default:
                $types .= 'b'; // blob and unknown
                break;
        }
    }
    return $types;
}
