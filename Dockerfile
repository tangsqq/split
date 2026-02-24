# 使用 PHP 8.2 + Apache 镜像
FROM php:8.2-apache

# 1. 安装 LibreOffice 和字体
# 关键修改：添加 sed 指令开启 contrib 源，以便安装 ttf-mscorefonts-installer (包含 Times New Roman)
RUN sed -i 's/main/main contrib/g' /etc/apt/sources.list.d/debian.sources || \
    sed -i 's/main/main contrib/g' /etc/apt/sources.list

RUN apt-get update && \
    # 预先接受微软字体协议，否则安装会卡住
    echo "ttf-mscorefonts-installer msttcorefonts/accepted-mscorefonts-eula select true" | debconf-set-selections && \
    apt-get install -y \
    libreoffice \
    # 核心：安装 Times New Roman, Arial, Verdana 等
    ttf-mscorefonts-installer \
    # 备用：安装度量衡一致的开源字体 (如 Liberation Serif)
    fonts-liberation \
    ghostscript \
    # 字体配置工具
    fontconfig \
    && rm -rf /var/lib/apt/lists/*

# 刷新系统字体缓存
RUN fc-cache -f -v

# 2. 调整 PHP 配置
RUN echo "upload_max_filesize = 100M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 110M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/uploads.ini

# 3. 设置工作目录
WORKDIR /var/www/html

# 4. 复制代码并立即设置权限
COPY . /var/www/html/

# 5. 【环境修复】
# 确保 LibreOffice 在运行阶段有地方写缓存和读取字体配置
RUN mkdir -p /var/www/.cache /var/www/.config && \
    chown -R www-data:www-data /var/www/ /var/www/html/ /tmp/ && \
    chmod -R 777 /tmp/

# 6. 开启 Apache 模块
RUN a2enmod rewrite

# 7. 解除 ImageMagick 对 PDF 的安全限制 (ImageMagick-6 或 7)
RUN find /etc/ImageMagick* -name "policy.xml" -exec sed -i 's/rights="none" pattern="PDF"/rights="read|write" pattern="PDF"/' {} + || true

EXPOSE 80

CMD ["apache2-foreground"]
