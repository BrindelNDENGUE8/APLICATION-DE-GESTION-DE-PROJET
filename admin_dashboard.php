<?php
// ... (code de session et de connexion)

// Récupérer la liste de tous les utilisateurs
try {
    // Les noms des colonnes sont corrects par rapport à votre schéma
    $stmt = $pdo->query("SELECT id, email, role, nom FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors du chargement des utilisateurs: " . $e->getMessage();
    $users = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Tableau de Bord Admin - Gestion des Utilisateurs</title>
</head>
<body>
    <div class="admin-dashboard-container">
        <h1><i class="fas fa-users"></i> Gestion des Utilisateurs</h1>

        <?php if (!empty($error)): ?>
            <p class="error-message"><?php echo $error; ?></p>
        <?php endif; ?>
        <?php
        // Récupérer les messages passés via l'URL (depuis delete_user.php par exemple)
        if (isset($_GET['message']) && !empty($_GET['message'])) {
            echo '<p class="message success">' . htmlspecialchars($_GET['message']) . '</p>';
        }
        if (isset($_GET['error']) && !empty($_GET['error'])) {
            echo '<p class="message error">' . htmlspecialchars($_GET['error']) . '</p>';
        }
        ?>

        <div class="action-buttons">
            <a href="create_user.php"><i class="fas fa-user-plus"></i> Créer un nouvel utilisateur</a>
        </div>

        <table class="user-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th> <th>Email</th>
                    <th>Rôle</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td data-label="ID"><?php echo htmlspecialchars($user['id']); ?></td>
                            <td data-label="Nom"><?php echo htmlspecialchars($user['nom']); ?></td> <td data-label="Email"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td data-label="Rôle"><?php echo htmlspecialchars($user['role']); ?></td>
                            <td class="actions">
                                <a href="edit_user.php?id=<?php echo $user['id']; ?>" title="Modifier"><i class="fas fa-edit"></i> Modifier</a>
                                <a href="reset_password.php?id=<?php echo $user['id']; ?>" title="Réinitialiser le mot de passe"><i class="fas fa-key"></i> Mdp</a>
                                <a href="delete_user.php?id=<?php echo $user['id']; ?>" class="delete" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');" title="Supprimer"><i class="fas fa-trash-alt"></i> Supprimer</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">Aucun utilisateur trouvé.</td> </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>