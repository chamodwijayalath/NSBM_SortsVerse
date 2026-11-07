#!/usr/bin/env bash
# Runs the site with PHP's built-in server: http://localhost:8000
cd "$(dirname "$0")" && php -S localhost:8000 -t . router.php
