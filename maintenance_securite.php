<?php
session_start();
require_once 'connexion.php';

// ===================================================================================
// PARTIE 1 : LOGIQUE PHP POUR LA MAINTENANCE ET SÉCURITÉ
// ===================================================================================
$message = '';

// --- Chargement des paramètres d'alerte ---
$alert_settings_keys = "'alert_email_address', 'alert_on_db_error', 'alert_on_backup_failure'";
$stmt = $pdo->query("SELECT cle, valeur FROM parametres_app WHERE cle IN ($alert_settings_keys)");
$settings_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$defaults = [
    'alert_email_address' => '',
    'alert_on_db_error' => '0',
    'alert_on_backup_failure' => '0'
];
$settings = array_merge($defaults, $settings_raw);


// --- Traitement de la sauvegarde des paramètres d'alerte ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_alerts'])) {
        try {
            $sql = "INSERT INTO parametres_app (cle, valeur) VALUES (:cle, :valeur)
                    ON CONFLICT (cle) DO UPDATE SET valeur = EXCLUDED.valeur";
            $stmt = $pdo->prepare($sql);
            
            // Assurer que les cases à cocher ont une valeur même si non cochées
            $alerts_to_save = [
                'alert_email_address' => $_POST['alert_email_address'] ?? '',
                'alert_on_db_error' => $_POST['alert_on_db_error'] ?? '0',
                'alert_on_backup_failure' => $_POST['alert_on_backup_failure'] ?? '0'
            ];

            foreach ($alerts_to_save as $key => $value) {
                $stmt->execute([':cle' => $key, ':valeur' => trim($value)]);
            }
            $message = '<div class="message success">Paramètres d\'alertes sauvegardés.</div>';
            // Recharger les paramètres
            $settings = array_merge($settings, $alerts_to_save);

        } catch (Exception $e) {
            $message = '<div class="message error">Erreur lors de la sauvegarde : ' . $e->getMessage() . '</div>';
        }
    }
    // Simulation d'envoi d'email de test
    if (isset($_POST['send_test_email'])) {
        $email = $settings['alert_email_address'];
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
             $message = '<div class="message success">Simulation: Un email de test a été envoyé à ' . htmlspecialchars($email) . '</div>';
        } else {
            $message = '<div class="message error">Veuillez définir une adresse email valide avant d\'envoyer un test.</div>';
        }
    }
}


// --- Lecture du fichier de log d'erreurs PHP ---
$error_log_path = ini_get('error_log');
$error_log_content = 'Fichier de log non trouvé ou non lisible.';
if ($error_log_path && is_readable($error_log_path)) {
    $lines = file($error_log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines) {
       $error_log_content = implode("\n", array_slice($lines, -50)); // Affiche les 50 dernières lignes
    } else {
       $error_log_content = 'Le fichier de log est vide.';
    }
}

