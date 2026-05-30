<?php
/**
 * update_location.php
 * Called by delivery person's browser every ~20s to push GPS coordinates.
 * Also clears the GpsForced flag when clear_force = true is sent.
 *
 * POST JSON body:
 *   phone       — PhoneNumber
 *   password    — their password
 *   lat         — latitude
 *   lng         — longitude
 *   status      — 1=online, 0=offline (default 1)
 *   clear_force — true: also clear admin's GPS force request
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$raw   = file_get_contents("php://input");
$input = json_decode($raw, true) ?: $_POST;

$phone      = trim($input["phone"]    ?? "");
$password   = trim($input["password"] ?? "");
$lat        = $input["lat"]    ?? null;
$lng        = $input["lng"]    ?? null;
$status     = isset($input["status"])      ? (int)$input["status"]      : 1;
$clearForce = isset($input["clear_force"]) ? (bool)$input["clear_force"] : false;

if (!$phone || $lat === null || $lng === null) {
    http_response_code(400);
    echo json_encode(["success"=>false,"message"=>"Missing required fields"]);
    exit;
}
if (!is_numeric($lat) || !is_numeric($lng) ||
    $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    http_response_code(400);
    echo json_encode(["success"=>false,"message"=>"Invalid coordinates"]);
    exit;
}

$lat = round((float)$lat, 7);
$lng = round((float)$lng, 7);

$conn = mysqli_connect("localhost", "root", "", "biomegadb");
if (!$conn) { http_response_code(500); echo json_encode(["success"=>false,"message"=>"DB error"]); exit; }

// Authenticate
$stmt = mysqli_prepare($conn, "SELECT PhoneNumber FROM deliveryperson WHERE PhoneNumber=? AND Password=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "ss", $phone, $password);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
if (mysqli_stmt_num_rows($stmt) === 0) {
    http_response_code(401);
    echo json_encode(["success"=>false,"message"=>"Invalid credentials"]);
    mysqli_stmt_close($stmt); mysqli_close($conn); exit;
}
mysqli_stmt_close($stmt);

// Upsert current location
$gpsForced  = $clearForce ? 0 : "GpsForced"; // if clearing, set to 0
$forcedAt   = $clearForce ? "NULL"  : "ForcedAt";
$forcedBy   = $clearForce ? "NULL"  : "ForcedByAdmin";

if ($clearForce) {
    $stmt2 = mysqli_prepare($conn,
        "INSERT INTO delivery_location (PhoneNumber, Latitude, Longitude, Status, GpsForced, ForcedAt, ForcedByAdmin, UpdatedAt)
         VALUES (?, ?, ?, ?, 0, NULL, NULL, NOW())
         ON DUPLICATE KEY UPDATE
           Latitude=VALUES(Latitude), Longitude=VALUES(Longitude),
           Status=VALUES(Status), GpsForced=0, ForcedAt=NULL, ForcedByAdmin=NULL, UpdatedAt=NOW()"
    );
} else {
    $stmt2 = mysqli_prepare($conn,
        "INSERT INTO delivery_location (PhoneNumber, Latitude, Longitude, Status, UpdatedAt)
         VALUES (?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
           Latitude=VALUES(Latitude), Longitude=VALUES(Longitude),
           Status=VALUES(Status), UpdatedAt=NOW()"
    );
}
mysqli_stmt_bind_param($stmt2, "sddi", $phone, $lat, $lng, $status);
$ok = mysqli_stmt_execute($stmt2);
mysqli_stmt_close($stmt2);

// Append to history
if ($ok && $lat != 0 && $lng != 0) {
    $stmt3 = mysqli_prepare($conn, "INSERT INTO delivery_location_history (PhoneNumber,Latitude,Longitude,UpdatedAt) VALUES (?,?,?,NOW())");
    mysqli_stmt_bind_param($stmt3, "sdd", $phone, $lat, $lng);
    mysqli_stmt_execute($stmt3);
    mysqli_stmt_close($stmt3);
}

mysqli_close($conn);
echo json_encode(["success"=>$ok,"message"=>$ok?"Location updated":"Update failed","lat"=>$lat,"lng"=>$lng,"time"=>date("Y-m-d H:i:s")]);
