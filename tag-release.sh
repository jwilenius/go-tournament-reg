#!/bin/bash
# Tag HEAD with the current plugin version

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

VERSION=$(grep "Version:" go-tournament-registration.php | head -1 | awk -F: '{print $2}' | tr -d ' ')
TAG="v${VERSION}"

git tag "$TAG"
echo "Tagged HEAD as $TAG"
echo ""
echo "Push with:"
echo "  git push origin $TAG"
