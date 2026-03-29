# Hướng Dẫn Deploy Project Vận Tải Đức Anh

Tài liệu này hướng dẫn deploy project lên VPS (Ubuntu 20.04/22.04) sử dụng Nginx, PHP 8.2, MySQL và SSL Cloudflare.

**Domains:**

- Frontend: `vantaiducanh.io.vn`
- Backend: `api.vantaiducanh.io.vn`

---

## 1. Chuẩn Bị Server (VPS)

Đảm bảo bạn đã cài đặt các thành phần cần thiết trên VPS:

```bash
# Cập nhật hệ thống
sudo apt update && sudo apt upgrade -y

# Cài đặt Nginx, MySQL, PHP 8.2, Redis và Supervisor
sudo apt install nginx mysql-server sudo apt install software-properties-common -y
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update && sudo apt install redis-server supervisor php8.2 php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-bcmath php8.2-curl php8.2-zip php8.2-redis unzip git -y
```

---

## 2. Thiết Lập MySQL

Sau khi cài đặt `mysql-server`, bạn cần bảo mật và tạo database:

### Bước 2.1: Bảo Mật MySQL
Chạy lệnh sau và làm theo hướng dẫn (thiết lập mật khẩu root, xóa user ẩn danh, v.v.):
```bash
sudo mysql_secure_installation
```

### Bước 2.2: Tạo Database và User
Truy cập MySQL prompt:
```bash
sudo mysql
```

> [!WARNING]  
> **Lưu ý quan trọng về tài khoản root:**  
> - Trên Ubuntu mới, tài khoản `root` mặc định dùng `auth_socket` (không dùng password khi vào qua sudo).  
> - **KHÔNG NÊN** dùng username `root` và password `root` (hoặc bất kỳ password nào) trực tiếp trong file `.env` của ứng dụng vì lý do bảo mật và có thể bị lỗi kết nối.  
> - Hãy tạo một user riêng như hướng dẫn dưới đây.

Chạy các lệnh SQL sau (thay đổi thông tin theo ý bạn):
```sql
-- Tạo database
CREATE DATABASE your_database_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tạo user và mật khẩu (Khuyên dùng thay vì root)
CREATE USER 'your_username'@'localhost' IDENTIFIED BY 'your_password';

-- Cấp quyền
GRANT ALL PRIVILEGES ON your_database_name.* TO 'your_username'@'localhost';

-- Nếu bạn MUỐN dùng username là 'root' và password là 'root':
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'root';

-- Refresh quyền
FLUSH PRIVILEGES;

EXIT;
```

---

## 3. Cấu Hình SSL Cloudflare

Bạn đã có chứng chỉ Cloudflare (Origin CA). Hãy upload chúng lên server.

1.  Tạo thư mục chứa SSL:
    ```bash
    sudo mkdir -p /etc/nginx/ssl
    ```
2.  Tạo file certificate và private key:
    - `sudo nano /etc/nginx/ssl/vantaiducanh.io.vn.pem` (Dán nội dung Certificate vào đây)
    - `sudo nano /etc/nginx/ssl/vantaiducanh.io.vn.key` (Dán nội dung Private Key vào đây)

---

## 4. Deploy Backend (Laravel)

### Bước 3.1: Upload Code

Upload code lên thư mục `/var/www/datvexe/backend`. Bạn có thể dùng Git clone hoặc upload file zip.

```bash
sudo mkdir -p /var/www/datvexe
# Clone hoặc copy code vào đây
```

### Bước 3.2: Cài Đặt Dependencies

```bash
cd /var/www/datvexe/backend
composer install --optimize-autoloader --no-dev
```

### Bước 3.3: Cấu Hình Environment

1.  Copy file `.env.production` (đã tạo) thành `.env`:
    ```bash
    cp .env.production.example .env
    ```
2.  Chỉnh sửa file `.env` và điền thông tin Database, Redis, App Key, v.v.

    ```bash
    nano .env
    ```

    **Lưu ý Redis:**

    - `REDIS_HOST=127.0.0.1`
    - `REDIS_PASSWORD=null` (hoặc mật khẩu nếu có)
    - `REDIS_PORT=6379`

