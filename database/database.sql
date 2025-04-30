-- Base de données pour l'application de chat Ollama

-- Création de la base de données si elle n'existe pas
CREATE DATABASE IF NOT EXISTS `ollama_chat` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ollama_chat`;

-- Structure de la table `conversations`
CREATE TABLE IF NOT EXISTS `conversations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Structure de la table `messages`
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `conversation_id` int NOT NULL,
  `role` enum('user','assistant') NOT NULL,
  `content` text NOT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `conversation_id` (`conversation_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion de données de test
INSERT INTO `conversations` (`id`, `title`, `created_at`, `updated_at`) VALUES
(1, 'Première conversation', NOW(), NOW()),
(2, 'Questions sur PHP', NOW(), NOW());

INSERT INTO `messages` (`conversation_id`, `role`, `content`, `created_at`) VALUES
(1, 'user', 'Bonjour, comment ça va ?', NOW()),
(1, 'assistant', 'Bonjour ! Je vais bien, merci. Comment puis-je vous aider aujourd\'hui ?', NOW()),
(2, 'user', 'Peux-tu m\'expliquer les closures en PHP ?', NOW()),
(2, 'assistant', 'Bien sûr ! En PHP, les closures (ou fonctions anonymes) sont des fonctions qui peuvent être créées sans nom. Elles sont particulièrement utiles lorsque vous avez besoin de créer une fonction à la volée, comme argument pour une autre fonction. Voici un exemple simple :\n\n```php\n$greeting = function($name) {\n    return "Bonjour, $name !";\n};\n\necho $greeting("John"); // Affiche : Bonjour, John !\n```\n\nLes closures peuvent également "capturer" des variables de leur portée environnante en utilisant le mot-clé `use` :\n\n```php\n$message = "Bonjour";\n$greeting = function($name) use ($message) {\n    return "$message, $name !";\n};\n\necho $greeting("Jane"); // Affiche : Bonjour, Jane !\n```', NOW());
