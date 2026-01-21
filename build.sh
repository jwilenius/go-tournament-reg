#!/bin/bash
# Build script for Go Tournament Registration plugin
# Creates a WordPress-installable ZIP file

set -e

# Get version from plugin file
VERSION=$(grep "Version:" go-tournament-registration.php | head -1 | awk -F: '{print $2}' | tr -d ' ')

# Output filename
OUTPUT="go-tournament-registration-${VERSION}.zip"

# Remove old build if exists
rm -f "$OUTPUT"

# Create ZIP using git archive (respects .gitattributes export-ignore)
git archive --format=zip --prefix=go-tournament-registration/ HEAD -o "$OUTPUT"

echo "Created: $OUTPUT"
echo "Plugin version: $VERSION"

# Show contents
echo ""
echo "ZIP contents:"
unzip -l "$OUTPUT"
