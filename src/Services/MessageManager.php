<?php

namespace Geekmusclay\Ollama\Services;

use Exception;
use PDO;
use PDOException;

/**
 * Classe MessageManager
 * Gère les messages (création, récupération, suppression)
 */
class MessageManager
{
    private string $dbHost = 'localhost';
    private string $dbName = 'database';
    private string $dbUser = 'root';
    private string $dbPass = 'root';
    private ?PDO $dbConn = null;
    
    /**
     * Constructeur
     * Initialise la connexion à la base de données
     */
    public function __construct()
    {
        try {
            $this->dbConn = new PDO(
                "mysql:host={$this->dbHost};dbname={$this->dbName};charset=utf8mb4",
                $this->dbUser,
                $this->dbPass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            throw new Exception("Erreur de connexion à la base de données: " . $e->getMessage());
        }
    }
    
    /**
     * Récupère tous les messages d'une conversation
     * 
     * @param int $conversationId Identifiant de la conversation
     * @return array Liste des messages
     */
    public function getMessagesForConversation(int $conversationId): array
    {
        try {
            $stmt = $this->dbConn->prepare("
                SELECT * FROM messages 
                WHERE conversation_id = ? 
                ORDER BY created_at ASC
            ");
            
            $stmt->execute([$conversationId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la récupération des messages: " . $e->getMessage());
        }
    }
    
    /**
     * Ajoute un message à une conversation
     * 
     * @param int $conversationId Identifiant de la conversation
     * @param string $role Rôle de l'émetteur (user ou assistant)
     * @param string $content Contenu du message
     * @param array $metadata Métadonnées supplémentaires (optionnel)
     * @return int Identifiant du nouveau message
     */
    public function addMessage(int $conversationId, string $role, string $content, array $metadata = null): int
    {
        try {
            $stmt = $this->dbConn->prepare("
                INSERT INTO messages (conversation_id, role, content, metadata, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $metadataJson = $metadata ? json_encode($metadata) : null;
            $stmt->execute([$conversationId, $role, $content, $metadataJson]);
            
            // Mettre à jour la date de dernière modification de la conversation
            $stmtUpdate = $this->dbConn->prepare("
                UPDATE conversations 
                SET updated_at = NOW() 
                WHERE id = ?
            ");
            $stmtUpdate->execute([$conversationId]);
            
            return $this->dbConn->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de l'ajout du message: " . $e->getMessage());
        }
    }
    
    /**
     * Récupère le dernier message de l'utilisateur dans une conversation
     * 
     * @param int $conversationId Identifiant de la conversation
     * @return array|null Le dernier message de l'utilisateur ou null si aucun message n'est trouvé
     */
    public function getLastUserMessage(int $conversationId): ?array
    {
        try {
            $stmt = $this->dbConn->prepare("
                SELECT * FROM messages 
                WHERE conversation_id = ? 
                AND role = 'user' 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            
            $stmt->execute([$conversationId]);
            $message = $stmt->fetch();
            
            return $message ?: null;
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la récupération du dernier message utilisateur: " . $e->getMessage());
        }
    }
    
    /**
     * Récupère un message spécifique
     * 
     * @param int $id Identifiant du message
     * @return array Détails du message
     */
    public function getMessage(int $id): array
    {
        try {
            $stmt = $this->dbConn->prepare("
                SELECT * FROM messages WHERE id = ?
            ");
            
            $stmt->execute([$id]);
            $message = $stmt->fetch();
            
            if (!$message) {
                throw new Exception("Message non trouvé");
            }
            
            // Décoder les métadonnées JSON si présentes
            if ($message['metadata']) {
                $message['metadata'] = json_decode($message['metadata'], true);
            }
            
            return $message;
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la récupération du message: " . $e->getMessage());
        }
    }
    
    /**
     * Supprime un message
     * 
     * @param int $id Identifiant du message
     * @return bool Succès de l'opération
     */
    public function deleteMessage(int $id): bool
    {
        try {
            $stmt = $this->dbConn->prepare("
                DELETE FROM messages WHERE id = ?
            ");
            
            $stmt->execute([$id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la suppression du message: " . $e->getMessage());
        }
    }
    
    /**
     * Supprime tous les messages d'une conversation
     * 
     * @param int $conversationId Identifiant de la conversation
     * @return bool Succès de l'opération
     */
    public function deleteAllMessagesForConversation(int $conversationId): bool
    {
        try {
            $stmt = $this->dbConn->prepare("
                DELETE FROM messages WHERE conversation_id = ?
            ");
            
            $stmt->execute([$conversationId]);
            return true;
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la suppression des messages: " . $e->getMessage());
        }
    }
}
