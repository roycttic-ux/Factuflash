#!/bin/bash
# Script para preparar Docker en el VPS

echo "Instalando Docker..."
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER

echo "Instalando Docker Compose..."
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

echo "Preparando carpetas..."
sudo mkdir -p /var/www/factuflash
sudo chown -R $USER:$USER /var/www/factuflash

echo "¡Listo! El servidor ya puede recibir el despliegue."
