#!/bin/sh
set -eu

# Met en cache configuration, routes, événements et vues avec l'environnement du conteneur.
php artisan optimize

exec "$@"
