<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Geekmusclay\Ollama\Core\Client;
use Geekmusclay\Ollama\Services\ConversationManager;
use Geekmusclay\Ollama\Services\MessageManager;

use Geekmusclay\DI\Core\Container;
use GuzzleHttp\Psr7\ServerRequest;
use Geekmusclay\Router\Core\Router;
use Psr\Http\Message\ServerRequestInterface;

// Activer les erreurs en développement
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Headers pour l'API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Parse .env file
$env = parse_ini_file(__DIR__ . '/../.env');
if (count($env) === 0) {
    die('No .env file found');
}

$container = new Container();
$container->set('defaultModel', $env['DEFAULT_MODEL']);
$container->set('apiUrl', $env['API_URL']);

$router = new Router($container);

$router->get('/', function (ServerRequestInterface $request): void {
    var_dump($request->getQueryParams());
    echo 'Hello World !';
});

$router->get('/hello/:name', function (string $name): void {
    if (null === $name) {
        echo 'Hello World !';
    } else {
        echo 'Hello ' . $name . ' !';
    }
})->with([
    'name' => '[a-zA-Z0-9]+'
]);

$router->get('/models', function (ServerRequestInterface $request) use ($container): void {
    // On prend le model de la requête ou le model par defaut
    $model = $request->getQueryParams()['model'] ?? $container->get('defaultModel');
    $apiUrl = $container->get('apiUrl');
    
    $models = (new Client($model, $apiUrl))->getModels();
    
    echo json_encode(['success' => true, 'data' => $models]);
});

$router->post('/chat', function (ServerRequestInterface $request) use ($container): void {
    $data = json_decode($request->getBody()->getContents(), true);
    $model = $data['model'] ?? $container->get('defaultModel');
    $apiUrl = $container->get('apiUrl');

    $response = (new Client($model, $apiUrl))->sendMessage($data['message'], $data['model'] ?? 'llama3');
    
    echo json_encode(['success' => true, 'data' => $response]);
});

$router->get('/conversations', function (): void {
    $conversations = (new ConversationManager())->getAllConversations();
    
    echo json_encode(['success' => true, 'data' => $conversations]);
});

$router->post('/conversations', function (ServerRequestInterface $request): void {
    $data = json_decode($request->getBody()->getContents(), true);
    $conversationId = (new ConversationManager())->createConversation($data['title'] ?? 'Nouvelle conversation');
    
    echo json_encode(['success' => true, 'data' => ['id' => $conversationId]]);
});

$router->get('/conversations/:id', function (int $id): void {
    $conversation = (new ConversationManager())->getConversation($id);
    
    echo json_encode(['success' => true, 'data' => $conversation]);
})->with([
    'id' => '[0-9]+'
]);

$router->put('/conversations/:id', function (ServerRequestInterface $request, int $id): void {
    $data = json_decode($request->getBody()->getContents(), true);
    $success = (new ConversationManager())->updateConversation($id, $data);
    
    echo json_encode(['success' => $success]);
})->with([
    'id' => '[0-9]+'
]);

$router->delete('/conversations/:id', function (int $id): void {
    $success = (new ConversationManager())->deleteConversation($id);
    
    echo json_encode(['success' => $success]);
})->with([
    'id' => '[0-9]+'
]);

$router->get('/conversations/:id/messages', function (int $id): void {
    $messages = (new MessageManager())->getMessagesForConversation($id);
    
    echo json_encode(['success' => true, 'data' => $messages]);
})->with([
    'id' => '[0-9]+'
]);

