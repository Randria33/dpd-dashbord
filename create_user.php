<?php
require_once 'autoload.php';
use Config\Database;

// Créer un utilisateur lecteur avec un mot de passe simple
$email = 'lecteur@dpd.fr';
$password = 'lecteur123';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    $db = Database::getInstance()->getConnection();
    
    // Supprimer l'ancien utilisateur s'il existe
    $deleteSql = "DELETE FROM utilisateurs WHERE email = :email";
    $deleteStmt = $db->prepare($deleteSql);
    $deleteStmt->execute([':email' => $email]);
    
    // Créer le nouvel utilisateur
    $insertSql = "INSERT INTO utilisateurs (email, mot_de_passe, nom, prenom, role, actif) 
                  VALUES (:email, :password, :nom, :prenom, :role, 1)";
    $insertStmt = $db->prepare($insertSql);
    $insertStmt->execute([
        ':email' => $email,
        ':password' => $hashedPassword,
        ':nom' => 'Lecteur',
        ':prenom' => 'Test',
        ':role' => 'lecteur'
    ]);
    
    echo "Utilisateur créé avec succès!<br>";
    echo "Email: $email<br>";
    echo "Mot de passe: $password<br>";
    echo "Hash: $hashedPassword<br>";
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage();
}