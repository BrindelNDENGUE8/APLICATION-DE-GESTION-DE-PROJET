<?php
session_start();
require_once 'connexion.php';

// ===================================================================================
// PARTIE 1 : LOGIQUE PHP POUR LA SAUVEGARDE ET LA RESTAURATION
// ===================================================================================
$message = '';

// --- LOGIQUE DE SAUVEGARDE ---
if (isset($_GET['action']) && $_GET['action'] == 'backup') {

    // --- Étape A : Sauvegarde de la Base de Données ---
    $tables = ['users', 'projets', 'taches', 'equipes', 'equipe_membres'];
    $backup_data = [];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT * FROM $table");
            $backup_data[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Ignore les tables qui n'existent pas encore, rend le script plus robuste
            continue;
        }
    }

    $backup_dir = sys_get_temp_dir() . '/backup_' . date('Y-m-d_H-i-s');
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir);
    }
    $db_backup_path = $backup_dir . '/_db_backup';
    mkdir($db_backup_path);

    // 1. Créer le fichier JSON (pour la restauration)
    $json_content = json_encode($backup_data, JSON_PRETTY_PRINT);
    file_put_contents($db_backup_path . '/sauvegarde_donnees.json', $json_content);

    // 2. Créer le fichier XML
    $xml = new SimpleXMLElement('<backup/>');
    foreach ($backup_data as $table_name => $table_data) {
        $table_element = $xml->addChild($table_name);
        foreach ($table_data as $row) {
            $row_element = $table_element->addChild(rtrim($table_name, 's'));
            foreach ($row as $key => $value) {
                $row_element->addChild($key, htmlspecialchars((string)$value));
            }
        }
    }
    $xml->asXML($db_backup_path . '/sauvegarde_donnees.xml');
    
    // 3. Créer les fichiers CSV
    foreach ($backup_data as $table_name => $table_data) {
        if (!empty($table_data)) {
            $csv_file = fopen($db_backup_path . "/{$table_name}.csv", 'w');
            fputcsv($csv_file, array_keys($table_data[0])); // Headers
            foreach ($table_data as $row) {
                fputcsv($csv_file, $row);
            }
            fclose($csv_file);
        }
    }

    // --- Étape B : Création de l'archive ZIP (Fichiers du projet + Sauvegarde DB) ---
    $zip_file_name = 'Sauvegarde_Projet_Complet_' . date('Y-m-d') . '.zip';
    $zip_file_path = sys_get_temp_dir() . '/' . $zip_file_name;
    
    $zip = new ZipArchive();
    if ($zip->open($zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        die("Impossible d'ouvrir l'archive ZIP");
    }

    // Ajout des fichiers de la base de données dans un sous-dossier du ZIP
    $db_files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($db_backup_path), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($db_files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = '_db_backup/' . basename($filePath);
            $zip->addFile($filePath, $relativePath);
        }
    }
    
    // Ajout des fichiers du projet
    $project_root = __DIR__; // Le dossier où se trouve ce script
    $project_files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($project_root), RecursiveIteratorIterator::LEAVES_ONLY);
    
    foreach ($project_files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($project_root) + 1);
            // Exclure les fichiers ZIP de sauvegarde précédents pour éviter la récursion
            if (pathinfo($relativePath, PATHINFO_EXTENSION) !== 'zip') {
                $zip->addFile($filePath, $relativePath);
            }
        }
    }
    
    $zip->close();

    // --- Étape C : Téléchargement du ZIP ---
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($zip_file_name) . '"');
    header('Content-Length: ' . filesize($zip_file_path));
    header('Pragma: no-cache'); 
    readfile($zip_file_path);

    // --- Étape D : Nettoyage ---
    // Nettoyage des fichiers de la base de données
    array_map('unlink', glob("$db_backup_path/*.*"));
    rmdir($db_backup_path);
    rmdir($backup_dir);
    // Nettoyage du fichier ZIP
    unlink($zip_file_path);
    exit();
}


