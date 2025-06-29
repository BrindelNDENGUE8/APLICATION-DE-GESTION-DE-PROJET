<?php
session_start();
require_once 'connexion.php';

header('Content-Type: application/json');

// --- Sécurité et validation des entrées ---
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Accès non autorisé.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? null;
$task_id = $_POST['task_id'] ?? null;

if (!$action || !$task_id) {
    echo json_encode(['success' => false, 'error' => 'Données manquantes.']);
    exit();
}

// --- Vérifier que l'utilisateur est bien assigné à cette tâche ---
$check_stmt = $pdo->prepare("SELECT COUNT(*) FROM taches WHERE id = ? AND assigne_a = ?");
$check_stmt->execute([$task_id, $user_id]);
if ($check_stmt->fetchColumn() == 0) {
    echo json_encode(['success' => false, 'error' => 'Permission refusée pour cette tâche.']);
    exit();
}

// --- Routeur d'actions ---
try {
    switch ($action) {
        case 'update_status':
            $new_status = $_POST['status'] ?? null;
            if (!in_array($new_status, ['non commencé', 'en cours', 'terminé'])) {
                throw new Exception('Statut invalide.');
            }
            // Si on passe à "terminé", la progression passe à 100%. Si on quitte "terminé", elle passe à 95%
            $progress_update = "";
            if ($new_status === 'terminé') {
                $progress_update = ", progression = 100";
            } else if ($new_status !== 'terminé') {
                $progress_update = ", progression = LEAST(progression, 95)"; // N'augmente pas la progression
            }

            $stmt = $pdo->prepare("UPDATE taches SET statut = ?, modifie_par = ?, derniere_modification = NOW() $progress_update WHERE id = ?");
            $stmt->execute([$new_status, $user_id, $task_id]);
            log_action($pdo, $task_id, $user_id, "a changé le statut à '{$new_status}'.");
            break;
        
        case 'update_progress':
            $new_progress = (int)($_POST['progression'] ?? 0);
            if ($new_progress < 0 || $new_progress > 100) {
                 throw new Exception('Progression invalide.');
            }
            
            // Mettre à jour le statut en fonction de la progression
            $new_status = 'en cours';
            if ($new_progress == 0) $new_status = 'non commencé';
            if ($new_progress == 100) $new_status = 'terminé';

            $stmt = $pdo->prepare("UPDATE taches SET progression = ?, statut = ?, modifie_par = ?, derniere_modification = NOW() WHERE id = ?");
            $stmt->execute([$new_progress, $new_status, $user_id, $task_id]);
            log_action($pdo, $task_id, $user_id, "a mis à jour la progression à {$new_progress}%.");
            break;
            
        default:
            throw new Exception('Action inconnue.');
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


function log_action($pdo, $tache_id, $user_id, $action_text) {
    try {
        $stmt = $pdo->prepare("INSERT INTO historique (tache_id, utilisateur_id, action) VALUES (?, ?, ?)");
        $stmt->execute([$tache_id, $user_id, $action_text]);
    } catch (PDOException $e) {
        // Ne pas bloquer l'action principale si le log échoue
        error_log("Failed to log action: " . $e->getMessage());
    }
}
