#!/usr/bin/env bash
# Starts a local minikube cluster (docker driver) with the ingress addon
# enabled — everything scripts/deploy.sh needs to be able to apply the
# k8s/ manifests against.
set -euo pipefail

minikube start --driver=docker --cpus=4 --memory=4500mb
minikube addons enable ingress

echo
echo "cluster ready. ingress-nginx pods:"
kubectl -n ingress-nginx get pods
