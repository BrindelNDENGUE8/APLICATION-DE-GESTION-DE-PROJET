<?php
// connexion.php - Utilisation de PDO pour la connexion à PostgreSQL

// Paramètres de connexion à la base de données PostgreSQL
$host = "localhost";
$dbname = "projet_gestion"; // Votre nom de base de données
$user = "postgres";      // Votre utilisateur PostgreSQL
$password = "cisco";     // Votre mot de passe PostgreSQL (Vérifiez-le bien !)
$port = "5432";          // Port par défaut de PostgreSQL

// DSN (Data Source Name) pour PDO PostgreSQL
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password";

$pdo = null; // Initialise $pdo à null par défaut

try {
    // Tenter d'établir la connexion PDO
    $pdo = new PDO($dsn);

    // Configurer les attributs de PDO pour de meilleures pratiques
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Lève des exceptions en cas d'erreur
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); // Récupère les résultats en tableaux associatifs
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // Désactive l'émulation des requêtes préparées (meilleure sécurité)

    // Optionnel : Définir l'encodage des caractères (déjà géré par PDO DSN généralement)
    // $pdo->exec("SET NAMES 'UTF8'");

    // Message de succès (à retirer en production)
    // echo "Connexion PDO à la base de données réussie !";

} catch (PDOException $e) {
    // Gérer l'erreur de connexion PDO
    // En production, loguez l'erreur et affichez un message générique.
    error_log("Erreur de connexion PDO à la base de données : " . $e->getMessage());
    die("Désolé, impossible de se connecter à la base de données pour le moment. Veuillez réessayer plus tard.");
}
?>