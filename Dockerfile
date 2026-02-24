# 使用 PHP 8.2 + Apache 镜像
FROM php:8.2-apache

# 1. 安装 LibreOffice 和必要的依赖
# 添加了中文字体以防止 Excel 转 PDF 乱码
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

# 4. 【核心修复】将当前目录下的所有文件拷贝到镜像中
# 这一步确保了 index.php 被放到了 /var/www/html/ 目录下
COPY . /var/www/html/

# 5. 确保权限正确：将所有权交给 Apache 用户
# 必须在 COPY 之后执行，否则新考入的文件权限还是 root 的
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# 6. 开启 Apache 的重写模块（增强兼容性）
RUN a2enmod rewrite

# 暴露 80 端口
EXPOSE 80

# 启动 Apache
CMD ["apache2-foreground"]
