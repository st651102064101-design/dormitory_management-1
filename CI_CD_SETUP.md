# 🚀 CI/CD Pipeline Setup (Local XAMPP Development)

GitHub Actions CI/CD workflows สำหรับ Dormitory Management System (XAMPP Local)

## � Quick Start - Local + Public Domain Deployment

GitHub Actions CI/CD workflows สำหรับ:
1. 🏠 **Local XAMPP Development** - `http://localhost/`
2. 🌐 **Public Domain** - `https://project.3bbddns.com:36140/`

---

## 📋 Workflows Available

### 1. **CI - PHP Validation** (`ci.yml`)
- ✅ Automatic on push/PR
- ✅ Validates PHP syntax
- ✅ No setup required

### 2. **CD - Deploy to Domain** (`deploy.yml`)
- 🚀 Automatic on push to `main`
- 🌐 Deploys via SSH to public domain
- ⚙️ Requires GitHub Secrets setup

### 3. **Local Development Setup** (`local-setup.yml`)
- 🔧 Manual trigger
- ✅ Verifies environment
- ✅ No setup required

---

## 🌐 Setup for Public Domain Deployment

### Quick Setup (5 minutes):

1. **Create SSH Key** (if not already done):
   ```bash
   ssh-keygen -t ed25519 -f ~/.ssh/github_deploy -N ""
   ```

2. **Add Public Key to Server**:
   ```bash
   ssh user@project.3bbddns.com
   mkdir -p ~/.ssh
   cat >> ~/.ssh/authorized_keys << 'EOF'
   [PASTE github_deploy.pub here]
   EOF
   chmod 600 ~/.ssh/authorized_keys
   ```

3. **Configure GitHub Secrets** (Repository → Settings → Secrets):
   - `DEPLOY_HOST`: `project.3bbddns.com`
   - `DEPLOY_SSH_PORT`: `22` (or your SSH port)
   - `DEPLOY_USER`: SSH username
   - `DEPLOY_SSH_KEY`: Contents of `~/.ssh/github_deploy`
   - `DEPLOY_PATH`: `/path/to/project`
   - `DEPLOY_DOMAIN`: `project.3bbddns.com:36140`

4. **Test & Deploy**:
   ```bash
   git push origin main
   # Check: Repository → Actions → CD - Deploy to Domain
   ```

📖 **See [DEPLOY_TO_PUBLIC_DOMAIN.md](DEPLOY_TO_PUBLIC_DOMAIN.md) for detailed instructions**

---

## 📊 Workflows

### 1. **CI - PHP Validation** (`ci.yml`)
ทำงานเมื่อ:
- Push code ไป `main` หรือ `develop` branch
- สร้าง Pull Request

ตรวจสอบ:
- ✅ PHP syntax errors
- ✅ Database connection file
- ✅ Common PHP mistakes

### 2. **CD - Build & Release** (`deploy.yml`)
ทำงานเมื่อ:
- Push ไป `main` branch
- สร้าง Git tag (`v*.*.*`)
- Manual trigger (Actions → CD - Build & Release → Run workflow)

ทำการ:
- 📦 Package project files
- 📝 Create release notes
- 💾 Generate release ZIP

### 3. **Local Development Setup** (`local-setup.yml`)
ทำงานเมื่อ:
- Manual trigger (Actions → Local Development Setup → Run workflow)

ตรวจสอบ:
- ✅ PHP configuration
- ✅ Project structure
- ✅ Database connection
- ✅ Critical files existence

---

## 🚀 Quick Start

### For XAMPP Local Development:

#### 1. Clone/Update Repository
```bash
cd /Applications/XAMPP/xamppfiles/htdocs/
git clone https://github.com/st651102064101-design/dormitory_management-1.git
cd dormitory_management
```

#### 2. Setup Database
```bash
# Start XAMPP MySQL
/Applications/XAMPP/bin/mysql.server start

# Create database
mysql -u root < database.sql

# Or manually:
# mysql -u root
# CREATE DATABASE dormitory_db;
# USE dormitory_db;
# SOURCE dump.sql;
```

#### 3. Configure Connection
Edit `ConnectDB.php`:
```php
$host = 'localhost';
$user = 'root';
$password = '';  // XAMPP default (empty)
$database = 'dormitory_db';
```

#### 4. Access Application
```
http://localhost/dormitory_management/
```

---

## 📊 CI/CD Workflow Status

### View Workflows:
1. GitHub Repository → **Actions** tab
2. Select workflow:
   - `CI - PHP Validation` → Automatic on push
   - `CD - Build & Release` → Manual trigger
   - `Local Development Setup` → Manual trigger

