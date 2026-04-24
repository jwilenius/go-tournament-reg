#!/bin/bash
#
# Start local WordPress development environment with Docker
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Go Tournament Registration - Local Development${NC}"
echo "================================================"

# Check for Docker
if ! command -v docker &> /dev/null; then
    echo -e "${RED}Error: Docker is not installed.${NC}"
    echo "Please install Docker Desktop: https://www.docker.com/products/docker-desktop"
    exit 1
fi

# Check if Docker is running
if ! docker info &> /dev/null; then
    echo -e "${RED}Error: Docker is not running.${NC}"
    echo "Please start Docker Desktop and try again."
    exit 1
fi

# Create .env file if it doesn't exist, or add any missing keys
if [ ! -f .env ]; then
    echo -e "${YELLOW}Creating .env file with default values...${NC}"
    touch .env
fi

add_env_default() {
    local key="$1" value="$2"
    if ! grep -q "^${key}=" .env; then
        echo "${key}=${value}" >> .env
    fi
}

add_env_default MYSQL_DATABASE wordpress
add_env_default MYSQL_USER wordpress
add_env_default MYSQL_PASSWORD wordpress
add_env_default MYSQL_ROOT_PASSWORD rootpassword
add_env_default WORDPRESS_VERSION 6.9.4
add_env_default ASTRA_VERSION 4.13.0

# Load version pins from .env
set -a; source .env; set +a

# Clean up any orphaned containers with conflicting names
for container in wp-go-reg-db wp-go-reg-wordpress; do
    if docker ps -a --format '{{.Names}}' | grep -q "^${container}$"; then
        echo -e "${YELLOW}Removing orphaned container: ${container}${NC}"
        docker rm -f "$container" > /dev/null 2>&1 || true
    fi
done

# Start containers
echo -e "${YELLOW}Starting Docker containers...${NC}"
docker-compose up -d

# Wait for WordPress to be ready
echo -e "${YELLOW}Waiting for WordPress to start...${NC}"
MAX_ATTEMPTS=30
ATTEMPT=0
while [ $ATTEMPT -lt $MAX_ATTEMPTS ]; do
    if curl -s -o /dev/null -w "%{http_code}" http://localhost:8080 | grep -q "200\|302"; then
        break
    fi
    ATTEMPT=$((ATTEMPT + 1))
    echo -n "."
    sleep 2
done
echo ""

if [ $ATTEMPT -eq $MAX_ATTEMPTS ]; then
    echo -e "${YELLOW}WordPress is starting up (this may take a moment on first run)${NC}"
fi

# Auto-configure WordPress and install Astra if not already set up
echo -e "${YELLOW}Checking WordPress setup...${NC}"
WP="docker-compose run --rm wpcli"

if ! $WP core is-installed --path=/var/www/html 2>/dev/null; then
    echo -e "${YELLOW}Running WordPress install...${NC}"
    $WP core install \
        --path=/var/www/html \
        --url=http://localhost:8080 \
        --title="Go Tournament Dev" \
        --admin_user=admin \
        --admin_password=admin \
        --admin_email=admin@localhost.local \
        --skip-email
    echo -e "${GREEN}WordPress installed${NC}"
fi

if ! $WP theme is-installed astra --path=/var/www/html 2>/dev/null; then
    echo -e "${YELLOW}Installing Astra ${ASTRA_VERSION}...${NC}"
    $WP theme install astra --version="${ASTRA_VERSION}" --path=/var/www/html
    echo -e "${GREEN}Astra installed${NC}"
fi

if ! $WP theme status astra --path=/var/www/html 2>/dev/null | grep -q "Active"; then
    echo -e "${YELLOW}Activating Astra theme...${NC}"
    $WP theme activate astra --path=/var/www/html
    echo -e "${GREEN}Astra activated${NC}"
fi

if ! $WP plugin is-active go-tournament-registration --path=/var/www/html 2>/dev/null; then
    echo -e "${YELLOW}Activating plugin...${NC}"
    $WP plugin activate go-tournament-registration --path=/var/www/html
    echo -e "${GREEN}Plugin activated${NC}"
fi

echo ""
echo -e "${GREEN}Local development environment is ready!${NC}"
echo "================================================"
echo ""
echo "WordPress:  http://localhost:8080"
echo "Admin:      http://localhost:8080/wp-admin"
echo "  Username: admin"
echo "  Password: admin"
echo ""
echo -e "${YELLOW}First time setup:${NC}"
echo "1. Visit http://localhost:8080 — WordPress is pre-configured with Astra"
echo "2. Create a page with shortcode: [go_tournament_registration]"
echo ""
echo -e "${YELLOW}Useful commands:${NC}"
echo "  docker-compose logs -f     # View logs"
echo "  docker-compose stop        # Stop containers"
echo "  docker-compose down        # Stop and remove containers"
echo "  docker-compose down -v     # Stop and remove containers + data"
echo ""
