#!/bin/bash
# Tag HEAD with the current plugin version

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

OVERRIDE=false
PUSH=false
BUMP_VERSION=""

while [ $# -gt 0 ]; do
    case "$1" in
        --override) OVERRIDE=true ;;
        --push) PUSH=true ;;
        --bump)
            shift
            BUMP_VERSION="$1"
            ;;
        --bump=*) BUMP_VERSION="${1#--bump=}" ;;
        -h|--help)
            echo "Usage: ./tag-release.sh [--bump X.Y.Z] [--override] [--push]"
            echo ""
            echo "Tags HEAD with the version from go-tournament-registration.php."
            echo ""
            echo "Options:"
            echo "  --bump X.Y.Z Update the plugin version to X.Y.Z (edits two lines"
            echo "               in go-tournament-registration.php and commits), then"
            echo "               exit. Does not create or push a tag — run without"
            echo "               --bump for that."
            echo "  --override   Delete and re-push the tag if it already exists"
            echo "  --push       Push the tag to origin after creating it"
            echo "  -h, --help   Show this help message"
            exit 0
            ;;
    esac
    shift
done

# --bump X.Y.Z: edit the two canonical version lines and commit, then exit.
if [ -n "$BUMP_VERSION" ]; then
    if ! [[ "$BUMP_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        echo "Error: --bump argument must be X.Y.Z (e.g. 0.6.0), got: $BUMP_VERSION" >&2
        exit 1
    fi

    PLUGIN_FILE="go-tournament-registration.php"
    if ! git diff --quiet -- "$PLUGIN_FILE"; then
        echo "Error: $PLUGIN_FILE has unstaged changes. Commit or stash them before bumping." >&2
        exit 1
    fi
    if ! git diff --cached --quiet -- "$PLUGIN_FILE"; then
        echo "Error: $PLUGIN_FILE has staged changes. Commit them before bumping." >&2
        exit 1
    fi

    CURRENT_VERSION=$(grep "Version:" "$PLUGIN_FILE" | head -1 | awk -F: '{print $2}' | tr -d ' ')
    if [ "$CURRENT_VERSION" = "$BUMP_VERSION" ]; then
        echo "Version is already $BUMP_VERSION — nothing to do."
        exit 0
    fi

    # Refuse to go backwards. `sort -V` orders versions numerically; the
    # lower of the two ends up first, so if that's the new version it's a
    # downgrade and we bail.
    LOWER=$(printf '%s\n%s\n' "$CURRENT_VERSION" "$BUMP_VERSION" | sort -V | head -1)
    if [ "$LOWER" = "$BUMP_VERSION" ]; then
        echo "Error: refusing to bump down from $CURRENT_VERSION to $BUMP_VERSION." >&2
        exit 1
    fi

    # Two canonical version locations: the plugin header and the GTR_VERSION constant.
    sed -i.bak \
        -e "s/^ \* Version: .*/ * Version: ${BUMP_VERSION}/" \
        -e "s/define('GTR_VERSION', '[^']*');/define('GTR_VERSION', '${BUMP_VERSION}');/" \
        "$PLUGIN_FILE"
    rm -f "${PLUGIN_FILE}.bak"

    # Verify both lines updated.
    HEADER_OK=$(grep -c "^ \* Version: ${BUMP_VERSION}\$" "$PLUGIN_FILE" || true)
    CONST_OK=$(grep -c "define('GTR_VERSION', '${BUMP_VERSION}');" "$PLUGIN_FILE" || true)
    if [ "$HEADER_OK" != "1" ] || [ "$CONST_OK" != "1" ]; then
        echo "Error: failed to update both version lines in $PLUGIN_FILE." >&2
        echo "  Plugin header matches: $HEADER_OK (want 1)" >&2
        echo "  GTR_VERSION constant matches: $CONST_OK (want 1)" >&2
        exit 1
    fi

    git add "$PLUGIN_FILE"
    git commit -m "chore: bump version to ${BUMP_VERSION}"
    echo "Bumped version: ${CURRENT_VERSION} → ${BUMP_VERSION}"
    exit 0
fi

VERSION=$(grep "Version:" go-tournament-registration.php | head -1 | awk -F: '{print $2}' | tr -d ' ')
TAG="v${VERSION}"

if git tag | grep -q "^${TAG}$"; then
    if [ "$OVERRIDE" = true ]; then
        git tag -d "$TAG"
        git push origin ":$TAG" 2>/dev/null || true
    else
        echo "Tag $TAG already exists. You must bump the version to set a new release tag."
        echo "   To overwrite the tag on the new HEAD, use --override."
        exit 1
    fi
fi

git tag "$TAG"
echo "Tagged HEAD as $TAG"

if [ "$PUSH" = true ]; then
    git push origin "$TAG"
    echo "Pushed $TAG to origin"
else
    echo ""
    echo "Push with:"
    echo "  git push origin $TAG"
fi
