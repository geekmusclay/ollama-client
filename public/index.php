<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Geekmusclay\Ollama\Core\Client;
use Geekmusclay\Ollama\Services\ConversationManager;
use Geekmusclay\Ollama\Services\MessageManager;

/**
 * Point d'entrée de l'API
 * Gère les requêtes entrantes et les redirige vers les contrôleurs appropriés
 */

// Activer les erreurs en développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Headers pour l'API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Gérer les requêtes OPTIONS (pre-flight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Autoloader
spl_autoload_register(function ($class) {
    // Chemin de base pour les classes
    $file = __DIR__ . '/src/' . $class . '.php';
    if (file_exists($file)) {
        require $file;
        return true;
    }
    return false;
});

// Récupérer l'URL demandée
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/back/'; // Ajuster selon votre configuration
$endpoint = str_replace($basePath, '', $requestUri);
$endpoint = strtok($endpoint, '?'); // Enlever les paramètres de requête

// Récupérer la méthode HTTP
$method = $_SERVER['REQUEST_METHOD'];

// Récupérer les données de la requête
$data = json_decode(file_get_contents('php://input'), true);

// Router simple
try {
    switch ($endpoint) {
        case 'models':
            $ollamaClient = new Client();
            $models = $ollamaClient->getModels();
            echo json_encode(['success' => true, 'data' => $models]);
            break;
        case 'chat':
            // Endpoint pour les conversations avec Ollama
            if ($method === 'POST') {
                // Envoyer un message à Ollama
                $ollamaClient = new Client();
                $response = $ollamaClient->sendMessage($data['message'], $data['model'] ?? 'llama3');
                echo json_encode(['success' => true, 'data' => $response]);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['error' => 'Méthode non autorisée']);
            }
            break;
            
        case 'conversations':
            $conversationManager = new ConversationManager();
            
            if ($method === 'GET') {
                // Récupérer toutes les conversations
                $conversations = $conversationManager->getAllConversations();
                echo json_encode(['success' => true, 'data' => $conversations]);
            } elseif ($method === 'POST') {
                // Créer une nouvelle conversation
                $conversationId = $conversationManager->createConversation($data['title'] ?? 'Nouvelle conversation');
                echo json_encode(['success' => true, 'data' => ['id' => $conversationId]]);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['error' => 'Méthode non autorisée']);
            }
            break;
            
        case (preg_match('/^conversations\/(\d+)$/', $endpoint, $matches) ? true : false):
            $conversationId = $matches[1];
            $conversationManager = new ConversationManager();
            
            if ($method === 'GET') {
                // Récupérer une conversation spécifique
                $conversation = $conversationManager->getConversation($conversationId);
                echo json_encode(['success' => true, 'data' => $conversation]);
            } elseif ($method === 'PUT') {
                // Mettre à jour une conversation
                $success = $conversationManager->updateConversation($conversationId, $data);
                echo json_encode(['success' => $success]);
            } elseif ($method === 'DELETE') {
                // Supprimer une conversation
                $success = $conversationManager->deleteConversation($conversationId);
                echo json_encode(['success' => $success]);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['error' => 'Méthode non autorisée']);
            }
            break;
            
        case (preg_match('/^conversations\/(\d+)\/messages$/', $endpoint, $matches) ? true : false):
            $conversationId = $matches[1];
            $messageManager = new MessageManager();
            
            if ($method === 'GET') {
                // Récupérer tous les messages d'une conversation
                $messages = $messageManager->getMessagesForConversation($conversationId);
                echo json_encode(['success' => true, 'data' => $messages]);
            } elseif ($method === 'POST') {
                // Ajouter un message à une conversation (méthode non-streaming)
                $client = new Client();
                $userMessageId = $messageManager->addMessage($conversationId, 'user', $data['content']);
                
                // Envoyer le message à Ollama et récupérer la réponse
                $response = $client->sendMessage($data['content'], $data['model'] ?? 'llama3');
                
                // Vérifier quelle clé est présente dans la réponse (response ou content)
                $responseContent = '';
                if (isset($response['response'])) {
                    $responseContent = $response['response'];
                } elseif (isset($response['content'])) {
                    $responseContent = $response['content'];
                } elseif (isset($response['text'])) {
                    $responseContent = $response['text'];
                } else {
                    // Fallback si aucune clé attendue n'est trouvée
                    $responseContent = json_encode($response);
                }
                
                $aiMessageId = $messageManager->addMessage($conversationId, 'assistant', $responseContent);
                
                echo json_encode([
                    'success' => true, 
                    'data' => [
                        'userMessageId' => $userMessageId,
                        'aiMessageId' => $aiMessageId,
                        'aiResponse' => $response
                    ]
                ]);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['error' => 'Méthode non autorisée']);
            }
            break;
            
        case (preg_match('/^conversations\/(\d+)\/messages\/user$/', $endpoint, $matches) ? true : false):
            $conversationId = $matches[1];
            $messageManager = new MessageManager();
            
            if ($method === 'POST') {
                // Enregistrer uniquement le message de l'utilisateur
                $messageId = $messageManager->addMessage($conversationId, 'user', $data['content']);
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'messageId' => $messageId
                    ]
                ]);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['error' => 'Méthode non autorisée']);
            }
            break;
            
        case (preg_match('/^conversations\/(\d+)\/messages\/assistant$/', $endpoint, $matches) ? true : false):
            $conversationId = $matches[1];
            $messageManager = new MessageManager();
            
            if ($method === 'POST') {
                // Enregistrer uniquement le message de l'assistant
                $messageId = $messageManager->addMessage($conversationId, 'assistant', $data['content']);
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'messageId' => $messageId
                    ]
                ]);
            } else {
                http_response_code(405); // Method Not Allowed
                echo json_encode(['error' => 'Méthode non autorisée']);
            }
            break;
            
        case (preg_match('/^conversations\/(\d+)\/messages\/stream$/', $endpoint, $matches) ? true : false):
            // Endpoint pour le streaming des réponses
            $conversationId = $matches[1];
            
            // Récupérer le modèle depuis les paramètres de requête
            $model = $_GET['model'] ?? 'llama3';
            
            // Récupérer le dernier message de l'utilisateur
            $messageManager = new MessageManager();
            $lastUserMessage = $messageManager->getLastUserMessage($conversationId);
            
            if (!$lastUserMessage) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Aucun message utilisateur trouvé']);
                exit;
            }
            
            // Récupérer l'historique des messages de la conversation (limité aux 10 derniers messages)
            $messageHistory = $messageManager->getMessagesForConversation($conversationId);
            
            // Limiter l'historique aux 10 derniers messages pour éviter des prompts trop longs
            // Note: nous excluons le dernier message car c'est celui que nous venons d'ajouter
            if (count($messageHistory) > 1) {
                $messageHistory = array_slice($messageHistory, 0, min(10, count($messageHistory) - 1));
            } else {
                $messageHistory = [];
            }
            
            // Initialiser le client Ollama
            $client = new Client();
            
            // Envoyer la réponse en streaming avec l'historique des messages
            $client->streamMessage($lastUserMessage['content'], $model, [], $messageHistory);
            exit; // Important pour arrêter l'exécution après le streaming
            
        default:
            http_response_code(404); // Not Found
            echo json_encode(['error' => 'Endpoint non trouvé']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => $e->getMessage()]);
}
