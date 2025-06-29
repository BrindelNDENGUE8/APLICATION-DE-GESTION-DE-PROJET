<?php
session_start();
require_once 'connexion.php'; // Assurez-vous que ce fichier gère la connexion PDO ($pdo)

// ===================================================================================
// SECTION 1 : NOUVEAU MOTEUR D'ANALYSE DE TÂCHES PAR IA (OPENAI)
// ===================================================================================

/**
 * Contacte l'API OpenAI pour obtenir des suggestions sur un ensemble de tâches critiques.
 * @param array $tasks Tableau des tâches critiques à analyser.
 * @param string $apiKey Votre clé d'API OpenAI.
 * @return array Une liste de suggestions générées par l'IA.
 */
function getTaskSuggestionsFromAI(array $tasks, string $apiKey): array
{
    // Sécurité : Vérifie si la clé API est celle par défaut ou est vide.
    if ($apiKey === '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' || empty($apiKey)) {
        return [['icon' => 'fa-key', 'title' => 'Clé API Manquante', 'suggestion' => 'Veuillez configurer votre clé API OpenAI pour activer l\'assistant.', 'type' => 'warning']];
    }
    // Si aucune tâche critique n'est trouvée, on retourne un message positif.
    if (empty($tasks)) {
        return [['icon' => 'fa-check-circle', 'title' => 'Aucun risque majeur détecté', 'suggestion' => 'Toutes les tâches en cours semblent se dérouler normalement. Excellent travail !', 'type' => 'success']];
    }

    $apiUrl = 'https://api.openai.com/v1/chat/completions';
    
    // Formatage des données des tâches pour le prompt
    $taskListString = "";
    foreach ($tasks as $task) {
        $taskListString .= "- Tâche: \"" . $task['nom'] . "\" (Projet: " . $task['nom_projet'] . "), Assignée à: " . ($task['nom_utilisateur'] ?? 'Personne') . ", Statut: " . $task['statut'] . ", Priorité: " . $task['priorite'] . ", Échéance: " . $task['date_fin_estimee'] . ".\n";
    }

    // Un prompt beaucoup plus intelligent qui demande des analyses et suggestions spécifiques.
    $system_prompt = "
        Tu es un assistant expert en gestion de projet, proactif et méticuleux. Analyse la liste de tâches critiques suivante et identifie les 3 à 5 principaux risques, goulots d'étranglement ou points d'attention.
        Pour chaque point identifié, fournis une suggestion claire, concise et professionnelle.
        Réponds UNIQUEMENT avec un objet JSON contenant une seule clé 'suggestions' qui est un tableau d'objets.
        Chaque objet dans le tableau doit contenir les clés : 'icon', 'title', 'suggestion', 'type'.
        
        Les types possibles sont : 
        - 'danger': Pour les risques imminents (ex: tâche critique en retard). Icône: 'fa-fire-alt'.
        - 'warning': Pour les points à surveiller (ex: surcharge d'un membre, échéance proche). Icône: 'fa-exclamation-triangle'.
        - 'info': Pour les optimisations et conseils (ex: deux tâches liées). Icône: 'fa-info-circle'.
    ";
    
    $user_prompt = "Voici la liste des tâches critiques à analyser pour mon tableau de bord :\n" . $taskListString;

    $data = [
        'model' => 'gpt-4o', // Modèle plus performant pour une analyse de meilleure qualité
        'messages' => [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $user_prompt]
        ],
        'response_format' => ['type' => 'json_object']
    ];
    $jsonData = json_encode($data);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 40,
    ]);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return [['icon' => 'fa-wifi', 'title' => 'Erreur de Connexion API', 'suggestion' => 'Impossible de contacter le service OpenAI. Erreur: ' . $error, 'type' => 'danger']];
    }

    $result = json_decode($response, true);
    if (isset($result['choices'][0]['message']['content'])) {
        $predictionJson = $result['choices'][0]['message']['content'];
        $prediction = json_decode($predictionJson, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($prediction['suggestions'])) {
            return $prediction['suggestions'];
        }
    }
    
    $error_detail = $result['error']['message'] ?? 'Format de réponse inattendu.';
    return [['icon' => 'fa-question-circle', 'title' => 'Réponse IA Invalide', 'suggestion' => $error_detail, 'type' => 'warning']];
}

// ===================================================================================
// SECTION 2 : RÉCUPÉRATION GLOBALE DES DONNÉES
// ===================================================================================

