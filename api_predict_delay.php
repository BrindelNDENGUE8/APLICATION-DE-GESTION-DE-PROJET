<?php
session_start();
header('Content-Type: application/json');

// --- Sécurité ---
if (!isset($_SESSION['user_id']) || !isset($_GET['project_id'])) {
    echo json_encode(['success' => false, 'error' => 'Accès non autorisé ou projet manquant.']);
    exit();
}

$project_id = $_GET['project_id'];

// --- SIMULATION DE LA PRÉDICTION IA ---
// Dans une application réelle, vous appelleriez ici votre service Python
// via cURL ou une autre méthode, en lui passant des données sur le projet.

// Génération de données aléatoires pour la démo
$prediction_percent = rand(5, 95); // Pourcentage de risque de retard

$suggestions = [
    "La date de fin semble serrée. Envisagez de l'ajuster de 3 jours.",
    "Le projet est sur la bonne voie. Maintenez le rythme actuel.",
    "Risque élevé. Suggestion : assigner un membre d'équipe supplémentaire sur les tâches critiques.",
    "La complexité est sous-estimée. Prévoyez une revue des tâches à venir.",
    "Excellente progression. Aucune action corrective n'est nécessaire pour le moment."
];
$suggestion = $suggestions[array_rand($suggestions)];


// On attend 1 seconde pour simuler le temps de calcul
sleep(1); 

// On renvoie la réponse au format JSON
echo json_encode([
    'success' => true,
    'project_id' => $project_id,
    'prediction_percent' => $prediction_percent,
    'suggestion' => $suggestion
]);

?>
