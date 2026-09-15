#!/usr/bin/env bash
# Builds both images locally and loads them straight into minikube's
# container runtime, so the demo cluster never has to pull from Docker Hub.
#
# Usage: ./scripts/load-images.sh <dockerhub-username> [tag]
set -euo pipefail
cd "$(dirname "$0")/.."

USERNAME="${1:?usage: load-images.sh <dockerhub-username> [tag]}"
TAG="${2:-dev}"

docker build -t "${USERNAME}/foogra-api:${TAG}" -f docker/api.Dockerfile .
docker build -t "${USERNAME}/foogra-web:${TAG}" -f docker/frontend.Dockerfile .

minikube image load "${USERNAME}/foogra-api:${TAG}"
minikube image load "${USERNAME}/foogra-web:${TAG}"

echo "loaded ${USERNAME}/foogra-api:${TAG} and ${USERNAME}/foogra-web:${TAG} into minikube"
