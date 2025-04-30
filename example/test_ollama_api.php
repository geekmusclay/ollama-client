<?php
/**
 * Script de test pour vérifier l'accès à l'API Ollama locale
 */

// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclure la classe OllamaClient
require_once __DIR__ . '/../../vendor/autoload.php';

use Geekmusclay\Ollama\Core\Client;

// Fonction pour afficher les résultats de test
function displayTestResult($testName, $success, $message = '', $data = null) {
    echo "<div style='margin-bottom: 20px; padding: 10px; border: 1px solid " . ($success ? 'green' : 'red') . ";'>";
    echo "<h3 style='color: " . ($success ? 'green' : 'red') . ";'>" . ($success ? '✓' : '✗') . " $testName</h3>";
    
    if (!empty($message)) {
        echo "<p>$message</p>";
    }
    
    if ($data !== null) {
        echo "<pre>" . print_r($data, true) . "</pre>";
    }
    
    echo "</div>";
}

// En-tête HTML
echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Test de l'API Ollama</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        h1 { color: #333; }
        pre { background-color: #f5f5f5; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Test de l'API Ollama</h1>
";

try {
    // Créer une instance de OllamaClient
    $ollamaClient = new Client();
    
    // Test 1: Vérifier la connexion à l'API Ollama en récupérant la liste des modèles
    try {
        $models = $ollamaClient->getModels();
        displayTestResult(
            "Connexion à l'API Ollama", 
            true, 
            "La connexion à l'API Ollama est établie avec succès.",
            $models
        );
        
        // Si nous avons des modèles, on peut tester l'envoi d'un message
        if (isset($models['models']) && count($models['models']) > 0) {
            $modelName = $models['models'][0]['name'];
            
            // Test 2: Envoyer un message simple
            try {
                $response = $ollamaClient->sendMessage("Bonjour, comment vas-tu ?", $modelName);
                displayTestResult(
                    "Envoi d'un message", 
                    true, 
                    "Message envoyé avec succès au modèle '$modelName'.",
                    $response
                );
            } catch (Exception $e) {
                displayTestResult(
                    "Envoi d'un message", 
                    false, 
                    "Erreur lors de l'envoi du message : " . $e->getMessage()
                );
            }
        } else {
            displayTestResult(
                "Liste des modèles", 
                false, 
                "Aucun modèle n'a été trouvé. Veuillez vérifier que des modèles sont installés sur votre instance Ollama."
            );
        }
    } catch (Exception $e) {
        displayTestResult(
            "Connexion à l'API Ollama", 
            false, 
            "Erreur lors de la connexion à l'API Ollama : " . $e->getMessage()
        );
    }
} catch (Exception $e) {
    displayTestResult(
        "Initialisation", 
        false, 
        "Erreur lors de l'initialisation : " . $e->getMessage()
    );
}

// Pied de page HTML
echo "
</body>
</html>";
