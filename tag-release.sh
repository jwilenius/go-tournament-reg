#!/bin/bash
# Tag HEAD with the current plugin version

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

OVERRIDE=false

for arg in "$@"; do
    case "$arg" in
        --override) OVERRIDE=true ;;
        -h|--help)
            echo "Usage: ./tag-release.sh [--override]"
            echo ""
            echo "Tags HEAD with the version from go-tournament-registration.php."
            echo ""
            echo "Options:"
            echo "  --override   Delete and re-push the tag if it already exists"
            echo "  -h, --help   Show this help message"
            exit 0
            ;;
    esac
done

VERSION=$(grep "Version:" go-tournament-registration.php | head -1 | awk -F: '{print $2}' | tr -d ' ')
TAG="v${VERSION}"

if git tag | grep -q "^${TAG}$"; then
    if [ "$OVERRIDE" = true ]; then
        git tag -d "$TAG"
        git push origin ":$TAG" 2>/dev/null || true
    else
        echo "Tag $TAG already exists. Use --override to replace it."
        exit 1
    fi
fi

git tag "$TAG"
echo "Tagged HEAD as $TAG"
echo ""
echo "Push with:"
echo "  git push origin $TAG"
