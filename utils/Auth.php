<?php
namespace Utils;

class Auth {
    private static $sessionStarted = false;
    
    public static function startSession() {
        // Vérifier si une session est déjà active
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        self::$sessionStarted = true;
    }
    
    public static function login($username, $password) {
        self::startSession();
        
        // Connexion à la base de données pour vérifier les utilisateurs
        $db = \Config\Database::getInstance()->getConnection();
        
        // Rechercher l'utilisateur
        $sql = "SELECT * FROM utilisateurs WHERE email = :email AND actif = 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':email' => $username]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        // Debug temporaire (à retirer après test)
        if ($user) {
            error_log("Utilisateur trouvé : " . $user['email']);
            error_log("Hash en base : " . $user['mot_de_passe']);
            error_log("Vérification : " . (password_verify($password, $user['mot_de_passe']) ? 'OUI' : 'NON'));
        } else {
            error_log("Aucun utilisateur trouvé pour : " . $username);
        }
        
        if ($user && password_verify($password, $user['mot_de_passe'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['email'],
                'nom' => $user['nom'],
                'prenom' => $user['prenom'],
                'role' => $user['role'],
                'logged_in' => true
            ];
            
            // Mettre à jour la dernière connexion
            $updateSql = "UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = :id";
            $updateStmt = $db->prepare($updateSql);
            $updateStmt->execute([':id' => $user['id']]);
            
            return true;
        }
        
        // Pour la démo, garder l'admin par défaut
        if ($username === 'admin@dpd.fr' && $password === 'admin123') {
            $_SESSION['user'] = [
                'id' => 1,
                'username' => $username,
                'nom' => 'Admin',
                'prenom' => 'DPD',
                'role' => 'admin',
                'logged_in' => true
            ];
            return true;
        }
        
        // Utilisateurs de test temporaires
        if ($username === 'lecteur@dpd.fr' && $password === 'lecteur123') {
            $_SESSION['user'] = [
                'id' => 99,
                'username' => $username,
                'nom' => 'Lecteur',
                'prenom' => 'Test',
                'role' => 'lecteur',
                'logged_in' => true
            ];
            return true;
        }
        
        if ($username === 'chef@dpd.fr' && $password === 'chef123') {
            $_SESSION['user'] = [
                'id' => 98,
                'username' => $username,
                'nom' => 'Chef',
                'prenom' => 'Equipe',
                'role' => 'chef_equipe',
                'logged_in' => true
            ];
            return true;
        }
        
        return false;
    }
    
    public static function logout() {
        self::startSession();
        session_destroy();
        self::$sessionStarted = false;
    }
    
    public static function isLoggedIn() {
        self::startSession();
        return isset($_SESSION['user']) && $_SESSION['user']['logged_in'] === true;
    }
    
    public static function getUser() {
        self::startSession();
        return $_SESSION['user'] ?? null;
    }
    
    public static function requireAuth() {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
    
    public static function requireRole($role) {
        self::requireAuth();
        $user = self::getUser();
        if ($user['role'] !== $role) {
            header('HTTP/1.1 403 Forbidden');
            exit('Accès interdit');
        }
    }
}