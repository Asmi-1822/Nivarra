<?php

require_once __DIR__.'/auth.php';

if(currentRole() !== 'manager')
{
    http_response_code(403);
    exit('Manager access required');
}