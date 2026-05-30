<?php
/**
 * check_gps_request.php
 * ─────────────────────
 * Called by the delivery person's page via fetch() every 5 seconds.
 * Returns JSON: { forced: bool, admin: string|null }
 *
 * The delivery person's browser checks this endpoint and shows
 * the mandatory GPS modal if forced = true.
 */

session_start();
header("Content-Type: application/json");

if (!isset($_SESSION["table"]) || $_SESSION["table"] !== "deliveryperson") {
    echo json_encode(["forced" => false]);
    exit;
}

$phone = $_SESSION["phone"] ?? "";
if (!$phone) { echo json_encode(["forced" => false]); exit; }

$conn = mysqli_connect("localhost", "root", "", "biomegadb");
$phone_escaped = mysqli_real_escape_string($conn, $phone);

$res = mysqli_query($conn, "SELECT GpsForced, ForcedByAdmin FROM delivery_location WHERE PhoneNumber = '$phone_escaped' LIMIT 1");
$row = mysqli_fetch_assoc($res);
mysqli_close($conn);

echo json_encode([
    "forced" => $row && $row["GpsForced"] == 1,
    "admin"  => $row["ForcedByAdmin"] ?? null,
]);
