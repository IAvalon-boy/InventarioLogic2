<?php
require_once 'config.php';

class Session {
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }
    }
    
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    public static function get($key, $default = null) {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }
    
    public static function setUser($user) {
        $_SESSION['user_id'] = $user['codUsuario'];
        $_SESSION['user_name'] = $user['nombreUsuario'];
        $_SESSION['user_level'] = $user['nivel'];
        $_SESSION['user_center'] = $user['centroAtencion'] ?? '';
        $_SESSION['user_phone'] = $user['tel'] ?? '';
        $_SESSION['user_status'] = $user['status'] ?? 1;
        $_SESSION['logged_in'] = true;
    }
    
    public static function getUser() {
        return [
            'id' => self::get('user_id'),
            'name' => self::get('user_name'),
            'level' => self::get('user_level'),
            'center' => self::get('user_center'),
            'phone' => self::get('user_phone'),
            'status' => self::get('user_status'),
            'logged_in' => self::get('logged_in', false)
        ];
    }
    
    public static function isLoggedIn() {
        return self::get('logged_in', false) === true;
    }
    
    public static function isAdmin() {
        return self::isLoggedIn() && self::get('user_level') == 1;
    }
    
    public static function destroy() {
        $_SESSION = [];
        session_destroy();
    }
}
?>