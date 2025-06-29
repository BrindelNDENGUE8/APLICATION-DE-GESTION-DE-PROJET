<?php
// On initialise les variables pour la réponse et le prompt.
// Cela évite des erreurs si la page est chargée pour la première fois.
$reponseDeGemini = "";
$promptUtilisateur = "";

// On vérifie si le formulaire a été soumis (si la méthode de la requête est POST).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // =================================================================
    // SECTION 1 : CONFIGURATION - METTEZ VOTRE CLÉ D'API ICI
    // =================================================================
    // Remplacez la ligne ci-dessous par la clé que vous avez obtenue de Google AI Studio.
    $apiKey = "AIzaSyAXZTwNvCHrruC-ZoQvjBauDKJrWA29QL8"; 

    // On récupère le prompt envoyé par l'utilisateur depuis le formulaire.
    $promptUtilisateur = $_POST['prompt'] ?? '';

    if (!empty($promptUtilisateur) && $apiKey !== "AIzaSyAXZTwNvCHrruC-ZoQvjBauDKJrWA29QL8") {
        
        // Configuration de l'API
        $model = 'gemini-pro';
        $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $apiKey;

        // Préparation des données à envoyer à Google au format JSON
        $data = ["contents" => [["parts" => [["text" => $promptUtilisateur]]]]];
        $jsonData = json_encode($data);

        // Initialisation de cURL pour envoyer la requête HTTP
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        // =================================================================
        // CORRECTION RAPIDE POUR L'ERREUR SSL (POUR TEST LOCAL UNIQUEMENT)
        // =================================================================
        // Cette ligne désactive la vérification du certificat SSL.
        // NE PAS UTILISER EN PRODUCTION. C'est une faille de sécurité.
        // La solution correcte est de configurer un 'cacert.pem' dans votre php.ini.
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        // Traitement de la réponse
        if ($error) {
            $reponseDeGemini = "<strong style='color:red;'>Erreur de connexion (cURL) :</strong> " . htmlspecialchars($error);
        } else {
            $responseData = json_decode($response);
            if (isset($responseData->candidates[0]->content->parts[0]->text)) {
                $reponseDeGemini = $responseData->candidates[0]->content->parts[0]->text;
            } else {
                $errorMessage = $responseData->error->message ?? 'Réponse invalide de l\'API. Avez-vous bien mis une clé valide ?';
                $reponseDeGemini = "<strong style='color:red;'>Erreur de l'API Gemini :</strong> " . htmlspecialchars($errorMessage);
            }
        }
    } elseif ($apiKey === "AIzaSyAXZTwNvCHrruC-ZoQvjBauDKJrWA29QL8") {
        $reponseDeGemini = "<strong style='color:orange;'>Attention :</strong> Veuillez mettre votre clé d'API dans le code PHP pour que cela fonctionne.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat avec Gemini</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap');
        :root {
            --bg-color: #f4f7f6;
            --container-bg: rgba(255, 255, 255, 0.6);
            --text-color: #333;
            --accent-color: #6f42c1;
            --border-color: rgba(255, 255, 255, 0.3);
            --shadow-color: rgba(0, 0, 0, 0.1);
        }
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 20px;
            box-sizing: border-box;
        }
        .container {
            width: 100%;
            max-width: 800px;
            background: var(--container-bg);
            backdrop-filter: blur(15px);
            border-radius: 20px;
            padding: 30px 40px;
            box-shadow: 0 8px 32px 0 var(--shadow-color);
            border: 1px solid var(--border-color);
        }
        h1 {
            color: var(--accent-color);
            text-align: center;
            margin-bottom: 20px;
        }
        form {
            width: 100%;
        }
        textarea {
            width: 100%;
            min-height: 120px;
            border-radius: 10px;
            border: 1px solid #ddd;
            padding: 15px;
            font-size: 1rem;
            font-family: inherit;
            resize: vertical;
            margin-bottom: 20px;
            box-sizing: border-box;
        }
        button {
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            display: block;
            margin: 0 auto;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(111, 66, 193, 0.3);
        }
        .result-box {
            margin-top: 30px;
            padding: 20px;
            background-color: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            text-align: left;
            min-height: 50px;
            white-space: pre-wrap;
            line-height: 1.7;
            border: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-brain"></i> Interroger l'IA Gemini avec PHP</h1>
        
        <!-- Le formulaire envoie les données à cette même page (action="") en utilisant la méthode POST. -->
        <form action="" method="POST">
            <textarea name="prompt" placeholder="Exemple : Explique le principe de la photosynthèse en termes simples..."><?php echo htmlspecialchars($promptUtilisateur); ?></textarea>
            <button type="submit">
                <i class="fas fa-paper-plane"></i> Envoyer
            </button>
        </form>

        <?php
        // On affiche cette section seulement si la variable $reponseDeGemini n'est pas vide.
        // Elle sera remplie après la soumission du formulaire.
        if (!empty($reponseDeGemini)):
        ?>
            <div class="result-box">
                <?php 
                // On affiche la réponse de Gemini. nl2br transforme les sauts de ligne en balises <br>.
                echo nl2br(htmlspecialchars($reponseDeGemini)); 
                ?>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>
