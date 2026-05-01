# 🌐 Deploy to Public Domain via SSH

GitHub Actions CD workflow สำหรับ deploy ไป `https://project.3bbddns.com:36140`

## 📋 ขั้นตอนการตั้งค่า

### Step 1: สร้าง SSH Key Pair

ใน local machine:
```bash
# สร้าง SSH key pair (ใช้ที่เดียวกัน)
ssh-keygen -t ed25519 -f ~/.ssh/github_deploy -N ""

# ดู public key
cat ~/.ssh/github_deploy.pub

# ดู private key (copy ไปใส่ GitHub Secrets)
cat ~/.ssh/github_deploy
```

### Step 2: Setup SSH Public Key บน Server

```bash
# SSH ไป server
ssh user@project.3bbddns.com

# Create .ssh directory
mkdir -p ~/.ssh
chmod 700 ~/.ssh

# Add public key
cat >> ~/.ssh/authorized_keys << 'EOF'
[PASTE github_deploy.pub CONTENT HERE]
EOF

chmod 600 ~/.ssh/authorized_keys

# Verify
exit
# ทดสอบ: ssh -i ~/.ssh/github_deploy user@project.3bbddns.com
```

### Step 3: ตั้งค่า GitHub Secrets

ไปที่: **Repository → Settings → Secrets and variables → Actions**

Click **"New repository secret"** และ add secrets ต่อไปนี้:

#### 1. `DEPLOY_HOST`
- **Value:** `project.3bbddns.com`
- **ตัวอย่าง:** Domain ของคุณ

#### 2. `DEPLOY_SSH_PORT`
- **Value:** `22` (default SSH port) หรือ port ที่ใช้
- **ถ้า SSH tunnel อยู่ port อื่น:** ใส่ port นั้น

#### 3. `DEPLOY_USER`
- **Value:** SSH username
- **ตัวอย่าง:** `ubuntu`, `root`, หรือ user ของคุณ

#### 4. `DEPLOY_SSH_KEY`
- **Value:** Contents of `~/.ssh/github_deploy` (private key)
- **⚠️ Paste ทั้งหมด** รวมบรรทัด `-----BEGIN OPENSSH PRIVATE KEY-----` และ `-----END OPENSSH PRIVATE KEY-----`

#### 5. `DEPLOY_PATH`
- **Value:** Path ของ project บน server
- **ตัวอย่าง:** `/home/user/dormitory_management` หรือ `/var/www/dormitory_management`

#### 6. `DEPLOY_DOMAIN` (Optional)
- **Value:** Public domain สำหรับ display
- **ตัวอย่าง:** `project.3bbddns.com:36140`

### Step 4: ทดสอบ SSH Connection

```bash
# ทดสอบสามารถ SSH ได้หรือไม่
ssh -i ~/.ssh/github_deploy user@project.3bbddns.com "ls -la"

# ทดสอบ git access (ถ้า server มี git)
ssh -i ~/.ssh/github_deploy user@project.3bbddns.com "git --version"
```

---

## 🚀 วิธีใช้

### Automatic Deployment:

เมื่อ push ไป `main` branch:
```bash
git add .
git commit -m "Update code"
git push origin main
# 🚀 CD workflow automatically deploys to domain
```

### Manual Deployment:

1. Repository → **Actions** tab
2. Select **"CD - Deploy to Domain"** workflow
3. Click **"Run workflow"**
4. Select branch: `main`
5. Click **"Run workflow"**

---

## 📊 Monitoring Deployment

### View Logs:
1. Repository → **Actions** tab
2. Click on latest **"CD - Deploy to Domain"** run
3. Check job output

### Verify Deployment:
```
✅ Access: https://project.3bbddns.com:36140/dormitory_management/Login.php
```

---

## 🔍 Troubleshooting

### Error: "Permission denied (publickey)"
**Solution:**
1. Verify SSH key on server:
   ```bash
   ssh user@project.3bbddns.com "cat ~/.ssh/authorized_keys"
   ```
2. Ensure DEPLOY_SSH_KEY secret has correct content
3. Check file permissions: `chmod 600 ~/.ssh/authorized_keys`

### Error: "Connection refused"
**Solution:**
1. Verify host is correct: `DEPLOY_HOST`
2. Verify SSH port: `DEPLOY_SSH_PORT`
3. Test manually: `ssh -i key user@host -p port`

### Error: "fatal: not a git repository"
**Solution:**
1. Verify `DEPLOY_PATH` exists
2. Initialize git if first time: `git clone https://github.com/... /path`

### Error: "Permission denied" at file level
**Solution:**
1. Check file permissions on server:
   ```bash
   ssh user@project.3bbddns.com "ls -la /path/to/project"
   ```
2. Fix permissions:
   ```bash
   ssh user@project.3bbddns.com "chmod -R 755 /path/to/project"
   ```

---

## 📝 What Gets Deployed

- ✅ All PHP files
- ✅ Directories and assets
- ✅ Database configuration (ConnectDB.php)
- ✅ Latest code from `main` branch
- ❌ Not deployed: .git, .github, node_modules, vendor, .env

---

## 🔐 Security Notes

1. **Keep private key secret** - Never commit to repository
2. **Use strong SSH keys** - ed25519 recommended
3. **Rotate keys periodically** - For security
4. **Monitor deployments** - Check Actions logs
5. **Restrict SSH access** - On server side if possible

---

## ✅ Quick Checklist

- [ ] SSH key pair created
- [ ] Public key added to server
- [ ] SSH connection tested manually
- [ ] GitHub Secrets configured (6 items)
- [ ] First push to `main` branch
- [ ] Verify deployment in Actions tab
- [ ] Access domain: `https://project.3bbddns.com:36140`

---

## 📚 Next Steps

1. **Create SSH keys** (Step 1)
2. **Add to server** (Step 2)
3. **Configure GitHub Secrets** (Step 3)
4. **Test connection** (Step 4)
5. **Push code** to trigger deployment
6. **Monitor in Actions tab**

---

## 🎯 Deployment Flow

```
┌─────────────────────────────────────┐
│  1. Push code to main branch        │
│     git push origin main            │
└────────────┬────────────────────────┘
             │
             ▼
┌─────────────────────────────────────┐
│  2. GitHub Actions runs CI           │
│     ✅ PHP validation                │
└────────────┬────────────────────────┘
             │
             ▼
┌─────────────────────────────────────┐
│  3. CD workflow starts               │
│     - Connect via SSH                │
│     - Pull latest code               │
│     - Update files                   │
└────────────┬────────────────────────┘
             │
             ▼
┌─────────────────────────────────────┐
│  4. Application updated              │
│     https://project.3bbddns.com:36140│
└─────────────────────────────────────┘
```

---

**Status:** ✅ Ready to Deploy
