<?php
namespace Utils;

class Validator {
    
    public function isValidId($id) {
        return is_numeric($id) && $id > 0;
    }
    
    public function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public function isValidDate($date, $format = 'Y-m-d') {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    public function validateDate($date) {
        if ($this->isValidDate($date)) {
            return $date;
        }
        return null;
    }
    
    public function isValidPhone($phone) {
        // Format français
        return preg_match('/^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/', $phone);
    }
    
    public function isValidPostalCode($code) {
        // Code postal français
        return preg_match('/^\d{5}$/', $code);
    }
    
    public function sanitizeString($string) {
        return htmlspecialchars(strip_tags(trim($string)), ENT_QUOTES, 'UTF-8');
    }
    
    public function validateRequired($value, $fieldName) {
        if (empty($value)) {
            return "$fieldName est requis";
        }
        return null;
    }
    
    public function validateLength($value, $fieldName, $min, $max) {
        $length = strlen($value);
        if ($length < $min) {
            return "$fieldName doit contenir au moins $min caractères";
        }
        if ($length > $max) {
            return "$fieldName ne doit pas dépasser $max caractères";
        }
        return null;
    }
    
    public function validateEnum($value, $fieldName, $allowedValues) {
        if (!in_array($value, $allowedValues)) {
            return "$fieldName doit être l'une des valeurs suivantes : " . implode(', ', $allowedValues);
        }
        return null;
    }
}