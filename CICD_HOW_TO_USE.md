# 🛠️ CI/CD Workflows - How to Use (XAMPP Local)

## ✨ What We Have

### 3 GitHub Actions Workflows

```
.github/workflows/
├── ci.yml              ✅ Automatic validation on every push
├── deploy.yml          📦 Build & release packages
└── local-setup.yml     🔧 Local setup verification
```

---

## 🚀 How to Use

### **1️⃣ CI - PHP Validation** (Automatic)

**When:** Every time you push code or create a Pull Request

**What it does:**
- ✅ Checks PHP syntax for errors
- ✅ Validates database connection file
- ✅ Scans for common PHP mistakes

**Example:**
```bash
# Make changes
echo "<?php echo 'Hello'; ?>" > test.php

# Commit & push
git add test.php
git commit -m "Add test file"
git push origin main

# GitHub Actions automatically validates
# ✅ If success → Green checkmark in Actions tab
# ❌ If error → Red X with error details
```

**View Results:**
1. Go to Repository → **Actions** tab
2. Click on the latest workflow run
3. See validation results

---

### **2️⃣ CD - Build & Release** (Manual)

**When:** You want to create a release package

**What it does:**
- 📦 Creates ZIP package of project
- 📝 Generates release notes
- 💾 Stores in Releases section

**How to Trigger:**

#### Method A: Using Git Tag (Recommended)
```bash
# Create a version tag
git tag v1.0.0
git push origin v1.0.0

# Workflow automatically creates release
# Download from: Repository → Releases
```

#### Method B: Manual Trigger
1. Go to Repository → **Actions** tab
2. Click **CD - Build & Release** workflow
3. Click **Run workflow**
4. Select branch: `main`
5. Click **Run workflow**

---

### **3️⃣ Local Development Setup** (Optional)

**When:** You want to verify local environment setup

**What it does:**
- ✅ Checks PHP configuration
- ✅ Verifies project structure
- ✅ Tests database connection
- ✅ Lists critical files

**How to Use:**
1. Go to Repository → **Actions** tab
2. Click **Local Development Setup**
3. Click **Run workflow**
4. Select branch: `main`
5. Click **Run workflow**
6. Check results for any issues

---

## 📊 Viewing Workflow Results

### Access GitHub Actions:
```
Repository → Actions → [Workflow Name]
```

### What You'll See:
- **Green ✅** → Workflow succeeded
- **Red ❌** → Workflow failed
- **Yellow 🟡** → Workflow running

### View Logs:
1. Click on workflow run
2. Click on job name
3. Expand steps to see details

---

## 💡 Daily Workflow

### Before Starting Development:
```bash
# Pull latest code
git pull origin main

# Create feature branch
git checkout -b feature/my-feature
```

### During Development:
```bash
# Make changes in your editor
# Test locally in XAMPP browser

# When ready, commit
git add .
git commit -m "Add my feature"

# Push to GitHub
git push origin feature/my-feature
```

### CI Validation Happens Automatically:
- GitHub validates your PHP code
- Check Actions tab for results
- If ✅ all good, proceed
- If ❌ fix errors and push again

### Creating a Release:
```bash
# When feature is complete & tested
git checkout main
git pull origin main
git merge feature/my-feature
git push origin main

# Create version tag
git tag v1.1.0
git push origin v1.1.0

# Download release package from Releases tab
```

---

## 📥 Deploying Releases to XAMPP

### When You Have a Release ZIP:

1. **Download** `dormitory_management-release.zip` from Releases
2. **Extract** to `/Applications/XAMPP/xamppfiles/htdocs/`
3. **Restart Apache** in XAMPP
4. **Access** http://localhost/dormitory_management/

```bash
# Command line approach:
cd /Applications/XAMPP/xamppfiles/htdocs/
unzip ~/Downloads/dormitory_management-release.zip
# Or overwrite existing:
# rm -rf dormitory_management
# unzip ~/Downloads/dormitory_management-release.zip
```

---

## 🔍 Troubleshooting

### Issue: Red ❌ in CI Validation
**Solution:**
1. Click workflow run
2. Check error message in logs
3. Fix PHP syntax error in your code
4. Commit & push again

Example error:
```
Parse error: syntax error, unexpected 'else' (T_ELSE) in /file.php on line 42
```

### Issue: Workflow Not Running
**Solution:**
1. Check you pushed to `main` or `develop`
2. Check branch name in workflow files
3. Give GitHub a few seconds to detect push

### Issue: Can't See Actions Tab
**Solution:**
1. Refresh page
2. Check repository is public (Actions needs to be enabled)
3. Check you have permission to view

---

## 📝 What to Commit

✅ **DO Commit:**
- `.github/workflows/` - Workflow files
- `CI_CD_SETUP.md` - Documentation
- `.gitignore` - Ignore rules
- All project source code

❌ **DON'T Commit:**
- `deploy_key` - SSH private key
- `.env` files - Credentials
- `vendor/` - Composer dependencies
- `node_modules/` - NPM packages

---

## 🎯 Next Steps

1. **Push some code** to `main` branch
   - CI validation runs automatically
   - ✅ Green = All good

2. **Create a release**
   - Create git tag: `git tag v1.0.0 && git push origin v1.0.0`
   - CD workflow creates release package
   - Download from Releases tab

3. **Deploy to XAMPP**
   - Extract release ZIP to htdocs
   - Restart Apache
   - Access http://localhost/dormitory_management/

---

## 📚 Command Reference

```bash
# View workflows
git log --oneline -10

# Create feature branch
git checkout -b feature/name

# Push to GitHub
git push origin feature/name

# Create release tag
git tag v1.0.0
git push origin v1.0.0

# Delete tag (if needed)
git tag -d v1.0.0
git push origin --delete v1.0.0

# Check status
git status
git log --oneline -5
```

---

## ✅ You're All Set!

Your CI/CD pipeline is ready:
- ✅ CI validates code automatically
- ✅ CD creates release packages
- ✅ Easy to deploy to XAMPP

**Happy coding!** 🚀
