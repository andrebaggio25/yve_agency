<?php
if (preg_match('/\.(js|css|png|jpg|gif|svg|ico|woff|woff2|ttf|eot)$/', $_SERVER["REQUEST_URI"])) {
    return false;
}
require_once 'public/index.php';
