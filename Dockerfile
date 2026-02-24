# 使用 PHP 8.2 + Apache 镜像
FROM php:8.2-apache

# 1. 安装 LibreOffice 和中文字体
# procps 帮助管理进程，fonts-wqy-zenhei 解决 Excel 转 PDF 时的中文乱码
RUN apt-get update && apt-get install -y \
    libreoffice \
    fonts-wqy-zenhei \
    ghostscript \
    procps \
    && rm -rf /var/lib/apt/lists/*

# 2. 调整 PHP 配置，适应大文件上传
RUN echo "upload_max_filesize = 50M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 55M" >> /usr/local/etc/php/conf.d/uploads.ini

# 3. 设置工作目录
WORKDIR /var/www/html

# 4. 复制代码
COPY . /var/www/html/

# 5. 【关键修复】权限与环境设置
# LibreOffice 必须在可写的 HOME 目录下运行
RUN mkdir -p /var/www/.config /var/www/.cache /var/www/html/temp_uploads && \
    chown -R www-data:www-data /var/www/ /var/www/html/ && \
    chmod -R 777 /tmp/

# 设置环境变量，强制 LibreOffice 使用指定的家目录
ENV HOME=/var/www

# 6. 开启 Apache 模块
RUN a2enmod rewrite

# 7. 解除 ImageMagick 限制
RUN if [ -f /etc/ImageMagick-6/policy.xml ]; then \
    sed -i 's/rights="none" pattern="PDF"/rights="read|write" pattern="PDF"/' /etc/ImageMagick-6/policy.xml; \
    fi

EXPOSE 80

CMD ["apache2-foreground"]