3.  Generate Key (nếu chưa có):
    ```bash
    php artisan key:generate
    ```
4.  Cache config:
    ```bash
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    ```

### Bước 3.4: Phân Quyền

```bash
sudo chown -R www-data:www-data /var/www/datvexe/backend
sudo chmod -R 775 /var/www/datvexe/backend/storage
sudo chmod -R 775 /var/www/datvexe/backend/bootstrap/cache
```

### Bước 3.5: Cấu Hình Nginx (Backend)

1.  Tạo file config từ file mẫu tôi đã tạo: `backend/deploy/nginx/api.vantaiducanh.io.vn.conf`.
2.  Copy vào thư mục cấu hình Nginx:
    ```bash
    sudo cp /var/www/datvexe/backend/deploy/nginx/api.vantaiducanh.io.vn.conf /etc/nginx/sites-available/api.vantaiducanh.io.vn
    ```
3.  Kích hoạt site:
    ```bash
    sudo ln -s /etc/nginx/sites-available/api.vantaiducanh.io.vn /etc/nginx/sites-enabled/
    ```

### Bước 3.6: Cấu Hình Supervisor (Worker, Queue, Reverb)

1.  Tạo file config từ file mẫu tôi đã tạo: `backend/deploy/supervisor/vantaiducanh-worker.conf`.
2.  Copy vào thư mục cấu hình Supervisor:
    ```bash
    sudo cp /var/www/datvexe/backend/deploy/supervisor/vantaiducanh-worker.conf /etc/supervisor/conf.d/vantaiducanh-worker.conf
    ```
3.  Load và Start các process:
    ```bash
    sudo supersdvisorctl reread
    sudo supervisorctl update
    sudo supervisorctl start all
    ```
4.  Kiểm tra status:
    ```bash
    sudo supervisorctl status
    ```

---

## 5. Deploy Frontend (React)

### Bước 4.1: Build Project

Lưu ý: Frontend nên được **build ở máy local** (hoặc CI/CD) rồi upload trọn bộ thư mục `dist` lên server để tiết kiệm resource cho VPS.

**Tại máy local:**

1.  Chỉnh sửa `.env.production`:
    ```env
    VITE_API_BASE_URL=https://api.vantaiducanh.io.vn
    ```
2.  Chạy lệnh build:
    ```bash
    npm run build
    ```
    (Lệnh này sẽ tạo ra thư mục `dist`).

### Bước 4.2: Upload Code

Upload toàn bộ nội dung trong thư mục `dist` (local) lên thư mục `/var/www/datvexe/frontend/dist` trên VPS.

```bash
mkdir -p /var/www/datvexe/frontend/dist
# Upload files vào đây
```

### Bước 4.3: Cấu Hình Nginx (Frontend)

1.  Tạo file config từ file mẫu tôi đã tạo: `frontend/deploy/nginx/vantaiducanh.io.vn.conf`.
2.  Copy vào thư mục cấu hình Nginx:
    ```bash
    sudo cp /var/www/datvexe/frontend/deploy/nginx/vantaiducanh.io.vn.conf /etc/nginx/sites-available/vantaiducanh.io.vn
    ```
3.  Kích hoạt site:
    ```bash
    sudo ln -s /etc/nginx/sites-available/vantaiducanh.io.vn /etc/nginx/sites-enabled/
    ```

---

## 6. Hoàn Tất

1.  Kiểm tra cấu hình Nginx:
    ```bash
    sudo nginx -t
    ```
2.  Khởi động lại Nginx:
    ```bash
    sudo systemctl restart nginx
    ```
3.  Kiểm tra truy cập:
    - Backend: `https://api.vantaiducanh.io.vn`
    - Frontend: `https://vantaiducanh.io.vn`

Chúc bạn thành công!

---

## 7. Thiết Lập CI/CD với GitHub Actions

Sau khi deploy thủ công thành công, bạn có thể thiết lập CI/CD để tự động deploy khi push code lên branch `main`.

### Bước 6.1: Tạo SSH Key Pair (Trên Máy Local)

