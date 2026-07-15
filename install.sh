#!/bin/bash

# ==============================================================================
# БАЗОВЫЕ НАСТРОЙКИ
# ==============================================================================
PROJECT_PATH="/var/www/ai-funnel"
GIT_URL="https://github.com/homeonfire/ai_funnel_bot.git"

# ==============================================================================
# РЕЖИМ ОБНОВЛЕНИЯ (Если проект уже установлен)
# ==============================================================================
if [ -d "$PROJECT_PATH/.git" ]; then
    echo "====================================================================="
    echo "🔄 Обнаружен установленный проект. Запускаем обновление..."
    echo "====================================================================="
    
    cd $PROJECT_PATH
    
    echo "📥 Получение новых изменений из GitHub..."
    git pull origin main # или master, если у тебя основная ветка называется так
    
    echo "📦 Обновление зависимостей Composer..."
    composer install --optimize-autoloader --no-dev
    
    echo "🗄 Выполнение новых миграций базы данных..."
    php artisan migrate --force
    
    echo "🧹 Обновление кэша Laravel..."
    php artisan optimize:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    
    echo "🔒 Восстановление прав доступа..."
    chown -R www-data:www-data $PROJECT_PATH
    chmod -R 775 $PROJECT_PATH/storage
    chmod -R 775 $PROJECT_PATH/bootstrap/cache
    
    echo "====================================================================="
    echo "✅ ОБНОВЛЕНИЕ УСПЕШНО ЗАВЕРШЕНО!"
    echo "====================================================================="
    exit 0
fi

# ==============================================================================
# РЕЖИМ УСТАНОВКИ С НУЛЯ (Интерактивный)
# ==============================================================================
echo "====================================================================="
echo "🚀 Мастер первой установки AiFunnel (LEMP + SSL)"
echo "====================================================================="
echo "Перед началом убедитесь, что А-запись вашего домена уже указывает на IP этого сервера!"
echo ""

read -p "🌐 Введите ваш домен (например, ai.domain.com): " DOMAIN
read -p "📧 Введите ваш Email (для выпуска бесплатного SSL): " EMAIL

# Генерируем данные для Базы Данных
DB_NAME="aifunnel"
DB_USER="funneluser"
DB_PASS=$(openssl rand -base64 12)

echo ""
echo "⏳ Начинаем установку... Это займет пару минут."
echo "====================================================================="

# 1. Обновляем систему и ставим нужные пакеты
apt update && apt upgrade -y
apt install -y software-properties-common curl wget git unzip
apt install -y nginx postgresql postgresql-contrib
apt install -y certbot python3-certbot-nginx

# 2. Ставим PHP 8.3
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.3-fpm php8.3-pgsql php8.3-cli php8.3-curl php8.3-mbstring \
    php8.3-xml php8.3-zip php8.3-intl php8.3-bcmath

# 3. Ставим Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# 4. Настраиваем PostgreSQL
echo "🐘 Настройка базы данных PostgreSQL..."
sudo -u postgres psql -c "CREATE DATABASE $DB_NAME;"
sudo -u postgres psql -c "CREATE USER $DB_USER WITH PASSWORD '$DB_PASS';"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;"
sudo -u postgres psql -c "ALTER DATABASE $DB_NAME OWNER TO $DB_USER;"

# 5. Клонируем проект с GitHub
echo "🐙 Клонирование репозитория..."
mkdir -p /var/www
cd /var/www
git clone $GIT_URL ai-funnel
cd $PROJECT_PATH

# 6. Настраиваем .env
echo "⚙️ Генерация конфигурации (.env)..."
cp .env.example .env

sed -i "s|^APP_NAME=.*|APP_NAME=AiFunnel|" .env
sed -i "s|^APP_ENV=.*|APP_ENV=production|" .env
sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" .env
sed -i "s|^APP_URL=.*|APP_URL=https://$DOMAIN|" .env

sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=pgsql|" .env
sed -i "s|^DB_HOST=.*|DB_HOST=127.0.0.1|" .env
sed -i "s|^DB_PORT=.*|DB_PORT=5432|" .env
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=$DB_NAME|" .env
sed -i "s|^DB_USERNAME=.*|DB_USERNAME=$DB_USER|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PASS|" .env

# 7. Сборка проекта
echo "📦 Установка зависимостей Composer..."
composer install --optimize-autoloader --no-dev

echo "🔑 Генерация ключа шифрования..."
php artisan key:generate --force

echo "🗄 Создание таблиц в базе данных..."
php artisan migrate --force

echo "🧹 Кэширование для ускорения работы..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 8. Настройка прав
echo "🔒 Настройка прав доступа для Nginx..."
chown -R www-data:www-data $PROJECT_PATH
find $PROJECT_PATH -type f -exec chmod 644 {} \;
find $PROJECT_PATH -type d -exec chmod 755 {} \;
chmod -R 775 $PROJECT_PATH/storage
chmod -R 775 $PROJECT_PATH/bootstrap/cache

# 9. Настраиваем Nginx
echo "🌐 Конфигурация веб-сервера..."
NGINX_CONF="/etc/nginx/sites-available/ai-funnel"
cat > $NGINX_CONF <<EOF
server {
    listen 80;
    server_name $DOMAIN;
    root $PROJECT_PATH/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

ln -s $NGINX_CONF /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
systemctl restart nginx

# 10. Выпуск SSL сертификата
echo "🔐 Запрос SSL-сертификата у Let's Encrypt..."
certbot --nginx -d $DOMAIN --non-interactive --agree-tos -m $EMAIL

echo "====================================================================="
echo "✅ УСТАНОВКА УСПЕШНО ЗАВЕРШЕНА!"
echo "====================================================================="
echo "🌐 Адрес панели:  https://$DOMAIN/admin"
echo "🐘 База данных:   $DB_NAME"
echo "👤 Юзер БД:       $DB_USER"
echo "🔑 Пароль БД:     $DB_PASS"
echo "====================================================================="
echo "Обязательно сохраните пароль от базы данных, если захотите подключиться к ней напрямую!"