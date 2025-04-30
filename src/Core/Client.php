<?php

namespace Geekmusclay\Ollama\Core;

use Exception;

/**
 * Classe OllamaClient
 * Gère les requêtes vers l'API Ollama
 */
class Client
{
    private string $apiUrl;
    
    /**
     * Constructeur
     * @param string $apiUrl URL de l'API Ollama (par défaut: http://localhost:11434)
     */
    public function __construct(string $apiUrl = 'http://localhost:11434')
    {
        $this->apiUrl = $apiUrl;
    }
    
    /**
     * Envoie un message à l'API Ollama et retourne la réponse
     * 
     * @param string $message Le message à envoyer
     * @param string $model Le modèle à utiliser (par défaut: llama3.2)
     * @param array $options Options supplémentaires pour l'API
     * @return array La réponse de l'API
     * @throws Exception En cas d'erreur lors de la requête
     */
    public function sendMessage(string $message, string $model = 'llama3.2', array $options = []): array
    {
        $url = $this->apiUrl . '/api/generate';
        
        $data = array_merge([
            'model' => 'llama3.2',
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
     * @param string $model Le modèle à utiliser (par défaut: llama3.2)
     * @param array $options Options supplémentaires pour l'API
     * @return void
     * @throws Exception En cas d'erreur lors de la requête
     */
    public function streamMessage(string $message, string $model = 'llama3.2', array $options = []): void
    {
        // Configuration des en-têtes pour le streaming
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        
        $url = $this->apiUrl . '/api/generate';
        
        $data = array_merge([
            'model' => 'llama3.2',
            'prompt' => $message,
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
            if ($jsonData !== null) {
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
