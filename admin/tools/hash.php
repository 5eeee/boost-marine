<?php
require_once __DIR__ . '/../config.php';
requireAuth();
echo password_hash('BoostMarine123456789', PASSWORD_DEFAULT);