<?php
require_once 'config.php';
if (!isLoggedIn()) exit;

$q = $_GET['q'] ?? '';
if (strlen($q) < 2) exit;

$user_id = getUserId();
$stmt = $conn->prepare("
    SELECT id, name, email FROM users 
    WHERE (name LIKE ? OR email LIKE ?) 
    AND id != ? 
    AND id NOT IN (SELECT blocked_id FROM blocks WHERE blocker_id = ?)
    LIMIT 10
");
$like = "%$q%";
$stmt->bind_param("ssii", $like, $like, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    echo "<a href='profile.php?username=" . urlencode($row['email']) . "' style='display:block;padding:15px;border-bottom:1px solid rgba(139,92,246,0.2);color:#f3e8ff;text-decoration:none;'>";
    echo "👤 " . htmlspecialchars($row['name']) . "<br><small style='color:#c084fc;'>" . htmlspecialchars($row['email']) . "</small>";
    echo "</a>";
}

?>
