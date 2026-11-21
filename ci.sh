#!/bin/sh
set -eu
cd "$(dirname "$0")"
php build/buildzip.php
