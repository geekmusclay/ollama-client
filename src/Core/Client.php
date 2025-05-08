<?php

namespace Geekmusclay\Ollama\Core;

use Exception;

/**
 * Classe OllamaClient
 * Gère les requêtes vers l'API Ollama
 */
class Client
{
    private string $model;
    private string $apiUrl;
    
    /**
     * Constructeur
     * @param string $model Le modèle à utiliser (par défaut: llama3.2)
     * @param string $apiUrl URL de l'API Ollama (par défaut: http://localhost:11434)
     */
    public function __construct(
        string $model = 'llama3.2', 
        string $apiUrl = 'http://localhost:11434'
    ) {
        $this->model = $model;
        $this->apiUrl = $apiUrl;
    }
    
    /**
     * Envoie un message à l'API Ollama et retourne la réponse
     * 
     * @param string $message Le message à envoyer
     * @param array $options Options supplémentaires pour l'API
     * @return array La réponse de l'API
     * @throws Exception En cas d'erreur lors de la requête
     */
    public function sendMessage(string $message, array $options = []): array
    {
        $url = $this->apiUrl . '/api/generate';
        
        $data = array_merge([
            'model' => $this->model,
            'prompt' => $message,
            'stream' => false
        ], $options);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception('Erreur cURL: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Erreur API Ollama: Code HTTP ' . $httpCode);
        }
        
        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Erreur de décodage JSON: ' . json_last_error_msg());
        }
        
        return $responseData;
    }
    
    /**
     * Envoie un message à l'API Ollama avec streaming
     * Cette méthode est utile pour afficher la réponse au fur et à mesure
     * 
     * @param string $message Le message à envoyer
     * @param array $options Options supplémentaires pour l'API
     * @param array $messageHistory Historique des messages précédents (optionnel)
     * @return void
     * @throws Exception En cas d'erreur lors de la requête
     */
    public function streamMessage(string $message, array $options = [], array $messageHistory = []): void
    {
        // Configuration des en-têtes pour le streaming
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        
        $url = $this->apiUrl . '/api/generate';
        
        // Construire le prompt avec l'historique des messages si disponible
        $prompt = $this->buildPromptWithHistory($message, $messageHistory);
        
        $data = array_merge([
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => true
        ], $options);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        // Fonction pour traiter les données de streaming
        $callback = function($ch, $data) {
            $jsonData = json_decode($data, true);
            if ($jsonData === null) {
                return strlen($data);
            }
            
            if (isset($jsonData['response'])) {
                echo "data: " . json_encode(['content' => $jsonData['response']]) . "\n\n";
                ob_flush();
                flush();
            }
            
            if (isset($jsonData['done']) && $jsonData['done'] === true) {
                echo "event: done\ndata: null\n\n";
                ob_flush();
                flush();
            }
            
            return strlen($data);
        };
        
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, $callback);
        
        curl_exec($ch);
        
        if (curl_errno($ch)) {
            echo "event: error\ndata: " . json_encode(['error' => curl_error($ch)]) . "\n\n";
            ob_flush();
            flush();
        }
        
        curl_close($ch);
    }
    
    /**
     * Construit un prompt incluant l'historique des messages pour le contexte
     * 
     * @param string $currentMessage Le message actuel de l'utilisateur
     * @param array $messageHistory L'historique des messages précédents
     * @return string Le prompt complet avec l'historique
     */
    private function buildPromptWithHistory(string $currentMessage, array $messageHistory = []): string
    {
        // Si pas d'historique, retourner simplement le message actuel
        if (empty($messageHistory)) {
            return $currentMessage;
        }
        
        // Construire le prompt avec l'historique
        $prompt = "";
        
        // Ajouter chaque message de l'historique au prompt
        foreach ($messageHistory as $message) {
            $role = $message['role'] === 'user' ? 'Utilisateur' : 'Assistant';
            $prompt .= "$role: {$message['content']}\n\n";
        }
        
        // Ajouter le message actuel
        $prompt .= "Utilisateur: $currentMessage\n\nAssistant:";
        
        return $prompt;
    }
    
    /**
     * Récupère la liste des modèles disponibles
     * 
     * @return array Liste des modèles
     * @throws Exception En cas d'erreur lors de la requête
     */
    public function getModels(): array
    {
        $url = $this->apiUrl . '/api/tags';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception('Erreur cURL: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Erreur API Ollama: Code HTTP ' . $httpCode);
        }
        
        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Erreur de décodage JSON: ' . json_last_error_msg());
        }
        
        return $responseData;
    }
}
