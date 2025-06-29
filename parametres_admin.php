<?php
session_start();
require_once 'connexion.php';

// ===================================================================================
// PARTIE 1 : LOGIQUE PHP POUR LES PARAMÈTRES
// ===================================================================================

$message = '';

// --- Chargement des paramètres actuels depuis la BDD ---
$stmt = $pdo->query("SELECT cle, valeur FROM parametres_app");
$settings_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$defaults = [
    'app_nom' => 'Mon Projet de Gestion',
    'app_langue' => 'fr',
    'app_timezone' => 'UTC',
    'securite_mdp_longueur' => '8',
    'email_host' => '',
    'email_port' => '587',
    'email_user' => '',
    'email_pass' => '',
    'ia_api_key' => '',
    'maintenance_mode' => '0'
];
$settings = array_merge($defaults, $settings_raw);


// --- Traitement de la sauvegarde des paramètres ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        $sql = "INSERT INTO parametres_app (cle, valeur) VALUES (:cle, :valeur)
                ON CONFLICT (cle) DO UPDATE SET valeur = EXCLUDED.valeur";
        $stmt = $pdo->prepare($sql);

        foreach ($_POST as $key => $value) {
            if ($key !== 'save_settings') { // Ne pas sauvegarder le bouton lui-même
                $stmt->execute([':cle' => $key, ':valeur' => trim($value)]);
            }
        }
        $message = '<div class="message success">Paramètres sauvegardés avec succès !</div>';
        // Recharger les paramètres pour afficher les nouvelles valeurs
        $stmt = $pdo->query("SELECT cle, valeur FROM parametres_app");
        $settings = array_merge($defaults, $stmt->fetchAll(PDO::FETCH_KEY_PAIR));

    } catch (Exception $e) {
        $message = '<div class="message error">Erreur lors de la sauvegarde : ' . $e->getMessage() . '</div>';
    }
}

// --- Chargement du journal d'activité ---
$logs_query = "
    SELECT h.date_action, u.nom, h.action, t.nom as tache_nom
    FROM historique h
    JOIN users u ON h.utilisateur_id = u.id
    LEFT JOIN taches t ON h.tache_id = t.id
    ORDER BY h.date_action DESC
    LIMIT 100";
$activity_logs = $pdo->query($logs_query)->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres de l'Application</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        (function() { const theme = localStorage.getItem('theme'); if (theme === 'dark') { document.documentElement.classList.add('dark-mode'); } })();
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        :root { --bg-color: #f4f7f9; --panel-color: #ffffff; --border-color: #e2e8f0; --text-primary: #1a202c; --text-secondary: #4a5568; --accent-color: #4f46e5; --accent-hover: #4338ca; --shadow-color: rgba(0, 0, 0, 0.05); }
        :root.dark-mode { --bg-color: #111827; --panel-color: #1f2937; --border-color: #374151; --text-primary: #f9fafb; --text-secondary: #9ca3af; --accent-color: #6366f1; --accent-hover: #818cf8; --shadow-color: rgba(0, 0, 0, 0.2); }
        
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-color); color: var(--text-primary); transition: background-color 0.3s, color 0.3s; line-height: 1.6; }
        
        #background-canvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; filter: blur(100px); }
        .page-wrapper { max-width: 1100px; margin: 2rem auto; padding: 1.5rem; position: relative; z-index: 1; }
        
        .header-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .header-controls h1 { color: var(--text-primary); font-size: 1.75rem; font-weight: 700; }
        .header-controls h1 i { margin-right: 0.75rem; color: var(--accent-color); }
        #theme-toggle { background: var(--panel-color); border: 1px solid var(--border-color); color: var(--text-secondary); padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s ease-in-out; }
        #theme-toggle:hover { color: var(--accent-color); border-color: var(--accent-color); }
        
        .card { background-color: color-mix(in srgb, var(--panel-color) 90%, transparent); backdrop-filter: blur(10px); border: 1px solid var(--border-color); border-radius: 12px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px -1px var(--shadow-color); }
        .card h2 { font-size: 1.25rem; margin-bottom: 1.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); }
        
        .tabs { display: flex; border-bottom: 1px solid var(--border-color); margin-bottom: 2rem; }
        .tab-link { padding: 0.75rem 1.5rem; cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -1px; color: var(--text-secondary); font-weight: 500; transition: all 0.2s; }
        .tab-link:hover { color: var(--accent-color); }
        .tab-link.active { color: var(--accent-color); border-bottom-color: var(--accent-color); }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.5s; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem 2rem; }
        label { font-weight: 500; margin-bottom: 0.5rem; display: block; color: var(--text-secondary); font-size: 0.9rem; }
        input, select { width: 100%; padding: 0.75rem 1rem; background-color: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); transition: all 0.2s; }
        input:focus, select:focus { outline: none; border-color: var(--accent-color); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent-color) 25%, transparent); }
        .form-footer { margin-top: 2rem; text-align: right; }
        .btn-primary { padding: 0.8rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; background-color: var(--accent-color); color: white; }
        .btn-primary:hover { background-color: var(--accent-hover); }

        /* Log styles */
        .log-list { max-height: 400px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px; padding: 0.5rem; }
        .log-item { display: flex; gap: 1.5rem; padding: 0.75rem; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; }
        .log-item:last-child { border-bottom: none; }
        .log-date { color: var(--text-secondary); width: 150px; }
        .log-user { color: var(--accent-color); font-weight: 600; width: 150px; }
        .log-action { flex: 1; }

        /* Toggle switch */
        .toggle-switch { display: flex; align-items: center; gap: 1rem; }
        .switch { position: relative; display: inline-block; width: 50px; height: 28px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 28px; }
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--accent-color); }
        input:checked + .slider:before { transform: translateX(22px); }
    </style>
