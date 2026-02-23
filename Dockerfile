# 使用 PHP 8.2 + Apache 镜像
FROM php:8.2-apache

# 安装 LibreOffice 和必要的依赖
# 安装 fonts-wqy-zenhei 是为了支持 Excel 转换时的中文字体，否则 PDF 会乱码
RUN apt-get update && apt-get install -y \
    libreoffice \
    fonts-wqy-zenhei \
    libcap2-bin \
    && rm -rf /var/lib/apt/lists/*

# 调整 PHP 配置：增加上传限制（默认 2M 太小，不适合 PDF）
RUN echo "upload_max_filesize = 50M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 55M" >> /usr/local/etc/php/conf.d/uploads.ini

# 设置工作目录
WORKDIR /var/www/html

# 赋予 Apache 目录权限
RUN chown -R www-data:www-data /var/www/html

# 暴露 80 端口
EXPOSE 80