### Check PHP Validation:
- Push code to `main` or `develop`
- GitHub Actions automatically validates syntax
- Check status badges on README

---

## 📦 Release Management

### Creating a Release:

#### Method 1: Create Git Tag
```bash
git tag v1.0.0
git push origin v1.0.0
# CD workflow automatically creates release
```

#### Method 2: Manual Release
1. GitHub → Actions → CD - Build & Release
2. Click "Run workflow"
3. Select branch: `main`
4. Click "Run workflow"

### Download Release:
- GitHub → Releases tab
- Download `dormitory_management-release.zip`

---

## 🛠️ Local Development Workflow

### Daily Development:
```bash
# Create feature branch
git checkout -b feature/your-feature

# Make changes
# ... edit files ...

# Commit changes
git add .
git commit -m "Add new feature"

# Push to GitHub
git push origin feature/your-feature

# Create Pull Request on GitHub
# CI validation runs automatically
```

### Merge to Main:
```bash
# After PR review and approval
git checkout main
git pull origin main
git merge feature/your-feature
git push origin main

# CI validation runs
# CD build & release package created
```

---

## 📝 Database Setup

### Option 1: Using SQL Dump
```bash
mysql -u root < database_wizard_final.sql
```

### Option 2: Manual Setup
```sql
CREATE DATABASE dormitory_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dormitory_db;

-- Import tables
-- (See database schema files)
```

### Option 3: Using phpMyAdmin (XAMPP)
1. Open http://localhost/phpmyadmin
2. Click "New"
3. Enter: `dormitory_db`
4. Click "Create"
5. Import SQL file via "Import" tab

---

## 🔧 Troubleshooting

### Problem: "MySQL connection failed"
**Solution:**
1. Start XAMPP MySQL: `/Applications/XAMPP/bin/mysql.server start`
2. Check credentials in `ConnectDB.php`
3. Ensure database exists:
   ```bash
   mysql -u root -e "SHOW DATABASES LIKE 'dormitory_db';"
   ```

### Problem: "File permissions denied"
**Solution:**
```bash
chmod -R 755 /Applications/XAMPP/xamppfiles/htdocs/dormitory_management
chmod -R 777 /Applications/XAMPP/xamppfiles/htdocs/dormitory_management/Public/Assets/Images
```

### Problem: "PHP syntax error in workflow"
**Solution:**
- Workflow shows exact file and line number
- Fix error locally and commit
- Push to GitHub → CI validates automatically

### Problem: "404 Not Found"
**Solution:**
1. Check Apache is running: `ps aux | grep httpd`
2. Verify project path: `/Applications/XAMPP/xamppfiles/htdocs/dormitory_management/`
3. Check `.htaccess` files (if using rewrites)

---

## 📚 Project Structure

```
dormitory_management/
├── .github/
│   └── workflows/
│       ├── ci.yml              # PHP validation
│       ├── deploy.yml          # Build & release
│       └── local-setup.yml     # Local setup check
├── Public/
│   ├── Assets/
│   │   └── Images/
│   │       └── Payments/       # (must be writable)
│   └── ...
├── Tenant/
│   ├── index.php
│   ├── payment.php
│   ├── renew_contract.php
│   └── ...
├── Manage/
│   └── ...
├── Reports/
│   └── ...
├── includes/
│   ├── apple_alert.php        # Alert component
│   └── ...
├── ConnectDB.php              # Database connection
├── config.php                 # Configuration
├── index.php                  # Home page
└── README.md
```

---

## 🔐 Security Notes for Local Development

1. **ConnectDB.php** - Contains database credentials
   - Keep in `.gitignore` if using different credentials per environment
   - Use strong passwords in production

2. **.env files** - Already in `.gitignore`
   - Create `.env.local` for local overrides
   - Never commit `.env` to repository

3. **SSH Keys** - Already in `.gitignore`
   - Deployment keys not tracked in git
   - Generate new keys for each environment

---

## ✅ Deployment Checklist

- [ ] PHP Validation passes (Green checkmark in Actions)
- [ ] All code committed and pushed
- [ ] Database migrations applied
- [ ] Local testing completed
- [ ] Ready to create release

---

## 📚 Resources

- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [XAMPP Documentation](https://www.apachefriends.org/)
- [PHP Manual](https://www.php.net/manual/)
- [MySQL Documentation](https://dev.mysql.com/doc/)

---

## 🆘 Getting Help

1. Check workflow logs in GitHub Actions
2. Review error messages in terminal
3. Check `.github/workflows/` files for workflow definitions
4. Review CI_CD_SETUP.md for detailed instructions

---

**Last Updated:** 2024
**Status:** ✅ Local XAMPP Development Ready
