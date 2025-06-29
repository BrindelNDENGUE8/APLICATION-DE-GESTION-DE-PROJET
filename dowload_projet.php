<?php
session_start();
require_once 'connexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$project_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$project_id) { die("ID de projet invalide."); }

// Vérification des permissions
function is_user_project_manager($pdo, $user_id, $project_id) {
    $stmt = $pdo->prepare("SELECT * FROM projets WHERE id = ? AND chef_id = ?");
    $stmt->execute([$project_id, $user_id]);
    return $stmt->fetch() !== false;
}
if ($_SESSION['role'] === 'chef' && !is_user_project_manager($pdo, $user_id, $project_id)) {
    die("Accès non autorisé à ce projet.");
}

// Récupérer le nom du projet
$stmt = $pdo->prepare("SELECT nom FROM projets WHERE id = ?");
$stmt->execute([$project_id]);
$projet = $stmt->fetch();
$project_name = $projet['nom'];

$project_root_path = 'projets_stockes/' . $project_id;

// Créer un fichier ZIP
$zip_file = tempnam(sys_get_temp_dir(), 'project_') . '.zip';
$zip = new ZipArchive();
if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($project_root_path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $file) {
        $file_path = $file->getRealPath();
        $relative_path = substr($file_path, strlen(realpath($project_root_path)) + 1);
        if ($file->isDir()) {
            $zip->addEmptyDir($relative_path);
        } else {
            $zip->addFile($file_path, $relative_path);
        }
    }
    $zip->close();

    // Envoyer le fichier ZIP
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $project_name . '.zip"');
    header('Content-Length: ' . filesize($zip_file));
    readfile($zip_file);
    unlink($zip_file);
    exit();
} else {
    die("Erreur lors de la création du fichier ZIP.");
}
?>