</head>
<body>

<canvas id="background-canvas"></canvas>
<div class="page-wrapper">
<div style="margin-bottom: 2rem;">
    <a href="create_user.php" class="btn btn-secondary" style="width:auto;">
        <i class="fas fa-users-cog"></i>
        <span>Aller à la gestion des utilisateurs</span>
    </a>
</div>
    <div class="header-controls">
        <h1><i class="fas fa-cog"></i>Paramètres</h1>
        <button id="theme-toggle" title="Changer de thème"><i class="fas fa-moon"></i></button>
    </div>

    <?php if ($message): echo $message; endif; ?>

    <div class="card">
        <div class="tabs">
            <a class="tab-link active" data-tab="general"><i class="fas fa-sliders-h"></i> Général</a>
            <a class="tab-link" data-tab="integrations"><i class="fas fa-plug"></i> Intégrations</a>
            <a class="tab-link" data-tab="logs"><i class="fas fa-history"></i> Journal d'activité</a>
            <a class="tab-link" data-tab="maintenance"><i class="fas fa-power-off"></i> Maintenance</a>
        </div>
        
        <form action="" method="POST">
            <div id="general" class="tab-content active">
                <h2>Paramètres Généraux</h2>
                <div class="form-grid">
                    <div><label for="app_nom">Nom de l'application</label><input type="text" id="app_nom" name="app_nom" value="<?= htmlspecialchars($settings['app_nom']) ?>"></div>
                    <div>
                        <label for="app_langue">Langue</label>
                        <select id="app_langue" name="app_langue">
                            <option value="fr" <?= $settings['app_langue'] == 'fr' ? 'selected' : '' ?>>Français</option>
                            <option value="en" <?= $settings['app_langue'] == 'en' ? 'selected' : '' ?>>English</option>
                        </select>
                    </div>
                    <div>
                        <label for="app_timezone">Fuseau Horaire</label>
                        <select id="app_timezone" name="app_timezone">
                            <?php foreach (DateTimeZone::listIdentifiers() as $tz): ?>
                                <option value="<?= $tz ?>" <?= $settings['app_timezone'] == $tz ? 'selected' : '' ?>><?= $tz ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div><label for="securite_mdp_longueur">Longueur minimale du mot de passe</label><input type="number" id="securite_mdp_longueur" name="securite_mdp_longueur" value="<?= htmlspecialchars($settings['securite_mdp_longueur']) ?>" min="8"></div>
                </div>
            </div>

            <div id="integrations" class="tab-content">
                <h2>Intégrations & API</h2>
                <div class="form-grid">
                    <div><label for="email_host">Serveur Email (SMTP)</label><input type="text" id="email_host" name="email_host" value="<?= htmlspecialchars($settings['email_host']) ?>"></div>
                    <div><label for="email_port">Port</label><input type="text" id="email_port" name="email_port" value="<?= htmlspecialchars($settings['email_port']) ?>"></div>
                    <div><label for="email_user">Utilisateur Email</label><input type="text" id="email_user" name="email_user" value="<?= htmlspecialchars($settings['email_user']) ?>"></div>
                    <div><label for="email_pass">Mot de passe Email</label><input type="password" id="email_pass" name="email_pass" value="<?= htmlspecialchars($settings['email_pass']) ?>"></div>
                    <div style="grid-column: 1 / -1;"><label for="ia_api_key">Clé API pour IA</label><input type="password" id="ia_api_key" name="ia_api_key" value="<?= htmlspecialchars($settings['ia_api_key']) ?>"></div>
                </div>
                 <p style="margin-top:1rem; color: var(--text-secondary); font-size:0.9rem;"><i class="fas fa-exclamation-triangle"></i> Attention: les clés API et mots de passe sont stockés dans la base de données. Pour une sécurité maximale, ils devraient être stockés dans des variables d'environnement sur le serveur.</p>
            </div>

            <div id="maintenance" class="tab-content">
                <h2>Mode Maintenance</h2>
                <div class="form-grid">
                   <div class="toggle-switch">
                       <label class="switch">
                           <input type="hidden" name="maintenance_mode" value="0"> <input type="checkbox" name="maintenance_mode" value="1" <?= ($settings['maintenance_mode'] ?? '0') == '1' ? 'checked' : '' ?>>
                           <span class="slider"></span>
                       </label>
                       <span>Activer le mode maintenance (l'application sera inaccessible aux utilisateurs)</span>
                   </div>
                </div>
            </div>

            <div class="form-footer">
                <button type="submit" name="save_settings" class="btn-primary"><i class="fas fa-save"></i> Sauvegarder les modifications</button>
            </div>
        </form>

        <div id="logs" class="tab-content">
            <h2>Journal d'activité récent</h2>
            <div class="log-list">
                <?php if(empty($activity_logs)): ?>
                    <div class="log-item">Aucune activité enregistrée.</div>
                <?php else: ?>
                    <?php foreach ($activity_logs as $log): ?>
                    <div class="log-item">
                        <span class="log-date"><?= date('d/m/Y H:i:s', strtotime($log['date_action'])) ?></span>
                        <span class="log-user"><?= htmlspecialchars($log['nom']) ?></span>
                        <span class="log-action"><?= htmlspecialchars($log['action']) ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

    // --- 1. GESTION DES ONGLETS ---
    const tabs = document.querySelectorAll('.tab-link');
    const tabContents = document.querySelectorAll('.tab-content');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const target = document.getElementById(tab.dataset.tab);
            tabContents.forEach(tc => {
                tc.classList.remove('active');
            });
            target.classList.add('active');
            // Le journal d'activité n'est pas dans le formulaire, il faut le gérer séparément
            document.getElementById('logs').style.display = (tab.dataset.tab === 'logs' ? 'block' : 'none');
        });
    });
    // Initialiser la visibilité du journal
    document.getElementById('logs').style.display = 'none';

    // --- 2. GESTION DU THÈME ---
    const themeToggleBtn = document.getElementById('theme-toggle');
    themeToggleBtn.addEventListener('click', () => {
        const root = document.documentElement;
        root.classList.toggle('dark-mode');
        localStorage.setItem('theme', root.classList.contains('dark-mode') ? 'dark' : 'light');
    });

    // --- 3. EFFET JS : ORBE DE PLASMA ---
    const canvas = document.getElementById('background-canvas');
    const ctx = canvas.getContext('2d');
    let animationFrameId;

    const mouse = { x: window.innerWidth / 2, y: window.innerHeight / 2 };
    window.addEventListener('mousemove', e => {
        mouse.x = e.clientX;
        mouse.y = e.clientY;
    });

    class Orb {
        constructor(color) {
            this.x = Math.random() * window.innerWidth;
            this.y = Math.random() * window.innerHeight;
            this.vx = (Math.random() - 0.5) * 2;
            this.vy = (Math.random() - 0.5) * 2;
            this.radius = Math.random() * 150 + 100;
            this.color = color;
        }

        update() {
            this.x += this.vx;
            this.y += this.vy;

            if (this.x + this.radius > window.innerWidth || this.x - this.radius < 0) this.vx *= -1;
            if (this.y + this.radius > window.innerHeight || this.y - this.radius < 0) this.vy *= -1;
        }

        draw() {
            const gradient = ctx.createRadialGradient(this.x, this.y, 0, this.x, this.y, this.radius);
            gradient.addColorStop(0, this.color);
            gradient.addColorStop(1, 'rgba(0,0,0,0)');
            ctx.fillStyle = gradient;
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
            ctx.fill();
        }
    }

    let orbs = [];
    function setupOrbs() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        orbs = [
            new Orb('rgba(66, 153, 225, 0.5)'),
            new Orb('rgba(79, 70, 229, 0.5)'),
        ];
        if (animationFrameId) cancelAnimationFrame(animationFrameId);
        loop();
    }

    function loop() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        orbs.forEach(orb => {
            orb.update();
            orb.draw();
        });
        animationFrameId = requestAnimationFrame(loop);
    }
    
    window.addEventListener('resize', setupOrbs);
    setupOrbs();
});
</script>

</body>
</html>