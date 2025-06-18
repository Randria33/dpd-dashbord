<?php
require_once 'autoload.php';
use Config\Database;

$db = Database::getInstance()->getConnection();

// Ajouter quelques logs de test
$actions = [
    ['action' => 'Connexion', 'type' => 'connexion'],
    ['action' => 'Création incident', 'type' => 'creation'],
    ['action' => 'Modification statut', 'type' => 'statut'],
    ['action' => 'Ajout remarque', 'type' => 'remarque']
];

foreach ($actions as $log) {
    $sql = "INSERT INTO logs_activite (utilisateur_id, action, entite, entite_id, type_action, created_at) 
            VALUES (1, :action, 'incident', 1, :type, NOW())";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':action' => $log['action'],
        ':type' => $log['type']
    ]);
}

echo "Logs de test ajoutés !";