<?php

namespace App\Logger;

interface Logger
{
    function write($msg, $content = null): void;
}