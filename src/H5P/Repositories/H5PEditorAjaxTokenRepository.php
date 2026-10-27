<?php

namespace Lambda\H5P\Repositories;

use Brnysn\LaravelH5P\Repositories\H5PEditorAjaxRepository;
use Tymon\JWTAuth\Facades\JWTAuth;

class H5PEditorAjaxTokenRepository extends H5PEditorAjaxRepository
{
    /**
     * The vendor implementation validates against Passport's RSA key
     * (storage/oauth-public.key) which lambda apps do not use — every file
     * upload failed with a misleading LIBRARY_NOT_FOUND. Lambda issues
     * HS256 tokens via tymon/jwt-auth, so validate with that instead.
     */
    public function validateEditorToken($token): bool
    {
        if (!$token) {
            return false;
        }

        try {
            return (bool) JWTAuth::setToken($token)->authenticate();
        } catch (\Exception $e) {
            return false;
        }
    }
}
