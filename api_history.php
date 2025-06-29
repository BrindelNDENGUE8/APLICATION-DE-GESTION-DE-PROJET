<?php
session_start();
require_once 'connexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'];

$items_per_page = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Construction de la requête SQL de base
$base_sql = "
    FROM historique h
    JOIN users u ON h.utilisateur_id = u.id
    JOIN taches t ON h.tache_id = t.id
    JOIN projets p ON t.projet_id = p.id
";

$where_clauses = [];
$params = [];

// Filtres
if (!empty($_GET['project_id'])) {
    $where_clauses[] = "p.id = ?";
    $params[] = $_GET['project_id'];
}
if (!empty($_GET['user_id'])) {
    $where_clauses[] = "h.utilisateur_id = ?";
    $params[] = $_GET['user_id'];
}

// Sécurité : Restreindre la vue pour les non-admins
if ($current_user_role !== 'admin') {
    $where_clauses[] = "p.id IN (
        SELECT p.id FROM projets p
        LEFT JOIN equipes e ON p.id = e.projet_id
        LEFT JOIN equipe_membres em ON e.id = em.equipe_id
        WHERE p.cree_par = ? OR e.chef_projet_id = ? OR em.utilisateur_id = ?
    )";
    $params[] = $current_user_id;
    $params[] = $current_user_id;
    $params[] = $current_user_id;
}


$sql = "SELECT h.action, h.date_action, u.nom as utilisateur_nom, t.nom as tache_nom, p.nom as projet_nom " . $base_sql;

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY h.date_action DESC LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $history = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'history' => $history]);
    
} catch (PDOException $e) {
    // Dans un vrai environnement, loguez cette erreur
    // error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database query failed.']);
}

