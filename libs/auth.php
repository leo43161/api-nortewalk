<?php

require_once('config/jwt/JWTExceptionWithPayloadInterface.php');
require_once('config/jwt/CachedKeySet.php');
require_once('config/jwt/ExpiredException.php');
require_once('config/jwt/SignatureInvalidException.php');
require_once('config/jwt/BeforeValidException.php');
require_once('config/jwt/JWK.php');
require_once('config/jwt/Key.php');
require_once('config/jwt/JWT.php');

use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\Key;
use Firebase\JWT\JWT;

class JwtHandler
{
    private $secretKey;

    public function __construct($secretKey)
    {
        $this->secretKey = $secretKey;
    }

    public function generateToken($adminId, $email, $role, $providerId = null)
    {
        $issuedAt = time();
        $payload = [
            'iat' => $issuedAt,
            'exp' => $issuedAt + (60 * 60 * 24), // 24h
            'data' => [
                'admin_id'    => $adminId,
                'email'       => $email,
                'role'        => $role,
                'provider_id' => $providerId !== null ? (int)$providerId : null
            ]
        ];
        return JWT::encode($payload, $this->secretKey, 'HS256');
    }

    public function validate($token)
    {
        try {
            $payload = JWT::decode($token, new Key($this->secretKey, 'HS256'));
            return ['status' => 'success', 'data' => $payload];
        } catch (ExpiredException $e) {
            return ['status' => 'error', 'message' => 'Token expirado'];
        } catch (SignatureInvalidException $e) {
            return ['status' => 'error', 'message' => 'Firma invalida'];
        } catch (BeforeValidException $e) {
            return ['status' => 'error', 'message' => 'Token aun no valido'];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Token invalido'];
        }
    }
}
