<?php
session_start();
require_once 'connexion.php';

// ===================================================================================
// SECTION 0 : VÉRIFICATION DE L'UTILISATEUR (SÉCURITÉ)
// ===================================================================================

// On s'assure qu'un utilisateur est connecté et qu'il est bien un chef de projet.
// Pour cet exemple, on suppose que l'ID du chef de projet est stocké en session.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chef') {
    // Si l'utilisateur n'est pas un chef de projet, on le redirige.
    header('Location: login.php'); // Redirigez vers la page de connexion
    exit();
}

// On récupère l'ID du chef de projet connecté. C'est la clé de tout le filtrage.
$chef_projet_id = $_SESSION['user_id'];

// ===================================================================================
// SECTION 1 : MOTEUR DE PRÉDICTION IA ET ANALYSE (INCHANGÉ)
// ===================================================================================

/**
 * Prépare et envoie une requête à l'API Google Gemini pour prédire l'issue d'un projet.
 * (Fonction identique à votre version, aucune modification nécessaire)
 */
function predictWithGeminiAPI(array $features, string $apiKey): array
{
    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $apiKey;

    // Construction d'un prompt détaillé pour l'IA
    $prompt = "
        Analyse les données de ce projet de gestion et prédis son statut.
        Réponds UNIQUEMENT avec un objet JSON contenant les clés 'status', 'conseil', 'icon', et 'color'.

        Voici les status possibles et leurs couleurs/icônes associées :
        - 'En bonne voie' (couleur: var(--success-color), icône: fa-check-circle)
        - 'À risque' (couleur: var(--warning-color), icône: fa-shield-alt)
        - 'Retard probable' (couleur: var(--danger-color), icône: fa-exclamation-triangle)

        Données du projet :
        - Nom du projet: " . $features['nom'] . "
        - Progression actuelle: " . round($features['progression_actuelle'] ?? 0) . "%
        - Jours planifiés: " . $features['duree_planifiee'] . "
        - Nombre total de tâches: " . $features['total_taches'] . "
        - Nombre de membres dans l'équipe: " . $features['total_membres'] . "
        - Tâches critiques encore actives: " . $features['taches_critiques_actives'] . "

        Fournis un conseil actionnable et concis basé sur ces données.
    ";

    $data = ['contents' => [['parts' => [['text' => $prompt]]]]];
    $jsonData = json_encode($data);

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return [
            'status' => 'Erreur API',
            'conseil' => 'Impossible de contacter le service de prédiction. Erreur: ' . $error,
            'icon' => 'fa-wifi',
            'color' => 'var(--text-secondary)'
        ];
    }

    $result = json_decode($response, true);
    
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $responseText = $result['candidates'][0]['content']['parts'][0]['text'];
        $jsonResponse = trim(str_replace(['```json', '```'], '', $responseText));
        $prediction = json_decode($jsonResponse, true);

        if (json_last_error() === JSON_ERROR_NONE && isset($prediction['status'])) {
            return $prediction;
        }
    }
    
    return [
        'status' => 'Réponse Invalide',
        'conseil' => 'La réponse de l\'IA n\'a pas pu être interprétée.',
        'icon' => 'fa-question-circle',
        'color' => 'var(--text-secondary)'
    ];
}

/**
 * Récupère les caractéristiques clés d'un projet pour l'analyse.
 * (Fonction identique, elle analyse un projet à la fois)
 */
function getProjectFeatures(PDO $pdo, int $projetId): ?array
{
    $query = "
        SELECT
            p.nom,
            p.date_debut,
            p.date_fin AS date_fin_prevue,
            (SELECT COUNT(*) FROM taches WHERE projet_id = p.id) as total_taches,
            (SELECT COUNT(*) FROM projet_membres WHERE projet_id = p.id) as total_membres,
            (SELECT AVG(progression) FROM taches WHERE projet_id = p.id) as progression_actuelle,
            (SELECT COUNT(*) FROM taches WHERE projet_id = p.id AND priorite = 'haute' AND statut != 'terminé') as taches_critiques_actives
        FROM projets p
        WHERE p.id = :projetId
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['projetId' => $projetId]);
    $features = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$features || !$features['date_debut'] || !$features['date_fin_prevue']) {
        return null;
    }
    
    $dateDebut = new DateTime($features['date_debut']);
    $dateFinPrevue = new DateTime($features['date_fin_prevue']);
    $features['duree_planifiee'] = $dateDebut->diff($dateFinPrevue)->days;

    return $features;
}