// !! SÉCURITÉ !! Remplacez ceci par votre véritable clé API OpenAI.
// Ne la stockez jamais en clair dans un fichier versionné.
$apiKey = 'VOTRE_CLÉ_API_OPENAI_ICI';

// --- KPIs Globaux ---
$kpi_projets_actifs = $pdo->query("SELECT COUNT(*) FROM projets WHERE date_fin IS NULL OR date_fin >= CURRENT_DATE")->fetchColumn();
$kpi_taches_critiques = $pdo->query("SELECT COUNT(*) FROM taches WHERE priorite = 'haute' AND statut != 'terminé'")->fetchColumn();
$kpi_completion_globale = $pdo->query("SELECT AVG(progression) FROM taches")->fetchColumn() ?? 0;
$kpi_utilisateurs_total = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// --- Vue Gantt ---
$projets_progress = $pdo->query("
    SELECT p.id, p.nom, p.date_debut, p.date_fin, COALESCE(AVG(t.progression), 0) as progression_moyenne
    FROM projets p LEFT JOIN taches t ON p.id = t.projet_id
    GROUP BY p.id, p.nom, p.date_debut, p.date_fin ORDER BY p.date_fin ASC
")->fetchAll(PDO::FETCH_ASSOC);

// --- Données pour la carte "Activité des Membres" ---
$member_performance_data = $pdo->query("
    SELECT u.nom, t.nom as tache_nom, t.date_fin_reelle FROM users u 
    JOIN taches t ON u.id = t.assigne_a
    WHERE t.statut = 'terminé' AND t.date_fin_reelle IS NOT NULL
    ORDER BY t.date_fin_reelle DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$tasks_by_user_data = $pdo->query("
    SELECT u.nom as utilisateur, t.nom as tache, t.statut, t.priorite, p.nom as nom_projet FROM users u 
    JOIN taches t ON u.id = t.assigne_a
    JOIN projets p ON t.projet_id = p.id
    WHERE t.statut != 'terminé'
    ORDER BY u.nom, t.priorite DESC
")->fetchAll(PDO::FETCH_ASSOC);


// --- Tâches critiques à analyser par l'IA ---
$critical_tasks_for_ai = $pdo->query("
    SELECT t.nom, p.nom as nom_projet, u.nom as nom_utilisateur, t.statut, t.priorite, t.date_fin_estimee
    FROM taches t
    JOIN projets p ON t.projet_id = p.id
    LEFT JOIN users u ON t.assigne_a = u.id
    WHERE t.statut = 'en cours' AND (t.priorite = 'haute' OR t.date_fin_estimee <= CURRENT_DATE + INTERVAL '3 days')
    ORDER BY t.date_fin_estimee ASC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// --- Appel à l'IA pour obtenir des suggestions ---
$ai_suggestions = getTaskSuggestionsFromAI($critical_tasks_for_ai, $apiKey);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Supervision IA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        :root {
            --bg-color: #f0f4f8; --panel-color: #ffffff; --border-color: #e2e8f0;
            --text-primary: #1e293b; --text-secondary: #64748b; --accent-color: #4f46e5;
            --accent-glow: rgba(79, 70, 229, 0.5); --accent-hover: #4338ca; 
            --shadow-color: rgba(71, 85, 105, 0.08); --shadow-hover-color: rgba(71, 85, 105, 0.15);
            --success-color: #10b981; --warning-color: #f59e0b; --danger-color: #ef4444; --info-color: #3b82f6;
        }
        :root.dark-mode {
            --bg-color: #0f172a; --panel-color: #1e293b; --border-color: #334155;
            --text-primary: #f1f5f9; --text-secondary: #94a3b8; --accent-color: #6366f1;
            --accent-glow: rgba(99, 102, 241, 0.5); --accent-hover: #818cf8;
            --shadow-color: rgba(0, 0, 0, 0.15); --shadow-hover-color: rgba(0, 0, 0, 0.3);
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideIn { from { width: 0; } to { width: var(--progress-width, 0%); } }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-color); color: var(--text-primary); transition: background-color 0.3s, color 0.3s; line-height: 1.6; }
        #background-canvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -2; opacity: 0.3; }
        .page-wrapper { max-width: 1800px; margin: 2rem auto; padding: 0 2rem; }
        
        .header { margin-bottom: 2rem; animation: fadeIn 0.5s ease-out both; }
        .header-controls { display: flex; justify-content: space-between; align-items: center; }
        h1 { font-size: 2rem; font-weight: 700; display: flex; align-items: center; gap: 1rem; }
        
        .btn { background: var(--panel-color); border: 1px solid var(--border-color); color: var(--text-secondary); padding: 0.6rem 1.2rem; border-radius: 8px; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s ease-in-out; text-decoration: none; }
        .btn:hover { color: var(--accent-color); border-color: var(--accent-color); transform: translateY(-2px); box-shadow: 0 4px 10px var(--shadow-hover-color); }
        
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }
        .kpi-card { background-color: var(--panel-color); border-radius: 12px; padding: 1.5rem; border: 1px solid var(--border-color); display: flex; align-items: center; gap: 1.5rem; transition: all 0.3s; box-shadow: 0 4px 6px -1px var(--shadow-color); animation: fadeIn 0.5s ease-out both; }
        .kpi-card:nth-child(2) { animation-delay: 0.1s; } .kpi-card:nth-child(3) { animation-delay: 0.2s; } .kpi-card:nth-child(4) { animation-delay: 0.3s; }
        .kpi-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px var(--shadow-hover-color), 0 0 15px var(--accent-glow); }
        .kpi-card .icon { font-size: 1.75rem; background-color: var(--bg-color); padding: 1rem; border-radius: 50%; width: 60px; height: 60px; display: flex; justify-content: center; align-items: center; }
        .kpi-card .text-content .value { font-size: 2.25rem; font-weight: 700; }
        .kpi-card .text-content .label { font-size: 0.9rem; color: var(--text-secondary); }
        
        .dashboard-layout { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; }
        @media (max-width: 1400px) { .dashboard-layout { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 900px) { .dashboard-layout { grid-template-columns: 1fr; } }
        
        .card { background-color: var(--panel-color); border-radius: 16px; padding: 2rem; border: 1px solid var(--border-color); box-shadow: 0 4px 12px var(--shadow-color); display: flex; flex-direction: column; transition: all 0.3s; animation: fadeIn 0.6s ease-out both; }
        .dashboard-layout .card:nth-child(1) { animation-delay: 0.5s; } .dashboard-layout .card:nth-child(2) { animation-delay: 0.6s; } .dashboard-layout .card:nth-child(3) { animation-delay: 0.7s; }
        
        .card-header { font-size: 1.25rem; margin-bottom: 1.5rem; display:flex; align-items:center; gap: 0.75rem; font-weight: 600; }
        .card-header i { color: var(--accent-color); }
        .card-content { flex-grow: 1; overflow-y: auto; scrollbar-width: thin; scrollbar-color: var(--accent-color) var(--bg-color); }
        .card-content::-webkit-scrollbar { width: 8px; }
        .card-content::-webkit-scrollbar-track { background: var(--bg-color); border-radius: 4px; }
        .card-content::-webkit-scrollbar-thumb { background-color: var(--accent-color); border-radius: 4px; }

        .gantt-chart-container, .task-list-container { display: flex; flex-direction: column; gap: 1rem; }
        .gantt-item { padding: 0.5rem 0; border-bottom: 1px solid var(--border-color); }
        .gantt-item:last-child { border: none; }
        .gantt-info-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem; }
        .project-name { font-weight: 500; }
        .gantt-dates { font-size: 0.75rem; color: var(--text-secondary); }
        .progress-bar-bg { background-color: var(--bg-color); border-radius: 5px; height: 8px; width: 100%; }
        .progress-bar-fill { height: 100%; background-color: var(--accent-color); border-radius: 5px; animation: slideIn 1s ease-out forwards; }
        
        .task-list { list-style: none; padding: 0; }
        .task-list li { padding: 0.75rem 0.25rem; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; transition: background-color 0.2s; }
        .task-list li:last-child { border-bottom: none; }
        .task-list li:hover { background-color: rgba(128,128,128, 0.1); border-radius: 4px; }
        .task-list span { font-size: 0.8rem; opacity: 0.8; color: var(--text-secondary); }

        .suggestion-list { display: flex; flex-direction: column; gap: 1rem; list-style: none; padding: 0; }
        .suggestion-item { display: flex; gap: 1.5rem; align-items: flex-start; padding: 1.25rem; border-radius: 8px; background: var(--bg-color); border-left: 5px solid; }
        .suggestion-item.danger { border-color: var(--danger-color); } .suggestion-item.warning { border-color: var(--warning-color); } .suggestion-item.info { border-color: var(--info-color); } .suggestion-item.success { border-color: var(--success-color); }
        .suggestion-icon { font-size: 1.5rem; margin-top: 0.25rem; }
        .suggestion-item.danger .suggestion-icon { color: var(--danger-color); } .suggestion-item.warning .suggestion-icon { color: var(--warning-color); } .suggestion-item.info .suggestion-icon { color: var(--info-color); } .suggestion-item.success .suggestion-icon { color: var(--success-color); }
        .suggestion-content h4 { margin: 0 0 0.25rem; font-size: 1.05rem; color: var(--text-primary); }
        .suggestion-content p { margin: 0; font-size: 0.9rem; color: var(--text-secondary); }
    </style>
</head>
<body>
    <canvas id="background-canvas"></canvas>

    <div class="page-wrapper">
        <div class="header">
            <div class="header-controls">
                <h1><i class="fas fa-cogs"></i> Dashboard de Supervision IA</h1>
                <div class="header-actions">
                    <button id="theme-toggle" class="btn" title="Changer de thème"><i class="fas fa-moon"></i></button>
                </div>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card"><div class="icon" style="color:var(--info-color);"><i class="fas fa-folder-open"></i></div><div class="text-content"><div class="value" data-target="<?= $kpi_projets_actifs ?>">0</div><div class="label">Projets Actifs</div></div></div>
            <div class="kpi-card"><div class="icon" style="color:var(--danger-color);"><i class="fas fa-exclamation-circle"></i></div><div class="text-content"><div class="value" data-target="<?= $kpi_taches_critiques ?>">0</div><div class="label">Tâches Critiques</div></div></div>
            <div class="kpi-card"><div class="icon" style="color:var(--success-color);"><i class="fas fa-chart-line"></i></div><div class="text-content"><div class="value" data-target="<?= round($kpi_completion_globale, 1) ?>">0</div><div class="label">Avancement Global (%)</div></div></div>
            <div class="kpi-card"><div class="icon" style="color:#9333ea;"><i class="fas fa-users"></i></div><div class="text-content"><div class="value" data-target="<?= $kpi_utilisateurs_total ?>">0</div><div class="label">Utilisateurs Inscrits</div></div></div>
        </div>

        <div class="dashboard-layout">
            <!-- Colonne 1 : Assistant IA en temps réel -->
            <div class="card">
                <h2 class="card-header"><i class="fas fa-magic-sparkles"></i> Assistant IA en Temps Réel</h2>
                <div class="card-content">
                    <ul class="suggestion-list">
                        <?php foreach ($ai_suggestions as $suggestion): ?>
                            <li class="suggestion-item <?= htmlspecialchars($suggestion['type']) ?>">
                                <i class="fas <?= htmlspecialchars($suggestion['icon']) ?> suggestion-icon"></i>
                                <div class="suggestion-content">
                                    <h4><?= htmlspecialchars($suggestion['title']) ?></h4>
                                    <p><?= htmlspecialchars($suggestion['suggestion']) ?></p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Colonne 2 : Chronologie -->
            <div class="card">
                <h2 class="card-header"><i class="fas fa-stream"></i>Chronologie des Projets</h2>
                <div class="card-content gantt-chart-container">
                    <?php if (empty($projets_progress)): ?><p style="text-align:center; color: var(--text-secondary);">Aucun projet à afficher.</p><?php else: foreach ($projets_progress as $project): ?>
                    <div class="gantt-item">
                        <div class="gantt-info-header">
                            <span class="project-name" title="<?= htmlspecialchars($project['nom']) ?>"><?= htmlspecialchars($project['nom']) ?></span>
                            <span style="font-weight: 600;"><?= round($project['progression_moyenne']) ?>%</span>
                        </div>
                        <div class="progress-bar-bg"><div class="progress-bar-fill" style="--progress-width: <?= round($project['progression_moyenne']) ?>%;"></div></div>
                        <div class="gantt-dates">Du <?= ($project['date_debut'] ? (new DateTime($project['date_debut']))->format('d/m/y') : 'N/A') ?> au <?= ($project['date_fin'] ? (new DateTime($project['date_fin']))->format('d/m/y') : 'N/A') ?></div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- Colonne 3 : Activité des Membres -->
            <div class="card">
                 <h2 class="card-header"><i class="fas fa-user-check"></i>Activité des Membres</h2>
                <div class="card-content">
                    <h4>Dernières Tâches Terminées</h4>
                     <ul class="task-list">
                        <?php if (empty($member_performance_data)): ?><p style="text-align:center; font-size:0.9rem; color: var(--text-secondary);">Aucune tâche terminée récemment.</p><?php else: foreach ($member_performance_data as $task): ?>
                        <li>
                            <strong><?= htmlspecialchars($task['tache_nom']) ?></strong><br>
                            <span>par <?= htmlspecialchars($task['nom']) ?> - le <?= (new DateTime($task['date_fin_reelle']))->format('d/m/Y') ?></span>
                        </li>
                        <?php endforeach; endif; ?>
                    </ul>
                    <hr style="margin: 1.5rem 0; border: 1px solid var(--border-color); border-top: none;">
                    <h4>Tâches en Cours</h4>
                    <ul class="task-list">
                         <?php if (empty($tasks_by_user_data)): ?><p style="text-align:center; font-size:0.9rem; color: var(--text-secondary);">Aucune tâche en cours.</p><?php else: foreach ($tasks_by_user_data as $task): ?>
                         <li>
                            <strong><?= htmlspecialchars($task['tache']) ?></strong> (Projet: <?= htmlspecialchars($task['nom_projet']) ?>)<br>
                            <span>Assignée à <?= htmlspecialchars($task['utilisateur']) ?> - Priorité: <span style="font-weight:500;"><?= htmlspecialchars($task['priorite']) ?></span></span>
                         </li>
                         <?php endforeach; endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        
        const themeToggleBtn = document.getElementById('theme-toggle');
        const updateTheme = () => {
            const isDark = document.documentElement.classList.contains('dark-mode');
            themeToggleBtn.querySelector('i').className = isDark ? 'fas fa-sun' : 'fas fa-moon';
            if (window.setupCanvas) window.setupCanvas();
        };
        
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
        updateTheme();
        
        themeToggleBtn.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark-mode');
            localStorage.setItem('theme', document.documentElement.classList.contains('dark-mode') ? 'dark' : 'light');
            updateTheme();
        });

        const animateKPIs = () => {
            const counters = document.querySelectorAll('.kpi-card .value');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const counter = entry.target;
                        const target = +counter.getAttribute('data-target');
                        const duration = 1500;
                        let startTimestamp = null;
                        const step = (timestamp) => {
                            if (!startTimestamp) startTimestamp = timestamp;
                            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                            let currentValue = progress * target;
                            
                            if (target % 1 !== 0) {
                                counter.innerText = currentValue.toFixed(1);
                            } else {
                                counter.innerText = Math.floor(currentValue);
                            }
                            
                            if (progress < 1) {
                                window.requestAnimationFrame(step);
                            } else {
                                if (target % 1 !== 0) counter.innerText = target.toFixed(1);
                                else counter.innerText = target;
                            }
                        };
                        window.requestAnimationFrame(step);
                        observer.unobserve(counter);
                    }
                });
            }, { threshold: 0.5 });
            counters.forEach(counter => observer.observe(counter));
        };
        animateKPIs();

        const canvas = document.getElementById('background-canvas');
        if(!canvas) return;
        let animationFrameId;
        window.setupCanvas = () => {
            if (animationFrameId) cancelAnimationFrame(animationFrameId);
            const context = canvas.getContext('2d');
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            const isDark = document.documentElement.classList.contains('dark-mode');
            const streamColor = isDark ? 'rgba(16, 185, 129, 0.07)' : 'rgba(79, 70, 229, 0.07)';
            context.font = '16px monospace';
            const columns = Math.floor(canvas.width / 16);
            const streams = Array.from({ length: columns }, () => ({ y: Math.random() * -canvas.height, speed: Math.random() * 3 + 1, char: String.fromCharCode(0x30A0 + Math.random() * 96) }));
            const draw = () => {
                context.fillStyle = isDark ? 'rgba(15, 23, 42, 0.1)' : 'rgba(240, 244, 248, 0.1)';
                context.fillRect(0, 0, canvas.width, canvas.height);
                context.fillStyle = streamColor;
                streams.forEach((stream, i) => {
                    context.fillText(stream.char, i * 16, stream.y);
                    stream.y += stream.speed;
                    if (stream.y > canvas.height) {
                        stream.y = 0;
                        stream.speed = Math.random() * 3 + 1;
                        stream.char = String.fromCharCode(0x30A0 + Math.random() * 96);
                    }
                });
                animationFrameId = requestAnimationFrame(draw);
            };
            draw();
        };
        window.addEventListener('resize', window.setupCanvas);
        window.setupCanvas();
    });
</script>
</body>
</html>
