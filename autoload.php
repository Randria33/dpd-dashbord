<?php
// autoload.php - Autoloader simple pour le projet

spl_autoload_register(function ($class) {
    // Convertir les namespaces en chemins de fichiers
    $file = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    
    // Vérifier si le fichier existe
    if (file_exists($file)) {
        require $file;
    }
});

// Charger les variables d'environnement si .env existe
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Configuration de base
date_default_timezone_set('Europe/Paris');
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ===== STRUCTURE DE BASE DE DONNÉES (database.sql) ===== */
/*

-- Base de données : dpd_incidents
CREATE DATABASE IF NOT EXISTS dpd_incidents 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE dpd_incidents;

-- Table des tournées
CREATE TABLE tournees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(10) NOT NULL UNIQUE,
    nom VARCHAR(100) NOT NULL,
    zone VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_numero (numero)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des types d'incidents
CREATE TABLE types_incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    couleur VARCHAR(7) DEFAULT '#2563eb',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_nom (nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table principale des incidents
CREATE TABLE incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournee_id INT NOT NULL,
    type_incident_id INT NOT NULL,
    nom_client VARCHAR(200) NOT NULL,
    adresse_complete VARCHAR(500) NOT NULL,
    ville VARCHAR(100),
    code_postal VARCHAR(5),
    numero_colis VARCHAR(50),
    numero_reclamation VARCHAR(50),
    date_incident DATE NOT NULL,
    description TEXT,
    statut ENUM('nouveau', 'en_cours', 'resolu', 'ferme') DEFAULT 'nouveau',
    priorite ENUM('basse', 'normale', 'haute', 'urgente') DEFAULT 'normale',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tournee_id) REFERENCES tournees(id) ON DELETE RESTRICT,
    FOREIGN KEY (type_incident_id) REFERENCES types_incidents(id) ON DELETE RESTRICT,
    
    INDEX idx_date (date_incident),
    INDEX idx_statut (statut),
    INDEX idx_priorite (priorite),
    INDEX idx_ville (ville),
    INDEX idx_colis (numero_colis),
    INDEX idx_reclamation (numero_reclamation),
    INDEX idx_client (nom_client),
    FULLTEXT idx_fulltext (nom_client, adresse_complete, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des utilisateurs (pour l'administration)
CREATE TABLE utilisateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100),
    role ENUM('admin', 'operateur', 'lecteur') DEFAULT 'lecteur',
    actif BOOLEAN DEFAULT TRUE,
    derniere_connexion DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des logs d'activité
CREATE TABLE logs_activite (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT,
    action VARCHAR(100) NOT NULL,
    entite VARCHAR(50),
    entite_id INT,
    details JSON,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL,
    INDEX idx_action (action),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Données de démonstration

-- Insertion des tournées
INSERT INTO tournees (numero, nom, zone) VALUES
('310', 'Tournée Paris Nord', 'Paris Nord'),
('320', 'Tournée Paris Sud', 'Paris Sud'),
('330', 'Tournée Paris Est', 'Paris Est'),
('340', 'Tournée Paris Ouest', 'Paris Ouest'),
('410', 'Tournée Banlieue Nord', 'Banlieue'),
('420', 'Tournée Banlieue Sud', 'Banlieue'),
('510', 'Tournée Express', 'Express');

-- Insertion des types d'incidents
INSERT INTO types_incidents (nom, description, couleur) VALUES
('Réclamation', 'Réclamation client', '#ef4444'),
('Colis endommagé', 'Colis arrivé endommagé', '#f59e0b'),
('Retard livraison', 'Livraison en retard', '#3b82f6'),
('Colis perdu', 'Colis non retrouvé', '#991b1b'),
('Adresse incorrecte', 'Problème d\'adresse', '#8b5cf6'),
('Client absent', 'Client absent lors de la livraison', '#6b7280'),
('Refus colis', 'Client refuse le colis', '#f97316');

-- Insertion d'un utilisateur admin par défaut
INSERT INTO utilisateurs (email, mot_de_passe, nom, prenom, role) VALUES
('admin@dpd.fr', '$2y$10$YourHashedPasswordHere', 'Admin', 'DPD', 'admin');

-- Insertion de quelques incidents de démonstration
INSERT INTO incidents (tournee_id, type_incident_id, nom_client, adresse_complete, ville, code_postal, numero_colis, numero_reclamation, date_incident, description, statut, priorite) VALUES
(1, 1, 'Jean Dupont', '123 Rue de la Paix 75001 Paris', 'Paris', '75001', 'COL123456', 'REC789', CURDATE(), 'Client mécontent du retard', 'nouveau', 'haute'),
(2, 2, 'Marie Martin', '45 Avenue des Champs 75008 Paris', 'Paris', '75008', 'COL789012', 'REC456', CURDATE() - INTERVAL 1 DAY, 'Carton abîmé', 'en_cours', 'normale'),
(1, 3, 'Pierre Bernard', '78 Boulevard Haussmann 75009 Paris', 'Paris', '75009', 'COL345678', NULL, CURDATE() - INTERVAL 2 DAY, 'Livraison promise non respectée', 'resolu', 'urgente'),
(3, 4, 'Sophie Leroy', '12 Rue du Commerce 75015 Paris', 'Paris', '75015', 'COL901234', 'REC123', CURDATE() - INTERVAL 3 DAY, 'Colis introuvable dans le système', 'nouveau', 'urgente'),
(4, 5, 'Luc Moreau', '56 Rue de Rivoli 75004 Paris', 'Paris', '75004', 'COL567890', NULL, CURDATE() - INTERVAL 4 DAY, 'Numéro de rue incorrect', 'ferme', 'basse');

-- Création d'une vue pour les statistiques rapides
CREATE VIEW vue_statistiques_incidents AS
SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN statut = 'nouveau' THEN 1 ELSE 0 END) as nouveaux,
    SUM(CASE WHEN statut = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
    SUM(CASE WHEN statut = 'resolu' THEN 1 ELSE 0 END) as resolus,
    SUM(CASE WHEN statut = 'ferme' THEN 1 ELSE 0 END) as fermes,
    SUM(CASE WHEN priorite = 'urgente' THEN 1 ELSE 0 END) as urgents,
    DATE(created_at) as date_creation
FROM incidents
GROUP BY DATE(created_at);

-- Procédure stockée pour nettoyer les vieux logs
DELIMITER //
CREATE PROCEDURE nettoyer_logs(IN jours INT)
BEGIN
    DELETE FROM logs_activite 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL jours DAY);
END//
DELIMITER ;

-- Trigger pour mettre à jour automatiquement la ville et le code postal
DELIMITER //
CREATE TRIGGER extract_ville_cp_before_insert
BEFORE INSERT ON incidents
FOR EACH ROW
BEGIN
    IF NEW.ville IS NULL OR NEW.code_postal IS NULL THEN
        IF NEW.adresse_complete REGEXP '[0-9]{5}' THEN
            SET NEW.code_postal = REGEXP_SUBSTR(NEW.adresse_complete, '[0-9]{5}');
            SET NEW.ville = TRIM(REGEXP_SUBSTR(NEW.adresse_complete, '[0-9]{5}[[:space:]]+([^,]+)', 1, 1, '', 1));
        END IF;
    END IF;
END//
DELIMITER ;

-- Permissions pour l'utilisateur de l'application
-- GRANT ALL PRIVILEGES ON dpd_incidents.* TO 'dpd_user'@'localhost' IDENTIFIED BY 'your_password';
-- FLUSH PRIVILEGES;

*/