<?php
session_start();
require_once 'connexion.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: connexion_admin.php');
    exit();
}

$user_id = $_GET['id'] ?? null;
$user_email = '';
$user_nom = ''; // Nouveau champ pour le nom
$message = '';
$error = '';

if ($user_id) {
    try {
        // Sélectionner l'email et le nom
        $stmt = $pdo->prepare("SELECT email, nom FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $user_email = $user['email'];
            $user_nom = $user['nom']; // Stocker le nom
        } else {
            $error = "Utilisateur non trouvé.";
        }
    } catch (PDOException $e) {
        $error = "Erreur lors du chargement de l'utilisateur: " . $e->getMessage();
    }
} else {
    $error = "ID d'utilisateur manquant.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id && !$error) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $error = "Veuillez entrer et confirmer le nouveau mot de passe.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        try {
            // Hacher le nouveau mot de passe
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("UPDATE users SET mot_de_passe = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            $message = "Mot de passe réinitialisé avec succès pour " . htmlspecialchars($user_nom) . " (" . htmlspecialchars($user_email) . ").";
        } catch (PDOException $e) {
            $error = "Erreur lors de la réinitialisation du mot de passe: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Réinitialiser Mot de Passe</title>
</head>
<body>
    <div class="form-container">
        <h2><i class="fas fa-key"></i> Réinitialiser le mot de passe</h2>

        <?php if (!empty($message)): ?>
            <p class="message success"><?php echo $message; ?></p>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <p class="message error"><?php echo $error; ?></p>
        <?php endif; ?>

        <?php if ($user_id && !$error): ?>
            <p class="user-email-display">Pour l'utilisateur : <strong><?php echo htmlspecialchars($user_nom); ?></strong> (<?php echo htmlspecialchars($user_email); ?>)</p>
            <form method="post">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($user_id); ?>">
                <input type="password" name="new_password" placeholder="Nouveau mot de passe" required autocomplete="new-password">
                <input type="password" name="confirm_password" placeholder="Confirmer le nouveau mot de passe" required autocomplete="new-password">
                <button type="submit"><i class="fas fa-sync-alt"></i> Réinitialiser le mot de passe</button>
            </form>
        <?php else: ?>
            <p>Impossible de réinitialiser le mot de passe. Veuillez retourner au tableau de bord.</p>
        <?php endif; ?>
        <a href="admin_dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Retour au Tableau de Bord</a>
    </div>
</body>
</html>