// --- LOGIQUE DE RESTAURATION (INCHANGÉE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_file'])) {
    if ($_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['backup_file']['tmp_name'];
        $json_content = file_get_contents($file_tmp_path);
        $data_to_restore = json_decode($json_content, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            $pdo->beginTransaction();
            try {
                $tables_ordered = ['equipe_membres', 'equipes', 'taches', 'projets', 'users'];
                
                foreach ($tables_ordered as $table) { $pdo->exec("DELETE FROM $table"); }
                
                foreach (array_reverse($tables_ordered) as $table) {
                    if (!empty($data_to_restore[$table])) {
                        $columns = array_keys($data_to_restore[$table][0]);
                        $placeholders = array_map(fn($c) => ":$c", $columns);
                        $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                        $stmt = $pdo->prepare($sql);
                        foreach ($data_to_restore[$table] as $row) { $stmt->execute($row); }
                    }
                }
                
                foreach (array_reverse($tables_ordered) as $table) {
                    if (isset($data_to_restore[$table][0]['id'])) {
                        $pdo->exec("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE(MAX(id), 1), true) FROM {$table};");
                    }
                }

                $pdo->commit();
                $message = '<div class="message success">La restauration a été effectuée avec succès !</div>';

            } catch (Exception $e) {
                $pdo->rollBack();
                $message = '<div class="message error">Échec de la restauration : ' . $e->getMessage() . '</div>';
            }
        } else {
            $message = '<div class="message error">Fichier JSON invalide ou corrompu.</div>';
        }
    } else {
        $message = '<div class="message error">Erreur lors du téléversement du fichier.</div>';
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sauvegarde & Restauration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        (function() { const theme = localStorage.getItem('theme'); if (theme === 'dark') { document.documentElement.classList.add('dark-mode'); } })();
    </script>
    <style>
        /* CSS INCHANGÉ - IDENTIQUE À LA VERSION PRÉCÉDENTE */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        :root { --bg-color: #f4f7f9; --panel-color: #ffffff; --border-color: #e2e8f0; --text-primary: #1a202c; --text-secondary: #4a5568; --accent-color: #3b82f6; --accent-hover: #2563eb; --shadow-color: rgba(0, 0, 0, 0.05); --wave-color-1: rgba(59, 130, 246, 0.3); --wave-color-2: rgba(34, 197, 94, 0.3); }
        :root.dark-mode { --bg-color: #111827; --panel-color: #1f2937; --border-color: #374151; --text-primary: #f9fafb; --text-secondary: #9ca3af; --accent-color: #4299e1; --accent-hover: #63b3ed; --shadow-color: rgba(0, 0, 0, 0.2); --wave-color-1: rgba(66, 153, 225, 0.2); --wave-color-2: rgba(74, 222, 128, 0.2); }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-color); color: var(--text-primary); transition: background-color 0.3s, color 0.3s; line-height: 1.6; }
        #background-canvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; }
        .page-wrapper { max-width: 900px; margin: 2rem auto; padding: 1.5rem; position: relative; z-index: 1; }
        .header-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .header-controls h1 { color: var(--text-primary); font-size: 1.75rem; font-weight: 700; }
        .header-controls h1 i { margin-right: 0.75rem; color: var(--accent-color); }
        #theme-toggle { background: var(--panel-color); border: 1px solid var(--border-color); color: var(--text-secondary); padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s ease-in-out; }
        #theme-toggle:hover { color: var(--accent-color); border-color: var(--accent-color); }
        .card { background-color: color-mix(in srgb, var(--panel-color) 95%, transparent); backdrop-filter: blur(5px); border: 1px solid var(--border-color); border-radius: 12px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px -1px var(--shadow-color); }
        .card h2 { font-size: 1.25rem; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); }
        .card p { color: var(--text-secondary); margin-bottom: 1.5rem; }
        .btn { width: auto; padding: 0.8rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background-color 0.2s ease-in-out, transform 0.1s ease; text-align: center; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn i { font-size: 1.1em; }
        .btn:active { transform: scale(0.98); }
        .btn-primary { background-color: var(--accent-color); color: white; }
        .btn-primary:hover { background-color: var(--accent-hover); }
        .danger-zone { border-color: #ef4444; background-color: color-mix(in srgb, #fee2e2 50%, transparent); }
        :root.dark-mode .danger-zone { border-color: #b91c1c; background-color: color-mix(in srgb, #450a0a 50%, transparent); }
        .danger-zone h2 { color: #b91c1c; }
        :root.dark-mode .danger-zone h2 { color: #fca5a5; }
        .danger-zone .btn-danger { background-color: #dc2626; color: white; }
        .danger-zone .btn-danger:hover { background-color: #b91c1c; }
        input[type="file"] { font-size: 1rem; }
        input[type="file"]::file-selector-button { padding: 0.7rem 1.2rem; margin-right: 1rem; background-color: var(--panel-color); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-secondary); cursor: pointer; transition: all 0.2s; }
        input[type="file"]::file-selector-button:hover { background-color: var(--bg-color); color: var(--accent-color); }
        .message { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; font-weight: 500; }
        .message.success { background-color: #d1fae5; color: #065f46; }
        .message.error { background-color: #fee2e2; color: #991b1b; }
        :root.dark-mode .message.success { background-color: #064e3b; color: #a7f3d0; }
        :root.dark-mode .message.error { background-color: #7f1d1d; color: #fecaca; }
    </style>
</head>
<body>

<canvas id="background-canvas"></canvas>

<div class="page-wrapper">
<div style="margin-bottom: 2rem;">
    <a href="create_user.php" class="btn btn-secondary" style="width:auto;">
        ← Aller à la gestion des utilisateurs
    </a>
</div>
    <div class="header-controls">
        <h1><i class="fas fa-database"></i>Sauvegarde & Restauration</h1>
        <button id="theme-toggle" title="Changer de thème"><i class="fas fa-moon"></i></button>
    </div>

    <?php if ($message): echo $message; endif; ?>

    <div class="card">
        <h2><i class="fas fa-download"></i> Sauvegarde Manuelle</h2>
        <p>Générez et téléchargez une archive ZIP unique contenant **tous les fichiers de votre projet** ainsi qu'une sauvegarde complète de la base de données (JSON, XML, CSV).</p>
        <a href="?action=backup" class="btn btn-primary"><i class="fas fa-file-archive"></i> Télécharger la Sauvegarde Totale</a>
    </div>

    <div class="card">
        <h2><i class="fas fa-info-circle"></i> Planification de Sauvegardes</h2>
        <p>La planification de sauvegardes automatiques doit être configurée au niveau du serveur via une tâche planifiée (cron job) qui exécuterait ce script. Contactez votre administrateur système pour mettre cela en place.</p>
    </div>

    <div class="card danger-zone">
        <h2><i class="fas fa-exclamation-triangle"></i> Zone de Restauration de la Base de Données</h2>
        <p><strong>Attention :</strong> La restauration effacera les données actuelles de la base pour les remplacer par le contenu de votre fichier de sauvegarde. Utilisez le fichier `sauvegarde_donnees.json` de votre archive.</p>
        <form action="" method="POST" enctype="multipart/form-data" onsubmit="return confirm('Êtes-vous absolument sûr ? Cette action est irréversible et effacera les données actuelles.');">
            <input type="file" name="backup_file" accept=".json" required>
            <button type="submit" class="btn btn-danger" style="margin-top: 1rem;"><i class="fas fa-upload"></i> Lancer la Restauration</button>
        </form>
    </div>
</div>

<script>
    // JS INCHANGÉ - IDENTIQUE À LA VERSION PRÉCÉDENTE
document.addEventListener('DOMContentLoaded', () => {
    const themeToggleBtn = document.getElementById('theme-toggle');
    themeToggleBtn.addEventListener('click', () => {
        const root = document.documentElement;
        root.classList.toggle('dark-mode');
        localStorage.setItem('theme', root.classList.contains('dark-mode') ? 'dark' : 'light');
    });
    const canvas = document.getElementById('background-canvas');
    const ctx = canvas.getContext('2d');
    let animationFrameId;
    let width, height, waves = [];
    class Wave {
        constructor(y, amp, len, speed, color) { this.y = y; this.amp = amp; this.len = len; this.speed = speed; this.color = color; this.phase = Math.random() * Math.PI * 2; }
        update() { this.phase += this.speed; }
        draw() {
            ctx.beginPath();
            ctx.moveTo(0, this.y);
            for (let x = 0; x < width; x++) { ctx.lineTo(x, this.y + Math.sin(x * this.len + this.phase) * this.amp); }
            ctx.strokeStyle = this.color;
            ctx.stroke();
        }
    }
    function setupWaves() {
        width = canvas.width = window.innerWidth;
        height = canvas.height = window.innerHeight;
        waves.length = 0;
        const colors = [getComputedStyle(document.documentElement).getPropertyValue('--wave-color-1').trim(), getComputedStyle(document.documentElement).getPropertyValue('--wave-color-2').trim()];
        for (let i = 0; i < 5; i++) { waves.push(new Wave(height / 2, Math.random() * 50 + 20, Math.random() * 0.02 + 0.01, Math.random() * 0.02 + 0.01, colors[i % 2])); }
        if (animationFrameId) cancelAnimationFrame(animationFrameId);
        loop();
    }
    function loop() {
        ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--bg-color').trim();
        ctx.fillRect(0, 0, width, height);
        waves.forEach(wave => { wave.update(); wave.draw(); });
        animationFrameId = requestAnimationFrame(loop);
    }
    const observer = new MutationObserver(setupWaves);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    window.addEventListener('resize', setupWaves);
    setupWaves();
});
</script>

</body>
</html>