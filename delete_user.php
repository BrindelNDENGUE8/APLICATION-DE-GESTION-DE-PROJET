<?php
session_start();
require_once 'connexion.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: connexion_admin.php');
    exit();
}

$user_id = $_GET['id'] ?? null;
$message = '';
$error = '';

if ($user_id) {
    try {
        // Optionnel : Vérifier si l'admin n'essaie pas de supprimer son propre compte
        if ($user_id == $_SESSION['user_id']) {
            $error = "Vous ne pouvez pas supprimer votre propre compte.";
        } else {
            // Vérifier que l'utilisateur existe avant de tenter de le supprimer
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
            $checkStmt->execute([$user_id]);
            if ($checkStmt->fetchColumn() === 0) {
                 $error = "Utilisateur non trouvé.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                if ($stmt->rowCount() > 0) {
                    $message = "Utilisateur supprimé avec succès.";
                } else {
                    // Cette partie est normalement couverte par le check précédent, mais par sécurité.
                    $error = "La suppression de l'utilisateur a échoué.";
                }
            }
        }
    } catch (PDOException $e) {
        $error = "Erreur lors de la suppression de l'utilisateur: " . $e->getMessage();
    }
} else {
    $error = "ID d'utilisateur manquant pour la suppression.";
}

// Rediriger vers le tableau de bord avec les messages
header('Location: admin_dashboard.php?message=' . urlencode($message) . '&error=' . urlencode($error));
exit();
?>