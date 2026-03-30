<?php
// REMOVER APÓS O DEBUG!
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
echo "<pre>";
echo "already_submitted: " . (isset($_SESSION['already_submitted']) ? 'SIM' : 'NAO') . "\n";
echo "SESSION completa:\n";
print_r($_SESSION);
echo "</pre>";