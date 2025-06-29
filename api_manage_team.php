<?php
session_start();
require_once 'connexion.php';

header('Content-Type: application/json');

// --- Sécurité et validation des entrées ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chef') {
    echo json_encode(['success' => false, 'error' => 'Accès non autorisé.']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;
if (!$action) {
    echo json_encode(['success' => false, 'error' => 'Aucune action spécifiée.']);
    exit();
}

try {
    // --- Routeur d'actions ---
    switch ($action) {
        case 'get_project_data':
            get_project_data($pdo);
            break;
        case 'add_member':
            add_member_to_team($pdo);
            break;
        case 'remove_member':
            remove_member_from_team($pdo);
            break;
        case 'assign_task':
            assign_task_to_member($pdo);
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Action inconnue.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur de base de données : ' . $e->getMessage()]);
}

// --- Fonctions pour chaque action ---

function get_project_data($pdo) {
    $project_id = $_GET['project_id'];
    
    // 1. Récupérer les membres de l'équipe
    $team_stmt = $pdo->prepare("SELECT u.id, u.nom FROM users u JOIN equipe_membres em ON u.id = em.utilisateur_id JOIN equipes e ON em.equipe_id = e.id WHERE e.projet_id = ?");
    $team_stmt->execute([$project_id]);
    $team_members = $team_stmt->fetchAll(PDO::FETCH_ASSOC);
    $team_member_ids = array_column($team_members, 'id');

    // 2. Récupérer les utilisateurs disponibles (ceux qui sont 'membre' et pas déjà dans l'équipe)
    $available_sql = "SELECT id, nom FROM users WHERE role = 'membre'";
    if (!empty($team_member_ids)) {
        $placeholders = implode(',', array_fill(0, count($team_member_ids), '?'));
        $available_sql .= " AND id NOT IN ($placeholders)";
    }
    $available_stmt = $pdo->prepare($available_sql);
    $available_stmt->execute($team_member_ids);
    $available_users = $available_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Récupérer les tâches du projet
    $tasks_stmt = $pdo->prepare("SELECT id, nom, assigne_a FROM taches WHERE projet_id = ? ORDER BY nom");
    $tasks_stmt->execute([$project_id]);
    $tasks = $tasks_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'team_members' => $team_members,
        'available_users' => $available_users,
        'tasks' => $tasks
    ]);
}

function add_member_to_team($pdo) {
    $project_id = $_POST['project_id'];
    $user_id = $_POST['user_id'];
    
    // Trouver l'ID de l'équipe pour ce projet
    $equipe_stmt = $pdo->prepare("SELECT id FROM equipes WHERE projet_id = ?");
    $equipe_stmt->execute([$project_id]);
    $equipe_id = $equipe_stmt->fetchColumn();
    
    if (!$equipe_id) { // Si l'équipe n'existe pas, on pourrait la créer ici si nécessaire
        echo json_encode(['success' => false, 'error' => 'Équipe non trouvée pour ce projet.']);
        return;
    }
    
    $insert_stmt = $pdo->prepare("INSERT INTO equipe_membres (equipe_id, utilisateur_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
    $insert_stmt->execute([$equipe_id, $user_id]);
    
    echo json_encode(['success' => true]);
}

function remove_member_from_team($pdo) {
    $project_id = $_POST['project_id'];
    $user_id = $_POST['user_id'];

    $equipe_stmt = $pdo->prepare("SELECT id FROM equipes WHERE projet_id = ?");
    $equipe_stmt->execute([$project_id]);
    $equipe_id = $equipe_stmt->fetchColumn();

    if ($equipe_id) {
        $delete_stmt = $pdo->prepare("DELETE FROM equipe_membres WHERE equipe_id = ? AND utilisateur_id = ?");
        $delete_stmt->execute([$equipe_id, $user_id]);
        
        // Optionnel : Désassigner toutes les tâches de ce membre pour ce projet
        $unassign_stmt = $pdo->prepare("UPDATE taches SET assigne_a = NULL WHERE projet_id = ? AND assigne_a = ?");
        $unassign_stmt->execute([$project_id, $user_id]);
    }

    echo json_encode(['success' => true]);
}

function assign_task_to_member($pdo) {
    $task_id = $_POST['task_id'];
    $user_id = !empty($_POST['user_id']) ? $_POST['user_id'] : null;
    
    $stmt = $pdo->prepare("UPDATE taches SET assigne_a = ? WHERE id = ?");
    $stmt->execute([$user_id, $task_id]);
    
    echo json_encode(['success' => true, 'message' => 'Tâche assignée.']);
}

?>
