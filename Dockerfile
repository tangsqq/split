# 使用 PHP 8.2 + Apache 镜像
FROM php:8.2-apache

# 1. 安装 LibreOffice, 中文字体(关键), 以及 libcap2-bin
RUN apt-get update && apt-get install -y \
    libreoffice \
    fonts-wqy-zenhei \
    libcap2-bin \
    && rm -rf /var/lib/apt/lists/*

# 2. 调整 PHP 配置：增加上传限制
RUN echo "upload_max_filesize = 50M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 55M" >> /usr/local/etc/php/conf.d/uploads.ini

# 3. 设置工作目录
WORKDIR /var/www/html

# 4. 复制代码并设置权限
COPY . /var/www/html/

# 确保 www-data 用户对目录有完全控制权，并创建临时目录
RUN mkdir -p /tmp/pdf_tool_ && \
    chown -R www-data:www-data /var/www/html /tmp/pdf_tool_ && \
    chmod -R 755 /var/www/html

# 5. 开启 Apache 模块
RUN a2enmod rewrite

EXPOSE 80

CMD ["apache2-foreground"]
