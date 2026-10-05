<?php

/**
 * This project is deployed with the document root at the project root, not in
 * public/. The real CodeIgniter 4 front controller is ../index.php (it defines
 * FCPATH and boots the framework).
 *
 * This file used to be a leftover WordPress stub that required
 * wp-blog-header.php. No WordPress exists here, so every request through this
 * path fataled with "Failed opening required ... wp-blog-header.php".
 *
 * public/ is a stale duplicate directory kept only for robots.txt, favicon.ico
 * and the assets/ copy. Nothing should execute here: refuse the request and
 * point at the real front controller.
 */

http_response_code(404);

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex', true);

echo "404 Not Found\n";
echo "public/ is not the document root. The application entry point is the project root index.php.\n";