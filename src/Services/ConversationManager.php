<?php

namespace Geekmusclay\Ollama\Services;

use PDO;
use Exception;
use PDOException;

/**
 * Classe ConversationManager
 * Gère les conversations (création, récupération, mise à jour, suppression)
 */
class ConversationManager
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
     * Récupère toutes les conversations
     * 
     * @return array Liste des conversations
     */
    public function getAllConversations(): array
    {
        try {
            $stmt = $this->dbConn->query("
                SELECT c.*, COUNT(m.id) as message_count, MAX(m.created_at) as last_message_date
                FROM conversations c
                LEFT JOIN messages m ON c.id = m.conversation_id
                GROUP BY c.id
                ORDER BY c.created_at DESC
            ");
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la récupération des conversations: " . $e->getMessage());
        }
    }
    
    /**
     * Récupère une conversation spécifique avec ses messages
     * 
     * @param int $id Identifiant de la conversation
     * @return array Détails de la conversation avec ses messages
     */
    public function getConversation(int $id): array
    {
        try {
            // Récupérer les informations de la conversation
            $stmtConv = $this->dbConn->prepare("
                SELECT * FROM conversations WHERE id = ?
            ");
            $stmtConv->execute([$id]);
            $conversation = $stmtConv->fetch();
            
            if (!$conversation) {
                throw new Exception("Conversation non trouvée");
            }
            
            // Récupérer les messages de la conversation
            $stmtMsg = $this->dbConn->prepare("
                SELECT * FROM messages 
                WHERE conversation_id = ? 
                ORDER BY created_at ASC
            ");
            $stmtMsg->execute([$id]);
            $messages = $stmtMsg->fetchAll();
            
            // Ajouter les messages à la conversation
            $conversation['messages'] = $messages;
            
            return $conversation;
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la récupération de la conversation: " . $e->getMessage());
        }
    }
    
    /**
     * Crée une nouvelle conversation
     * 
     * @param string $title Titre de la conversation
     * @return int Identifiant de la nouvelle conversation
     */
    public function createConversation(string $title): int
    {
        try {
            $stmt = $this->dbConn->prepare("
                INSERT INTO conversations (title, created_at, updated_at)
                VALUES (?, NOW(), NOW())
            ");
            
            $stmt->execute([$title]);
            return $this->dbConn->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la création de la conversation: " . $e->getMessage());
        }
    }
    
    /**
     * Met à jour une conversation
     * 
     * @param int $id Identifiant de la conversation
     * @param array $data Données à mettre à jour (title)
     * @return bool Succès de l'opération
     */
    public function updateConversation(int $id, array $data): bool
    {
        try {
            $stmt = $this->dbConn->prepare("
                UPDATE conversations 
                SET title = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$data['title'], $id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de la mise à jour de la conversation: " . $e->getMessage());
        }
    }
    
    /**
     * Supprime une conversation et tous ses messages
     * 
     * @param int $id Identifiant de la conversation
     * @return bool Succès de l'opération
     */
    public function deleteConversation(int $id): bool
    {
        try {
            $this->dbConn->beginTransaction();
            
            // Supprimer d'abord les messages associés
            $stmtMsg = $this->dbConn->prepare("
                DELETE FROM messages WHERE conversation_id = ?
            ");
            $stmtMsg->execute([$id]);
            
            // Puis supprimer la conversation
            $stmtConv = $this->dbConn->prepare("
                DELETE FROM conversations WHERE id = ?
            ");
            $stmtConv->execute([$id]);
            
            $this->dbConn->commit();
            return $stmtConv->rowCount() > 0;
        } catch (PDOException $e) {
            $this->dbConn->rollBack();
            throw new Exception("Erreur lors de la suppression de la conversation: " . $e->getMessage());
        }
    }
}
