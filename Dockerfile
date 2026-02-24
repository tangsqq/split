# 使用 PHP 8.2 + Apache 镜像
FROM php:8.2-apache

# 1. 安装 LibreOffice 和中文字体
# 注意：添加了 ghostscript 是为了处理 PDF 兼容性
RUN apt-get update && apt-get install -y \
    libreoffice \
    fonts-wqy-zenhei \
    ghostscript \
    && rm -rf /var/lib/apt/lists/*

# 2. 调整 PHP 配置
RUN echo "upload_max_filesize = 50M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 55M" >> /usr/local/etc/php/conf.d/uploads.ini

# 3. 设置工作目录
WORKDIR /var/www/html

# 4. 复制代码并立即设置权限
COPY . /var/www/html/

# 5. 【关键修复】创建并授权临时目录
# 必须让 www-data 用户拥有主目录，LibreOffice 才能运行
RUN mkdir -p /var/www/html/temp_uploads && \
    mkdir -p /var/www/.cache && \
    chown -R www-data:www-data /var/www/ /var/www/html/ /tmp/ && \
    chmod -R 777 /tmp/

# 6. 开启 Apache 模块
RUN a2enmod rewrite

# 7. 【关键修复】解除 Linux 对 PDF 的安全限制
RUN sed -i 's/rights="none" pattern="PDF"/rights="read|write" pattern="PDF"/' /etc/ImageMagick-6/policy.xml || true

EXPOSE 80

CMD ["apache2-foreground"]