// ===================================================================================
// SECTION 2 : CONFIGURATION ET RÉCUPÉRATION DES DONNÉES (MODIFIÉ)
// ===================================================================================

// --- Configuration ---
// IMPORTANT : Pour la sécurité, ne stockez JAMAIS votre clé API directement dans le code.
// Utilisez des variables d'environnement ou un fichier de configuration non public.
$apiKey = 'AIzaSyAXZTwNvCHrruC-ZoQvjBauDKJrWA29QL8'; // <-- À REMPLACER

// --- Requête principale modifiée ---
// MODIFICATION : On sélectionne uniquement les projets où le chef_id correspond à l'utilisateur connecté.
$projets_actifs_query = $pdo->prepare("
    SELECT * FROM projets 
    WHERE chef_id = :chef_id 
    AND (date_fin IS NULL OR date_fin >= CURRENT_DATE) 
    ORDER BY date_fin ASC
");
$projets_actifs_query->execute(['chef_id' => $chef_projet_id]);
$projets_actifs = $projets_actifs_query->fetchAll(PDO::FETCH_ASSOC);

// --- Appel de l'IA (logique inchangée, mais données filtrées) ---
$predictions_ia = [];
foreach ($projets_actifs as $projet) {
    $features = getProjectFeatures($pdo, $projet['id']);
    if ($features) {
        $prediction = predictWithGeminiAPI($features, $apiKey);
        $predictions_ia[] = array_merge($features, ['prediction' => $prediction]);
    }
}

// ===================================================================================
// SECTION 3 : INDICATEURS CLÉS (KPIs) (MODIFIÉ)
// ===================================================================================

// MODIFICATION : Tous les KPIs sont maintenant calculés uniquement pour les projets du chef connecté.

// 1. Nombre de projets actifs du chef
$kpi_projets_actifs = count($projets_actifs);

// 2. Tâches critiques dans les projets du chef
$kpi_taches_critiques_query = $pdo->prepare("
    SELECT COUNT(t.id) 
    FROM taches t
    JOIN projets p ON t.projet_id = p.id
    WHERE p.chef_id = :chef_id 
    AND t.priorite = 'haute' 
    AND t.statut != 'terminé'
");
$kpi_taches_critiques_query->execute(['chef_id' => $chef_projet_id]);
$kpi_taches_critiques = $kpi_taches_critiques_query->fetchColumn();

// 3. Complétion globale des projets du chef
$kpi_completion_globale_query = $pdo->prepare("
    SELECT AVG(t.progression) 
    FROM taches t
    JOIN projets p ON t.projet_id = p.id
    WHERE p.chef_id = :chef_id
    AND (p.date_fin IS NULL OR p.date_fin >= CURRENT_DATE)
");
$kpi_completion_globale_query->execute(['chef_id' => $chef_projet_id]);
$kpi_completion_globale = $kpi_completion_globale_query->fetchColumn() ?? 0;

// 4. KPI pertinent pour un chef : Nombre total de membres dans ses équipes
$kpi_membres_equipe_query = $pdo->prepare("
    SELECT COUNT(DISTINCT pm.utilisateur_id)
    FROM projet_membres pm
    JOIN projets p ON pm.projet_id = p.id
    WHERE p.chef_id = :chef_id
");
$kpi_membres_equipe_query->execute(['chef_id' => $chef_projet_id]);
$kpi_membres_total = $kpi_membres_equipe_query->fetchColumn();


// --- Données pour les graphiques (logique inchangée, mais données filtrées) ---
$risk_distribution = [];
if (!empty($predictions_ia)) {
    $risk_distribution = array_count_values(array_column(array_column($predictions_ia, 'prediction'), 'status'));
}
$chart_data_json = json_encode([
    'labels' => array_keys($risk_distribution),
    'data' => array_values($risk_distribution),
]);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Chef de Projet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* CSS identique à votre version, aucune modification nécessaire */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        :root {
            --bg-color: #f0f4f8;
            --panel-color: #ffffff;
            --border-color: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --accent-color: #4f46e5;
            --accent-hover: #4338ca;
            --shadow-color: rgba(71, 85, 105, 0.1);
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
        }
        
        :root.dark-mode {
            --bg-color: #0f172a;
            --panel-color: #1e293b;
            --border-color: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent-color: #6366f1;
            --accent-hover: #818cf8;
            --shadow-color: rgba(0, 0, 0, 0.2);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-primary);
            transition: background-color 0.3s, color 0.3s;
            line-height: 1.6;
        }
        
        #background-canvas {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            z-index: -1; opacity: 0.5; transition: opacity 0.5s;
        }

        .page-wrapper { max-width: 1600px; margin: 2rem auto; padding: 0 2rem; }
        
        .header-controls {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;
        }
        .header-controls h1 { font-size: 2rem; font-weight: 700; }
        .header-controls h1 i { margin-right: 1rem; color: var(--accent-color); }
        .header-actions { display: flex; gap: 1rem; }
        
        .btn {
            background: var(--panel-color); border: 1px solid var(--border-color);
            color: var(--text-secondary); padding: 0.6rem 1.2rem; border-radius: 8px;
            cursor: pointer; font-weight: 500; display: flex; align-items: center;
            gap: 0.5rem; transition: all 0.2s ease-in-out; text-decoration: none;
        }
        .btn:hover { color: var(--accent-color); border-color: var(--accent-color); background-color: var(--bg-color); transform: translateY(-2px); }

        .kpi-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem; margin-bottom: 2.5rem;
        }
        .kpi-card {
            background-color: var(--panel-color); border-radius: 12px; padding: 1.5rem;
            border: 1px solid var(--border-color); display: flex; align-items: center; gap: 1.5rem;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: 0 4px 6px -1px var(--shadow-color);
        }
        .kpi-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px var(--shadow-color); border-left: 4px solid var(--accent-color); }
        .kpi-card .icon { font-size: 1.75rem; color: var(--text-secondary); background-color: var(--bg-color); padding: 1rem; border-radius: 50%; width: 60px; height: 60px; display: flex; justify-content: center; align-items: center; }
        .kpi-card .text-content .value { font-size: 2.25rem; font-weight: 700; }
        .kpi-card .text-content .label { font-size: 0.9rem; color: var(--text-secondary); }

        .main-content { display: grid; grid-template-columns: 1fr 400px; gap: 2rem; }
        @media (max-width: 1200px) { .main-content { grid-template-columns: 1fr; } }
        
        .card {
            background-color: var(--panel-color); border-radius: 16px; padding: 2rem;
            border: 1px solid var(--border-color); box-shadow: 0 4px 6px -1px var(--shadow-color);
        }
        .card h2 { font-size: 1.5rem; margin-bottom: 1.5rem; display:flex; align-items:center; gap: 0.75rem;}
        .card h2 i { color: var(--accent-color); }

        /* Styles pour les prédictions IA */
        .ia-predictions-container { display: flex; flex-direction: column; gap: 1.5rem; }
        .ia-card {
            border-left: 5px solid;
            padding: 1.5rem;
            border-radius: 8px;
            background-color: var(--bg-color);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .ia-card:hover { transform: scale(1.02); box-shadow: 0 8px 20px -4px var(--shadow-color); }
        .ia-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .ia-card-header h3 { font-size: 1.1rem; font-weight: 600; }
        .ia-status { display: flex; align-items: center; gap: 0.5rem; font-weight: 600; font-size: 1rem; }
        .ia-card-body { font-size: 0.9rem; color: var(--text-secondary); }
        .ia-card-features { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-top: 1rem; font-size: 0.85rem; }

        /* Styles pour le graphique */
        .chart-container { position: relative; height: 300px; width: 100%; }
    </style>
</head>
<body>
    <canvas id="background-canvas"></canvas>

    <div class="page-wrapper">
        <div class="header-controls">
            <h1><i class="fas fa-user-tie"></i>Tableau de Bord - Mes Projets</h1>
            <div class="header-actions">
                <a href="logout.php" class="btn"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                <button id="theme-toggle" class="btn" title="Changer de thème"><i class="fas fa-moon"></i></button>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="icon" style="color: var(--info-color);"><i class="fas fa-folder-open"></i></div>
                <div class="text-content"><div class="value"><?= $kpi_projets_actifs ?></div><div class="label">Mes Projets Actifs</div></div>
            </div>
            <div class="kpi-card">
                <div class="icon" style="color: var(--danger-color);"><i class="fas fa-exclamation-circle"></i></div>
                <div class="text-content"><div class="value"><?= $kpi_taches_critiques ?></div><div class="label">Tâches Critiques</div></div>
            </div>
            <div class="kpi-card">
                <div class="icon" style="color: var(--success-color);"><i class="fas fa-chart-line"></i></div>
                <div class="text-content"><div class="value"><?= round($kpi_completion_globale, 1) ?>%</div><div class="label">Avancement Moyen</div></div>
            </div>
            <div class="kpi-card">
                <div class="icon" style="color: #9333ea;"><i class="fas fa-users"></i></div>
                <div class="text-content"><div class="value"><?= $kpi_membres_total ?></div><div class="label">Membres d'Équipe</div></div>
            </div>
        </div>

        <div class="main-content">
            <div class="card">
                <h2><i class="fas fa-bullseye"></i>Analyse et Prédictions IA sur Mes Projets</h2>
                <div class="ia-predictions-container">
                    <?php if (empty($predictions_ia)): ?>
                        <p style="text-align:center; color: var(--text-secondary);">Vous n'avez aucun projet actif à analyser.</p>
                    <?php else: ?>
                        <?php foreach ($predictions_ia as $p_ia): ?>
                            <div class="ia-card" style="border-color: <?= htmlspecialchars($p_ia['prediction']['color']) ?>;">
                                <div class="ia-card-header">
                                    <h3><?= htmlspecialchars($p_ia['nom']) ?></h3>
                                    <span class="ia-status" style="color: <?= htmlspecialchars($p_ia['prediction']['color']) ?>;">
                                        <i class="fas <?= htmlspecialchars($p_ia['prediction']['icon']) ?>"></i>
                                        <span><?= htmlspecialchars($p_ia['prediction']['status']) ?></span>
                                    </span>
                                </div>
                                <div class="ia-card-body">
                                    <strong>Diagnostic IA :</strong> <?= htmlspecialchars($p_ia['prediction']['conseil']) ?>
                                </div>
                                <div class="ia-card-features">
                                    <span><i class="fas fa-tasks"></i> <?= $p_ia['total_taches'] ?> tâches</span>
                                    <span><i class="fas fa-users"></i> <?= $p_ia['total_membres'] ?> membres</span>
                                    <span><i class="fas fa-hourglass-half"></i> <?= $p_ia['progression_actuelle'] ? round($p_ia['progression_actuelle']) : 0 ?>% complété</span>
                                    <span><i class="fas fa-flag"></i> <?= $p_ia['taches_critiques_actives'] ?> tâches critiques</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sidebar">
                <div class="card">
                    <h2><i class="fas fa-chart-pie"></i>Répartition des Risques (Mes Projets)</h2>
                    <div class="chart-container">
                        <canvas id="riskChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
    // Le JavaScript est identique, il est piloté par les données JSON générées par PHP.
    // Comme les données sont déjà filtrées, le JS affichera automatiquement les bons graphiques.
    document.addEventListener('DOMContentLoaded', () => {
        const themeToggleBtn = document.getElementById('theme-toggle');
        const updateButton = () => {
            const isDark = document.documentElement.classList.contains('dark-mode');
            themeToggleBtn.querySelector('i').className = isDark ? 'fas fa-sun' : 'fas fa-moon';
            if (window.setupCanvas) window.setupCanvas();
        };
        
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark-mode');
        }
        updateButton();

        themeToggleBtn.addEventListener('click', () => {
            document.documentElement.classList.toggle('dark-mode');
            const theme = document.documentElement.classList.contains('dark-mode') ? 'dark' : 'light';
            localStorage.setItem('theme', theme);
            updateButton();
            if (window.riskChart instanceof Chart) {
                 window.riskChart.destroy();
            }
            renderRiskChart();
        });

        const renderRiskChart = () => {
            const ctxElement = document.getElementById('riskChart');
            if (!ctxElement) return;
            const ctx = ctxElement.getContext('2d');
            const chartData = JSON.parse('<?= $chart_data_json ?>');
            const isDark = document.documentElement.classList.contains('dark-mode');
            
            const colors = {
                'En bonne voie': 'rgba(16, 185, 129, 0.7)',
                'À risque': 'rgba(245, 158, 11, 0.7)',
                'Retard probable': 'rgba(239, 68, 68, 0.7)',
                'Erreur API': 'rgba(100, 116, 139, 0.7)',
                'Réponse Invalide': 'rgba(100, 116, 139, 0.7)'
            };
            const borderColors = {
                'En bonne voie': 'rgba(16, 185, 129, 1)',
                'À risque': 'rgba(245, 158, 11, 1)',
                'Retard probable': 'rgba(239, 68, 68, 1)',
                'Erreur API': 'rgba(100, 116, 139, 1)',
                'Réponse Invalide': 'rgba(100, 116, 139, 1)'
            };

            if (chartData.labels.length === 0) {
                ctx.font = "16px 'Inter'";
                ctx.fillStyle = isDark ? '#94a3b8' : '#64748b';
                ctx.textAlign = "center";
                ctx.fillText("Aucune donnée de risque à afficher.", ctxElement.width / 2, ctxElement.height / 2);
                return;
            }

            window.riskChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Répartition des Risques',
                        data: chartData.data,
                        backgroundColor: chartData.labels.map(label => colors[label] || '#ccc'),
                        borderColor: chartData.labels.map(label => borderColors[label] || '#ccc'),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: isDark ? '#f8fafc' : '#1e293b',
                                font: { family: "'Inter', sans-serif" }
                            }
                        }
                    }
                }
            });
        }
        renderRiskChart();


        const canvas = document.getElementById('background-canvas');
        const context = canvas.getContext('2d');
        let animationFrameId;

        window.setupCanvas = () => {
            if (animationFrameId) {
                cancelAnimationFrame(animationFrameId);
            }
            if (!canvas) return;
            
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            const isDark = document.documentElement.classList.contains('dark-mode');
            const streamColor = isDark ? 'rgba(16, 185, 129, 0.1)' : 'rgba(79, 70, 229, 0.07)';
            context.font = '16px monospace';

            const columns = Math.floor(canvas.width / 16);
            const streams = Array.from({ length: columns }, () => ({
                y: Math.random() * -canvas.height,
                speed: Math.random() * 4 + 1,
                length: Math.floor(Math.random() * 20 + 10),
                chars: Array.from({length: Math.floor(Math.random() * 20 + 10)}, () => String.fromCharCode(0x30A0 + Math.random() * 96))
            }));

            const draw = () => {
                context.fillStyle = isDark ? 'rgba(15, 23, 42, 0.1)' : 'rgba(240, 244, 248, 0.1)';
                context.fillRect(0, 0, canvas.width, canvas.height);
                context.fillStyle = streamColor;

                streams.forEach((stream, i) => {
                    for (let j = 0; j < stream.length; j++) {
                        const char = stream.chars[j];
                        context.fillText(char, i * 16, stream.y + j * 16);
                    }
                    stream.y += stream.speed;
                    if (stream.y > canvas.height + stream.length * 16) {
                        stream.y = Math.random() * -100 - stream.length * 16;
                        stream.speed = Math.random() * 4 + 1;
                    }
                });
                animationFrameId = requestAnimationFrame(draw);
            };
            draw();
        };

        window.addEventListener('resize', setupCanvas);
        setupCanvas();
    });
</script>
</body>
</html>