#!/usr/bin/env bash

date
composer install --prefer-dist --optimize-autoloader --no-dev
serverless deploy
serverless info