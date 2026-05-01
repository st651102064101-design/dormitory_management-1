# 🚀 CI/CD Pipeline Setup

GitHub Actions CI/CD workflows สำหรับ Dormitory Management System

## 📋 Workflows

### 1. **CI - PHP Validation** (`ci.yml`)
ทำงานเมื่อ:
- Push code ไป `main` หรือ `develop` branch
- สร้าง Pull Request

ตรวจสอบ:
- ✅ PHP syntax errors
- ✅ Database connection file
- ✅ Common PHP mistakes

### 2. **CD - Deploy to Server** (`deploy.yml`)
ทำงานเมื่อ:
- Push ไป `main` branch
- Manual trigger (Actions → CD - Deploy to Server → Run workflow)

ทำการ:
- 📥 Pull latest code from repository
- 🔧 Set proper file permissions
- 🧹 Clear cache

---

## 🔐 Setup Instructions

### Step 1: สร้าง SSH Keys

ใน local machine ของคุณ:
```bash
# สร้าง SSH key pair (ถ้ายังไม่มี)
ssh-keygen -t ed25519 -f deploy_key -N ""

# ดู private key (ใช้ใน GitHub Secrets)
cat deploy_key
```

### Step 2: Setup SSH Key บน Server

```bash
# เชื่อมต่อ server ด้วย SSH
ssh user@your-server-ip

# สร้าง .ssh directory
mkdir -p ~/.ssh
chmod 700 ~/.ssh

# เพิ่ม public key
cat deploy_key.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

### Step 3: เซ็ตค่า GitHub Secrets

ไปที่: **Settings → Secrets and variables → Actions → New repository secret**

เพิ่ม 3 secrets ต่อไปนี้:

| Secret Name | Value | ตัวอย่าง |
|---|---|---|
| `DEPLOY_HOST` | IP address หรือ domain ของ server | `project.3bbddns.com` |
| `DEPLOY_USER` | SSH username | `ubuntu` หรือ `root` |
| `DEPLOY_SSH_KEY` | Contents of `deploy_key` (private key) | `-----BEGIN OPENSSH PRIVATE KEY-----...` |
| `DEPLOY_PATH` | Path ของ project บน server | `/var/www/dormitory_management` |

### Step 4: ยืนยันการตั้งค่า

```bash
# Test SSH connection ก่อน setup
ssh -i deploy_key user@your-server-ip "cd /path/to/project && ls -la"
```

---

## 📊 Monitoring Workflows

### ดู workflow status:
1. ไปที่ repository → **Actions** tab
2. เลือก workflow (`CI - PHP Validation` หรือ `CD - Deploy to Server`)
3. ดู run history และ logs

### ดู deployment logs:
```bash
ssh user@your-server-ip "cd /path/to/project && git log --oneline -10"
```

---

## 🔄 Manual Deployment

ถ้าต้องการ deploy อย่างไม่ใช่เมื่อ push:

1. ไปที่ repository → **Actions** tab
2. เลือก **CD - Deploy to Server** workflow
3. คลิก **Run workflow**
4. เลือก branch (`main`) แล้ว **Run workflow**

---

## 🛠️ Troubleshooting

### Problem: "Permission denied (publickey)"
**Solution:**
```bash
# ตรวจสอบ SSH key permissions บน server
ssh user@server "ls -la ~/.ssh/"
# authorized_keys ต้องมี permission 600
```

### Problem: "fatal: not a git repository"
**Solution:**
```bash
# ตรวจสอบว่า project folder มี .git
ssh user@server "ls -la /path/to/project/.git"
```

### Problem: "Permission denied" ที่ file/folder
**Solution:**
```bash
# ใหญ่ permissions ใหม่
ssh user@server "chmod -R 755 /path/to/project"
```

---

## 📝 Git Workflow Recommendation

```bash
# ทำ feature branch
git checkout -b feature/new-feature

# Commit changes
git add .
git commit -m "Add new feature"

# Push to GitHub
git push origin feature/new-feature

# Create Pull Request บน GitHub
# CI จะ run automatically

# Merge PR
# CD จะ run automatically และ deploy ไป server
```

---

## ⚙️ Environment Configuration

ถ้า server ใช้ environment variables ที่ต่างกัน:

1. สร้าง `.env.production` บน server
2. เพิ่ม step ใน `deploy.yml` เพื่อ source `.env`:
```yaml
- name: Load environment
  run: |
    ssh -i ~/.ssh/deploy_key ${{ secrets.DEPLOY_USER }}@${{ secrets.DEPLOY_HOST }} << 'EOF'
      cd ${{ secrets.DEPLOY_PATH }}
      source .env.production
      # your custom commands
    EOF
```

---

## 📚 Resources

- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [Using secrets in GitHub Actions](https://docs.github.com/en/actions/security-guides/using-secrets-in-github-actions)
- [SSH Deploy Action](https://github.com/appleboy/ssh-action)

---

## ✅ Checklist

- [ ] SSH keys สร้างแล้ว
- [ ] Public key เพิ่มไปบน server แล้ว
- [ ] GitHub Secrets ตั้งค่าแล้ว
- [ ] Test CI workflow สำเร็จ
- [ ] Test CD workflow สำเร็จ
- [ ] Documentation updated
