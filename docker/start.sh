#!/bin/sh
set -e
cd /var/www/html

# Railway/Render memberi port lewat variabel PORT
PORT="${PORT:-80}"
echo "Listen ${PORT}" > /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Cadangan bila APP_KEY lupa diisi (berubah tiap restart, jadi sebaiknya tetap isi APP_KEY)
if [ -z "$APP_KEY" ]; then
  echo "PERINGATAN: APP_KEY belum diatur, memakai kunci sementara."
  export APP_KEY="$(php artisan key:generate --show)"
fi

php artisan migrate --force --seed
php artisan optimize
chown -R www-data:www-data storage bootstrap/cache

# Scheduler (reset harian 00:00 WIB) berjalan di latar belakang sebagai www-data
su -m -s /bin/sh www-data -c "php artisan schedule:work" >/dev/null 2>&1 &

exec apache2-foreground
