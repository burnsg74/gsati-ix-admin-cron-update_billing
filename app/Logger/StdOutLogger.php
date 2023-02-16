<?php

namespace App\Logger;

use Exception;

class StdOutLogger implements Logger
{
    public function write($msg, $content = null): void
    {
        if ($content) {
            if (is_array($content)) {
                try {
                    $content = json_encode($content, JSON_THROW_ON_ERROR);
                } catch (Exception) {
                    $content  = print_r($content, true);
                }
            }
            $msg .= " :: $content";
        }
        
        echo "$msg\r\n";
    }
}