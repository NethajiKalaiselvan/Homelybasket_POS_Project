<?php
require_once 'config/database.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

requireLogin();

// Fetch settings
$settings = $db->resultset("SELECT * FROM settings");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['settings'] as $key => $value) {
        $db->query(
            "UPDATE settings SET setting_value = :value WHERE setting_key = :key",
            [':value' => $value, ':key' => $key]
        );
    }
    header('Location: settings.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Supermarket Billing System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="container mt-4">
        <h2>Settings</h2>
        <form method="POST" action="settings.php">
            <div class="row">
                <?php foreach ($settings as $setting): ?>
                <div class="col-md-6 mb-3">
                    <label for="<?= $setting['setting_key'] ?>" class="form-label">
                        <?= htmlspecialchars($setting['setting_name'] ?? $setting['description'] ?? 'Unnamed Setting') ?>
                    </label>
                    <input type="text" id="<?= $setting['setting_key'] ?>" name="settings[<?= $setting['setting_key'] ?>]" 
                           class="form-control" value="<?= htmlspecialchars($setting['setting_value']) ?>">
                </div>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
