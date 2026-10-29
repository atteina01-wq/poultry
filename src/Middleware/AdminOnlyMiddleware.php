<?php
namespace Poultry\Middleware;

class AdminOnlyMiddleware extends RoleMiddleware {
    public function __construct() {
        parent::__construct(['admin']);
    }
}
