<?php
namespace Utils;

class Response {
    
    public function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        // CORS headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    public function success($message = 'Succès', $data = null, $statusCode = 200) {
        $response = [
            'success' => true,
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        return $this->json($response, $statusCode);
    }
    
    public function error($message = 'Erreur', $statusCode = 500, $errors = []) {
        $response = [
            'success' => false,
            'message' => $message
        ];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        return $this->json($response, $statusCode);
    }
    
    public function unauthorized($message = 'Non autorisé') {
        return $this->error($message, 401);
    }
    
    public function forbidden($message = 'Accès interdit') {
        return $this->error($message, 403);
    }
    
    public function notFound($message = 'Ressource non trouvée') {
        return $this->error($message, 404);
    }
    
    public function validationError($errors) {
        return $this->error('Erreur de validation', 422, $errors);
    }
}