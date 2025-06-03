<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch contacts for this user
$stmt = $pdo->prepare("SELECT name, email, phone, address, group_id FROM contacts WHERE user_id = ?");
$stmt->execute([$user_id]);
$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers to force download of CSV file
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=contacts_export.csv');

// Open output stream
$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, ['Name', 'Email', 'Phone', 'Address', 'GroupID']);

// Output each contact row
foreach ($contacts as $contact) {
    $row = [
        isset($contact['name']) ? $contact['name'] : '',
        isset($contact['email']) ? $contact['email'] : '',
        isset($contact['phone']) ? $contact['phone'] : '',
        isset($contact['address']) ? $contact['address'] : '',
        isset($contact['group_id']) ? $contact['group_id'] : ''
    ];
    fputcsv($output, $row);
}

fclose($output);
exit();
?>
