<?php
session_start();
require_once 'config.php';

// Only respond to logged in users
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_verified']) || $_SESSION['is_verified'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if ID parameter is present
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
    exit();
}

$event_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Get the event data
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT * FROM events WHERE id = ? AND created_by = ?");
$stmt->bind_param('ii', $event_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Event not found or access denied']);
    $conn->close();
    exit();
}

$event = $result->fetch_assoc();
$conn->close();

// Return event data as JSON
header('Content-Type: application/json');
echo json_encode(['success' => true, 'event' => $event]);
exit();
?> 