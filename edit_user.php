<?php
session_start();
require_once 'connexion.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: connexion_admin.php');
    exit();
}

$user_id = $_GET['id'] ?? null;
$user = null;
$message = '';
$error = '';

if ($user_id) {
    try {
        // Sélectionner le nom également
        $stmt = $pdo->prepare("SELECT id, nom, email, role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = "Utilisateur non trouvé.";
        }
    } catch (PDOException $e) {
        $error = "Erreur lors du chargement de l'utilisateur: " . $e->getMessage();
    }
} else {
    $error = "ID d'utilisateur manquant.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $new_nom = $_POST['nom'] ?? $user['nom']; // Nouveau champ
    $new_email = $_POST['email'] ?? $user['email'];
    $new_role = $_POST['role'] ?? $user['role'];

    // Validation basique
    if (empty($new_nom) || empty($new_email) || empty($new_role)) {
        $error = "Le nom, l'email et le rôle sont obligatoires.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format d'email invalide.";
    } elseif (!in_array($new_role, ['admin', 'chef', 'membre'])) { // Vérifier les rôles valides
        $error = "Rôle invalide sélectionné.";
    } else {
        try {
            // Vérifier si le nouvel email existe déjà pour un AUTRE utilisateur
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
            $checkStmt->execute([$new_email, $user_id]);
            if ($checkStmt->fetchColumn() > 0) {
                $error = "Cet email est déjà utilisé par un autre utilisateur.";
            } else {
                $updateStmt = $pdo->prepare("UPDATE users SET nom = ?, email = ?, role = ? WHERE id = ?");
                $updateStmt->execute([$new_nom, $new_email, $new_role, $user_id]);
                $message = "Utilisateur mis à jour avec succès !";
                // Mettre à jour les données de l'utilisateur affichées sur la page
                $user['nom'] = $new_nom;
                $user['email'] = $new_email;
                $user['role'] = $new_role;
            }
        } catch (PDOException $e) {
            $error = "Erreur lors de la mise à jour: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Modifier Utilisateur</title>
</head>
<body>
    <div class="form-container">
        <h2><i class="fas fa-edit"></i> Modifier l'utilisateur</h2>

        <?php if (!empty($message)): ?>
            <p class="message success"><?php echo $message; ?></p>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <p class="message error"><?php echo $error; ?></p>
        <?php endif; ?>

        <?php if ($user): ?>
            <form method="post">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($user['id']); ?>">
                <input type="text" name="nom" placeholder="Nom" value="<?php echo htmlspecialchars($user['nom']); ?>" required autocomplete="off"> <input type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($user['email']); ?>" required autocomplete="off">
                <select name="role" required>
                    <option value="admin" <?php echo ($user['role'] == 'admin') ? 'selected' : ''; ?>>Administrateur</option>
                    <option value="chef" <?php echo ($user['role'] == 'chef') ? 'selected' : ''; ?>>Chef de Projet</option>
                    <option value="membre" <?php echo ($user['role'] == 'membre') ? 'selected' : ''; ?>>Membre d'Équipe</option>
                </select>
                <button type="submit"><i class="fas fa-save"></i> Mettre à jour l'utilisateur</button>
            </form>
        <?php else: ?>
            <p>Impossible de charger les détails de l'utilisateur.</p>
        <?php endif; ?>
        <a href="admin_dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Retour au Tableau de Bord</a>
    </div>
</body>
</html>