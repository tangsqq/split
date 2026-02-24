# 使用 PHP 8.2 + Apache 镜像
FROM php:8.2-apache

# 1. 安装 LibreOffice 和必要的依赖
# 增加了 libxrender1, libxtst6 等库以增强 LibreOffice 转换的稳定性
RUN apt-get update && apt-get install -y \
    libreoffice \
    fonts-wqy-zenhei \
    libcap2-bin \
    libxrender1 \
    libxtst6 \
    libdbus-glib-1-2 \
    && rm -rf /var/lib/apt/lists/*

# 2. 调整 PHP 配置：增加上传限制和执行时间限制
RUN echo "upload_max_filesize = 50M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 55M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini

# 3. 设置工作目录
WORKDIR /var/www/html

# 4. 拷贝文件
COPY . /var/www/html/

# 5. 确保权限正确
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# 6. 开启 Apache 的重写模块
RUN a2enmod rewrite

EXPOSE 80

CMD ["apache2-foreground"]
