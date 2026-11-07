<?php
/* Router for PHP's built-in server (start.bat / start.sh): blocks direct access to private files. */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
if (preg_match('#^/(data|config|lib|database|legacy)(/|$)#', $path) || preg_match('#\.(sqlite|sql|md|bat|sh)$#i', $path) || basename($path) === 'router.php') {
    http_response_code(403);
    exit('Forbidden');
}
return false;
