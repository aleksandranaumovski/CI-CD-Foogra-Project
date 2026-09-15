#!/usr/bin/env bash
# Applies k8s/ to whatever cluster the current kubeconfig points at.
#
# The image tag is defined in two places — the container spec in
# 32-api-deployment.yaml / 40-web-deployment.yaml, AND the `images:` block
# in kustomization.yaml, which kustomize applies last. Both must point at
# the same username/tag or kubectl ends up pulling the wrong image
# (typically ImagePullBackOff trying to fetch ":latest" from Docker Hub
# instead of the image you just loaded locally).
#
# Usage: ./scripts/deploy.sh <dockerhub-username> [tag]
set -euo pipefail
cd "$(dirname "$0")/.."

USERNAME="${1:?usage: deploy.sh <dockerhub-username> [tag]}"
TAG="${2:-dev}"

find k8s -name '*.yaml' -exec sed -i "s#DOCKERHUB_USERNAME#${USERNAME}#g" {} +
sed -i -E "s#(newTag: ).*#\1${TAG}#g" k8s/kustomization.yaml

kubectl apply -k k8s/

kubectl -n foogra rollout status statefulset/db --timeout=180s
kubectl -n foogra rollout status deployment/api --timeout=300s
kubectl -n foogra rollout status deployment/web --timeout=180s

echo
kubectl -n foogra get all