$router->post('/conversations/:id/messages', function (ServerRequestInterface $request, int $id) use ($container): void {
    // On prend le model de la requête ou le model par defaut
    $model = $request->getQueryParams()['model'] ?? $container->get('defaultModel');
    $apiUrl = $container->get('apiUrl');
    
    // Ajouter un message à une conversation (méthode non-streaming)
    $client = new Client($model, $apiUrl);
    $messageManager = new MessageManager();
    $data = json_decode($request->getBody()->getContents(), true);
    $userMessageId = $messageManager->addMessage($id, 'user', $data['content']);
    
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
    
    $aiMessageId = $messageManager->addMessage($id, 'assistant', $responseContent);
    
    echo json_encode([
        'success' => true, 
        'data' => [
            'userMessageId' => $userMessageId,
            'aiMessageId' => $aiMessageId,
            'aiResponse' => $response
        ]
    ]);
})->with([
    'id' => '[0-9]+'
]);

$router->post('/conversations/:id/messages/user', function (ServerRequestInterface $request, int $id): void {
    $messageManager = new MessageManager();
    $data = json_decode($request->getBody()->getContents(), true);
    $messageId = $messageManager->addMessage($id, 'user', $data['content']);
    echo json_encode([
        'success' => true,
        'data' => [
            'messageId' => $messageId
        ]
    ]);
})->with([
    'id' => '[0-9]+'
]);

$router->post('/conversations/:id/messages/assistant', function (ServerRequestInterface $request, int $id): void {
    $messageManager = new MessageManager();
    $data = json_decode($request->getBody()->getContents(), true);
    $messageId = $messageManager->addMessage($id, 'assistant', $data['content']);
    echo json_encode([
        'success' => true,
        'data' => [
            'messageId' => $messageId
        ]
    ]);
})->with([
    'id' => '[0-9]+'
]);

$router->get('/conversations/:id/messages/:messageId', function (int $id, int $messageId): void {
    $message = (new MessageManager())->getMessage($id, $messageId);
    
    echo json_encode($message);
})->with([
    'id' => '[0-9]+',
    'messageId' => '[0-9]+'
]);

$router->get('/conversations/:id/messages/stream', function (ServerRequestInterface $request, int $id) use ($container): void {
    // Récupérer le dernier message de l'utilisateur
    $messageManager = new MessageManager();
    $lastUserMessage = $messageManager->getLastUserMessage($id);
    
    if (!$lastUserMessage) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Aucun message utilisateur trouvé']);
        exit;
    }
    
    // Récupérer l'historique des messages de la conversation (limité aux 10 derniers messages)
    $messageHistory = $messageManager->getMessagesForConversation($id);
    
    // Limiter l'historique aux 10 derniers messages pour éviter des prompts trop longs
    // Note: nous excluons le dernier message car c'est celui que nous venons d'ajouter
    if (count($messageHistory) > 1) {
        $messageHistory = array_slice($messageHistory, 0, min(10, count($messageHistory) - 1));
    } else {
        $messageHistory = [];
    }
    
    // On prend le model de la requête ou le model par defaut
    $model = $request->getQueryParams()['model'] ?? $container->get('defaultModel');
    $apiUrl = $container->get('apiUrl');
    
    // Initialiser le client Ollama
    $client = new Client($model, $apiUrl);
    
    // Envoyer la réponse en streaming avec l'historique des messages
    $client->streamMessage($lastUserMessage['content'], [], $messageHistory);
    exit; // Important pour arrêter l'exécution après le streaming
})->with([
    'id' => '[0-9]+'
]);

$router->delete('/conversations/:id/messages/:messageId', function (int $id, int $messageId): void {
    $success = (new MessageManager())->deleteMessage($id, $messageId);
    
    echo json_encode(['success' => $success]);
})->with([
    'id' => '[0-9]+',
    'messageId' => '[0-9]+'
]);

$router->post('/conversations/:id/assistant', function (ServerRequestInterface $request, int $id): void {
    $messageManager = new MessageManager();
    $data = json_decode($request->getBody()->getContents(), true);
    $messageId = $messageManager->addMessage($id, 'assistant', $data['content']);
    echo json_encode([
        'success' => true,
        'data' => [
            'messageId' => $messageId
        ]
    ]);
})->with([
    'id' => '[0-9]+'
]);

try {
    $router->run(ServerRequest::fromGlobals());
} catch (Exception $e) {
    die($e->getMessage());
}