```bash
# Tạo key pair mới cho GitHub Actions
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/github_actions_key -N ""

# Xem public key
cat ~/.ssh/github_actions_key.pub

# Xem private key (sẽ dùng cho GitHub Secret)
cat ~/.ssh/github_actions_key
```

### Bước 6.2: Thêm Public Key Vào VPS

```bash
# SSH vào VPS
ssh root@your-vps-ip

# Thêm public key vào authorized_keys
echo "PASTE_PUBLIC_KEY_HERE" >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

### Bước 6.3: Thêm GitHub Secrets

Vào repo GitHub → **Settings** → **Secrets and variables** → **Actions** → **New repository secret**

| Secret Name   | Giá trị                                                 |
| ------------- | ------------------------------------------------------- |
| `VPS_HOST`    | IP của VPS (vd: `103.xxx.xxx.xxx`)                      |
| `VPS_USER`    | `root` (hoặc user bạn dùng)                             |
| `VPS_SSH_KEY` | Nội dung file `~/.ssh/github_actions_key` (PRIVATE key) |
| `VPS_PORT`    | `22`                                                    |

### Bước 6.4: Upload Deploy Scripts Lên VPS

```bash
# Copy scripts lên VPS (chạy từ thư mục project)
scp backend/deploy/scripts/deploy-backend.sh root@your-vps-ip:/var/www/datvexe/backend/deploy/scripts/
scp frontend/deploy/scripts/deploy-frontend.sh root@your-vps-ip:/var/www/datvexe/frontend/deploy/scripts/

# SSH vào VPS và cấp quyền thực thi
ssh root@your-vps-ip
chmod +x /var/www/datvexe/backend/deploy/scripts/deploy-backend.sh
chmod +x /var/www/datvexe/frontend/deploy/scripts/deploy-frontend.sh
```

### Bước 6.5: Kiểm Tra CI/CD

1. Push một commit bất kỳ lên branch `main`
2. Vào tab **Actions** trên GitHub repo
3. Kiểm tra workflow **Deploy to VPS** có chạy thành công không
4. Truy cập website để verify:
   - Backend: `https://api.vantaiducanh.io.vn`
   - Frontend: `https://vantaiducanh.io.vn`

### Chạy Thủ Công (Optional)

Bạn có thể trigger workflow thủ công:

1. Vào **Actions** → **Deploy to VPS**
2. Click **Run workflow** → Chọn branch `main` → **Run workflow**

---

## 8. Troubleshooting

### Lỗi Connection Refused (MySQL) - Deep Debugging

1.  **Kiểm tra port và service:**
    ```bash
    # Xem MySQL có đang lắng nghe trên port 3306 không
    sudo netstat -plnt | grep mysql
    ```
    Nếu không thấy dòng có `3306`, MySQL chưa chạy. Chạy: `sudo systemctl restart mysql`.

2.  **Kiểm tra bind-address:**
    Mở file config: `sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf`.
    Tìm dòng `bind-address`. Đảm bảo nó là `127.0.0.1`. Nếu là IP khác, hãy đổi lại và restart MySQL.

3.  **Thử kết nối bằng tay trên VPS:**
    ```bash
    # Thử kết nối bằng account root/root
    mysql -u root -h 127.0.0.1 -p
    ```
    Nếu lệnh này chạy được mà Laravel không chạy được, vấn đề nằm ở file `.env` hoặc cache của Laravel.

4.  **Xóa sạch cache Laravel (Cực kỳ quan trọng):**
    ```bash
    php artisan config:clear
    php artisan cache:clear
    ```
5.  **Kiểm tra DB_CONNECTION:** Đảm bảo `DB_CONNECTION=mysql`.

- Kiểm tra port SSH trên VPS: `sudo netstat -tlnp | grep ssh`
- Kiểm tra firewall: `sudo ufw status`

### Lỗi Permission Denied (publickey)

- Kiểm tra file `~/.ssh/authorized_keys` trên VPS có public key chưa
- Đảm bảo quyền đúng: `chmod 600 ~/.ssh/authorized_keys`

### Lỗi Composer Out of Memory

- Thêm vào deploy script: `COMPOSER_MEMORY_LIMIT=-1 composer install`