// --- Infos sur la base de données ---
try {
    $db_status = $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
    $db_version = $pdo->query("SELECT version()")->fetchColumn();
    $db_driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
} catch (PDOException $e) {
    $db_status = "Erreur de connexion";
    $db_version = "N/A";
    $db_driver = "N/A";
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance & Sécurité</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        (function() { const theme = localStorage.getItem('theme'); if (theme === 'dark') { document.documentElement.classList.add('dark-mode'); } })();
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500&family=Inter:wght@400;500;600;700&display=swap');
        
        :root { --bg-color: #f4f7f9; --panel-color: #ffffff; --border-color: #e2e8f0; --text-primary: #1a202c; --text-secondary: #4a5568; --accent-color: #059669; --accent-hover: #047857; --shadow-color: rgba(0, 0, 0, 0.05); --circuit-bg: #f9fafb; --circuit-line: #e2e8f0; --circuit-pulse: #10b981;}
        :root.dark-mode { --bg-color: #111827; --panel-color: #1f2937; --border-color: #374151; --text-primary: #f9fafb; --text-secondary: #9ca3af; --accent-color: #34d399; --accent-hover: #6ee7b7; --shadow-color: rgba(0, 0, 0, 0.2); --circuit-bg: #1f2937; --circuit-line: #374151; --circuit-pulse: #34d399;}
        
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-color); color: var(--text-primary); transition: background-color 0.3s, color 0.3s; line-height: 1.6; }
        
        #background-canvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; opacity: 0.5; }
        .page-wrapper { max-width: 1200px; margin: 2rem auto; padding: 1.5rem; position: relative; z-index: 1; }
        
        .header-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .header-controls h1 { color: var(--text-primary); font-size: 1.75rem; font-weight: 700; }
        .header-controls h1 i { margin-right: 0.75rem; color: var(--accent-color); }
        .header-actions { display: flex; gap: 1rem; }
        #theme-toggle, .btn-back { background: var(--panel-color); border: 1px solid var(--border-color); color: var(--text-secondary); padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s ease-in-out; text-decoration: none; }
        #theme-toggle:hover, .btn-back:hover { color: var(--accent-color); border-color: var(--accent-color); }

        .dashboard-grid { display: grid; grid-template-columns: repeat(1, 1fr); gap: 2rem; }
        @media (min-width: 1024px) { .dashboard-grid { grid-template-columns: 1fr 1fr; } }
        .grid-span-2 { grid-column: span 1; }
        @media (min-width: 1024px) { .grid-span-2 { grid-column: span 2; } }

        .card { background-color: color-mix(in srgb, var(--panel-color) 90%, transparent); backdrop-filter: blur(5px); border: 1px solid var(--border-color); border-radius: 12px; padding: 2rem; box-shadow: 0 4px 6px -1px var(--shadow-color); }
        .card h2 { font-size: 1.25rem; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 0.75rem; }
        
        .log-viewer { background-color: var(--bg-color); border-radius: 8px; padding: 1rem; height: 300px; overflow-y: auto; font-family: 'Roboto Mono', monospace; font-size: 0.85rem; white-space: pre-wrap; word-wrap: break-word; }
        
        .db-status-list dt { font-weight: 600; color: var(--text-primary); margin-top: 1rem; }
        .db-status-list dd { color: var(--text-secondary); margin-left: 0; }
        .status-ok { color: var(--accent-color); font-weight: bold; }
        .status-error { color: #ef4444; font-weight: bold; }

        .form-grid { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
        label { font-weight: 500; margin-bottom: 0.5rem; display: block; color: var(--text-secondary); }
        input, select { width: 100%; padding: 0.75rem 1rem; background-color: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); }
        input:focus, select:focus { outline: none; border-color: var(--accent-color); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent-color) 25%, transparent); }
        .form-footer { margin-top: 2rem; display: flex; justify-content: flex-end; gap: 1rem; }
        .btn { padding: 0.8rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background-color: var(--accent-color); color: white; }
        .btn-primary:hover { background-color: var(--accent-hover); }
        .btn-secondary { background-color: var(--panel-color); color: var(--text-secondary); border: 1px solid var(--border-color); }
        .btn-secondary:hover { background-color: var(--bg-color); border-color: var(--text-secondary); }

        .toggle-switch { display: flex; align-items: center; gap: 1rem; margin-top: 1rem; }
        .switch { position: relative; display: inline-block; width: 50px; height: 28px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 28px; }
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--accent-color); }
        input:checked + .slider:before { transform: translateX(22px); }

        .message { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; font-weight: 500; text-align: center; }
        .message.success { background-color: #d1fae5; color: #065f46; }
        .message.error { background-color: #fee2e2; color: #991b1b; }
        :root.dark-mode .message.success { background-color: #064e3b; color: #a7f3d0; }
        :root.dark-mode .message.error { background-color: #7f1d1d; color: #fecaca; }
    </style>
</head>
<body>
<canvas id="background-canvas"></canvas>
<div class="page-wrapper">
    <div class="header-controls">
        <h1><i class="fas fa-tools"></i>Maintenance & Sécurité</h1>
        <div class="header-actions">
            <a href="create_user" class="btn-back"><i class="fas fa-arrow-left"></i> Panel Admin </a>
            <button id="theme-toggle" title="Changer de thème"><i class="fas fa-moon"></i></button>
        </div>
    </div>

    <?php if ($message): echo $message; endif; ?>

    <div class="dashboard-grid">
        <div class="card grid-span-2">
            <h2><i class="fas fa-bug"></i> Journal des Erreurs Système</h2>
            <pre class="log-viewer"><?= htmlspecialchars($error_log_content) ?></pre>
        </div>

        <div class="card">
            <h2><i class="fas fa-database"></i> État de la Base de Données</h2>
            <dl class="db-status-list">
                <dt>Status</dt>
                <dd class="status-ok"><?= htmlspecialchars($db_status) ?></dd>
                
                <dt>Système</dt>
                <dd><?= htmlspecialchars($db_driver) ?></dd>
                
                <dt>Version du Serveur</dt>
                <dd><?= htmlspecialchars($db_version) ?></dd>
            </dl>
        </div>

        <div class="card">
            <h2><i class="fas fa-bell"></i> Configuration des Alertes</h2>
            <form action="" method="POST">
                <div class="form-grid">
                    <div>
                        <label for="alert_email_address">Adresse email pour les alertes</label>
                        <input type="email" id="alert_email_address" name="alert_email_address" value="<?= htmlspecialchars($settings['alert_email_address']) ?>" placeholder="admin@exemple.com">
                    </div>
                    <div class="toggle-switch">
                       <label class="switch">
                           <input type="hidden" name="alert_on_db_error" value="0">
                           <input type="checkbox" name="alert_on_db_error" value="1" <?= $settings['alert_on_db_error'] == '1' ? 'checked' : '' ?>>
                           <span class="slider"></span>
                       </label>
                       <span>Alerte en cas d'erreur de base de données</span>
                   </div>
                   <div class="toggle-switch">
                       <label class="switch">
                           <input type="hidden" name="alert_on_backup_failure" value="0">
                           <input type="checkbox" name="alert_on_backup_failure" value="1" <?= $settings['alert_on_backup_failure'] == '1' ? 'checked' : '' ?>>
                           <span class="slider"></span>
                       </label>
                       <span>Alerte en cas d'échec de sauvegarde</span>
                   </div>
                </div>
                <div class="form-footer">
                    <button type="submit" name="send_test_email" class="btn btn-secondary"><i class="fas fa-paper-plane"></i> Test</button>
                    <button type="submit" name="save_alerts" class="btn btn-primary"><i class="fas fa-save"></i> Sauvegarder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

    // --- 1. GESTION DU THÈME ---
    const themeToggleBtn = document.getElementById('theme-toggle');
    themeToggleBtn.addEventListener('click', () => {
        const root = document.documentElement;
        root.classList.toggle('dark-mode');
        localStorage.setItem('theme', root.classList.contains('dark-mode') ? 'dark' : 'light');
    });

    // --- 2. EFFET JS : CIRCUIT IMPRIMÉ ---
    const canvas = document.getElementById('background-canvas');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        let animationFrameId;
        const settings = {
            gridSize: 20,
            pulseSpeed: 0.05,
            pulseLength: 20
        };
        let nodes = [];
        let pulses = [];

        class Pulse {
            constructor(path) {
                this.path = path;
                this.pathIndex = 0;
                this.progress = 0;
            }
            update() {
                this.progress += settings.pulseSpeed;
                if (this.progress >= 1) {
                    this.progress = 0;
                    this.pathIndex++;
                    if (this.pathIndex >= this.path.length - 1) {
                        return false; // Pulse finished
                    }
                }
                return true;
            }
            draw() {
                const start = this.path[this.pathIndex];
                const end = this.path[this.pathIndex + 1];
                const x = start.x + (end.x - start.x) * this.progress;
                const y = start.y + (end.y - start.y) * this.progress;
                ctx.beginPath();
                ctx.arc(x, y, 2, 0, Math.PI * 2);
                ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--circuit-pulse').trim();
                ctx.fill();
            }
        }

        function setupCircuit() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            nodes = [];
            pulses = [];
            const cols = Math.floor(canvas.width / settings.gridSize);
            const rows = Math.floor(canvas.height / settings.gridSize);

            for (let i = 0; i <= cols; i++) {
                for (let j = 0; j <= rows; j++) {
                    if (Math.random() > 0.8) {
                        nodes.push({ x: i * settings.gridSize, y: j * settings.gridSize, connections: [] });
                    }
                }
            }

            nodes.forEach(nodeA => {
                nodes.forEach(nodeB => {
                    if (nodeA === nodeB) return;
                    const dist = Math.sqrt(Math.pow(nodeA.x - nodeB.x, 2) + Math.pow(nodeA.y - nodeB.y, 2));
                    if (dist <= settings.gridSize * 1.5 && Math.random() > 0.5) {
                        nodeA.connections.push(nodeB);
                    }
                });
            });

            if (animationFrameId) cancelAnimationFrame(animationFrameId);
            loop();
        }

        function loop() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            const lineColor = getComputedStyle(document.documentElement).getPropertyValue('--circuit-line').trim();
            ctx.strokeStyle = lineColor;
            ctx.lineWidth = 0.5;

            nodes.forEach(node => {
                node.connections.forEach(conn => {
                    ctx.beginPath();
                    ctx.moveTo(node.x, node.y);
                    ctx.lineTo(conn.x, conn.y);
                    ctx.stroke();
                });
            });

            if (Math.random() > 0.98 && nodes.length > 1) {
                const startNode = nodes[Math.floor(Math.random() * nodes.length)];
                let path = [startNode];
                let currentNode = startNode;
                for (let i = 0; i < settings.pulseLength; i++) {
                    if (currentNode.connections.length > 0) {
                        const nextNode = currentNode.connections[Math.floor(Math.random() * currentNode.connections.length)];
                        path.push(nextNode);
                        currentNode = nextNode;
                    } else { break; }
                }
                if (path.length > 1) pulses.push(new Pulse(path));
            }
            
            pulses = pulses.filter(pulse => pulse.update());
            pulses.forEach(pulse => pulse.draw());

            animationFrameId = requestAnimationFrame(loop);
        }
        
        const observer = new MutationObserver(setupCircuit);
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        window.addEventListener('resize', setupCircuit);
        setupCircuit();
    }
});
</script>
</body>
</html>