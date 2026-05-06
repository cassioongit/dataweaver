#!/bin/bash

# Vogel / Conciliação Digital - Dev Environment Setup
# This script starts both the Vite Frontend and the PHP Legacy API

echo "Vogel Dev Start Sequence"
echo "-----------------------------------"
echo "[Frontend]: Vite on port 5173"
echo "[Backend]: PHP API on port 8888"
echo "-----------------------------------"

# Kill any stray processes
pkill -f vite
pkill -f "php -S localhost:8888"

# Start concurrently
npm run dev:all
